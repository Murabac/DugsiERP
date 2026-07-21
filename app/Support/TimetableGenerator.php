<?php

namespace App\Support;

use App\Enums\ClassStatus;
use App\Enums\StaffStatus;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimetableGenerator
{
    /**
     * Generate timetables for all active classes in one pass.
     * Respects teacher subject assignments, assigned classes, and work-day shifts.
     * Each class keeps one teacher per subject for the whole week (no split
     * English teachers inside the same class).
     *
     * @param  array<string, int>  $periodCounts  subject name => periods per week (same plan for every class)
     * @return array{placed: int, skipped: int, classes: int}
     */
    public static function generateAll(string $academicYear, array $periodCounts): array
    {
        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        if ($classes->isEmpty()) {
            return ['placed' => 0, 'skipped' => 0, 'classes' => 0];
        }

        $subjects = Subject::query()->orderBy('sort_order')->get()->keyBy('name');
        $subjectIds = $subjects->pluck('id')->all();

        $teachersBySubject = TeacherSubjectAssignment::query()
            ->whereIn('subject_id', $subjectIds)
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('staff_id')->unique()->values()->all());

        $staff = Staff::query()
            ->where('status', StaffStatus::Active)
            ->with('assignedClasses')
            ->get()
            ->keyBy('id');

        $placed = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $classes,
            $academicYear,
            $periodCounts,
            $subjects,
            $teachersBySubject,
            $staff,
            &$placed,
            &$skipped,
        ) {
            TimetableSlot::query()
                ->where('academic_year', $academicYear)
                ->whereIn('class_id', $classes->pluck('id'))
                ->delete();

            /** @var array<string, list<int>> day|period => teacher ids */
            $busyTeachers = [];
            /** @var array<int, int> teacherId => reserved weekly periods (for spreading load across classes) */
            $teacherLoad = [];

            foreach ($classes as $class) {
                $result = self::placeForClass(
                    $class,
                    $academicYear,
                    $periodCounts,
                    $subjects,
                    $teachersBySubject,
                    $staff,
                    $busyTeachers,
                    $teacherLoad,
                );
                $placed += $result['placed'];
                $skipped += $result['skipped'];
            }
        });

        return [
            'placed' => $placed,
            'skipped' => $skipped,
            'classes' => $classes->count(),
        ];
    }

    /**
     * @param  array<string, int>  $periodCounts
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<int, list<int>>  $teachersBySubject
     * @param  Collection<int, Staff>  $staff
     * @param  array<string, list<int>>  $busyTeachers
     * @param  array<int, int>  $teacherLoad
     * @return array{placed: int, skipped: int}
     */
    private static function placeForClass(
        SchoolClass $class,
        string $academicYear,
        array $periodCounts,
        Collection $subjects,
        Collection $teachersBySubject,
        Collection $staff,
        array &$busyTeachers,
        array &$teacherLoad,
    ): array {
        $classId = (int) $class->id;
        $room = $class->classroom();
        $placed = 0;
        $skipped = 0;

        // Lock one teacher per subject for this class before placing periods.
        $subjectTeacher = self::assignSubjectTeachers(
            $classId,
            $periodCounts,
            $subjects,
            $teachersBySubject,
            $staff,
            $teacherLoad,
        );

        $queue = [];
        foreach ($periodCounts as $name => $count) {
            $subject = $subjects->get($name);
            if (! $subject || $count < 1) {
                continue;
            }
            for ($i = 0; $i < $count; $i++) {
                $queue[] = $subject;
            }
        }

        self::seededShuffle($queue, $classId * 9973 + strlen($academicYear));

        $cells = [];
        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                $cells[] = [$day, $period];
            }
        }
        self::seededShuffle($cells, $classId * 7919 + 17);

        foreach ($cells as [$day, $period]) {
            if ($queue === []) {
                break;
            }

            $periodNumber = (int) $period['period'];
            $key = $day.'|'.$periodNumber;
            $busy = $busyTeachers[$key] ?? [];
            $placedThisCell = false;

            foreach ($queue as $qi => $subject) {
                $lockedTeacherId = $subjectTeacher[$subject->id] ?? null;
                if ($lockedTeacherId === null) {
                    continue;
                }

                $teacherId = self::pickTeacher(
                    [$lockedTeacherId],
                    $busy,
                    $staff,
                    $classId,
                    $day,
                    $periodNumber,
                );

                if ($teacherId === null) {
                    continue;
                }

                TimetableSlot::query()->create([
                    'class_id' => $classId,
                    'academic_year' => $academicYear,
                    'day_of_week' => $day,
                    'period_number' => $periodNumber,
                    'start_time' => $period['start'],
                    'end_time' => $period['end'],
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacherId,
                    'room' => $room,
                ]);

                $busy[] = $teacherId;
                $busyTeachers[$key] = $busy;

                array_splice($queue, $qi, 1);
                $placed++;
                $placedThisCell = true;
                break;
            }

            if (! $placedThisCell) {
                $skipped++;
            }
        }

        $skipped += count($queue);

        return ['placed' => $placed, 'skipped' => $skipped];
    }

    /**
     * Choose one teacher per subject for this class. Prefers teachers with lower
     * reserved load so multiple English teachers split across classes, not within one.
     *
     * @param  array<string, int>  $periodCounts
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<int, list<int>>  $teachersBySubject
     * @param  Collection<int, Staff>  $staff
     * @param  array<int, int>  $teacherLoad
     * @return array<int, int> subjectId => teacherId
     */
    private static function assignSubjectTeachers(
        int $classId,
        array $periodCounts,
        Collection $subjects,
        Collection $teachersBySubject,
        Collection $staff,
        array &$teacherLoad,
    ): array {
        $owners = [];

        foreach ($periodCounts as $name => $count) {
            if ($count < 1) {
                continue;
            }

            $subject = $subjects->get($name);
            if (! $subject) {
                continue;
            }

            $candidates = $teachersBySubject->get($subject->id) ?? [];
            $eligible = [];
            foreach ($candidates as $candidateId) {
                $candidateId = (int) $candidateId;
                $member = $staff->get($candidateId);
                if (! $member || ! self::canTeachClass($member, $classId)) {
                    continue;
                }
                if (self::availablePeriodCount($member) < 1) {
                    continue;
                }
                $eligible[] = $candidateId;
            }

            if ($eligible === []) {
                continue;
            }

            usort(
                $eligible,
                fn (int $a, int $b) => ($teacherLoad[$a] ?? 0) <=> ($teacherLoad[$b] ?? 0) ?: $a <=> $b
            );

            $chosen = $eligible[0];
            $owners[$subject->id] = $chosen;
            $teacherLoad[$chosen] = ($teacherLoad[$chosen] ?? 0) + $count;
        }

        return $owners;
    }

    private static function canTeachClass(Staff $member, int $classId): bool
    {
        $assignedIds = $member->assignedClasses->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $assignedIds === [] || in_array($classId, $assignedIds, true);
    }

    private static function availablePeriodCount(Staff $member): int
    {
        $count = 0;
        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                if ($member->availableAt($day, $period['period'])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  list<int>  $candidates
     * @param  list<int>  $busy
     * @param  Collection<int, Staff>  $staff
     */
    private static function pickTeacher(
        array $candidates,
        array $busy,
        Collection $staff,
        int $classId,
        string $day,
        int $periodNumber,
    ): ?int {
        foreach ($candidates as $candidateId) {
            $candidateId = (int) $candidateId;
            if (in_array($candidateId, $busy, true)) {
                continue;
            }

            /** @var Staff|null $member */
            $member = $staff->get($candidateId);
            if (! $member) {
                continue;
            }

            if (! $member->availableAt($day, $periodNumber)) {
                continue;
            }

            if (! self::canTeachClass($member, $classId)) {
                continue;
            }

            return $candidateId;
        }

        return null;
    }

    /**
     * @param  list<mixed>  $items
     */
    private static function seededShuffle(array &$items, int $seed): void
    {
        $n = count($items);
        if ($n < 2) {
            return;
        }

        $state = $seed > 0 ? $seed : 1;
        for ($i = $n - 1; $i > 0; $i--) {
            $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $state % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
}
