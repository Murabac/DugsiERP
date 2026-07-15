<?php

namespace Tests\Feature;

use App\Enums\AcademicTerm;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\LetterGrade;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeBoundary;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\GradeScale;
use App\Support\SchoolWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GradeBoundary::seedDefaults();
    }

    /**
     * @return array{admin: User, class: SchoolClass, student: Student, subject: Subject}
     */
    private function seedClassWithStudent(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $student = Student::query()->create([
            'student_code' => 'STU-G01',
            'full_name' => 'Faadumo Xasan Warsame',
            'dob' => '2010-03-15',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);
        $subject = Subject::query()->create(['name' => 'Mathematics', 'sort_order' => 1]);

        return compact('admin', 'class', 'student', 'subject');
    }

    /**
     * @return array{teacher: User, staff: Staff}
     */
    private function seedTeacherForClass(SchoolClass $class, Subject $subject): array
    {
        $year = AcademicYear::current();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-GR-'.uniqid(),
            'full_name' => 'Class Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'wed',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        return compact('teacher', 'staff');
    }

    public function test_letter_grade_uses_default_boundaries(): void
    {
        $this->assertSame(LetterGrade::A, GradeScale::letterFor(85));
        $this->assertSame(LetterGrade::A, GradeScale::letterFor(100));
        $this->assertSame(LetterGrade::B, GradeScale::letterFor(70));
        $this->assertSame(LetterGrade::C, GradeScale::letterFor(55));
        $this->assertSame(LetterGrade::D, GradeScale::letterFor(40));
        $this->assertSame(LetterGrade::F, GradeScale::letterFor(39));
        $this->assertSame(LetterGrade::F, GradeScale::letterFor(0));
    }

    public function test_admin_can_enter_and_save_grades(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        $term = AcademicTerm::Term2->value;

        $this->actingAs($admin)
            ->get(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term,
            ]))
            ->assertOk()
            ->assertSee('Grade Entry')
            ->assertSee('Faadumo Xasan Warsame');

        $this->actingAs($admin)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term,
                'scores' => [$student->id => 88],
                'remarks' => [$student->id => 'Excellent'],
            ])
            ->assertRedirect(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term,
            ]));

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'letter_grade' => LetterGrade::A->value,
            'remarks' => 'Excellent',
            'entered_by' => $admin->id,
        ]);
    }

    public function test_admin_can_update_boundaries_and_teacher_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $payload = [
            'boundaries' => [
                ['letter' => 'A', 'min_percent' => 80, 'max_percent' => 100, 'remark' => 'Excellent'],
                ['letter' => 'B', 'min_percent' => 65, 'max_percent' => 79, 'remark' => 'Good'],
                ['letter' => 'C', 'min_percent' => 50, 'max_percent' => 64, 'remark' => 'Satisfactory'],
                ['letter' => 'D', 'min_percent' => 40, 'max_percent' => 49, 'remark' => 'Pass'],
                ['letter' => 'F', 'min_percent' => 0, 'max_percent' => 39, 'remark' => 'Fail'],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('grades.boundaries.update'), $payload)
            ->assertRedirect(route('grades.boundaries'));

        $this->assertDatabaseHas('grade_boundaries', [
            'letter' => 'A',
            'min_percent' => 80,
        ]);

        $this->actingAs($teacher)
            ->post(route('grades.boundaries.update'), $payload)
            ->assertForbidden();
    }

    public function test_student_report_shows_rank(): void
    {
        ['admin' => $admin, 'class' => $class, 'subject' => $subject] = $this->seedClassWithStudent();
        $year = AcademicYear::current();
        $term = AcademicTerm::Term2;

        $second = Student::query()->create([
            'student_code' => 'STU-G02',
            'full_name' => 'Cabdiraxmaan Faarax Muuse',
            'dob' => '2010-07-22',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $second->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 2,
        ]);

        $first = Student::query()->where('student_code', 'STU-G01')->firstOrFail();

        foreach ([[$first->id, 95], [$second->id, 70]] as [$sid, $score]) {
            Grade::query()->create([
                'student_id' => $sid,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term,
                'academic_year' => $year,
                'score_percent' => $score,
                'letter_grade' => GradeScale::letterFor((float) $score),
                'entered_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('grades.report', [
                'class' => $class->id,
                'student' => $first->id,
                'term' => $term->value,
            ]))
            ->assertOk()
            ->assertSee('Grade Report Card')
            ->assertSee('1 of 2');

        $this->actingAs($admin)
            ->get(route('grades.print', [
                'class' => $class->id,
                'student' => $first->id,
                'term' => $term->value,
            ]))
            ->assertOk()
            ->assertSee(\App\Models\SchoolSetting::schoolName());
    }

    public function test_teacher_cannot_grade_untought_class(): void
    {
        ['class' => $class] = $this->seedClassWithStudent();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-GR',
            'full_name' => 'Other Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);

        $this->actingAs($teacher)
            ->get(route('grades.index', ['class' => $class->id]))
            ->assertForbidden();
    }

    public function test_teacher_can_grade_taught_class(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        $year = AcademicYear::current();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-GR2',
            'full_name' => 'Class Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'wed',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        $this->actingAs($teacher)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => AcademicTerm::Term1->value,
                'scores' => [$student->id => 72],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'letter_grade' => LetterGrade::B->value,
            'entered_by' => $teacher->id,
        ]);
    }

    public function test_teacher_can_enter_missing_grade_even_when_other_grades_would_be_locked(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        ['teacher' => $teacher] = $this->seedTeacherForClass($class, $subject);
        $term = AcademicTerm::Term1->value;

        // No existing grade — create is always allowed for taught subject.
        $this->actingAs($teacher)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term,
                'scores' => [$student->id => 65],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'score_percent' => 65,
            'entered_by' => $teacher->id,
        ]);
        $this->assertNotNull(Grade::query()->where('student_id', $student->id)->value('first_entered_at'));
    }

    public function test_teacher_edit_within_first_day_does_not_require_note(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        ['teacher' => $teacher] = $this->seedTeacherForClass($class, $subject);
        $term = AcademicTerm::Term1;
        $year = AcademicYear::current();

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'academic_year' => $year,
            'score_percent' => 70,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $teacher->id,
            'first_entered_at' => now()->subHours(6),
        ]);

        $this->actingAs($teacher)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 75],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'score_percent' => 75,
        ]);
        $this->assertDatabaseCount('grade_edit_logs', 1);
    }

    public function test_teacher_edit_after_day_one_requires_note(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        ['teacher' => $teacher] = $this->seedTeacherForClass($class, $subject);
        $term = AcademicTerm::Term1;
        $year = AcademicYear::current();

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'academic_year' => $year,
            'score_percent' => 70,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $teacher->id,
            'first_entered_at' => now()->subDays(2),
        ]);

        $this->actingAs($teacher)
            ->from(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term->value,
            ]))
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 80],
            ])
            ->assertInvalid(['edit_notes.'.$student->id]);

        $this->actingAs($teacher)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 80],
                'edit_notes' => [$student->id => 'Corrected marking error'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'score_percent' => 80]);
        $this->assertDatabaseHas('grade_edit_logs', [
            'edited_by' => $teacher->id,
            'note' => 'Corrected marking error',
            'new_score' => 80,
        ]);
    }

    public function test_teacher_cannot_edit_after_lock_window(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        ['teacher' => $teacher] = $this->seedTeacherForClass($class, $subject);
        $term = AcademicTerm::Term1;
        $year = AcademicYear::current();

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'academic_year' => $year,
            'score_percent' => 70,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $teacher->id,
            'first_entered_at' => now()->subDays(6),
        ]);

        $this->actingAs($teacher)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 90],
                'edit_notes' => [$student->id => 'Too late'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'score_percent' => 70,
        ]);
        $this->assertDatabaseCount('grade_edit_logs', 0);
    }

    public function test_admin_can_edit_locked_grade_and_history_is_visible(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        $term = AcademicTerm::Term1;
        $year = AcademicYear::current();

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'academic_year' => $year,
            'score_percent' => 70,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $admin->id,
            'first_entered_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->from(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term->value,
            ]))
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 88],
            ])
            ->assertInvalid(['edit_notes.'.$student->id]);

        $this->actingAs($admin)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 88],
                'edit_notes' => [$student->id => 'Admin correction'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'score_percent' => 88,
        ]);
        $this->assertDatabaseHas('grade_edit_logs', [
            'edited_by' => $admin->id,
            'note' => 'Admin correction',
        ]);

        $this->actingAs($admin)
            ->get(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term->value,
            ]))
            ->assertOk()
            ->assertSee('1 edit')
            ->assertSee($admin->name)
            ->assertSee('Admin correction');
    }

    public function test_super_admin_can_update_grade_edit_window(): void
    {
        $super = User::factory()->role(UserRole::SuperAdmin)->create();
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($super)
            ->post(route('settings.grade-edit-window'), [
                'grade_edit_window_days' => 7,
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'academic']));

        $this->assertSame(7, \App\Models\SchoolSetting::gradeEditWindowDays());

        $this->actingAs($admin)
            ->post(route('settings.grade-edit-window'), [
                'grade_edit_window_days' => 3,
            ])
            ->assertForbidden();

        $this->actingAs($super)
            ->post(route('settings.grade-edit-window'), [
                'grade_edit_window_days' => 20,
            ])
            ->assertSessionHasErrors('grade_edit_window_days');
    }

    public function test_subject_teacher_cannot_open_student_report(): void
    {
        ['class' => $class, 'subject' => $subject] = $this->seedClassWithStudent();
        $year = AcademicYear::current();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-GR3',
            'full_name' => 'Subject Teacher Only',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'wed',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        $this->actingAs($teacher)
            ->get(route('grades.report', ['class' => $class->id]))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('grades.print', [
                'class' => $class->id,
                'student' => Student::query()->where('student_code', 'STU-G01')->value('id'),
                'term' => AcademicTerm::Term2->value,
            ]))
            ->assertForbidden();
    }

    public function test_headmaster_can_open_student_report(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        $year = AcademicYear::current();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-HM',
            'full_name' => 'Class Headmaster',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $headmaster = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $class->update(['homeroom_teacher_id' => $staff->id]);

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term2,
            'academic_year' => $year,
            'score_percent' => 90,
            'letter_grade' => LetterGrade::A,
            'entered_by' => $headmaster->id,
        ]);

        $this->actingAs($headmaster)
            ->get(route('grades.report', [
                'class' => $class->id,
                'student' => $student->id,
                'term' => AcademicTerm::Term2->value,
            ]))
            ->assertOk()
            ->assertSee('Grade Report Card');
    }

    public function test_student_profile_grades_tab(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term2,
            'academic_year' => AcademicYear::current(),
            'score_percent' => 81,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('students.show', ['student' => $student, 'tab' => 'grades', 'term' => AcademicTerm::Term2->value]))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('81.0%');
    }

    public function test_partial_save_rolls_back_when_any_edit_note_missing(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $first, 'subject' => $subject] = $this->seedClassWithStudent();
        $year = AcademicYear::current();
        $term = AcademicTerm::Term1;

        $second = Student::query()->create([
            'student_code' => 'STU-G-PART',
            'full_name' => 'Second Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $second->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 2,
        ]);

        foreach ([[$first->id, 70], [$second->id, 71]] as [$sid, $score]) {
            Grade::query()->create([
                'student_id' => $sid,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term,
                'academic_year' => $year,
                'score_percent' => $score,
                'letter_grade' => LetterGrade::B,
                'entered_by' => $admin->id,
                'first_entered_at' => now()->subDays(2),
            ]);
        }

        $this->actingAs($admin)
            ->from(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term->value,
            ]))
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [
                    $first->id => 90,
                    $second->id => 91,
                ],
                'edit_notes' => [
                    $first->id => 'Fixed first only',
                ],
            ])
            ->assertInvalid(['edit_notes.'.$second->id]);

        $this->assertDatabaseHas('grades', ['student_id' => $first->id, 'score_percent' => 70]);
        $this->assertDatabaseHas('grades', ['student_id' => $second->id, 'score_percent' => 71]);
        $this->assertDatabaseCount('grade_edit_logs', 0);
    }

    public function test_teacher_cannot_grade_subject_not_on_their_timetable(): void
    {
        ['class' => $class, 'student' => $student, 'subject' => $math] = $this->seedClassWithStudent();
        ['teacher' => $teacher] = $this->seedTeacherForClass($class, $math);
        $english = Subject::query()->create(['name' => 'English', 'sort_order' => 2]);

        $this->actingAs($teacher)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $english->id,
                'term' => AcademicTerm::Term1->value,
                'scores' => [$student->id => 80],
            ])
            ->assertForbidden();
    }

    public function test_blank_score_does_not_clear_existing_grade(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        $term = AcademicTerm::Term1;

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'academic_year' => AcademicYear::current(),
            'score_percent' => 70,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $admin->id,
            'first_entered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('grades.index', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => $term->value,
            ]))
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => ''],
            ])
            ->assertInvalid(['scores.'.$student->id]);

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'score_percent' => 70,
        ]);
    }

    public function test_remark_only_change_requires_note_and_is_logged(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();
        $term = AcademicTerm::Term1;

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => $term,
            'academic_year' => AcademicYear::current(),
            'score_percent' => 70,
            'letter_grade' => LetterGrade::B,
            'remarks' => 'Old remark',
            'entered_by' => $admin->id,
            'first_entered_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 70],
                'remarks' => [$student->id => 'New remark'],
            ])
            ->assertInvalid(['edit_notes.'.$student->id]);

        $this->actingAs($admin)
            ->post(route('grades.store'), [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'term' => $term->value,
                'scores' => [$student->id => 70],
                'remarks' => [$student->id => 'New remark'],
                'edit_notes' => [$student->id => 'Clarified remark'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'remarks' => 'New remark',
        ]);
        $this->assertDatabaseHas('grade_edit_logs', [
            'edited_by' => $admin->id,
            'old_remarks' => 'Old remark',
            'new_remarks' => 'New remark',
            'note' => 'Clarified remark',
        ]);
    }

    public function test_admin_can_update_school_profile_and_teacher_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($admin)
            ->post(route('settings.school-profile'), [
                'school_name' => 'Hargeisa Secondary',
                'school_tagline' => 'Secondary School',
                'school_location' => 'Hargeisa',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'school']));

        $this->assertSame('Hargeisa Secondary', \App\Models\SchoolSetting::schoolName());

        $this->actingAs($teacher)
            ->post(route('settings.school-profile'), [
                'school_name' => 'Hacked School',
                'school_location' => 'Somewhere',
            ])
            ->assertForbidden();
    }

    public function test_grade_creating_sets_first_entered_at(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student, 'subject' => $subject] = $this->seedClassWithStudent();

        $grade = Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term1,
            'academic_year' => AcademicYear::current(),
            'score_percent' => 50,
            'letter_grade' => LetterGrade::C,
            'entered_by' => $admin->id,
        ]);

        $this->assertNotNull($grade->fresh()->first_entered_at);
    }
}
