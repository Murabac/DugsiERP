<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Support\AbsenceSmsStub;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();
        $classes = $this->accessibleClasses($user, $year);

        $requestedClassId = (int) $request->query('class', 0);
        if ($requestedClassId > 0) {
            $schoolClass = $classes->firstWhere('id', $requestedClassId);
            abort_unless($schoolClass !== null, 403);
        } else {
            $schoolClass = $classes->first();
        }

        $date = $this->resolveDateQuery($request->query('date'), now()->toDateString());

        $rows = collect();
        $existing = collect();
        $isSchoolDay = SchoolWeek::dayKey($date) !== null;

        if ($schoolClass) {
            $enrollments = $schoolClass->activeEnrollments()
                ->with(['student.primaryGuardian'])
                ->orderBy('roll_number')
                ->get();

            $existing = AttendanceRecord::query()
                ->where('class_id', $schoolClass->id)
                ->whereDate('date', $date)
                ->get()
                ->keyBy('student_id');

            $rows = $enrollments->map(function (Enrollment $enrollment) use ($existing) {
                $student = $enrollment->student;
                $record = $existing->get($student->id);
                $oldStatus = old('statuses.'.$student->id);
                $status = $oldStatus
                    ?? $record?->status?->value
                    ?? AttendanceStatus::Present->value;

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'roll' => str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT),
                    'status' => $status,
                    'reason' => old('reasons.'.$student->id, $record?->reason ?? ''),
                    'guardian_phone' => $student->primaryGuardian?->phone,
                ];
            });
        }

        return view('attendance.index', [
            'classes' => $classes,
            'schoolClass' => $schoolClass,
            'date' => $date->toDateString(),
            'dateLabel' => $date->format('j F Y'),
            'rows' => $rows,
            'isSchoolDay' => $isSchoolDay,
            'alreadyMarked' => $existing->isNotEmpty(),
            'statuses' => AttendanceStatus::cases(),
            'academicYear' => $year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $year = AcademicYear::current();

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'reasons' => ['nullable', 'array'],
            'reasons.*' => ['nullable', 'string', 'max:255'],
            'send_sms' => ['nullable', 'boolean'],
        ]);

        $schoolClass = SchoolClass::query()->findOrFail($data['class_id']);
        abort_unless($user->canViewSchoolClass($schoolClass), 403);
        abort_unless($schoolClass->academic_year === $year && $schoolClass->status === ClassStatus::Active, 404);

        $date = Carbon::parse($data['date'])->startOfDay();
        $sendSms = $request->boolean('send_sms');

        $enrollments = $schoolClass->activeEnrollments()
            ->with(['student.primaryGuardian'])
            ->get()
            ->keyBy('student_id');

        $smsCount = 0;

        DB::transaction(function () use ($data, $enrollments, $schoolClass, $date, $user, $sendSms, &$smsCount) {
            foreach ($data['statuses'] as $studentId => $statusValue) {
                $studentId = (int) $studentId;
                if (! $enrollments->has($studentId)) {
                    continue;
                }

                $status = AttendanceStatus::from($statusValue);
                $reason = $status->requiresReason()
                    ? trim((string) ($data['reasons'][$studentId] ?? ''))
                    : null;

                $record = AttendanceRecord::query()
                    ->where('student_id', $studentId)
                    ->where('class_id', $schoolClass->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                $attrs = [
                    'status' => $status,
                    'reason' => $reason !== '' ? $reason : null,
                    'marked_by' => $user->id,
                ];

                if ($record) {
                    $record->update($attrs);
                } else {
                    $record = AttendanceRecord::query()->create([
                        'student_id' => $studentId,
                        'class_id' => $schoolClass->id,
                        'date' => $date->toDateString(),
                        ...$attrs,
                    ]);
                }

                if ($sendSms && $status === AttendanceStatus::Absent) {
                    $student = $enrollments->get($studentId)->student;
                    $record->setRelation('schoolClass', $schoolClass);
                    if (AbsenceSmsStub::log($student, $record, $student->primaryGuardian?->phone)) {
                        $smsCount++;
                    }
                }
            }
        });

        $message = 'Attendance saved for '.$schoolClass->displayName().' on '.$date->format('j F Y').'.';
        if ($sendSms && $smsCount > 0) {
            $message .= ' '.$smsCount.' absence SMS attempted via notification service.';
        } elseif ($sendSms && $smsCount === 0) {
            $message .= ' No new absence SMS to send.';
        }

        return redirect()
            ->route('attendance.index', [
                'class' => $schoolClass->id,
                'date' => $date->toDateString(),
            ])
            ->with('status', $message);
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();
        $classes = $this->accessibleClasses($user, $year);

        $requestedClassId = (int) $request->query('class', 0);
        if ($requestedClassId > 0) {
            $schoolClass = $classes->firstWhere('id', $requestedClassId);
            abort_unless($schoolClass !== null, 403);
        } else {
            $schoolClass = $classes->first();
        }

        $from = $this->resolveDateQuery($request->query('from'), now()->subDays(14)->toDateString());
        $to = $this->resolveDateQuery($request->query('to'), now()->toDateString());
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $days = collect();

        if ($schoolClass) {
            $records = AttendanceRecord::query()
                ->where('class_id', $schoolClass->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->get();

            $days = $records
                ->groupBy(fn (AttendanceRecord $r) => $r->date->toDateString())
                ->map(function ($group, $dateKey) {
                    $counts = [
                        'present' => $group->filter(fn (AttendanceRecord $r) => $r->status === AttendanceStatus::Present)->count(),
                        'late' => $group->filter(fn (AttendanceRecord $r) => $r->status === AttendanceStatus::Late)->count(),
                        'absent' => $group->filter(fn (AttendanceRecord $r) => $r->status === AttendanceStatus::Absent)->count(),
                        'suspended' => $group->filter(fn (AttendanceRecord $r) => $r->status === AttendanceStatus::Suspended)->count(),
                    ];
                    $total = array_sum($counts);

                    return [
                        'date' => $dateKey,
                        'date_label' => Carbon::parse($dateKey)->format('D, j M Y'),
                        'present' => $counts['present'],
                        'late' => $counts['late'],
                        'absent' => $counts['absent'],
                        'suspended' => $counts['suspended'],
                        'total' => $total,
                        'rate' => $total > 0
                            ? round((($counts['present'] + $counts['late']) / $total) * 100, 1)
                            : null,
                    ];
                })
                ->sortByDesc('date')
                ->values();
        }

        return view('attendance.history', [
            'classes' => $classes,
            'schoolClass' => $schoolClass,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'academicYear' => $year,
        ]);
    }

    public function print(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();

        $data = $request->validate([
            'class' => ['required', 'integer', 'exists:classes,id'],
            'date' => ['required', 'date'],
        ]);

        $schoolClass = SchoolClass::query()->findOrFail($data['class']);
        abort_unless($user->canViewSchoolClass($schoolClass), 403);

        $date = Carbon::parse($data['date'])->startOfDay();

        $enrollments = $schoolClass->activeEnrollments()
            ->with('student')
            ->orderBy('roll_number')
            ->get();

        $records = AttendanceRecord::query()
            ->where('class_id', $schoolClass->id)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($records) {
            $record = $records->get($enrollment->student_id);

            return [
                'roll' => str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT),
                'name' => $enrollment->student->full_name,
                'status' => $record?->status,
                'reason' => $record?->reason,
            ];
        });

        return view('attendance.print', [
            'schoolClass' => $schoolClass,
            'date' => $date,
            'dateLabel' => $date->format('j F Y'),
            'rows' => $rows,
            'academicYear' => $year,
            'counts' => [
                'present' => $rows->filter(fn ($r) => $r['status'] === AttendanceStatus::Present)->count(),
                'late' => $rows->filter(fn ($r) => $r['status'] === AttendanceStatus::Late)->count(),
                'absent' => $rows->filter(fn ($r) => $r['status'] === AttendanceStatus::Absent)->count(),
                'suspended' => $rows->filter(fn ($r) => $r['status'] === AttendanceStatus::Suspended)->count(),
                'unmarked' => $rows->filter(fn ($r) => $r['status'] === null)->count(),
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function accessibleClasses($user, string $year)
    {
        $query = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->orderBy('form_level')
            ->orderBy('section');

        if ($user->isTeacher()) {
            $ids = $user->taughtClassIds($year);
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get();
    }

    private function resolveDateQuery(mixed $value, string $fallback): Carbon
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return Carbon::parse($fallback)->startOfDay();
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $raw);

            return $date && $date->format('Y-m-d') === $raw
                ? $date->startOfDay()
                : Carbon::parse($fallback)->startOfDay();
        } catch (\Throwable) {
            return Carbon::parse($fallback)->startOfDay();
        }
    }
}
