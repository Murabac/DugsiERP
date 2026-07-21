<?php

namespace App\Support;

use App\Enums\StaffStatus;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;

/**
 * Actionable rearrange suggestions for empty timetable cells.
 * Only suggests moves that help place a missing subject — not pointless
 * “swap English into the hole” tips that leave another empty.
 */
class TimetableMoveHints
{
    /**
     * @return list<array{
     *     empty_day: string,
     *     empty_period: int,
     *     empty_label: string,
     *     text: string,
     *     kind: string
     * }>
     */
    public static function forClass(SchoolClass $class, ?string $academicYear = null): array
    {
        $academicYear ??= AcademicYear::current();
        $classId = (int) $class->id;
        $plan = SchoolWeek::weeklyPeriods();

        $allSlots = TimetableSlot::query()
            ->with(['schoolClass', 'teacher', 'subject'])
            ->where('academic_year', $academicYear)
            ->get();

        $classSlots = $allSlots->where('class_id', $classId)->values();
        $empties = self::emptyCells($classSlots);
        if ($empties === []) {
            return [];
        }

        $placedCounts = [];
        foreach ($classSlots as $slot) {
            $name = $slot->subject?->name;
            if ($name) {
                $placedCounts[$name] = ($placedCounts[$name] ?? 0) + 1;
            }
        }

        $deficits = [];
        foreach ($plan as $name => $need) {
            if ($need < 1) {
                continue;
            }
            $have = $placedCounts[$name] ?? 0;
            if ($have < $need) {
                $deficits[$name] = $need - $have;
            }
        }

        if ($deficits === []) {
            return [[
                'empty_day' => $empties[0]['day'],
                'empty_period' => $empties[0]['period'],
                'empty_label' => SchoolWeek::dayLabel($empties[0]['day']).' P'.$empties[0]['period'],
                'kind' => 'info',
                'text' => 'All subjects from the weekly plan are already placed — this empty is spare capacity (or the plan is shorter than the week). No hire/move needed for subjects.',
            ]];
        }

        $staff = Staff::query()
            ->where('status', StaffStatus::Active)
            ->with('assignedClasses')
            ->get()
            ->keyBy('id');

        $teachersBySubjectName = self::teachersBySubjectName($staff);
        $busyIndex = self::busyIndex($allSlots);

        $hints = [];
        foreach ($empties as $empty) {
            $emptyLabel = SchoolWeek::dayLabel($empty['day']).' P'.$empty['period'];
            $emptyKey = $empty['day'].'|'.$empty['period'];
            $best = null;

            foreach ($deficits as $subjectName => $_short) {
                $candidates = $teachersBySubjectName[$subjectName] ?? [];
                foreach ($candidates as $teacherId) {
                    $teacher = $staff->get($teacherId);
                    if (! $teacher || ! self::canTeachClass($teacher, $classId)) {
                        continue;
                    }
                    if (! $teacher->availableAt($empty['day'], $empty['period'])) {
                        continue;
                    }

                    $conflict = $busyIndex[$emptyKey][$teacherId] ?? null;

                    // Prefer unblock over a plain add when the teacher is stuck elsewhere.
                    if ($conflict && (int) $conflict->class_id !== $classId) {
                        $otherClass = $conflict->schoolClass?->displayName() ?? 'another class';
                        $otherSubject = $conflict->subject?->name ?? 'lesson';
                        $teacherName = $teacher->full_name;
                        $dest = self::findRelocationTarget($conflict, $allSlots, $busyIndex, $staff);
                        $best = [
                            'empty_day' => $empty['day'],
                            'empty_period' => $empty['period'],
                            'empty_label' => $emptyLabel,
                            'kind' => 'unblock',
                            'text' => $dest
                                ? "1) Open {$otherClass} → move {$otherSubject} from {$emptyLabel} to {$dest['label']}. 2) Return here → + Add {$subjectName} ({$teacherName})."
                                : "1) Open {$otherClass} → move {$otherSubject} off {$emptyLabel}. 2) Return here → + Add {$subjectName} ({$teacherName}).",
                        ];
                        break 2;
                    }

                    if (! $conflict && ($best === null || $best['kind'] !== 'unblock')) {
                        $best = [
                            'empty_day' => $empty['day'],
                            'empty_period' => $empty['period'],
                            'empty_label' => $emptyLabel,
                            'kind' => 'add',
                            'text' => "+ Add {$subjectName} at {$emptyLabel} — {$teacher->full_name} is free",
                        ];
                        // Keep scanning deficits for a better unblock on this empty.
                    }
                }
            }

            if ($best === null) {
                $best = self::sameClassTwoStep(
                    $empty,
                    $emptyLabel,
                    $emptyKey,
                    $class,
                    $classSlots,
                    $deficits,
                    $teachersBySubjectName,
                    $staff,
                    $busyIndex,
                );
            }

            if ($best !== null) {
                $hints[] = $best;
            }

            if (count($hints) >= 5) {
                break;
            }
        }

        usort($hints, function (array $a, array $b) {
            $rank = ['unblock' => 0, 'add' => 1, 'two_step' => 2, 'info' => 3];

            return ($rank[$a['kind']] ?? 9) <=> ($rank[$b['kind']] ?? 9);
        });

        $unique = [];
        $seen = [];
        foreach ($hints as $hint) {
            if (isset($seen[$hint['text']])) {
                continue;
            }
            $seen[$hint['text']] = true;
            $unique[] = $hint;
            if (count($unique) >= 5) {
                break;
            }
        }

        if ($unique === []) {
            $missing = implode(', ', array_keys($deficits));
            $unique[] = [
                'empty_day' => $empties[0]['day'],
                'empty_period' => $empties[0]['period'],
                'empty_label' => SchoolWeek::dayLabel($empties[0]['day']).' P'.$empties[0]['period'],
                'kind' => 'info',
                'text' => "Still missing: {$missing}. No free teacher for those subjects at the empty period(s) — check teacher schedules or Requirements.",
            ];
        }

        return $unique;
    }

