<?php

namespace Database\Seeders;

use App\Enums\AcademicTerm;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeBoundary;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\DocumentNumbers;
use App\Support\FeeCalculator;
use App\Support\GradeScale;
use App\Support\Money;
use App\Support\MonthlyInvoiceGenerator;
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
        $this->seedSubjectsCatalog();
        $this->seedStaffAndUsers();
        $this->seedSubjectsAndAssignments();
        $this->seedClassesAndStudents();
        $this->seedAttendance();
        $this->seedGrades();
        $this->seedFees();
        $this->seedTransport();
        $this->seedPayroll();
        $this->seedNotificationTemplates();
    }

    private function seedStaffAndUsers(): void
    {
        $password = Hash::make('password');
        $fullWeek = Staff::defaultWorkSchedule();

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
                    'phones' => ['+252634000001'],
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
                    'phones' => ['+252634000005'],
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
                    'phones' => ['+252634000006'],
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
                    'subjects' => ['Mathematics', 'Physics'],
                    'work_days' => $fullWeek,
                    'qualification' => "Bachelor's Degree",
                    'fixed_salary_usd' => 620,
                    'date_joined' => '2019-09-01',
                    'phone' => '+252634000101',
                    'phones' => ['+252634000101', '+252634000201'],
                    'gender' => Gender::Male,
                ],
                'user' => [
                    'email' => 'teacher@dugsi.edu.sl',
                    'role' => UserRole::Teacher,
                ],
            ],
        ];

        // code, name, subjects[], schedule, salary, joined, gender, phones[]
        $extraTeachers = [
            [
                'EMP-002', 'Hodan Jama Axmed', ['English', 'Somali Language'], $fullWeek, 590, '2020-01-15', Gender::Female,
                ['+252634000102', '+252634000202'],
            ],
            [
                'EMP-003', 'Mohamed Ali Warsame', ['Physics', 'Mathematics'], $this->seedScheduleMorningHeavy(), 650, '2018-08-20', Gender::Male,
                ['+252634000103'],
            ],
            [
                'EMP-004', 'Fatuma Hassan Dirie', ['Biology', 'Chemistry'], $this->seedScheduleAfternoonHeavy(), 570, '2021-02-10', Gender::Female,
                ['+252634000104', '+252634000204'],
            ],
            [
                'EMP-007', 'Xaawo Ibrahim Muuse', ['Somali Language'], $fullWeek, 580, '2019-09-01', Gender::Female,
                ['+252634000107'],
            ],
            [
                'EMP-008', 'Axmed Muuse Warsame', ['Arabic Language', 'Islamic Studies'], $this->seedScheduleAlternateDays(), 600, '2018-01-10', Gender::Male,
                ['+252634000108'],
            ],
            [
                'EMP-009', 'Yusuf Cabdi Axmed', ['Islamic Studies', 'Arabic Language'], $fullWeek, 575, '2020-09-01', Gender::Male,
                ['+252634000109', '+252634000209'],
            ],
            [
                'EMP-010', 'Nuur Axmed Gaas', ['Geography', 'History'], $this->seedScheduleMorningHeavy(), 560, '2021-09-01', Gender::Male,
                ['+252634000110'],
            ],
            [
                'EMP-011', 'Raxmo Warsame Dirie', ['History', 'Geography'], $this->seedScheduleAfternoonHeavy(), 555, '2022-01-15', Gender::Female,
                ['+252634000111'],
            ],
            [
                'EMP-012', 'Khalid Daahir Ciise', ['Chemistry', 'Biology', 'Physics'], $fullWeek, 610, '2019-09-01', Gender::Male,
                ['+252634000112', '+252634000212'],
            ],
        ];

        foreach ($people as $row) {
            $subjects = $row['staff']['subjects'] ?? [];
            unset($row['staff']['subjects']);

            $staffData = array_merge([
                'status' => StaffStatus::Active,
                'subject_specialty' => null,
                'qualification' => null,
                'gender' => null,
                'phones' => null,
                'work_days' => null,
            ], $row['staff']);

            if ($subjects !== []) {
                $phones = Staff::normalizePhones($staffData['phones'] ?? (filled($staffData['phone'] ?? null) ? [$staffData['phone']] : []));
                $schedule = Staff::normalizeWorkSchedule($staffData['work_days'] ?? Staff::defaultWorkSchedule());
                $staffData['phones'] = $phones !== [] ? $phones : null;
                $staffData['phone'] = $phones[0] ?? ($staffData['phone'] ?? null);
                $staffData['work_days'] = $schedule !== [] ? $schedule : null;
                $staffData['subject_specialty'] = $subjects[0];
            }

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

            if ($subjects !== []) {
                $this->syncTeacherSubjects($staff, $subjects);
            }

            User::query()->updateOrCreate(
                ['email' => $row['user']['email']],
                [
                    'name' => $staff->full_name,
                    'phone' => $staff->phone,
                    'password' => $password,
                    'role' => $row['user']['role']->value,
                    'is_active' => true,
                    'staff_id' => $staff->id,
                    'email_verified_at' => now(),
                ]
            );
        }

        foreach ($extraTeachers as [$code, $name, $subjects, $schedule, $salary, $joined, $gender, $phones]) {
            $phones = Staff::normalizePhones($phones);
            $schedule = Staff::normalizeWorkSchedule($schedule);

            $staff = Staff::query()->updateOrCreate(
                ['employee_code' => $code],
                [
                    'full_name' => $name,
                    'role_label' => StaffRoleLabel::Teacher,
                    'subject_specialty' => $subjects[0] ?? null,
                    'work_days' => $schedule !== [] ? $schedule : null,
                    'fixed_salary_usd' => $salary,
                    'date_joined' => $joined,
                    'gender' => $gender,
                    'phone' => $phones[0] ?? null,
                    'phones' => $phones !== [] ? $phones : null,
                    'qualification' => "Bachelor's Degree",
                    'status' => StaffStatus::Active,
                ]
            );

            $this->syncTeacherSubjects($staff, $subjects);
        }
    }

    private function seedSubjectsCatalog(): void
    {
        foreach (Subjects::all() as $index => $name) {
            Subject::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1]
            );
        }
    }

    private function seedSubjectsAndAssignments(): void
    {
        $this->seedSubjectsCatalog();

        // Ensure every teacher with a specialty has at least that assignment (gap fill).
        $teachers = Staff::query()
            ->where('role_label', StaffRoleLabel::Teacher)
            ->whereNotNull('subject_specialty')
            ->get();

        foreach ($teachers as $teacher) {
            $names = $teacher->subjectNames();
            if ($names === [] && filled($teacher->subject_specialty)) {
                $names = [(string) $teacher->subject_specialty];
            }
            if ($names !== []) {
                $this->syncTeacherSubjects($teacher, $names);
            }
        }
    }

    /**
     * @param  list<string>  $subjectNames
     */
    private function syncTeacherSubjects(Staff $staff, array $subjectNames): void
    {
        $subjectIds = [];
        foreach ($subjectNames as $index => $name) {
            $catalogIndex = array_search($name, Subjects::all(), true);
            $subject = Subject::query()->firstOrCreate(
                ['name' => $name],
                ['sort_order' => $catalogIndex === false ? ($index + 1) : ($catalogIndex + 1)]
            );
            $subjectIds[] = $subject->id;

            TeacherSubjectAssignment::query()->updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'subject_id' => $subject->id,
                ],
                ['class_id' => null]
            );
        }

        $query = TeacherSubjectAssignment::query()
            ->where('staff_id', $staff->id)
            ->whereNull('class_id');

        if ($subjectIds !== []) {
            $query->whereNotIn('subject_id', $subjectIds);
        }

        $query->delete();
    }

    /** Morning-focused: first shift most days, second on Sat/Wed only. */
    private function seedScheduleMorningHeavy(): array
    {
        return [
            'sat' => ['first', 'second'],
            'sun' => ['first'],
            'mon' => ['first'],
            'tue' => ['first'],
            'wed' => ['first', 'second'],
        ];
    }

    /** Afternoon-focused: second shift most days, first on Sat/Mon. */
    private function seedScheduleAfternoonHeavy(): array
    {
        return [
            'sat' => ['first', 'second'],
            'sun' => ['second'],
            'mon' => ['first', 'second'],
            'tue' => ['second'],
            'wed' => ['second'],
        ];
    }

    /** Alternate days / shifts across the school week. */
    private function seedScheduleAlternateDays(): array
    {
        return [
            'sat' => ['first'],
            'sun' => ['second'],
            'mon' => ['first'],
            'tue' => ['second'],
            'wed' => ['first', 'second'],
        ];
    }

    private function seedTeacherClassAssignments(string $year): void
    {
        $classes = SchoolClass::query()
            ->where('academic_year', $year)
            ->where('status', ClassStatus::Active)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get()
            ->keyBy(fn (SchoolClass $c) => $c->form_level.'-'.$c->section);

        $map = [
            'EMP-001' => ['1-A', '1-B', '2-A'],
            'EMP-101' => ['1-A', '1-B', '2-A'],
            'EMP-002' => ['1-A', '2-A', '3-A'],
            'EMP-003' => ['2-A', '2-B', '3-B'],
            'EMP-004' => ['3-A', '4-A'],
            'EMP-007' => ['1-A', '1-B'],
            'EMP-008' => ['2-B', '3-A'],
            'EMP-009' => ['1-B', '2-A', '4-A'],
            'EMP-010' => ['3-B', '4-B'],
            'EMP-011' => ['2-B', '4-A'],
            'EMP-012' => ['3-A', '3-B', '4-A', '4-B'],
        ];

        foreach ($map as $code => $keys) {
            $staff = Staff::query()->where('employee_code', $code)->first();
            if (! $staff) {
                continue;
            }
            $ids = [];
            foreach ($keys as $key) {
                $class = $classes->get($key);
                if ($class) {
                    $ids[] = $class->id;
                }
            }
            $staff->assignedClasses()->sync($ids);
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

        $this->seedTeacherClassAssignments($year);

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
     * Sample attendance for the last 3 months of school days (Sat–Wed).
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

        $schoolDays = $this->recentSchoolDays(3);
        if ($schoolDays === []) {
            return;
        }

        foreach ($schoolDays as $date) {
            foreach ($enrollments as $enrollment) {
                [$status, $reason] = $this->sampleAttendanceStatus(
                    (int) $enrollment->student_id,
                    $date
                );

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'student_id' => $enrollment->student_id,
                        'class_id' => $enrollment->class_id,
                        'date' => $date,
                    ],
                    [
                        'status' => $status,
                        'reason' => $reason,
                        'marked_by' => $markerId,
                    ]
                );
            }
        }
    }

    /**
     * School days (Sat–Wed) from today back through the last N calendar months.
     *
     * @return list<string> Y-m-d
     */
    private function recentSchoolDays(int $months): array
    {
        $end = now()->startOfDay();
        $start = now()->startOfMonth()->subMonths(max(0, $months - 1))->startOfDay();
        $ayStart = Carbon::create(AcademicYear::startYear(), 9, 1)->startOfDay();
        if ($start->lt($ayStart)) {
            $start = $ayStart->copy();
        }

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if (SchoolWeek::dayKey($cursor) !== null) {
                $days[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $days;
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
     * Default A–F boundaries + Term 1 & Term 2 sample scores for all demo classes.
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
        $subjects = Subject::query()->orderBy('sort_order')->get();

        if ($subjects->isEmpty()) {
            return;
        }

        $classes = SchoolClass::query()
            ->where('academic_year', $year)
            ->where('status', ClassStatus::Active)
            ->get();

        $terms = [
            AcademicTerm::Term1->value => now()->subMonths(4)->subDays(3),
            AcademicTerm::Term2->value => now()->subWeeks(2),
        ];

        foreach ($classes as $class) {
            $enrollments = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('academic_year', $year)
                ->where('status', StudentStatus::Active)
                ->get(['student_id']);

            if ($enrollments->isEmpty()) {
                continue;
            }

            foreach ($terms as $termValue => $enteredAt) {
                $term = AcademicTerm::from($termValue);
                $termOffset = $term === AcademicTerm::Term1 ? 0 : 5;

                foreach ($enrollments as $enrollment) {
                    foreach ($subjects as $subject) {
                        $percent = 40 + (($enrollment->student_id * 7 + $subject->id * 11 + $class->id * 3 + $termOffset) % 56);
                        $letter = GradeScale::letterFor((float) $percent);
                        if ($letter === null) {
                            continue;
                        }

                        $marks = \App\Support\TermMarks::marksFromPercent((float) $percent, $term);

                        Grade::query()->updateOrCreate(
                            [
                                'student_id' => $enrollment->student_id,
                                'class_id' => $class->id,
                                'subject_id' => $subject->id,
                                'term' => $term,
                                'academic_year' => $year,
                            ],
                            [
                                'score_marks' => $marks,
                                'score_percent' => $percent,
                                'letter_grade' => $letter,
                                'remarks' => $percent >= 85 ? 'Excellent work' : ($percent < 40 ? 'Needs support' : null),
                                'entered_by' => $enteredBy,
                                'first_entered_at' => $enteredAt,
                            ]
                        );
                    }
                }
            }
        }
    }

    private function seedFees(): void
    {
        $year = AcademicYear::current();

        \App\Models\SchoolSetting::set('monthly_fee_usd', '45');
        \App\Models\SchoolSetting::set('sibling_discount_percent', '10');
        \App\Models\SchoolSetting::set('need_based_discount_percent', '20');

        // Flag a few students for need-based demo.
        Student::query()->whereIn('student_code', ['STU-001', 'STU-005'])->update(['need_based_discount_amount' => 9]);

        $financeId = User::query()->where('email', 'finance@dugsi.edu.sl')->value('id')
            ?? User::query()->where('email', 'admin@dugsi.edu.sl')->value('id');

        if (! $financeId) {
            return;
        }

        // Last 3 billable months (within the academic year).
        $months = [];
        for ($i = 2; $i >= 0; $i--) {
            $months[] = now()->startOfMonth()->subMonths($i);
        }
        $ayStart = Carbon::create(AcademicYear::startYear(), 9, 1)->startOfMonth();
        $months = array_values(array_filter(
            $months,
            fn ($m) => $m->gte($ayStart) && $m->lte(now()->startOfMonth())
        ));
        if ($months === []) {
            $months = [now()->startOfMonth()];
        }

        $enrollments = Enrollment::query()
            ->with(['student.primaryGuardian', 'schoolClass'])
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->get();

        foreach ($months as $billingMonth) {
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;
                $class = $enrollment->schoolClass;
                if (! $student || ! $class) {
                    continue;
                }

                try {
                    $quote = FeeCalculator::quote($student, $class, $year);
                } catch (\Throwable) {
                    continue;
                }

                $invoice = Invoice::query()
                    ->where('student_id', $student->id)
                    ->whereDate('billing_month', $billingMonth->toDateString())
                    ->first();

                if (! $invoice) {
                    $invoice = Invoice::query()->create([
                        'invoice_number' => DocumentNumbers::nextInvoiceNumber($billingMonth),
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'academic_year' => $year,
                        'billing_month' => $billingMonth->toDateString(),
                        'base_amount' => $quote['base'],
                        'discount_applied' => $quote['discount'],
                        'discount_reason' => $quote['reason'],
                        'amount_due' => $quote['due'],
                        'amount_paid' => 0,
                        'status' => InvoiceStatus::Unpaid,
                    ]);
                }

                if ($invoice->payments()->exists()) {
                    continue;
                }

                // Deterministic payment mix across the last 3 months:
                // ~50% paid in full, ~25% partial, ~25% unpaid.
                $pattern = ($student->id + $billingMonth->month) % 4;

                if ($pattern === 3) {
                    continue; // unpaid
                }

                $payAmount = $pattern === 2
                    ? Money::round(((float) $invoice->amount_due) * 0.5)
                    : (float) $invoice->amount_due;

                if ($payAmount <= 0) {
                    continue;
                }

                Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'amount' => $payAmount,
                    'method' => $student->id % 2 === 0 ? PaymentMethod::Cash : PaymentMethod::MobileMoney,
                    'receipt_number' => DocumentNumbers::nextReceiptNumber($billingMonth->copy()->addDays(5 + ($student->id % 10))),
                    'paid_at' => $billingMonth->copy()->addDays(5 + ($student->id % 10))->setTime(10, 0),
                    'recorded_by' => $financeId,
                    'notes' => $pattern === 2 ? 'Partial payment — balance due' : null,
                ]);
                $invoice->refreshStatusFromPayments();
            }
        }
    }

    private function seedPayroll(): void
    {
        $actorId = User::query()->where('email', 'finance@dugsi.edu.sl')->value('id')
            ?? User::query()->where('email', 'admin@dugsi.edu.sl')->value('id');

        if (! $actorId) {
            return;
        }

        $actor = User::query()->find($actorId);
        if (! $actor) {
            return;
        }

        $month = now()->startOfMonth()->subMonth();

        try {
            \App\Support\PayrollGenerator::confirm($month, $actor, 'Seeded payroll run');
        } catch (\Throwable) {
            // Run may already exist on re-seed of fees-only paths.
        }
    }

    private function seedTransport(): void
    {
        $year = AcademicYear::current();

        $driver = Staff::query()->create([
            'employee_code' => Staff::nextEmployeeCode(),
            'full_name' => 'Cali Bus Driver',
            'role_label' => StaffRoleLabel::Driver,
            'status' => StaffStatus::Active,
            'phone' => '+252634009900',
            'date_joined' => now()->toDateString(),
        ]);

        $bus = \App\Models\Vehicle::query()->create([
            'plate_number' => 'SL-BUS-01',
            'label' => 'Hargeisa North',
            'capacity' => 40,
            'make_model' => null,
            'status' => \App\Enums\VehicleStatus::Active,
            'driver_staff_id' => $driver->id,
        ]);

        $route = \App\Models\TransportRoute::query()->create([
            'name' => 'Hargeisa North',
            'code' => null,
            'vehicle_id' => $bus->id,
            'academic_year' => $year,
            'status' => \App\Enums\TransportRouteStatus::Active,
            'notes' => null,
        ]);

        $riders = Student::query()
            ->where('status', StudentStatus::Active)
            ->whereHas('enrollments', fn ($e) => $e->where('academic_year', $year)->where('status', StudentStatus::Active))
            ->orderBy('id')
            ->limit(5)
            ->get();

        foreach ($riders as $student) {
            \App\Models\TransportAssignment::query()->create([
                'student_id' => $student->id,
                'route_id' => $route->id,
                'stop_id' => null,
                'academic_year' => $year,
                'status' => \App\Enums\TransportAssignmentStatus::Active,
                'started_on' => now()->startOfMonth()->toDateString(),
            ]);
        }

        FeeCalculator::clearSiblingCache();
        MonthlyInvoiceGenerator::recalculateUnpaid(null, $year);
    }

    private function seedNotificationTemplates(): void
    {
        $defaults = [
            [
                'type' => \App\Enums\NotificationType::AbsenceAlert,
                'name' => 'Absence Alert',
                'body' => 'Dear parent, {student_name} ({class}) was absent on {date}. Please contact the school.',
                'variables' => ['student_name', 'class', 'date'],
            ],
            [
                'type' => \App\Enums\NotificationType::FeeReminder,
                'name' => 'Fee Due Reminder',
                'body' => 'Dear parent, fee reminder for {student_name}: {amount} due by {due_date}.',
                'variables' => ['student_name', 'amount', 'due_date'],
            ],
            [
                'type' => \App\Enums\NotificationType::FeeOverdue,
                'name' => 'Fee Overdue Notice',
                'body' => 'Dear parent, fee for {student_name} of {amount} is overdue by {days} days. Please pay soon.',
                'variables' => ['student_name', 'amount', 'days'],
            ],
        ];

        foreach ($defaults as $row) {
            \App\Models\NotificationTemplate::query()->updateOrCreate(
                ['type' => $row['type']->value],
                [
                    'name' => $row['name'],
                    'channel' => 'sms',
                    'body' => $row['body'],
                    'variables' => $row['variables'],
                    'is_active' => true,
                ],
            );
        }
    }
}
