<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use App\Support\Subjects as SubjectCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $academicYear = AcademicYear::current();
        $canEdit = $user->isAdmin();

        if ($canEdit) {
            return $this->adminIndex($request, $academicYear);
        }

        return $this->teacherIndex($request, $academicYear);
    }

    public function print(Request $request): View
    {
        $user = $request->user();
        $academicYear = AcademicYear::current();

        if ($user->isAdmin()) {
            $class = $this->resolveClass($request, $academicYear);
            abort_unless($class, 404, 'No active class available to print.');

            return view('timetable.print', [
                'mode' => 'class',
                'schoolClass' => $class,
                'academicYear' => $academicYear,
                'days' => SchoolWeek::days(),
                'periods' => SchoolWeek::periods(),
                'grid' => $this->buildClassGrid($class, $academicYear),
                'subjectColors' => SchoolWeek::subjectColors(),
            ]);
        }

        $staff = $user->staff;
        abort_unless($staff, 403);

        if ($request->query('view') === 'class') {
            $class = $this->resolveTeacherClass($staff->id, $academicYear, $request->query('class'));
            abort_unless($class, 404);

            return view('timetable.print', [
                'mode' => 'class',
                'schoolClass' => $class,
                'academicYear' => $academicYear,
                'days' => SchoolWeek::days(),
                'periods' => SchoolWeek::periods(),
                'grid' => $this->buildClassGrid($class, $academicYear),
                'subjectColors' => SchoolWeek::subjectColors(),
                'highlightTeacherId' => $staff->id,
            ]);
        }

        return view('timetable.print', [
            'mode' => 'teacher',
            'staff' => $staff,
            'academicYear' => $academicYear,
            'days' => SchoolWeek::days(),
            'periods' => SchoolWeek::periods(),
            'grid' => $this->buildTeacherGrid($staff->id, $academicYear),
            'subjectColors' => SchoolWeek::subjectColors(),
        ]);
    }

    private function resolveTeacherClass(int $teacherId, string $academicYear, mixed $classId): ?SchoolClass
    {
        $teachingClassIds = TimetableSlot::query()
            ->where('academic_year', $academicYear)
            ->where('teacher_id', $teacherId)
            ->distinct()
            ->pluck('class_id');

        if ($teachingClassIds->isEmpty()) {
            return null;
        }

        $id = (int) ($classId ?: $teachingClassIds->first());

        return SchoolClass::query()
            ->whereIn('id', $teachingClassIds)
            ->where('id', $id)
            ->where('academic_year', $academicYear)
            ->first()
            ?? SchoolClass::query()->whereIn('id', $teachingClassIds)->orderBy('form_level')->orderBy('section')->first();
    }

    public function upsertSlot(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $academicYear = AcademicYear::current();

        $data = $request->validate([
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')->where(
                    fn ($q) => $q->where('status', ClassStatus::Active->value)
                        ->where('academic_year', $academicYear)
                ),
            ],
            'day_of_week' => ['required', Rule::in(SchoolWeek::days())],
            'period_number' => ['required', 'integer', 'between:1,'.SchoolWeek::periodCount()],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => [
                'required',
                Rule::exists('staff', 'id')->where(
                    fn ($q) => $q->where('role_label', StaffRoleLabel::Teacher->value)
                        ->where('status', StaffStatus::Active->value)
                ),
            ],
        ]);

        $period = SchoolWeek::period((int) $data['period_number']);
        abort_unless($period, 422);

        $assigned = TeacherSubjectAssignment::query()
            ->where('staff_id', $data['teacher_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'teacher_id' => 'That teacher is not assigned to the selected subject.',
            ]);
        }

        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $room = $class->classroom();

        $this->assertNoTeacherConflict(
            academicYear: $academicYear,
            day: $data['day_of_week'],
            periodNumber: (int) $data['period_number'],
            teacherId: (int) $data['teacher_id'],
            classId: (int) $data['class_id'],
        );

        TimetableSlot::query()->updateOrCreate(
            [
                'class_id' => $data['class_id'],
                'academic_year' => $academicYear,
                'day_of_week' => $data['day_of_week'],
                'period_number' => $data['period_number'],
            ],
            [
                'start_time' => $period['start'],
                'end_time' => $period['end'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $data['teacher_id'],
                'room' => $room,
            ]
        );

        return redirect()
            ->route('timetable.index', ['class' => $data['class_id']])
            ->with('status', 'Timetable slot saved.');
    }

    public function clearSlot(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $academicYear = AcademicYear::current();

        $data = $request->validate([
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')->where(
                    fn ($q) => $q->where('status', ClassStatus::Active->value)
                        ->where('academic_year', $academicYear)
                ),
            ],
            'day_of_week' => ['required', Rule::in(SchoolWeek::days())],
            'period_number' => ['required', 'integer', 'between:1,'.SchoolWeek::periodCount()],
        ]);

        TimetableSlot::query()
            ->where('class_id', $data['class_id'])
            ->where('academic_year', $academicYear)
            ->where('day_of_week', $data['day_of_week'])
            ->where('period_number', $data['period_number'])
            ->delete();

        return redirect()
            ->route('timetable.index', ['class' => $data['class_id']])
            ->with('status', 'Slot cleared.');
    }

    public function swapSlots(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $academicYear = AcademicYear::current();
        $maxPeriod = SchoolWeek::periodCount();

        $data = $request->validate([
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')->where(
                    fn ($q) => $q->where('status', ClassStatus::Active->value)
                        ->where('academic_year', $academicYear)
                ),
            ],
            'from_day' => ['required', Rule::in(SchoolWeek::days())],
            'from_period' => ['required', 'integer', 'between:1,'.$maxPeriod],
            'to_day' => ['required', Rule::in(SchoolWeek::days())],
            'to_period' => ['required', 'integer', 'between:1,'.$maxPeriod],
        ]);

        $classId = (int) $data['class_id'];
        $fromDay = $data['from_day'];
        $fromPeriod = (int) $data['from_period'];
        $toDay = $data['to_day'];
        $toPeriod = (int) $data['to_period'];

        if ($fromDay === $toDay && $fromPeriod === $toPeriod) {
            return redirect()->route('timetable.index', ['class' => $classId]);
        }

        $from = TimetableSlot::query()
            ->where('class_id', $classId)
            ->where('academic_year', $academicYear)
            ->where('day_of_week', $fromDay)
            ->where('period_number', $fromPeriod)
            ->first();

        $to = TimetableSlot::query()
            ->where('class_id', $classId)
            ->where('academic_year', $academicYear)
            ->where('day_of_week', $toDay)
            ->where('period_number', $toPeriod)
            ->first();

        if (! $from) {
            throw ValidationException::withMessages([
                'from_day' => 'Nothing to move from that cell.',
            ]);
        }

        // After move/swap, teachers must be free at their new times in other classes.
        if ($from->teacher_id && $this->teacherIsBusy($academicYear, $toDay, $toPeriod, (int) $from->teacher_id, $classId)) {
            throw ValidationException::withMessages([
                'to_day' => 'Cannot move: that teacher is already booked in another class at the target period.',
            ]);
        }

        if ($to?->teacher_id && $this->teacherIsBusy($academicYear, $fromDay, $fromPeriod, (int) $to->teacher_id, $classId)) {
            throw ValidationException::withMessages([
                'to_day' => 'Cannot swap: the other teacher is already booked in another class at the source period.',
            ]);
        }

        DB::transaction(function () use ($from, $to, $toDay, $toPeriod, $fromDay, $fromPeriod) {
            if ($to) {
                $fromSubject = $from->subject_id;
                $fromTeacher = $from->teacher_id;
                $from->update([
                    'subject_id' => $to->subject_id,
                    'teacher_id' => $to->teacher_id,
                ]);
                $to->update([
                    'subject_id' => $fromSubject,
                    'teacher_id' => $fromTeacher,
                ]);

                return;
            }

            $period = SchoolWeek::period($toPeriod);
            abort_unless($period, 422);

            $from->update([
                'day_of_week' => $toDay,
                'period_number' => $toPeriod,
                'start_time' => $period['start'],
                'end_time' => $period['end'],
            ]);
        });

        return redirect()
            ->route('timetable.index', ['class' => $classId])
            ->with('status', 'Timetable rearranged.');
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $academicYear = AcademicYear::current();
        $defaults = SchoolWeek::defaultWeeklyPeriods();
        $subjectNames = SubjectCatalog::all();

        $rules = [
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')->where(
                    fn ($q) => $q->where('status', ClassStatus::Active->value)
                        ->where('academic_year', $academicYear)
                ),
            ],
            'periods' => ['required', 'array'],
        ];

        foreach ($subjectNames as $name) {
            $rules['periods.'.$name] = ['required', 'integer', 'min:0', 'max:'.SchoolWeek::weeklyCapacity()];
        }

        $data = $request->validate($rules);

        $periodCounts = [];
        $total = 0;
        foreach ($subjectNames as $name) {
            $count = (int) ($data['periods'][$name] ?? $defaults[$name] ?? 0);
            $periodCounts[$name] = $count;
            $total += $count;
        }

        if ($total > SchoolWeek::weeklyCapacity()) {
            throw ValidationException::withMessages([
                'periods' => 'Total periods ('.$total.') exceed weekly capacity ('.SchoolWeek::weeklyCapacity().').',
            ]);
        }

        if ($total < 1) {
            throw ValidationException::withMessages([
                'periods' => 'Set at least one subject period before generating.',
            ]);
        }

        $class = SchoolClass::query()->findOrFail((int) $data['class_id']);
        $classId = $class->id;
        $room = $class->classroom();

        $subjects = Subject::query()->orderBy('sort_order')->get()->keyBy('name');
        $teachersBySubject = TeacherSubjectAssignment::query()
            ->whereIn('subject_id', $subjects->pluck('id'))
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('staff_id')->unique()->values()->all());

        $missingTeachers = [];
        foreach ($periodCounts as $name => $count) {
            if ($count < 1) {
                continue;
            }
            $subject = $subjects->get($name);
            if (! $subject || ($teachersBySubject->get($subject->id) ?? []) === []) {
                $missingTeachers[] = $name;
            }
        }

        if ($missingTeachers !== []) {
            throw ValidationException::withMessages([
                'periods' => 'Assign a teacher to these subjects before generating: '.implode(', ', $missingTeachers).'.',
            ]);
        }

        $placed = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $classId,
            $academicYear,
            $periodCounts,
            $subjects,
            $teachersBySubject,
            $room,
            &$placed,
            &$skipped,
        ) {
            TimetableSlot::query()
                ->where('class_id', $classId)
                ->where('academic_year', $academicYear)
                ->delete();

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

            // Class-specific shuffle so each class gets a unique timetable shape.
            $this->seededShuffle($queue, $classId * 9973 + strlen($academicYear));

            $cells = [];
            foreach (SchoolWeek::periods() as $period) {
                foreach (SchoolWeek::days() as $day) {
                    $cells[] = [$day, $period];
                }
            }
            $this->seededShuffle($cells, $classId * 7919 + 17);

            $busyTeachers = TimetableSlot::query()
                ->where('academic_year', $academicYear)
                ->where('class_id', '!=', $classId)
                ->whereNotNull('teacher_id')
                ->get(['day_of_week', 'period_number', 'teacher_id'])
                ->groupBy(fn ($s) => $s->day_of_week.'|'.$s->period_number)
                ->map(fn ($rows) => $rows->pluck('teacher_id')->all());

            foreach ($cells as [$day, $period]) {
                if ($queue === []) {
                    break;
                }

                $key = $day.'|'.$period['period'];
                $busy = $busyTeachers[$key] ?? [];
                $placedThisCell = false;

                foreach ($queue as $qi => $subject) {
                    $candidates = $teachersBySubject->get($subject->id) ?? [];
                    $teacherId = null;
                    foreach ($candidates as $candidateId) {
                        if (! in_array($candidateId, $busy, true)) {
                            $teacherId = $candidateId;
                            break;
                        }
                    }

                    // Never place a period without an available teacher.
                    if ($teacherId === null) {
                        continue;
                    }

                    TimetableSlot::query()->create([
                        'class_id' => $classId,
                        'academic_year' => $academicYear,
                        'day_of_week' => $day,
                        'period_number' => $period['period'],
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
        });

        $message = "Timetable generated for {$class->displayName()} ({$placed} periods).";
        if ($skipped > 0) {
            $message .= " {$skipped} period(s) could not be placed due to teacher conflicts.";
        }

        return redirect()
            ->route('timetable.index', ['class' => $classId])
            ->with('status', $message);
    }

    private function adminIndex(Request $request, string $academicYear): View
    {
        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $class = $this->resolveClass($request, $academicYear, $classes);

        $subjects = Subject::query()->orderBy('sort_order')->get();
        $teachers = Staff::query()
            ->where('role_label', StaffRoleLabel::Teacher)
            ->where('status', StaffStatus::Active)
            ->orderBy('full_name')
            ->get();

        $teachersBySubject = TeacherSubjectAssignment::query()
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('staff_id')->unique()->values());

        return view('timetable.index', [
            'mode' => 'admin',
            'canEdit' => true,
            'classes' => $classes,
            'schoolClass' => $class,
            'academicYear' => $academicYear,
            'days' => SchoolWeek::days(),
            'periods' => SchoolWeek::periods(),
            'grid' => $class ? $this->buildClassGrid($class, $academicYear) : [],
            'subjects' => $subjects,
            'teachers' => $teachers,
            'teachersBySubject' => $teachersBySubject,
            'subjectColors' => SchoolWeek::subjectColors(),
            'defaultWeeklyPeriods' => SchoolWeek::defaultWeeklyPeriods(),
            'weeklyCapacity' => SchoolWeek::weeklyCapacity(),
            'periodCount' => SchoolWeek::periodCount(),
        ]);
    }

    private function teacherIndex(Request $request, string $academicYear): View
    {
        $staff = $request->user()->staff;
        abort_unless($staff, 403, 'Your account is not linked to a staff record.');

        $teacherView = $request->query('view') === 'class' ? 'class' : 'mine';

        $myGrid = $this->buildTeacherGrid($staff->id, $academicYear);

        $teachingClassIds = TimetableSlot::query()
            ->where('academic_year', $academicYear)
            ->where('teacher_id', $staff->id)
            ->distinct()
            ->pluck('class_id');

        $teachingClasses = SchoolClass::query()
            ->whereIn('id', $teachingClassIds)
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $schoolClass = null;
        $classGrid = [];
        if ($teachingClasses->isNotEmpty()) {
            $requestedId = (int) $request->query('class', $teachingClasses->first()->id);
            $schoolClass = $teachingClasses->firstWhere('id', $requestedId) ?? $teachingClasses->first();
            $classGrid = $this->buildClassGrid($schoolClass, $academicYear);
        }

        return view('timetable.teacher', [
            'mode' => 'teacher',
            'canEdit' => false,
            'teacherView' => $teacherView,
            'staff' => $staff,
            'academicYear' => $academicYear,
            'days' => SchoolWeek::days(),
            'periods' => SchoolWeek::periods(),
            'myGrid' => $myGrid,
            'mySubjectColors' => $this->colorsForGrid($myGrid),
            'teachingClasses' => $teachingClasses,
            'schoolClass' => $schoolClass,
            'classGrid' => $classGrid,
            'classSubjectColors' => $classGrid !== [] ? SchoolWeek::subjectColors() : [],
        ]);
    }

    /**
     * @param  array<int, array<string, TimetableSlot|null>>  $grid
     * @return array<string, string>
     */
    private function colorsForGrid(array $grid): array
    {
        $all = SchoolWeek::subjectColors();
        $names = [];
        foreach ($grid as $row) {
            foreach ($row as $slot) {
                if ($slot?->subject?->name) {
                    $names[$slot->subject->name] = true;
                }
            }
        }

        return array_intersect_key($all, $names);
    }

    private function resolveClass(Request $request, string $academicYear, $classes = null): ?SchoolClass
    {
        $classes ??= SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        if ($classes->isEmpty()) {
            return null;
        }

        $id = (int) $request->query('class', $classes->first()->id);

        return $classes->firstWhere('id', $id) ?? $classes->first();
    }

    /**
     * @return array<int, array<string, TimetableSlot|null>>
     */
    private function buildClassGrid(SchoolClass $class, string $academicYear): array
    {
        $slots = TimetableSlot::query()
            ->with(['subject', 'teacher'])
            ->where('class_id', $class->id)
            ->where('academic_year', $academicYear)
            ->get();

        $grid = [];
        foreach (SchoolWeek::periods() as $period) {
            foreach (SchoolWeek::days() as $day) {
                $grid[$period['period']][$day] = null;
            }
        }

        foreach ($slots as $slot) {
            $grid[$slot->period_number][$slot->day_of_week] = $slot;
        }

        return $grid;
    }

    /**
     * @return array<int, array<string, TimetableSlot|null>>
     */
    private function buildTeacherGrid(int $teacherId, string $academicYear): array
    {
        $slots = TimetableSlot::query()
            ->with(['subject', 'schoolClass'])
            ->where('teacher_id', $teacherId)
            ->where('academic_year', $academicYear)
            ->get();

        $grid = [];
        foreach (SchoolWeek::periods() as $period) {
            foreach (SchoolWeek::days() as $day) {
                $grid[$period['period']][$day] = null;
            }
        }

        foreach ($slots as $slot) {
            $grid[$slot->period_number][$slot->day_of_week] = $slot;
        }

        return $grid;
    }

    private function assertNoTeacherConflict(
        string $academicYear,
        string $day,
        int $periodNumber,
        int $teacherId,
        int $classId,
    ): void {
        if ($this->teacherIsBusy($academicYear, $day, $periodNumber, $teacherId, $classId)) {
            throw ValidationException::withMessages([
                'teacher_id' => 'This teacher is already booked for that day and period in another class.',
            ]);
        }
    }

    private function teacherIsBusy(
        string $academicYear,
        string $day,
        int $periodNumber,
        int $teacherId,
        int $classId,
    ): bool {
        return TimetableSlot::query()
            ->where('academic_year', $academicYear)
            ->where('day_of_week', $day)
            ->where('period_number', $periodNumber)
            ->where('teacher_id', $teacherId)
            ->where('class_id', '!=', $classId)
            ->exists();
    }

    /**
     * Deterministic Fisher–Yates shuffle (unique shape per class, stable across runs).
     *
     * @param  list<mixed>  $items
     */
    private function seededShuffle(array &$items, int $seed): void
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