    /**
     * Move lesson F into empty E only when that frees F's old cell for a missing subject.
     *
     * @param  array{day: string, period: int}  $empty
     * @param  Collection<int, TimetableSlot>  $classSlots
     * @param  array<string, int>  $deficits
     * @param  array<string, list<int>>  $teachersBySubjectName
     * @param  Collection<int, Staff>  $staff
     * @param  array<string, array<int, TimetableSlot>>  $busyIndex
     * @return array{empty_day: string, empty_period: int, empty_label: string, text: string, kind: string}|null
     */
    private static function sameClassTwoStep(
        array $empty,
        string $emptyLabel,
        string $emptyKey,
        SchoolClass $class,
        Collection $classSlots,
        array $deficits,
        array $teachersBySubjectName,
        Collection $staff,
        array $busyIndex,
    ): ?array {
        $classId = (int) $class->id;

        foreach ($classSlots as $slot) {
            if (! $slot->teacher_id || ! $slot->subject) {
                continue;
            }

            $mover = $staff->get((int) $slot->teacher_id);
            if (! $mover || ! $mover->availableAt($empty['day'], $empty['period'])) {
                continue;
            }

            $busyElsewhere = $busyIndex[$emptyKey][(int) $slot->teacher_id] ?? null;
            if ($busyElsewhere && (int) $busyElsewhere->class_id !== $classId) {
                continue;
            }

            $fromDay = $slot->day_of_week;
            $fromPeriod = (int) $slot->period_number;
            $fromKey = $fromDay.'|'.$fromPeriod;
            $fromLabel = SchoolWeek::dayLabel($fromDay).' P'.$fromPeriod;
            if ($fromKey === $emptyKey) {
                continue;
            }

            foreach ($deficits as $subjectName => $_short) {
                // Don't "free" a cell by moving away the only instance of a subject we still need more of
                // if that subject is the one we're moving — moving English when English is short makes it worse.
                if ($slot->subject->name === $subjectName) {
                    continue;
                }

                foreach ($teachersBySubjectName[$subjectName] ?? [] as $teacherId) {
                    $teacher = $staff->get($teacherId);
                    if (! $teacher || ! self::canTeachClass($teacher, $classId)) {
                        continue;
                    }
                    if (! $teacher->availableAt($fromDay, $fromPeriod)) {
                        continue;
                    }

                    // After moving $slot to empty, $fromKey is free for this teacher
                    // unless they are busy in another class at fromKey.
                    $busyAtFrom = $busyIndex[$fromKey][$teacherId] ?? null;
                    if ($busyAtFrom && (int) $busyAtFrom->class_id !== $classId) {
                        continue;
                    }
                    // If this teacher is the one we're moving, they'll be at empty — free at from. OK.
                    // If someone else occupies fromKey in this class, that's $slot itself — becomes free. OK.

                    return [
                        'empty_day' => $empty['day'],
                        'empty_period' => $empty['period'],
                        'empty_label' => $emptyLabel,
                        'kind' => 'two_step',
                        'text' => "1) Drag {$slot->subject->name} from {$fromLabel} → {$emptyLabel}. 2) Add {$subjectName} at {$fromLabel} ({$teacher->full_name} free there).",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TimetableSlot>  $classSlots
     * @return list<array{day: string, period: int}>
     */
    private static function emptyCells(Collection $classSlots): array
    {
        $filled = [];
        foreach ($classSlots as $slot) {
            if ($slot->subject_id) {
                $filled[$slot->day_of_week.'|'.$slot->period_number] = true;
            }
        }

        $empties = [];
        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                $key = $day.'|'.$period['period'];
                if (! isset($filled[$key])) {
                    $empties[] = ['day' => $day, 'period' => (int) $period['period']];
                }
            }
        }

        return $empties;
    }

    /**
     * @param  Collection<int, Staff>  $staff
     * @return array<string, list<int>>
     */
    private static function teachersBySubjectName(Collection $staff): array
    {
        $subjectNames = Subject::query()->pluck('name', 'id');
        $map = [];
        $rows = TeacherSubjectAssignment::query()
            ->whereIn('staff_id', $staff->keys())
            ->get(['staff_id', 'subject_id']);

        foreach ($rows as $row) {
            $name = $subjectNames[$row->subject_id] ?? null;
            if ($name === null) {
                continue;
            }
            $map[$name][] = (int) $row->staff_id;
        }

        foreach ($map as $name => $ids) {
            $map[$name] = array_values(array_unique($ids));
        }

        return $map;
    }

    /**
     * @param  Collection<int, TimetableSlot>  $allSlots
     * @return array<string, array<int, TimetableSlot>> day|period => teacherId => slot
     */
    private static function busyIndex(Collection $allSlots): array
    {
        $index = [];
        foreach ($allSlots as $slot) {
            if (! $slot->teacher_id) {
                continue;
            }
            $key = $slot->day_of_week.'|'.$slot->period_number;
            $index[$key][(int) $slot->teacher_id] = $slot;
        }

        return $index;
    }

    private static function canTeachClass(Staff $teacher, int $classId): bool
    {
        $assignedIds = $teacher->assignedClasses->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $assignedIds === [] || in_array($classId, $assignedIds, true);
    }

    /**
     * @param  Collection<int, TimetableSlot>  $allSlots
     * @param  array<string, array<int, TimetableSlot>>  $busyIndex
     * @param  Collection<int, Staff>  $staff
     * @return array{day: string, period: int, label: string}|null
     */
    private static function findRelocationTarget(
        TimetableSlot $conflict,
        Collection $allSlots,
        array $busyIndex,
        Collection $staff,
    ): ?array {
        $teacher = $staff->get((int) $conflict->teacher_id);
        if (! $teacher) {
            return null;
        }

        $classId = (int) $conflict->class_id;
        $filled = [];
        foreach ($allSlots->where('class_id', $classId) as $slot) {
            if ($slot->subject_id) {
                $filled[$slot->day_of_week.'|'.$slot->period_number] = true;
            }
        }

        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                $periodNumber = (int) $period['period'];
                $key = $day.'|'.$periodNumber;
                if (isset($filled[$key])) {
                    continue;
                }
                if ($day === $conflict->day_of_week && $periodNumber === (int) $conflict->period_number) {
                    continue;
                }
                if (! $teacher->availableAt($day, $periodNumber)) {
                    continue;
                }
                $busy = $busyIndex[$key][(int) $conflict->teacher_id] ?? null;
                if ($busy && (int) $busy->class_id !== $classId) {
                    continue;
                }

                return [
                    'day' => $day,
                    'period' => $periodNumber,
                    'label' => SchoolWeek::dayLabel($day).' P'.$periodNumber,
                ];
            }
        }

        return null;
    }
}
