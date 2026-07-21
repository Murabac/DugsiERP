<?php

namespace App\Support;

use App\Enums\ClassStatus;
use App\Enums\StaffStatus;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\TimetableSlot;

/**
 * Staffing + timetable fill for admins.
 *
 * Rules:
 * - Each class must fill every period defined in Settings (e.g. Sat–Tue 7, Wed 6).
 * - Empty = a required day/period with no subject on the timetable (like “—” on print).
 * - A teacher who covers several subjects splits their week across those subjects.
 */
class TimetableRequirements
{
    /**
     * @return array{
     *     academic_year: string,
     *     active_classes: int,
     *     weekly_capacity: int,
     *     plan_total: int,
     *     plan_gap: int,
     *     teachers_on_roster: int,
     *     ft_teachers_needed_overall: int,
     *     teachers_short_overall: int,
     *     subject_hire_total: int,
     *     more_teachers_needed: int,
     *     staffing_chart: array<string, mixed>,
     *     subject_staffing: list<array{
     *         subject: string,
     *         periods_per_class: int,
     *         lessons_needed: int,
     *         ft_needed: int,
     *         teachers_assigned: int,
     *         short: int
     *     }>,
     *     empty_periods_per_class: int,
     *     filled_periods_per_class: int,
     *     total_empty_periods: int,
     *     day_structure_label: string,
     *     short_subjects: list<string>,
     *     headline: string,
     *     plan_message: string|null,
     *     week_fill_chart: array<string, mixed>,
     *     class_empty_chart: array<string, mixed>,
     *     subject_load_chart: array<string, mixed>,
     *     class_fill: list<array{
     *         class_id: int,
     *         class_name: string,
     *         required: int,
     *         filled: int,
     *         empty: int
     *     }>,
     *     subject_ft_check: list<array{
     *         subject: string,
     *         periods_per_class: int,
     *         lessons_needed: int,
     *         lessons_label: string,
     *         ft_enough: bool,
     *         short_by: int,
     *         ft_teachers_needed: int,
     *         verdict: string
     *     }>,
     *     subjects_needing_teachers: list<array{
     *         subject: string,
     *         have: int,
     *         need: int,
     *         more: int,
     *         plain: string
     *     }>
     * }
     */
    public static function analyze(?string $academicYear = null): array
    {
        $academicYear ??= AcademicYear::current();
        $plan = SchoolWeek::weeklyPeriods();
        $weeklyCapacity = SchoolWeek::weeklyCapacity();
        $planTotal = (int) array_sum($plan);
        $planGap = max(0, $weeklyCapacity - $planTotal);

        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();
        $activeClasses = $classes->count();

        $teachers = Staff::query()
            ->where('status', StaffStatus::Active)
            ->whereHas('subjectAssignments')
            ->with(['subjectAssignments.subject'])
            ->orderBy('full_name')
            ->get();

        $classFill = self::classFill($classes, $academicYear, $weeklyCapacity);
        $avgEmpty = $activeClasses > 0
            ? (int) round(collect($classFill)->avg('empty') ?? 0)
            : 0;
        $avgFilled = $activeClasses > 0
            ? (int) round(collect($classFill)->avg('filled') ?? 0)
            : 0;
        $totalEmpty = (int) collect($classFill)->sum('empty');

        $subjectFtCheck = self::oneFullTimeCheck($plan, $activeClasses, $weeklyCapacity);
        $subjectStaffing = self::subjectStaffing($plan, $activeClasses, $weeklyCapacity, $teachers);
        $ftNeededOverall = (int) collect($subjectStaffing)->sum('ft_needed');
        $rosterCount = $teachers->count();
        $teachersShortOverall = max(0, $ftNeededOverall - $rosterCount);

        $subjectsNeeding = self::subjectsNeedingTeachers($teachers, $plan, $activeClasses, $weeklyCapacity);
        $subjectHireTotal = (int) collect($subjectsNeeding)->sum('more');
        $ftShortSubjects = collect($subjectFtCheck)->where('ft_enough', false);
        $moreFromFtCheck = (int) $ftShortSubjects->sum(fn (array $row) => max(0, $row['ft_teachers_needed'] - 1));
        // Overall short is the main hire signal (1 FT per subject, 2 for heavy subjects, minus roster).
        $moreTeachersNeeded = max(
            $teachersShortOverall,
            $moreFromFtCheck,
            (int) collect($subjectsNeeding)->max('more') ?: 0,
        );
        $shortSubjects = $ftShortSubjects->pluck('subject')->values()->all();

        return [
            'academic_year' => $academicYear,
            'active_classes' => $activeClasses,
            'weekly_capacity' => $weeklyCapacity,
            'plan_total' => $planTotal,
            'plan_gap' => $planGap,
            'empty_periods_per_class' => $avgEmpty,
            'filled_periods_per_class' => $avgFilled,
            'total_empty_periods' => $totalEmpty,
            'day_structure_label' => self::dayStructureLabel(),
            'short_subjects' => $shortSubjects,
            'teachers_on_roster' => $rosterCount,
            'ft_teachers_needed_overall' => $ftNeededOverall,
            'teachers_short_overall' => $teachersShortOverall,
            'subject_hire_total' => $subjectHireTotal,
            'more_teachers_needed' => $moreTeachersNeeded,
            'headline' => self::headline($moreTeachersNeeded, $activeClasses),
            'plan_message' => $planGap > 0
                ? "Subject plan covers {$planTotal}/{$weeklyCapacity} periods — at least {$planGap} empty per class even after generate."
                : null,
            'week_fill_chart' => self::weekFillChart($avgFilled, $avgEmpty),
            'class_empty_chart' => self::classEmptyChart($classFill, $weeklyCapacity),
            'subject_load_chart' => self::subjectLoadChart($subjectFtCheck, $weeklyCapacity),
            'staffing_chart' => self::staffingChart($rosterCount, $teachersShortOverall),
            'class_fill' => $classFill,
            'subject_ft_check' => $subjectFtCheck,
            'subject_staffing' => $subjectStaffing,
            'subjects_needing_teachers' => $subjectsNeeding,
        ];
    }

