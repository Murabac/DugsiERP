<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Support\AcademicYear;
use App\Support\Money;
use App\Support\SchoolWeek;
use App\Support\StaffAttendancePunch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();
        $checkin = $this->staffCheckinTab($user);

        $kind = $user->dashboardKind();

        $data = match ($kind) {
            'finance' => $this->financeData($user, $year),
            'teacher' => $this->teacherData($user, $year),
            default => $this->adminData($user, $year),
        };

        $view = match ($kind) {
            'finance' => 'dashboard.finance',
            'teacher' => 'dashboard.teacher',
            default => 'dashboard.admin',
        };

        return view($view, array_merge($data, $checkin));
    }

    /**
     * @return array{staffCheckinAction: ?string, staffCheckinUrl: ?string}
     */
    private function staffCheckinTab($user): array
    {
        $staff = $user->staff;
        if (! $staff || blank($staff->checkin_token)) {
            return [
                'staffCheckinAction' => null,
                'staffCheckinUrl' => null,
            ];
        }

        $action = StaffAttendancePunch::nextAction($staff);
        if ($action === 'done') {
            return [
                'staffCheckinAction' => 'done',
                'staffCheckinUrl' => null,
            ];
        }

        return [
            'staffCheckinAction' => $action,
            'staffCheckinUrl' => route('staff-checkin.mine'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminData($user, string $year): array
    {
        $enrolledStudents = Enrollment::query()
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->distinct()
            ->count('student_id');
        $staff = Staff::query()->where('status', StaffStatus::Active)->count();
        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->count();
        $slots = TimetableSlot::query()->where('academic_year', $year)->count();

        $classFillQuery = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->withCount(['activeEnrollments as enrolled_count'])
            ->orderBy('form_level')
            ->orderBy('section');

        $classFillTotal = (clone $classFillQuery)->count();
        $classFill = $classFillQuery
            ->limit(20)
            ->get()
            ->map(fn (SchoolClass $c) => [
                'name' => $c->displayName(),
                'enrolled' => (int) $c->enrolled_count,
                'capacity' => $c->capacity,
                'pct' => $c->capacity > 0 ? (int) round(($c->enrolled_count / $c->capacity) * 100) : 0,
                'url' => route('classes.roster', $c),
            ]);

        $classFillChart = [
            'type' => 'bar',
            'horizontal' => true,
            'legend' => false,
            'suffix' => '%',
            'max' => 100,
            'labels' => $classFill->map(fn (array $row) => $row['name'].' ('.$row['enrolled'].'/'.$row['capacity'].')')->all(),
            'datasets' => [
                [
                    'label' => 'Fill %',
                    'data' => $classFill->pluck('pct')->all(),
                    'backgroundColor' => $classFill->map(function (array $row) {
                        if ($row['pct'] >= 95) {
                            return '#dc2626';
                        }
                        if ($row['pct'] >= 80) {
                            return '#d97706';
                        }

                        return '#1e3a6e';
                    })->all(),
                ],
            ],
        ];

        return [
            'user' => $user,
            'academicYear' => $year,
            'stats' => [
                ['label' => 'Enrolled Students', 'value' => (string) $enrolledStudents, 'sub' => 'Active enrollments in '.$year, 'icon' => 'users', 'accent' => true],
                ['label' => 'Total Staff', 'value' => (string) $staff, 'sub' => 'Active staff records', 'icon' => 'briefcase', 'accent' => false],
                ['label' => 'Active Classes', 'value' => (string) $classes, 'sub' => 'Academic year '.$year, 'icon' => 'layers', 'accent' => false],
                ['label' => 'Timetable Slots', 'value' => (string) $slots, 'sub' => 'Scheduled periods this year', 'icon' => 'calendar', 'accent' => false],
            ],
            'classFill' => $classFill,
            'classFillChart' => $classFillChart,
            'classFillTotal' => $classFillTotal,
            'activity' => $this->recentActivity($year),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherData($user, string $year): array
    {
        $staff = $user->staff;
        $dayKey = SchoolWeek::dayKey();
        $dayLabel = $dayKey ? SchoolWeek::dayLabel($dayKey) : now()->format('l');

        $todaySlots = collect();
        $nextPeriod = null;
        $periodsToday = 0;
        $myClasses = collect();
        $studentTotal = 0;

        if ($staff) {
            $allSlots = TimetableSlot::query()
                ->with(['subject', 'schoolClass'])
                ->where('academic_year', $year)
                ->where('teacher_id', $staff->id)
                ->get();

            if ($dayKey) {
                $todaySlots = $allSlots
                    ->where('day_of_week', $dayKey)
                    ->sortBy('period_number')
                    ->values();
                $periodsToday = $todaySlots->count();
                $nextPeriod = $this->periodStatusLabel($todaySlots);
            }

            $classIds = $allSlots->pluck('class_id')->unique()->filter();
            $classes = SchoolClass::query()
                ->whereIn('id', $classIds)
                ->withCount(['activeEnrollments as enrolled_count'])
                ->orderBy('form_level')
                ->orderBy('section')
                ->get();

            $myClasses = $classes->map(function (SchoolClass $c) use ($allSlots) {
                $subjects = $allSlots
                    ->where('class_id', $c->id)
                    ->pluck('subject.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'name' => $c->displayName(),
                    'subjects' => $subjects !== '' ? $subjects : '—',
                    'students' => (int) $c->enrolled_count,
                    'url' => route('classes.roster', $c),
                ];
            });

            $studentTotal = (int) $classes->sum('enrolled_count');
        }

        $todayRows = collect(SchoolWeek::periods())->map(function (array $period) use ($todaySlots) {
            $slot = $todaySlots->firstWhere('period_number', $period['period']);

            return [
                'period' => $period['period'],
                'label' => $period['label'],
                'subject' => $slot?->subject?->name,
                'class' => $slot?->schoolClass?->displayName(),
            ];
        });

        return [
            'user' => $user,
            'academicYear' => $year,
            'dayKey' => $dayKey,
            'dayLabel' => $dayLabel,
            'stats' => [
                [
                    'label' => 'Periods Today',
                    'value' => $dayKey ? (string) $periodsToday : '—',
                    'sub' => $dayKey
                        ? ($nextPeriod ?? ($periodsToday ? 'Done for today' : 'No periods today'))
                        : 'No school day (Thu–Fri)',
                    'icon' => 'calendar',
                    'accent' => true,
                ],
                [
                    'label' => 'Students Total',
                    'value' => (string) $studentTotal,
                    'sub' => $myClasses->count().' class'.($myClasses->count() === 1 ? '' : 'es').' on your timetable',
                    'icon' => 'users',
                    'accent' => false,
                ],
                [
                    'label' => 'Grade Entry',
                    'value' => (string) $myClasses->count(),
                    'sub' => $myClasses->isEmpty() ? 'No classes assigned yet' : 'Enter scores for your classes',
                    'icon' => 'graduation-cap',
                    'accent' => false,
                ],
            ],
            'todayRows' => $todayRows,
            'myClasses' => $myClasses,
            'hasStaffLink' => (bool) $staff,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financeData($user, string $year): array
    {
        $enrolledStudents = Enrollment::query()
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->distinct()
            ->count('student_id');
        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->count();

        $collected = Money::round(
            Invoice::query()->where('academic_year', $year)->sum('amount_paid')
        );
        $due = Money::round(
            Invoice::query()->where('academic_year', $year)->sum('amount_due')
        );
        $outstanding = Money::round(max(0, $due - $collected));
        $recentInvoices = Invoice::query()
            ->with(['student', 'schoolClass'])
            ->where('academic_year', $year)
            ->latest('id')
            ->limit(6)
            ->get();

        $feesChart = [
            'type' => 'doughnut',
            'legend' => true,
            'currency' => true,
            'labels' => ['Collected', 'Outstanding'],
            'datasets' => [
                [
                    'data' => [$collected, $outstanding],
                    'backgroundColor' => ['#1e3a6e', '#cbd5e1'],
                ],
            ],
        ];

        return [
            'user' => $user,
            'academicYear' => $year,
            'stats' => [
                ['label' => 'Enrolled Students', 'value' => (string) $enrolledStudents, 'sub' => 'Active enrollments in '.$year, 'icon' => 'users', 'accent' => true],
                ['label' => 'Active Classes', 'value' => (string) $classes, 'sub' => 'Current academic year', 'icon' => 'layers', 'accent' => false],
                ['label' => 'Fees Collected', 'value' => Money::format($collected), 'sub' => 'Of '.Money::format($due).' due', 'icon' => 'dollar-sign', 'accent' => false],
            ],
            'feesChart' => $feesChart,
            'recentInvoices' => $recentInvoices,
        ];
    }

    /**
     * @param  Collection<int, TimetableSlot>  $todaySlots
     */
    private function periodStatusLabel(Collection $todaySlots): ?string
    {
        if ($todaySlots->isEmpty()) {
            return null;
        }

        $now = now()->format('H:i');

        foreach ($todaySlots as $slot) {
            $start = substr((string) $slot->start_time, 0, 5);
            $end = substr((string) $slot->end_time, 0, 5);
            $subject = $slot->subject?->name ?? 'Class';

            if ($start <= $now && $now < $end) {
                return sprintf('Now: %s P%d', $subject, $slot->period_number);
            }

            if ($start > $now) {
                return sprintf('Next: %s P%d at %s', $subject, $slot->period_number, $start);
            }
        }

        return null;
    }

    /**
     * @return Collection<int, array{type: string, text: string, time: string}>
     */
    private function recentActivity(string $year): Collection
    {
        $items = collect();

        Student::query()->latest('id')->limit(5)->get()->each(function (Student $s) use ($items) {
            $items->push([
                'type' => 'admission',
                'text' => 'Student '.$s->full_name.' ('.$s->student_code.') on record',
                'time' => optional($s->created_at)?->diffForHumans() ?? '',
                'sort' => $s->created_at?->timestamp ?? 0,
            ]);
        });

        Staff::query()->latest('id')->limit(5)->get()->each(function (Staff $s) use ($items) {
            $items->push([
                'type' => 'staff',
                'text' => 'Staff '.$s->full_name.' ('.$s->employee_code.') — '.$s->roleDisplayName(),
                'time' => optional($s->created_at)?->diffForHumans() ?? '',
                'sort' => $s->created_at?->timestamp ?? 0,
            ]);
        });

        Enrollment::query()
            ->with(['student', 'schoolClass'])
            ->where('academic_year', $year)
            ->latest('id')
            ->limit(5)
            ->get()
            ->each(function (Enrollment $e) use ($items) {
                $items->push([
                    'type' => 'enrollment',
                    'text' => ($e->student?->full_name ?? 'Student').' enrolled in '.($e->schoolClass?->displayName() ?? 'a class'),
                    'time' => optional($e->created_at)?->diffForHumans() ?? '',
                    'sort' => $e->created_at?->timestamp ?? 0,
                ]);
            });

        $classIdsWithSlots = TimetableSlot::query()
            ->where('academic_year', $year)
            ->select('class_id')
            ->selectRaw('max(updated_at) as last_at')
            ->groupBy('class_id')
            ->orderByDesc('last_at')
            ->limit(5)
            ->get();

        $classes = SchoolClass::query()->whereIn('id', $classIdsWithSlots->pluck('class_id'))->get()->keyBy('id');
        foreach ($classIdsWithSlots as $row) {
            $class = $classes->get($row->class_id);
            if (! $class) {
                continue;
            }
            $items->push([
                'type' => 'timetable',
                'text' => 'Timetable updated for '.$class->displayName(),
                'time' => \Illuminate\Support\Carbon::parse($row->last_at)->diffForHumans(),
                'sort' => \Illuminate\Support\Carbon::parse($row->last_at)->timestamp,
            ]);
        }

        return $items->sortByDesc('sort')->take(8)->values();
    }
}
