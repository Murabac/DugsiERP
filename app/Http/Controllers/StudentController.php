<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Enums\WaitlistStatus;
use App\Models\ClassWaitlist;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\AcademicYear;
use App\Support\SomalilandCities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'cities' => SomalilandCities::all(),
            'dobMinYear' => $birthYears['min'],
            'dobMaxYear' => $birthYears['max'],
            'dobDefault' => AcademicYear::defaultDob(),
        ]);
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

        $message = $waitlisted
            ? $student->full_name.' was added to the waitlist for this class (class is full). Enroll them when capacity allows.'
            : 'Student added successfully. Profile is now live.';

        return redirect()
            ->route('students.show', $student)
            ->with('status', $message);
    }

    public function show(Student $student): View
    {
        $student->load([
            'guardians',
            'enrollments.schoolClass',
            'currentEnrollment.schoolClass',
            'primaryGuardian',
            'activeWaitlistEntry.schoolClass',
        ]);

        $enrollment = $student->currentEnrollment;
        $waitlist = $student->activeWaitlistEntry;

        return view('students.show', [
            'student' => $student,
            'enrollment' => $enrollment,
            'waitlist' => $waitlist,
            'schoolClass' => $enrollment?->schoolClass ?? $waitlist?->schoolClass,
            'tab' => request()->query('tab', 'overview'),
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
}