    public static function availablePeriods(Staff $teacher): int
    {
        $count = 0;
        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                if ($teacher->availableAt($day, $period['period'])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Required slots from Settings day structure (e.g. Sat–Tue 7, Wed 6).
     *
     * @return list<array{day: string, period: int}>
     */
    public static function requiredSlots(): array
    {
        $slots = [];
        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                $slots[] = ['day' => $day, 'period' => $period['period']];
            }
        }

        return $slots;
    }

    /** e.g. "Sat–Tue 7 · Wed 6" */
    public static function dayStructureLabel(): string
    {
        $byCount = [];
        foreach (SchoolWeek::periodsPerDay() as $day => $count) {
            $byCount[$count][] = $day;
        }

        $parts = [];
        foreach ($byCount as $count => $days) {
            $first = SchoolWeek::dayLabel($days[0]);
            $last = SchoolWeek::dayLabel($days[array_key_last($days)]);
            $range = $first === $last
                ? substr($first, 0, 3)
                : substr($first, 0, 3).'–'.substr($last, 0, 3);
            $parts[] = $range.' '.$count;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SchoolClass>  $classes
     * @return list<array{class_id: int, class_name: string, required: int, filled: int, empty: int}>
     */
    private static function classFill($classes, string $academicYear, int $weeklyCapacity): array
    {
        if ($classes->isEmpty()) {
            return [];
        }

        $requiredKeys = [];
        foreach (self::requiredSlots() as $slot) {
            $requiredKeys[$slot['day'].':'.$slot['period']] = true;
        }

        $filledByClass = TimetableSlot::query()
            ->where('academic_year', $academicYear)
            ->whereIn('class_id', $classes->pluck('id'))
            ->whereNotNull('subject_id')
            ->get(['class_id', 'day_of_week', 'period_number'])
            ->groupBy('class_id')
            ->map(function ($slots) use ($requiredKeys) {
                $unique = [];
                foreach ($slots as $slot) {
                    $key = $slot->day_of_week.':'.$slot->period_number;
                    if (isset($requiredKeys[$key])) {
                        $unique[$key] = true;
                    }
                }

                return count($unique);
            });

        $out = [];
        foreach ($classes as $class) {
            $filled = (int) ($filledByClass[$class->id] ?? 0);
            $filled = min($filled, $weeklyCapacity);
            $out[] = [
                'class_id' => $class->id,
                'class_name' => $class->displayName(),
                'required' => $weeklyCapacity,
                'filled' => $filled,
                'empty' => max(0, $weeklyCapacity - $filled),
            ];
        }

        usort($out, fn (array $a, array $b) => $b['empty'] <=> $a['empty'] ?: strcmp($a['class_name'], $b['class_name']));

        return $out;
    }

    /**
     * Per-subject FT teachers needed (ungrouped) vs how many teachers are assigned.
     *
     * @param  array<string, int>  $plan
     * @param  \Illuminate\Support\Collection<int, Staff>  $teachers
     * @return list<array{
     *     subject: string,
     *     periods_per_class: int,
     *     lessons_needed: int,
     *     ft_needed: int,
     *     teachers_assigned: int,
     *     short: int
     * }>
     */
    private static function subjectStaffing(array $plan, int $activeClasses, int $weeklyCapacity, $teachers): array
    {
        $assigned = [];
        foreach ($teachers as $teacher) {
            foreach ($teacher->subjectAssignments as $assignment) {
                $name = $assignment->subject?->name;
                if ($name === null) {
                    continue;
                }
                $assigned[$name][$teacher->id] = true;
            }
        }

        $out = [];
        foreach ($plan as $subjectName => $periodsPerClass) {
            if ($periodsPerClass < 1) {
                continue;
            }

            $lessonsNeeded = $activeClasses * $periodsPerClass;
            $ftNeeded = $weeklyCapacity > 0
                ? (int) max(1, (int) ceil($lessonsNeeded / $weeklyCapacity))
                : 1;
            $teachersAssigned = count($assigned[$subjectName] ?? []);

            $out[] = [
                'subject' => $subjectName,
                'periods_per_class' => $periodsPerClass,
                'lessons_needed' => $lessonsNeeded,
                'ft_needed' => $ftNeeded,
                'teachers_assigned' => $teachersAssigned,
                'short' => max(0, $ftNeeded - $teachersAssigned),
            ];
        }

        usort($out, fn (array $a, array $b) => $b['short'] <=> $a['short']
            ?: $b['ft_needed'] <=> $a['ft_needed']
            ?: strcmp($a['subject'], $b['subject']));

        return $out;
    }

    /**
     * Simple “is 1 full-time teacher enough for this subject?” table.
     * Short subjects stay as their own rows; matching “enough” subjects collapse
     * into “All other subjects (N/class)” with “X each”.
     *
     * @param  array<string, int>  $plan
     * @return list<array{
     *     subject: string,
     *     periods_per_class: int,
     *     lessons_needed: int,
     *     lessons_label: string,
     *     ft_enough: bool,
     *     short_by: int,
     *     ft_teachers_needed: int,
     *     verdict: string
     * }>
     */
    private static function oneFullTimeCheck(array $plan, int $activeClasses, int $weeklyCapacity): array
    {
        $rows = [];
        foreach ($plan as $subjectName => $periodsPerClass) {
            if ($periodsPerClass < 1) {
                continue;
            }

            $lessonsNeeded = $activeClasses * $periodsPerClass;
            $shortBy = max(0, $lessonsNeeded - $weeklyCapacity);
            $ftEnough = $shortBy === 0;
            $ftTeachersNeeded = $weeklyCapacity > 0
                ? (int) max(1, (int) ceil($lessonsNeeded / $weeklyCapacity))
                : 1;

            $rows[] = [
                'subject' => $subjectName,
                'periods_per_class' => $periodsPerClass,
                'lessons_needed' => $lessonsNeeded,
                'lessons_label' => (string) $lessonsNeeded,
                'ft_enough' => $ftEnough,
                'short_by' => $shortBy,
                'ft_teachers_needed' => $ftTeachersNeeded,
                'verdict' => $ftEnough
                    ? 'Yes (on paper)'
                    : 'No — short by '.$shortBy,
            ];
        }

        usort($rows, function (array $a, array $b) {
            if ($a['ft_enough'] !== $b['ft_enough']) {
                return $a['ft_enough'] ? 1 : -1;
            }

            return $b['short_by'] <=> $a['short_by'] ?: strcmp($a['subject'], $b['subject']);
        });

        $short = array_values(array_filter($rows, fn (array $row) => ! $row['ft_enough']));
        $enough = array_values(array_filter($rows, fn (array $row) => $row['ft_enough']));

        // Match the explain table: list short subjects, then collapse the rest.
        if ($short === [] || count($enough) < 2) {
            return array_values(array_merge($short, $enough));
        }

        $enoughByPeriods = [];
        foreach ($enough as $row) {
            $enoughByPeriods[$row['periods_per_class']][] = $row;
        }

        $collapsed = [];
        $bucketCount = count($enoughByPeriods);
        ksort($enoughByPeriods);
        foreach ($enoughByPeriods as $periodsPerClass => $group) {
            if (count($group) < 2) {
                $collapsed[] = $group[0];
                continue;
            }

            $lessonsNeeded = $group[0]['lessons_needed'];
            $collapsed[] = [
                'subject' => $bucketCount === 1 ? 'All other subjects' : 'Other subjects',
                'periods_per_class' => $periodsPerClass,
                'lessons_needed' => $lessonsNeeded,
                'lessons_label' => $lessonsNeeded.' each',
                'ft_enough' => true,
                'short_by' => 0,
                'ft_teachers_needed' => 1,
                'verdict' => 'Yes (on paper)',
            ];
        }

        return array_values(array_merge($short, $collapsed));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Staff>  $teachers
     * @param  array<string, int>  $plan
     * @return list<array{subject: string, have: int, need: int, more: int, plain: string}>
     */
    private static function subjectsNeedingTeachers($teachers, array $plan, int $activeClasses, int $weeklyCapacity): array
    {
        if ($activeClasses < 1 || $weeklyCapacity < 1) {
            return [];
        }

        $shares = [];
        foreach ($teachers as $teacher) {
            $names = $teacher->subjectAssignments
                ->map(fn ($a) => $a->subject?->name)
                ->filter()
                ->unique()
                ->values();
            $count = max(1, $names->count());
            $share = self::availablePeriods($teacher) / $count;
            foreach ($names as $name) {
                $shares[$name]['supply'] = ($shares[$name]['supply'] ?? 0) + $share;
                $shares[$name]['teachers'][] = $teacher->id;
            }
        }

        $out = [];
        foreach ($plan as $subjectName => $periodsPerClass) {
            if ($periodsPerClass < 1) {
                continue;
            }

            $demand = $activeClasses * $periodsPerClass;
            $supply = (float) ($shares[$subjectName]['supply'] ?? 0);
            $have = count(array_unique($shares[$subjectName]['teachers'] ?? []));

            // How many full-week teachers this subject needs if they mainly teach it.
            $need = (int) max(1, (int) ceil($demand / $weeklyCapacity));
            $haveFte = $supply / $weeklyCapacity;
            $more = (int) max(0, (int) ceil($need - $haveFte));

            // Also: if demand exceeds shared supply periods, hire for the gap.
            $periodGap = max(0, $demand - $supply);
            $moreFromPeriods = (int) ceil($periodGap / $weeklyCapacity);
            $more = max($more, $moreFromPeriods);

            if ($more < 1) {
                continue;
            }

            $out[] = [
                'subject' => $subjectName,
                'have' => $have,
                'need' => $need,
                'more' => $more,
                'plain' => $more === 1
                    ? "{$subjectName}: you have {$have}, need about {$need} — hire 1 more"
                    : "{$subjectName}: you have {$have}, need about {$need} — hire {$more} more",
            ];
        }

        usort($out, fn (array $a, array $b) => $b['more'] <=> $a['more'] ?: strcmp($a['subject'], $b['subject']));

        return $out;
    }

    private static function headline(int $more, int $activeClasses): string
    {
        if ($activeClasses < 1) {
            return 'No classes';
        }

        if ($more < 1) {
            return 'Enough teachers';
        }

        return $more === 1
            ? '+1 teacher'
            : "+{$more} teachers";
    }

    /**
     * @return array<string, mixed>
     */
    private static function weekFillChart(int $filled, int $empty): array
    {
        return [
            'type' => 'doughnut',
            'legend' => true,
            'labels' => ['Filled', 'Empty'],
            'datasets' => [
                [
                    'data' => [$filled, $empty],
                    'backgroundColor' => ['#1e3a6e', $empty > 0 ? '#f59e0b' : '#e2e8f0'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function staffingChart(int $have, int $short): array
    {
        return [
            'type' => 'doughnut',
            'legend' => true,
            'labels' => ['On roster', 'Still needed'],
            'datasets' => [
                [
                    'data' => [$have, $short],
                    'backgroundColor' => ['#1e3a6e', $short > 0 ? '#d97706' : '#e2e8f0'],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{class_name: string, empty: int}>  $classFill
     * @return array<string, mixed>
     */
    private static function classEmptyChart(array $classFill, int $weeklyCapacity): array
    {
        $labels = array_map(fn (array $row) => $row['class_name'], $classFill);
        $data = array_map(fn (array $row) => $row['empty'], $classFill);
        $colors = array_map(
            fn (array $row) => $row['empty'] > 0 ? '#d97706' : '#16a34a',
            $classFill
        );

        return [
            'type' => 'bar',
            'horizontal' => true,
            'legend' => false,
            'max' => max($weeklyCapacity, ...( $data ?: [0] )),
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Empty',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
        ];
    }

    /**
     * @param  list<array{subject: string, lessons_needed: int, ft_enough: bool}>  $subjectFtCheck
     * @return array<string, mixed>
     */
    private static function subjectLoadChart(array $subjectFtCheck, int $weeklyCapacity): array
    {
        $labels = [];
        $data = [];
        $colors = [];

        foreach ($subjectFtCheck as $row) {
            $labels[] = $row['subject'];
            $data[] = $row['lessons_needed'];
            $colors[] = $row['ft_enough'] ? '#1e3a6e' : '#d97706';
        }

        return [
            'type' => 'bar',
            'horizontal' => true,
            'legend' => false,
            'max' => max($weeklyCapacity, ...( $data ?: [0] )) + 2,
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Lessons',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
        ];
    }
}
