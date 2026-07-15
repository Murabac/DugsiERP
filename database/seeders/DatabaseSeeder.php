<?php

namespace Database\Seeders;

use App\Enums\AcademicTerm;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeBoundary;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\GradeScale;
use App\Support\SchoolWeek;
use App\Support\Subjects;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedStaffAndUsers();
        $this->seedSubjectsAndAssignments();
        $this->seedClassesAndStudents();
        $this->seedAttendance();
        $this->seedGrades();
    }

    private function seedStaffAndUsers(): void
    {
        $password = Hash::make('password');

        // Demo logins + design-reference staff (App.tsx STAFF list).
        $people = [
            [
                'staff' => [
                    'employee_code' => 'EMP-100',
                    'full_name' => 'Axmed Nuur Ibrahim',
                    'role_label' => StaffRoleLabel::Admin,
                    'fixed_salary_usd' => 800,
                    'date_joined' => '2016-01-10',
                    'phone' => '+252634000001',
                    'gender' => Gender::Male,
                ],
                'user' => [
                    'email' => 'superadmin@dugsi.edu.sl',
                    'role' => UserRole::SuperAdmin,
                ],
            ],
            [
                'staff' => [
                    'employee_code' => 'EMP-005',
                    'full_name' => 'Cabdi Xasan Ciise',
                    'role_label' => StaffRoleLabel::Admin,
                    'fixed_salary_usd' => 720,
                    'date_joined' => '2017-03-05',
                    'phone' => '+252634000005',
                    'gender' => Gender::Male,
                ],
                'user' => [
                    'email' => 'admin@dugsi.edu.sl',
                    'role' => UserRole::Admin,
                ],
            ],
            [
                'staff' => [
                    'employee_code' => 'EMP-006',
                    'full_name' => 'Ruqiyo Maxamed Nuur',
                    'role_label' => StaffRoleLabel::Finance,
                    'fixed_salary_usd' => 680,
                    'date_joined' => '2020-06-01',
                    'phone' => '+252634000006',
                    'gender' => Gender::Female,
                ],
                'user' => [
                    'email' => 'finance@dugsi.edu.sl',
                    'role' => UserRole::Finance,
                ],
            ],
            [
                'staff' => [
                    'employee_code' => 'EMP-001',
                    'full_name' => 'Abdirahman Farah Jama',
                    'role_label' => StaffRoleLabel::Teacher,
                    'subject_specialty' => 'Mathematics',
                    'qualification' => "Bachelor's Degree",
                    'fixed_salary_usd' => 620,
                    'date_joined' => '2019-09-01',
                    'phone' => '+252634000101',
                    'gender' => Gender::Male,
                ],
                'user' => [
                    'email' => 'teacher@dugsi.edu.sl',
                    'role' => UserRole::Teacher,
                ],
            ],
        ];

        $extraTeachers = [
            ['EMP-002', 'Hodan Jama Axmed', 'English', 590, '2020-01-15', Gender::Female, '+252634000102'],
            ['EMP-003', 'Mohamed Ali Warsame', 'Physics', 650, '2018-08-20', Gender::Male, '+252634000103'],
            ['EMP-004', 'Fatuma Hassan Dirie', 'Biology', 570, '2021-02-10', Gender::Female, '+252634000104'],
            ['EMP-007', 'Xaawo Ibrahim Muuse', 'Somali Language', 580, '2019-09-01', Gender::Female, '+252634000107'],
            ['EMP-008', 'Axmed Muuse Warsame', 'Arabic Language', 600, '2018-01-10', Gender::Male, '+252634000108'],
            ['EMP-009', 'Yusuf Cabdi Axmed', 'Islamic Studies', 575, '2020-09-01', Gender::Male, '+252634000109'],
            ['EMP-010', 'Nuur Axmed Gaas', 'Geography', 560, '2021-09-01', Gender::Male, '+252634000110'],
            ['EMP-011', 'Raxmo Warsame Dirie', 'History', 555, '2022-01-15', Gender::Female, '+252634000111'],
            ['EMP-012', 'Khalid Daahir Ciise', 'Chemistry', 610, '2019-09-01', Gender::Male, '+252634000112'],
        ];

        foreach ($people as $row) {
            $staffData = array_merge([
                'status' => StaffStatus::Active,
                'subject_specialty' => null,
                'qualification' => null,
                'gender' => null,
            ], $row['staff']);

            // Preserve a locally created EMP-001 (e.g. personal admin) — put Math teacher on EMP-101 instead.
            if ($row['staff']['employee_code'] === 'EMP-001') {
                $existing = Staff::query()->where('employee_code', 'EMP-001')->first();
                if ($existing && $existing->role_label !== StaffRoleLabel::Teacher) {
                    $staffData['employee_code'] = 'EMP-101';
                }
            }

            $staff = Staff::query()->updateOrCreate(
                ['employee_code' => $staffData['employee_code']],
                $staffData
            );

            User::query()->updateOrCreate(
                ['email' => $row['user']['email']],
                [
                    'name' => $staff->full_name,
                    'phone' => $staff->phone,
                    'password' => $password,
                    'role' => $row['user']['role'],
                    'is_active' => true,
                    'staff_id' => $staff->id,
                    'email_verified_at' => now(),
                ]
            );
        }

        foreach ($extraTeachers as [$code, $name, $subject, $salary, $joined, $gender, $phone]) {
            Staff::query()->updateOrCreate(
                ['employee_code' => $code],
                [
                    'full_name' => $name,
                    'role_label' => StaffRoleLabel::Teacher,
                    'subject_specialty' => $subject,
                    'fixed_salary_usd' => $salary,
                    'date_joined' => $joined,
                    'gender' => $gender,
                    'phone' => $phone,
                    'qualification' => "Bachelor's Degree",
                    'status' => StaffStatus::Active,
                ]
            );
        }
    }

    private function seedSubjectsAndAssignments(): void
    {
        foreach (Subjects::all() as $index => $name) {
            Subject::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1]
            );
        }

        $teachers = Staff::query()
            ->where('role_label', StaffRoleLabel::Teacher)
            ->whereNotNull('subject_specialty')
            ->get();

        foreach ($teachers as $teacher) {
            $subject = Subject::query()->where('name', $teacher->subject_specialty)->first();
            if (! $subject) {
                continue;
            }

            TeacherSubjectAssignment::query()->updateOrCreate(
                [
                    'staff_id' => $teacher->id,
                    'subject_id' => $subject->id,
                ],
                ['class_id' => null]
            );
        }
    }

    private function seedClassesAndStudents(): void
    {
        $year = AcademicYear::current();
        $sections = [
            [1, 'A'], [1, 'B'],
            [2, 'A'], [2, 'B'],
            [3, 'A'], [3, 'B'],
            [4, 'A'], [4, 'B'],
        ];

        foreach ($sections as [$form, $section]) {
            SchoolClass::query()->updateOrCreate(
                [
                    'form_level' => $form,
                    'section' => $section,
                    'academic_year' => $year,
                ],
                [
                    'capacity' => 30,
                    'room' => SchoolWeek::defaultClassRoom($form, $section),
                    'status' => ClassStatus::Active,
                ]
            );
        }

        // Demo headmaster: Math teacher (teacher@dugsi.edu.sl) for Form 1-A.
        $mathTeacher = Staff::query()
            ->whereIn('employee_code', ['EMP-001', 'EMP-101'])
            ->where('role_label', StaffRoleLabel::Teacher)
            ->orderBy('employee_code')
            ->first();
        if ($mathTeacher) {
            SchoolClass::query()
                ->where('form_level', 1)
                ->where('section', 'A')
                ->where('academic_year', $year)
                ->update(['homeroom_teacher_id' => $mathTeacher->id]);
        }

        // Design-reference STUDENTS (+ a few extras so every class has roster data).
        $samples = [
            ['STU-001', 'Faadumo Xasan Warsame', Gender::Female, '2010-03-15', 'Hargeisa', '1-A', 'Xasan Warsame Jama', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-002', 'Cabdiraxmaan Faarax Muuse', Gender::Male, '2010-07-22', 'Hargeisa', '1-A', 'Faarax Muuse Ciise', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-003', 'Hodan Jaamac Nuur', Gender::Female, '2009-11-05', 'Berbera', '2-A', 'Jaamac Nuur Farah', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-004', 'Maxamed Cali Axmed', Gender::Male, '2009-02-18', 'Hargeisa', '2-B', 'Cali Axmed Rooble', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-005', 'Saynab Axmed Idiris', Gender::Female, '2008-09-30', 'Burao', '3-A', 'Axmed Idiris Muuse', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-006', 'Cumar Mahad Xirsi', Gender::Male, '2008-04-12', 'Hargeisa', '3-B', 'Mahad Xirsi Dheere', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-007', 'Fartun Abuukar Saalax', Gender::Female, '2007-12-08', 'Hargeisa', '4-A', 'Abuukar Saalax Jama', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-008', 'Ibraahin Maxamuud Geelle', Gender::Male, '2010-06-25', 'Hargeisa', '1-B', 'Maxamuud Geelle Axmed', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-009', 'Asad Caydarus Yaasiin', Gender::Male, '2009-08-14', 'Berbera', '2-A', 'Caydarus Yaasiin', GuardianRelationship::Father, StudentStatus::Transferred],
            ['STU-010', 'Khadra Muuse Dirie', Gender::Female, '2007-05-03', 'Hargeisa', '4-A', 'Muuse Dirie Guure', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-011', 'Liban Cali Farah', Gender::Male, '2008-01-19', 'Hargeisa', '3-A', 'Cali Farah Warsame', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-012', 'Nasteexo Warsame Rooble', Gender::Female, '2010-10-07', 'Berbera', '1-B', 'Warsame Rooble Jama', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-013', 'Caasha Maxamed Xirsi', Gender::Female, '2009-04-11', 'Hargeisa', '2-A', 'Maxamed Xirsi Guure', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-014', 'Faysal Axmed Jama', Gender::Male, '2009-06-22', 'Hargeisa', '2-A', 'Axmed Jama Warsame', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-015', 'Ilhan Cumar Warsame', Gender::Female, '2009-01-09', 'Hargeisa', '2-A', 'Cumar Warsame Ciise', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-016', 'Nimco Cabdi Farah', Gender::Female, '2009-03-21', 'Hargeisa', '2-A', 'Cabdi Farah Muuse', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-017', 'Qamar Xirsi Daahir', Gender::Female, '2009-05-14', 'Burao', '2-A', 'Xirsi Daahir Guure', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-018', 'Roble Maxamuud Gaas', Gender::Male, '2009-09-02', 'Hargeisa', '2-A', 'Maxamuud Gaas Axmed', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-019', 'Saciido Aadan Warsame', Gender::Female, '2009-12-18', 'Berbera', '2-A', 'Aadan Warsame Ciise', GuardianRelationship::Father, StudentStatus::Active],
            // Extra roster so remaining sections are not empty
            ['STU-020', 'Aamina Xasan Guure', Gender::Female, '2010-01-12', 'Hargeisa', '1-A', 'Xasan Guure Farah', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-021', 'Yuusuf Cabdi Rooble', Gender::Male, '2010-08-30', 'Hargeisa', '1-A', 'Cabdi Rooble Jama', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-022', 'Maryan Axmed Ciise', Gender::Female, '2010-04-05', 'Berbera', '1-B', 'Axmed Ciise Warsame', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-023', 'Abdullahi Nuur Farah', Gender::Male, '2009-07-19', 'Hargeisa', '2-B', 'Nuur Farah Axmed', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-024', 'Sahra Jama Dirie', Gender::Female, '2009-11-28', 'Hargeisa', '2-B', 'Jama Dirie Muuse', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-025', 'Hamza Warsame Cali', Gender::Male, '2008-06-08', 'Burao', '3-A', 'Warsame Cali Guure', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-026', 'Deeqa Maxamed Saalax', Gender::Female, '2008-02-25', 'Hargeisa', '3-B', 'Maxamed Saalax Jama', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-027', 'Ismail Guuleed Axmed', Gender::Male, '2008-10-11', 'Hargeisa', '3-B', 'Guuleed Axmed Farah', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-028', 'Hibo Cabdiraxmaan Nuur', Gender::Female, '2007-03-16', 'Hargeisa', '4-A', 'Cabdiraxmaan Nuur Ciise', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-029', 'Nasir Daahir Warsame', Gender::Male, '2007-08-22', 'Berbera', '4-B', 'Daahir Warsame Jama', GuardianRelationship::Father, StudentStatus::Active],
            ['STU-030', 'Hodan Faarax Muuse', Gender::Female, '2007-01-30', 'Hargeisa', '4-B', 'Faarax Muuse Guure', GuardianRelationship::Father, StudentStatus::Active],
        ];

        $classCache = [];
        foreach ($samples as [$code, $name, $gender, $dob, $city, $classKey, $guardian, $rel, $status]) {
            [$form, $section] = explode('-', $classKey);
            $cacheKey = "{$form}-{$section}";
            if (! isset($classCache[$cacheKey])) {
                $classCache[$cacheKey] = SchoolClass::query()
                    ->where('form_level', (int) $form)
                    ->where('section', $section)
                    ->where('academic_year', $year)
                    ->first();
            }
            $class = $classCache[$cacheKey];
            if (! $class) {
                continue;
            }

            $student = Student::query()->updateOrCreate(
                ['student_code' => $code],
                [
                    'full_name' => $name,
                    'dob' => $dob,
                    'gender' => $gender,
                    'city' => $city,
                    'status' => $status,
                ]
            );

            $primary = $student->guardians()->where('is_primary', true)->first();
            if ($primary) {
                $primary->update([
                    'full_name' => $guardian,
                    'relationship' => $rel,
                    'phone' => $primary->phone ?: '+252634'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT),
                ]);
            } else {
                Guardian::query()->create([
                    'student_id' => $student->id,
                    'full_name' => $guardian,
                    'phone' => '+252634'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT),
                    'relationship' => $rel,
                    'is_primary' => true,
                ]);
            }

            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year', $year)
                ->first();

            if ($enrollment) {
                $updates = [];
                if ($enrollment->status !== $status) {
                    $updates['status'] = $status;
                }
                if ((int) $enrollment->class_id !== (int) $class->id) {
                    $updates['class_id'] = $class->id;
                    $updates['roll_number'] = ((int) Enrollment::query()
                        ->where('class_id', $class->id)
                        ->where('academic_year', $year)
                        ->where('id', '!=', $enrollment->id)
                        ->max('roll_number')) + 1;
                }
                if ($updates !== []) {
                    $enrollment->update($updates);
                }
            } else {
                $roll = ((int) Enrollment::query()
                    ->where('class_id', $class->id)
                    ->where('academic_year', $year)
                    ->max('roll_number')) + 1;

                Enrollment::query()->create([
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'academic_year' => $year,
                    'roll_number' => $roll,
                    'enrollment_date' => now()->toDateString(),
                    'status' => $status,
                ]);
            }
        }
    }

    /**
     * Sample attendance for the last ~2 weeks of school days (Sat–Wed).
     */
    private function seedAttendance(): void
    {
        $markerId = User::query()->where('email', 'admin@dugsi.edu.sl')->value('id')
            ?? User::query()->where('email', 'teacher@dugsi.edu.sl')->value('id');

        if (! $markerId) {
            return;
        }

        $enrollments = Enrollment::query()
            ->where('status', StudentStatus::Active)
            ->where('academic_year', AcademicYear::current())
            ->get(['student_id', 'class_id']);

        if ($enrollments->isEmpty()) {
            return;
        }

        $schoolDays = [];
        $cursor = Carbon::parse('2026-07-15')->startOfDay(); // Wed
        while (count($schoolDays) < 10) {
            if (SchoolWeek::dayKey($cursor) !== null) {
                $schoolDays[] = $cursor->toDateString();
            }
            $cursor->subDay();
        }

        foreach ($schoolDays as $date) {
            foreach ($enrollments as $enrollment) {
                [$status, $reason] = $this->sampleAttendanceStatus(
                    (int) $enrollment->student_id,
                    $date
                );

                $record = AttendanceRecord::query()
                    ->where('student_id', $enrollment->student_id)
                    ->where('class_id', $enrollment->class_id)
                    ->whereDate('date', $date)
                    ->first();

                $attrs = [
                    'status' => $status,
                    'reason' => $reason,
                    'marked_by' => $markerId,
                ];

                if ($record) {
                    $record->update($attrs);
                } else {
                    AttendanceRecord::query()->create([
                        'student_id' => $enrollment->student_id,
                        'class_id' => $enrollment->class_id,
                        'date' => $date,
                        ...$attrs,
                    ]);
                }
            }
        }
    }

    /**
     * Deterministic mix so demos look realistic without random flicker on re-seed.
     *
     * @return array{0: AttendanceStatus, 1: ?string}
     */
    private function sampleAttendanceStatus(int $studentId, string $date): array
    {
        $bucket = ($studentId + (int) str_replace('-', '', $date)) % 20;

        return match (true) {
            $bucket === 0 => [AttendanceStatus::Absent, 'Sick'],
            $bucket === 1 => [AttendanceStatus::Absent, 'Family matter'],
            $bucket === 2 => [AttendanceStatus::Late, 'Traffic'],
            $bucket === 3 => [AttendanceStatus::Late, null],
            $bucket === 4 && $studentId % 7 === 0 => [AttendanceStatus::Suspended, 'Disciplinary'],
            default => [AttendanceStatus::Present, null],
        };
    }

    /**
     * Default A–F boundaries + Term 2 sample scores for demo classes.
     */
    private function seedGrades(): void
    {
        GradeBoundary::seedDefaults();

        $enteredBy = User::query()->where('email', 'admin@dugsi.edu.sl')->value('id')
            ?? User::query()->where('email', 'teacher@dugsi.edu.sl')->value('id');

        if (! $enteredBy) {
            return;
        }

        $year = AcademicYear::current();
        $term = AcademicTerm::Term2;
        $subjects = Subject::query()->orderBy('sort_order')->get();

        if ($subjects->isEmpty()) {
            return;
        }

        // Seed Form 1-A and Form 2-A so grade entry + report have data on first open.
        $classes = SchoolClass::query()
            ->where('academic_year', $year)
            ->where('status', ClassStatus::Active)
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2->where('form_level', 1)->where('section', 'A'))
                    ->orWhere(fn ($q2) => $q2->where('form_level', 2)->where('section', 'A'));
            })
            ->get();

        foreach ($classes as $class) {
            $enrollments = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('academic_year', $year)
                ->where('status', StudentStatus::Active)
                ->get(['student_id']);

            foreach ($enrollments as $enrollment) {
                foreach ($subjects as $subject) {
                    $score = 45 + (($enrollment->student_id * 7 + $subject->id * 11 + $class->id * 3) % 51);
                    $letter = GradeScale::letterFor((float) $score);
                    if ($letter === null) {
                        continue;
                    }

                    $existing = Grade::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('class_id', $class->id)
                        ->where('subject_id', $subject->id)
                        ->where('term', $term)
                        ->where('academic_year', $year)
                        ->first();

                    $attrs = [
                        'score_percent' => $score,
                        'letter_grade' => $letter,
                        'remarks' => $score >= 85 ? 'Excellent work' : ($score < 40 ? 'Needs support' : null),
                        'entered_by' => $enteredBy,
                    ];

                    if ($existing) {
                        $existing->update($attrs);
                    } else {
                        Grade::query()->create([
                            'student_id' => $enrollment->student_id,
                            'class_id' => $class->id,
                            'subject_id' => $subject->id,
                            'term' => $term,
                            'academic_year' => $year,
                            'first_entered_at' => now()->subDays(2),
                            ...$attrs,
                        ]);
                    }
                }
            }
        }
    }
}
