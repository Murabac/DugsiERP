<?php

namespace Tests\Feature;

use App\Enums\AcademicTerm;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Enums\WaitlistStatus;
use App\Models\ClassWaitlist;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassStudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_class(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'form_level' => 1,
                'academic_year' => '2024-25',
                'sections' => [
                    [
                        'section' => 'A',
                        'capacity' => 30,
                        'room' => 'R-1A',
                    ],
                ],
            ])
            ->assertRedirect(route('classes.manage'));

        $this->assertDatabaseHas('classes', [
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => '2024-25',
            'status' => ClassStatus::Active->value,
            'room' => 'R-1A',
        ]);
    }

    public function test_admin_can_create_multiple_sections_at_once(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('classes.store'), [
                'form_level' => 2,
                'academic_year' => AcademicYear::current(),
                'sections' => [
                    ['section' => 'A', 'capacity' => 30, 'room' => 'R-2A'],
                    ['section' => 'B', 'capacity' => 28, 'room' => 'R-2B'],
                    ['section' => 'C', 'capacity' => 25, 'room' => 'R-2C'],
                ],
            ])
            ->assertRedirect(route('classes.manage'));

        $this->assertDatabaseHas('classes', ['form_level' => 2, 'section' => 'A', 'room' => 'R-2A']);
        $this->assertDatabaseHas('classes', ['form_level' => 2, 'section' => 'B', 'room' => 'R-2B']);
        $this->assertDatabaseHas('classes', ['form_level' => 2, 'section' => 'C', 'room' => 'R-2C']);
    }

    public function test_teacher_cannot_manage_classes(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get(route('classes.manage'))
            ->assertForbidden();
    }

    public function test_class_first_roster_and_admission(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Form 2 - A')
            ->assertSee(AcademicYear::current());

        $this->actingAs($admin)
            ->post(route('students.store'), [
                'full_name' => 'Faadumo Xasan Warsame',
                'dob' => AcademicYear::defaultDob(),
                'gender' => Gender::Female->value,
                'city' => 'Hargeisa',
                'class_id' => $class->id,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active->value,
                'guardian_name' => 'Xasan Warsame Jama',
                'guardian_phone' => '+252634001234',
                'relationship' => GuardianRelationship::Father->value,
            ])
            ->assertRedirect();

        $student = Student::query()->where('full_name', 'Faadumo Xasan Warsame')->first();
        $this->assertNotNull($student);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'roll_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('classes.roster', $class))
            ->assertOk()
            ->assertSee('Faadumo Xasan Warsame')
            ->assertSee('Search name or ID within this class');

        $this->actingAs($admin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Personal Details')
            ->assertSee('Xasan Warsame Jama');
    }

    public function test_admission_rejects_class_from_other_academic_year(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $oldClass = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => '2023-24',
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('students.store'), [
                'full_name' => 'Axmed Cali Yuusuf',
                'dob' => AcademicYear::defaultDob(),
                'gender' => Gender::Male->value,
                'city' => 'Hargeisa',
                'class_id' => $oldClass->id,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active->value,
                'guardian_name' => 'Cali Yuusuf',
                'guardian_phone' => '+252634009999',
                'relationship' => GuardianRelationship::Father->value,
            ])
            ->assertSessionHasErrors('class_id');

        $this->assertDatabaseMissing('students', ['full_name' => 'Axmed Cali Yuusuf']);
    }

    public function test_full_class_admission_goes_to_waitlist(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 3,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 1,
            'status' => ClassStatus::Active,
        ]);

        $existing = Student::query()->create([
            'student_code' => 'STU-001',
            'full_name' => 'Existing Student',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $existing->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'roll_number' => 1,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('students.store'), [
                'full_name' => 'Waitlisted Student',
                'dob' => AcademicYear::defaultDob(),
                'gender' => Gender::Female->value,
                'city' => 'Hargeisa',
                'class_id' => $class->id,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active->value,
                'guardian_name' => 'Guardian Name',
                'guardian_phone' => '+252634008888',
                'relationship' => GuardianRelationship::Mother->value,
            ])
            ->assertRedirect();

        $student = Student::query()->where('full_name', 'Waitlisted Student')->first();
        $this->assertNotNull($student);
        $this->assertSame(StudentStatus::Waitlisted, $student->status);
        $this->assertDatabaseHas('class_waitlist', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'status' => WaitlistStatus::Waiting->value,
            'position' => 1,
        ]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id]);
    }

    public function test_admin_can_enroll_from_waitlist_after_capacity_increase(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'B',
            'academic_year' => AcademicYear::current(),
            'capacity' => 1,
            'status' => ClassStatus::Active,
        ]);

        $filled = Student::query()->create([
            'student_code' => 'STU-002',
            'full_name' => 'Seat Holder',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $filled->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'roll_number' => 1,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
        ]);

        $waitingStudent = Student::query()->create([
            'student_code' => 'STU-003',
            'full_name' => 'Queued Student',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Female,
            'status' => StudentStatus::Waitlisted,
        ]);

        $entry = ClassWaitlist::query()->create([
            'student_id' => $waitingStudent->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'position' => 1,
            'status' => WaitlistStatus::Waiting,
        ]);

        $this->actingAs($admin)
            ->post(route('classes.waitlist.enroll', [$class, $entry]))
            ->assertSessionHasErrors('waitlist');

        $this->actingAs($admin)
            ->put(route('classes.update', $class), [
                'form_level' => 2,
                'section' => 'B',
                'academic_year' => AcademicYear::current(),
                'capacity' => 2,
                'room' => $class->room ?? 'R-2B',
            ])
            ->assertRedirect(route('classes.roster', $class));

        $this->actingAs($admin)
            ->post(route('classes.waitlist.enroll', [$class, $entry]))
            ->assertRedirect(route('classes.roster', $class));

        $this->assertSame(StudentStatus::Active, $waitingStudent->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $waitingStudent->id,
            'class_id' => $class->id,
            'roll_number' => 2,
        ]);
        $this->assertSame(WaitlistStatus::Enrolled, $entry->fresh()->status);
    }

    public function test_cannot_change_class_academic_year_when_students_enrolled(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'C',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-010',
            'full_name' => 'Enrolled Student',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'roll_number' => 1,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
        ]);

        $this->actingAs($admin)
            ->put(route('classes.update', $class), [
                'form_level' => 1,
                'section' => 'C',
                'academic_year' => '2023-24',
                'capacity' => 30,
                'room' => $class->room ?? 'R-1C',
            ])
            ->assertSessionHasErrors('academic_year');

        $this->assertSame(AcademicYear::current(), $class->fresh()->academic_year);
    }

    public function test_classes_index_only_shows_current_academic_year(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'Z',
            'academic_year' => '2023-24',
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Form 1 - A')
            ->assertDontSee('Form 1 - Z');
    }

    public function test_finance_cannot_browse_classes(): void
    {
        $finance = User::factory()->role(UserRole::Finance)->create();

        $this->actingAs($finance)
            ->get(route('classes.index'))
            ->assertForbidden();
    }

    public function test_teacher_can_only_view_taught_class_roster_and_students(): void
    {
        $year = AcademicYear::current();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-T40',
            'full_name' => 'Scoped Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
        ]);

        $taught = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $other = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);

        $subject = Subject::query()->create(['name' => 'English', 'sort_order' => 1]);
        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $taught->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        $mine = Student::query()->create([
            'student_code' => 'STU-T1',
            'full_name' => 'My Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        $theirs = Student::query()->create([
            'student_code' => 'STU-T2',
            'full_name' => 'Other Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $mine->id,
            'class_id' => $taught->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);
        Enrollment::query()->create([
            'student_id' => $theirs->id,
            'class_id' => $other->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $this->actingAs($teacher)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertSee('Form 1 - A')
            ->assertDontSee('Form 1 - B');

        $this->actingAs($teacher)
            ->get(route('classes.roster', $taught))
            ->assertOk()
            ->assertSee('My Student');

        $this->actingAs($teacher)
            ->get(route('classes.roster', $other))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('students.show', $mine))
            ->assertOk()
            ->assertSee('My Student');

        $this->actingAs($teacher)
            ->get(route('students.show', $theirs))
            ->assertForbidden();
    }

    public function test_admin_can_find_students_by_parent_across_classes(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $phone = '+252 63 400 9999';

        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-2B',
            'status' => ClassStatus::Active,
        ]);

        $childA = Student::query()->create([
            'student_code' => 'STU-P1',
            'full_name' => 'Sibling One',
            'dob' => '2010-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        $childB = Student::query()->create([
            'student_code' => 'STU-P2',
            'full_name' => 'Sibling Two',
            'dob' => '2009-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        Guardian::query()->create([
            'student_id' => $childA->id,
            'full_name' => 'Shared Parent Warsame',
            'phone' => $phone,
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);
        Guardian::query()->create([
            'student_id' => $childB->id,
            'full_name' => 'Shared Parent Warsame',
            'phone' => '+252634009999', // same digits, different formatting
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        Enrollment::query()->create([
            'student_id' => $childA->id,
            'class_id' => $classA->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);
        Enrollment::query()->create([
            'student_id' => $childB->id,
            'class_id' => $classB->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('students.by-parent', ['q' => 'Shared Parent']))
            ->assertOk()
            ->assertSee('Sibling One')
            ->assertSee('Sibling Two')
            ->assertSee('Form 1 - A')
            ->assertSee('Form 2 - B');

        $this->actingAs($admin)
            ->get(route('students.by-parent', ['q' => '4009999']))
            ->assertOk()
            ->assertSee('Sibling One')
            ->assertSee('Sibling Two');

        // LIKE metacharacters are treated as literals, not wildcards.
        $this->actingAs($admin)
            ->get(route('students.by-parent', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Sibling One');

        $this->actingAs($admin)
            ->get(route('students.by-parent', ['q' => 'a']))
            ->assertOk()
            ->assertSee('Enter at least 2 characters');
    }

    public function test_teacher_parent_search_only_shows_taught_students(): void
    {
        $year = AcademicYear::current();
        $phone = '+252634008888';

        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-2B',
            'status' => ClassStatus::Active,
        ]);

        $taught = Student::query()->create([
            'student_code' => 'STU-T1',
            'full_name' => 'Taught Child',
            'dob' => '2010-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        $other = Student::query()->create([
            'student_code' => 'STU-T2',
            'full_name' => 'Other Child',
            'dob' => '2009-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        Guardian::query()->create([
            'student_id' => $taught->id,
            'full_name' => 'Scoped Parent',
            'phone' => $phone,
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);
        Guardian::query()->create([
            'student_id' => $other->id,
            'full_name' => 'Scoped Parent',
            'phone' => $phone,
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        Enrollment::query()->create([
            'student_id' => $taught->id,
            'class_id' => $classA->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);
        Enrollment::query()->create([
            'student_id' => $other->id,
            'class_id' => $classB->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $staff = Staff::query()->create([
            'employee_code' => 'EMP-PS',
            'full_name' => 'Parent Search Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $subject = Subject::query()->create(['name' => 'English', 'sort_order' => 1]);
        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $classA->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        $this->actingAs($teacher)
            ->get(route('students.by-parent', ['q' => 'Scoped Parent']))
            ->assertOk()
            ->assertSee('Taught Child')
            ->assertDontSee('Other Child');
    }

    public function test_admin_can_edit_student_and_guardian(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $otherClass = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-EDIT',
            'full_name' => 'Original Name',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Female,
            'city' => 'Hargeisa',
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'roll_number' => 1,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
        ]);

        $guardian = Guardian::query()->create([
            'student_id' => $student->id,
            'full_name' => 'Original Guardian',
            'phone' => '+252634000001',
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('Edit Student');

        $this->actingAs($admin)
            ->put(route('students.update', $student), [
                'full_name' => 'Updated Student Name',
                'dob' => AcademicYear::defaultDob(),
                'gender' => Gender::Female->value,
                'city' => 'Berbera',
                'class_id' => $otherClass->id,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active->value,
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'full_name' => 'Updated Student Name',
            'city' => 'Berbera',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'class_id' => $otherClass->id,
        ]);

        $this->actingAs($admin)
            ->put(route('students.guardians.update', [$student, $guardian]), [
                'full_name' => 'Updated Guardian',
                'phone' => '+252634000099',
                'relationship' => GuardianRelationship::Mother->value,
                'is_primary' => '1',
            ])
            ->assertRedirect(route('students.show', ['student' => $student, 'tab' => 'guardians']));

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'full_name' => 'Updated Guardian',
            'phone' => '+252634000099',
            'relationship' => GuardianRelationship::Mother->value,
        ]);
    }

    public function test_student_class_transfer_moves_open_invoices_and_grades(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $from = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $to = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-XFER',
            'full_name' => 'Transfer Student',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $from->id,
            'academic_year' => $year,
            'roll_number' => 1,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
        ]);
        Guardian::query()->create([
            'student_id' => $student->id,
            'full_name' => 'Transfer Parent',
            'phone' => '+252634000777',
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        \App\Models\SchoolSetting::set('monthly_fee_usd', '45');
        $invoice = \App\Models\Invoice::query()->create([
            'invoice_number' => 'INV-XFER-1',
            'student_id' => $student->id,
            'class_id' => $from->id,
            'academic_year' => $year,
            'billing_month' => now()->startOfMonth()->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 0,
            'status' => \App\Enums\InvoiceStatus::Unpaid,
        ]);
        $subject = Subject::query()->create(['name' => 'Mathematics', 'sort_order' => 1]);
        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $from->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term1,
            'academic_year' => $year,
            'score_percent' => 80,
            'letter_grade' => \App\Enums\LetterGrade::B,
            'entered_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('students.update', $student), [
                'full_name' => $student->full_name,
                'dob' => $student->dob->format('Y-m-d'),
                'gender' => $student->gender->value,
                'class_id' => $to->id,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active->value,
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'class_id' => $to->id,
        ]);
        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'class_id' => $to->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_cannot_destroy_last_guardian(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $student = Student::query()->create([
            'student_code' => 'STU-G1',
            'full_name' => 'Only One Guardian',
            'dob' => AcademicYear::defaultDob(),
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        $guardian = Guardian::query()->create([
            'student_id' => $student->id,
            'full_name' => 'Solo Guardian',
            'phone' => '+252634000888',
            'relationship' => GuardianRelationship::Mother,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('students.show', ['student' => $student, 'tab' => 'guardians']))
            ->delete(route('students.guardians.destroy', [$student, $guardian]))
            ->assertRedirect(route('students.show', ['student' => $student, 'tab' => 'guardians']))
            ->assertSessionHasErrors('guardian');

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id]);
    }

    public function test_admin_can_update_class_room(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 3,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-3A',
            'status' => ClassStatus::Active,
        ]);

        $this->actingAs($admin)
            ->put(route('classes.update', $class), [
                'form_level' => 3,
                'section' => 'A',
                'academic_year' => AcademicYear::current(),
                'capacity' => 32,
                'room' => 'R-3A-NEW',
            ])
            ->assertRedirect(route('classes.manage'));

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'capacity' => 32,
            'room' => 'R-3A-NEW',
        ]);
    }
}
