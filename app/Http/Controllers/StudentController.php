<?php

namespace App\Http\Controllers;

use App\Enums\AcademicTerm;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\WaitlistStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassWaitlist;
use App\Models\DocumentLog;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AcademicYear;
use App\Support\SomalilandCities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function create(Request $request): View
    {
        $preselectedClassId = $request->query('class');
        $academicYear = AcademicYear::current();
        $birthYears = AcademicYear::birthYearBounds();

        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $preselected = $classes->firstWhere('id', (int) $preselectedClassId);

        return view('students.create', [
            'classes' => $classes,
            'preselectedClass' => $preselected,
            'genders' => Gender::cases(),
            'statuses' => array_values(array_filter(
                StudentStatus::cases(),
                fn (StudentStatus $s) => $s !== StudentStatus::Waitlisted
            )),
            'relationships' => GuardianRelationship::cases(),
            'academicYear' => $academicYear,
            'dobMinYear' => $birthYears['min'],
            'dobMaxYear' => $birthYears['max'],
            'dobDefault' => AcademicYear::defaultDob(),
            'cities' => SomalilandCities::all(),
        ]);
    }

    /**
     * Find students by parent/guardian name or phone across all classes.
     */
    public function byParent(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $families = collect();
        $tooShort = false;

        if ($q !== '') {
            if (mb_strlen($q) < 2) {
                $tooShort = true;
            } else {
                $like = '%'.$this->escapeLike($q).'%';

                $matched = Guardian::query()
                    ->where(function ($query) use ($like) {
                        // Bind ESCAPE char — inline ESCAPE '\' breaks PDO emulated prepares on MariaDB.
                        $query->whereRaw('full_name LIKE ? ESCAPE ?', [$like, '\\'])
                            ->orWhereRaw('phone LIKE ? ESCAPE ?', [$like, '\\']);
                    })
                    ->get();

                $phoneKeys = $matched
                    ->map(fn (Guardian $g) => $g->normalizedPhone())
                    ->filter()
                    ->unique()
                    ->values();

                $guardians = $matched;

                if ($phoneKeys->isNotEmpty()) {
                    $siblings = Guardian::query()
                        ->whereNotNull('phone')
                        ->where('phone', '!=', '')
                        ->whereNotIn('id', $matched->pluck('id')->all() ?: [0])
                        ->with(['student.currentEnrollment.schoolClass', 'student.primaryGuardian'])
                        ->get()
                        ->filter(fn (Guardian $g) => $phoneKeys->contains($g->normalizedPhone()));

                    $matched->loadMissing(['student.currentEnrollment.schoolClass', 'student.primaryGuardian']);
                    $guardians = $matched->concat($siblings)->unique('id')->values();
                } else {
                    $guardians->loadMissing(['student.currentEnrollment.schoolClass', 'student.primaryGuardian']);
                }

                $families = $guardians
                    ->groupBy(function (Guardian $g) {
                        $phone = $g->normalizedPhone();

                        return $phone !== '' ? 'phone:'.$phone : 'guardian:'.$g->id;
                    })
                    ->map(function ($group) use ($user) {
                        /** @var \Illuminate\Support\Collection<int, Guardian> $group */
                        $students = $group
                            ->pluck('student')
                            ->filter()
                            ->unique('id')
                            ->filter(fn (Student $s) => $user->canViewStudent($s))
                            ->values();

                        if ($students->isEmpty()) {
                            return null;
                        }

                        $primary = $group->firstWhere('is_primary', true) ?? $group->first();

                        return [
                            'parent_name' => $primary->full_name,
                            'parent_phone' => $primary->phone,
                            'relationship' => $primary->relationship?->label(),
                            'students' => $students->map(function (Student $student) use ($group) {
                                $links = $group->where('student_id', $student->id);

                                return [
                                    'student' => $student,
                                    'class' => $student->currentEnrollment?->schoolClass,
                                    'guardians' => $links->values(),
                                ];
                            }),
                        ];
                    })
                    ->filter()
                    ->sortBy('parent_name')
                    ->values();
            }
        }

        return view('students.by-parent', [
            'q' => $q,
            'families' => $families,
            'searched' => $q !== '',
            'tooShort' => $tooShort,
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = AcademicYear::current();
        $birthYears = AcademicYear::birthYearBounds();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => [
                'required',
                'date',
                'before:today',
                'after_or_equal:'.$birthYears['min'].'-01-01',
                'before_or_equal:'.$birthYears['max'].'-12-31',
            ],
            'gender' => ['required', Rule::enum(Gender::class)],
            'city' => ['nullable', 'string', Rule::in(SomalilandCities::all())],
            'address' => ['nullable', 'string', 'max:255'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'class_id' => [
                'required',
                Rule::exists('classes', 'id')->where(
                    fn ($q) => $q->where('status', ClassStatus::Active->value)
                        ->where('academic_year', $academicYear)
                ),
            ],
            'enrollment_date' => ['required', 'date'],
            'status' => [
                'required',
                Rule::enum(StudentStatus::class)->except([StudentStatus::Waitlisted]),
            ],
            'need_based_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:32'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
        ]);

        $data['academic_year'] = $academicYear;

        [$student, $waitlisted] = DB::transaction(function () use ($data, $request) {
            $schoolClass = SchoolClass::query()
                ->whereKey($data['class_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('students', 'public');
            }

            $onWaitlist = $schoolClass->isFull();

            $student = Student::query()->create([
                'student_code' => Student::nextStudentCode(),
                'full_name' => $data['full_name'],
                'dob' => $data['dob'],
                'gender' => $data['gender'],
                'photo_path' => $photoPath,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'previous_school' => $data['previous_school'] ?? null,
                'status' => $onWaitlist ? StudentStatus::Waitlisted : $data['status'],
                'need_based_discount_amount' => round((float) ($data['need_based_discount_amount'] ?? 0), 2),
            ]);

            Guardian::query()->create([
                'student_id' => $student->id,
                'full_name' => $data['guardian_name'],
                'phone' => $data['guardian_phone'],
                'relationship' => $data['relationship'],
                'is_primary' => true,
            ]);

            if ($onWaitlist) {
                ClassWaitlist::query()->create([
                    'student_id' => $student->id,
                    'class_id' => $schoolClass->id,
                    'academic_year' => $data['academic_year'],
                    'position' => $schoolClass->nextWaitlistPosition(),
                    'status' => WaitlistStatus::Waiting,
                ]);
            } else {
                Enrollment::query()->create([
                    'student_id' => $student->id,
                    'class_id' => $schoolClass->id,
                    'academic_year' => $data['academic_year'],
                    'roll_number' => $schoolClass->nextRollNumber(),
                    'enrollment_date' => $data['enrollment_date'],
                    'status' => $data['status'],
                ]);
            }

            return [$student, $onWaitlist];
        });

        if (! $waitlisted && $student->status === StudentStatus::Active) {
            $enrollment = $student->currentEnrollment()->with('schoolClass')->first();
            if ($enrollment?->schoolClass) {
                try {
                    \App\Support\MonthlyInvoiceGenerator::ensureForStudent(
                        $student->load('primaryGuardian'),
                        $enrollment->schoolClass,
                    );
                } catch (\Throwable) {
                    // Fee not configured or future month — admission still succeeds.
                }
            }
        }

        $message = $waitlisted
            ? $student->full_name.' was added to the waitlist for this class (class is full). Enroll them when capacity allows.'
            : 'Student added successfully. Profile is now live.';

        return redirect()
            ->route('students.show', $student)
            ->with('status', $message);
    }

    public function edit(Student $student): View
    {
        $academicYear = AcademicYear::current();
        $birthYears = AcademicYear::birthYearBounds();

        $student->load(['currentEnrollment.schoolClass', 'activeWaitlistEntry.schoolClass']);

        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        return view('students.edit', [
            'student' => $student,
            'enrollment' => $student->currentEnrollment,
            'waitlist' => $student->activeWaitlistEntry,
            'classes' => $classes,
            'genders' => Gender::cases(),
            'statuses' => array_values(array_filter(
                StudentStatus::cases(),
                fn (StudentStatus $s) => $s !== StudentStatus::Waitlisted
            )),
            'academicYear' => $academicYear,
            'dobMinYear' => $birthYears['min'],
            'dobMaxYear' => $birthYears['max'],
            'dobDefault' => AcademicYear::defaultDob(),
            'cities' => SomalilandCities::all(),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $academicYear = AcademicYear::current();
        $birthYears = AcademicYear::birthYearBounds();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => [
                'required',
                'date',
                'before:today',
                'after_or_equal:'.$birthYears['min'].'-01-01',
                'before_or_equal:'.$birthYears['max'].'-12-31',
            ],
            'gender' => ['required', Rule::enum(Gender::class)],
            'city' => ['nullable', 'string', Rule::in(SomalilandCities::all())],
            'address' => ['nullable', 'string', 'max:255'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'class_id' => [
                'nullable',
                'integer',
                Rule::exists('classes', 'id')->where(
                    fn ($q) => $q->where('status', ClassStatus::Active->value)
                        ->where('academic_year', $academicYear)
                ),
            ],
            'enrollment_date' => ['nullable', 'date'],
            'status' => [
                'required',
                $student->status === StudentStatus::Waitlisted
                    ? Rule::in([StudentStatus::Waitlisted->value])
                    : Rule::enum(StudentStatus::class)->except([StudentStatus::Waitlisted]),
            ],
            'need_based_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        DB::transaction(function () use ($student, $data, $request, $academicYear) {
            $photoPath = $student->photo_path;
            $previousPhoto = $student->photo_path;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('students', 'public');
            }

            $student->update([
                'full_name' => $data['full_name'],
                'dob' => $data['dob'],
                'gender' => $data['gender'],
                'photo_path' => $photoPath,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'previous_school' => $data['previous_school'] ?? null,
                'status' => $data['status'],
                'need_based_discount_amount' => round((float) ($data['need_based_discount_amount'] ?? 0), 2),
            ]);

            if ($request->hasFile('photo') && $previousPhoto && $previousPhoto !== $photoPath) {
                Storage::disk('public')->delete($previousPhoto);
            }

            $enrollment = $student->currentEnrollment()->lockForUpdate()->first();
            if ($enrollment) {
                $enrollmentUpdates = [
                    'status' => $data['status'],
                ];
                if (! empty($data['enrollment_date'])) {
                    $enrollmentUpdates['enrollment_date'] = $data['enrollment_date'];
                }

                $oldClassId = (int) $enrollment->class_id;
                $newClassId = (int) ($data['class_id'] ?? 0);
                if ($newClassId > 0 && $newClassId !== $oldClassId) {
                    $newClass = SchoolClass::query()->whereKey($newClassId)->lockForUpdate()->firstOrFail();
                    if ($newClass->isFull() && ! $newClass->enrollments()->where('student_id', $student->id)->exists()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'class_id' => $newClass->displayName().' is full. Increase capacity or choose another class.',
                        ]);
                    }
                    $enrollmentUpdates['class_id'] = $newClass->id;
                    $enrollmentUpdates['academic_year'] = $academicYear;
                    $enrollmentUpdates['roll_number'] = $newClass->nextRollNumber();

                    // Keep open invoices and current-year grades with the new class.
                    Invoice::query()
                        ->where('student_id', $student->id)
                        ->where('academic_year', $academicYear)
                        ->where('class_id', $oldClassId)
                        ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
                        ->update(['class_id' => $newClass->id]);

                    Grade::query()
                        ->where('student_id', $student->id)
                        ->where('academic_year', $academicYear)
                        ->where('class_id', $oldClassId)
                        ->update(['class_id' => $newClass->id]);
                }

                $enrollment->update($enrollmentUpdates);
            }
        });

        \App\Support\MonthlyInvoiceGenerator::recalculateUnpaid($student->fresh());

        return redirect()
            ->route('students.show', $student)
            ->with('status', 'Student profile updated.');
    }

    public function show(Request $request, Student $student): View|RedirectResponse
    {
        abort_unless($request->user()->canViewStudent($student), 403);

        $user = $request->user();
        $canSeeFees = $user->isAdmin();
        $canSeeDocuments = $user->isAdmin();
        $canSeeTransport = $user->isAdmin() || $user->isFinance();
        $isFinanceOnly = $user->isFinance() && ! $user->isAdmin();

        if ($isFinanceOnly) {
            $allowedTabs = ['overview', 'guardians'];
        } else {
            $allowedTabs = ['overview', 'guardians', 'attendance', 'grades'];
        }
        if ($canSeeFees) {
            $allowedTabs[] = 'fees';
        }
        if ($canSeeDocuments) {
            $allowedTabs[] = 'documents';
        }
        if ($canSeeTransport) {
            $allowedTabs[] = 'transport';
        }

        $tab = (string) $request->query('tab', 'overview');
        if (! in_array($tab, $allowedTabs, true)) {
            return redirect()->route('students.show', ['student' => $student, 'tab' => 'overview']);
        }

        $student->load([
            'guardians',
            'enrollments.schoolClass',
            'currentEnrollment.schoolClass',
            'primaryGuardian',
            'activeWaitlistEntry.schoolClass',
            'activeTransportAssignment.route.vehicle',
            'activeTransportAssignment.stop',
        ]);

        $enrollment = $student->currentEnrollment;
        $waitlist = $student->activeWaitlistEntry;

        $attendanceHistory = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->orderByDesc('date')
            ->limit(30)
            ->get();

        $attendanceStats = [
            'present' => $attendanceHistory->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Present)->count(),
            'late' => $attendanceHistory->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Late)->count(),
            'absent' => $attendanceHistory->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Absent)->count(),
            'suspended' => $attendanceHistory->filter(fn ($r) => $r->status === \App\Enums\AttendanceStatus::Suspended)->count(),
        ];
        $attendanceTotal = array_sum($attendanceStats);
        $attendanceRate = $attendanceTotal > 0
            ? round((($attendanceStats['present'] + $attendanceStats['late']) / $attendanceTotal) * 100, 1)
            : null;

        $gradeTerm = AcademicTerm::tryFrom((string) $request->query('term', AcademicTerm::Term2->value))
            ?? AcademicTerm::Term2;
        $year = AcademicYear::current();

        $studentGrades = collect();
        if ($enrollment) {
            $studentGrades = Grade::query()
                ->with('subject')
                ->where('student_id', $student->id)
                ->where('class_id', $enrollment->class_id)
                ->where('term', $gradeTerm)
                ->where('academic_year', $year)
                ->get()
                ->sortBy(fn (Grade $g) => $g->subject?->sort_order ?? 999)
                ->values();
        }

        $studentInvoices = collect();
        $studentDocuments = collect();
        if ($canSeeFees) {
            $studentInvoices = Invoice::query()
                ->with(['payments', 'schoolClass'])
                ->where('student_id', $student->id)
                ->where('academic_year', $year)
                ->orderByDesc('billing_month')
                ->get();
        }
        if ($canSeeDocuments) {
            $studentDocuments = DocumentLog::query()
                ->where('student_id', $student->id)
                ->latest('generated_at')
                ->limit(50)
                ->get();
        }

        $transportRoutes = collect();
        if ($canSeeTransport) {
            $transportRoutes = \App\Models\TransportRoute::query()
                ->with('vehicle')
                ->withCount('activeAssignments')
                ->where('academic_year', $year)
                ->where('status', \App\Enums\TransportRouteStatus::Active)
                ->orderBy('name')
                ->get();
        }

        return view('students.show', [
            'student' => $student,
            'enrollment' => $enrollment,
            'waitlist' => $waitlist,
            'schoolClass' => $enrollment?->schoolClass ?? $waitlist?->schoolClass,
            'tab' => $tab,
            'canSeeFees' => $canSeeFees,
            'canSeeDocuments' => $canSeeDocuments,
            'canSeeTransport' => $canSeeTransport,
            'transportRoutes' => $transportRoutes,
            'transportFeeUsd' => \App\Models\SchoolSetting::transportFeeUsd(),
            'attendanceHistory' => $attendanceHistory,
            'attendanceRate' => $attendanceRate,
            'gradeTerm' => $gradeTerm,
            'gradeTerms' => AcademicTerm::options(),
            'studentGrades' => $studentGrades,
            'studentInvoices' => $studentInvoices,
            'studentDocuments' => $studentDocuments,
            'academicYear' => $year,
        ]);
    }

    public function storeGuardian(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($student, $data) {
            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                $student->guardians()->update(['is_primary' => false]);
            }

            if (! $student->guardians()->exists()) {
                $isPrimary = true;
            }

            $student->guardians()->create([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'relationship' => $data['relationship'],
                'is_primary' => $isPrimary,
            ]);
        });

        return redirect()
            ->route('students.show', ['student' => $student, 'tab' => 'guardians'])
            ->with('status', 'Guardian added.');
    }

    public function updateGuardian(Request $request, Student $student, Guardian $guardian): RedirectResponse
    {
        abort_unless((int) $guardian->student_id === (int) $student->id, 404);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($student, $guardian, $data, $request) {
            $isPrimary = $request->boolean('is_primary');

            if ($isPrimary) {
                $student->guardians()->where('id', '!=', $guardian->id)->update(['is_primary' => false]);
            } elseif ($guardian->is_primary) {
                // Keep at least one primary contact.
                $isPrimary = true;
            }

            $guardian->update([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'relationship' => $data['relationship'],
                'is_primary' => $isPrimary,
            ]);
        });

        return redirect()
            ->route('students.show', ['student' => $student, 'tab' => 'guardians'])
            ->with('status', 'Guardian updated.');
    }

    public function destroyGuardian(Student $student, Guardian $guardian): RedirectResponse
    {
        abort_unless((int) $guardian->student_id === (int) $student->id, 404);

        if ($student->guardians()->count() <= 1) {
            return redirect()
                ->route('students.show', ['student' => $student, 'tab' => 'guardians'])
                ->withErrors(['guardian' => 'A student must have at least one guardian.']);
        }

        DB::transaction(function () use ($student, $guardian) {
            $wasPrimary = $guardian->is_primary;
            $guardian->delete();

            if ($wasPrimary) {
                $student->guardians()->orderBy('id')->limit(1)->update(['is_primary' => true]);
            }
        });

        return redirect()
            ->route('students.show', ['student' => $student, 'tab' => 'guardians'])
            ->with('status', 'Guardian removed.');
    }

    public function updateNeedBased(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'need_based_discount_amount' => ['required', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        $student->update([
            'need_based_discount_amount' => round((float) $data['need_based_discount_amount'], 2),
        ]);

        $revised = \App\Support\MonthlyInvoiceGenerator::recalculateUnpaid($student->fresh());

        $amount = (float) $student->need_based_discount_amount;
        $message = $amount > 0
            ? 'Need-based fee discount set to '.\App\Support\Money::format($amount).' for '.$student->full_name.'.'
            : 'Need-based fee discount cleared for '.$student->full_name.'.';
        if ($revised > 0) {
            $message .= ' Updated '.$revised.' unpaid invoice'.($revised === 1 ? '' : 's').'.';
        }

        return redirect()
            ->route('students.show', ['student' => $student, 'tab' => 'overview'])
            ->with('status', $message);
    }
}
