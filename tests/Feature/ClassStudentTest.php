<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Enums\WaitlistStatus;
use App\Models\ClassWaitlist;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\AcademicYear;
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
                'section' => 'A',
                'academic_year' => '2024-25',
                'capacity' => 30,
            ])
            ->assertRedirect(route('classes.manage'));

        $this->assertDatabaseHas('classes', [
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => '2024-25',
            'status' => ClassStatus::Active->value,
        ]);
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
}
