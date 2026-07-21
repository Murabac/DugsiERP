<?php

namespace Tests\Feature;

use App\Enums\PayrollRunStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\UserRole;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_teacher_staff_with_login(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Hodan Jama Axmed',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subjects' => ['English'],
                'work_days' => ['sat', 'sun', 'mon', 'tue', 'wed'],
                'phone' => '+252634111111',
                'date_joined' => '2020-01-15',
                'fixed_salary_usd' => 590,
                'qualification' => "Bachelor's Degree",
                'status' => StaffStatus::Active->value,
                'create_login' => '1',
                'login_email' => 'hodan@dugsi.edu.sl',
            ])
            ->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Hodan Jama Axmed')->first();
        $this->assertNotNull($staff);
        $this->assertSame('English', $staff->subject_specialty);
        $this->assertSame(['English'], $staff->subjectNames());
        $this->assertSame(['sat', 'sun', 'mon', 'tue', 'wed'], $staff->workDayList());
        $this->assertSame(['first', 'second'], $staff->shiftsOn('sat'));

        $this->assertDatabaseHas('users', [
            'email' => 'hodan@dugsi.edu.sl',
            'role' => UserRole::Teacher->value,
            'staff_id' => $staff->id,
        ]);
    }

    public function test_admin_cannot_create_admin_login_via_staff(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'New Admin Staff',
                'role_label' => StaffRoleLabel::Admin->value,
                'status' => StaffStatus::Active->value,
                'create_login' => '1',
                'login_email' => 'newadmin@dugsi.edu.sl',
            ])
            ->assertSessionHasErrors('role_label');
    }

    public function test_super_admin_can_create_admin_login_via_staff(): void
    {
        $super = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($super)
            ->post(route('staff.store'), [
                'full_name' => 'New Admin',
                'role_label' => StaffRoleLabel::Admin->value,
                'status' => StaffStatus::Active->value,
                'create_login' => '1',
                'login_email' => 'newadmin@dugsi.edu.sl',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@dugsi.edu.sl',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_admin_cannot_create_admin_user(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Blocked Admin',
                'role_label' => StaffRoleLabel::Admin->value,
                'status' => StaffStatus::Active->value,
                'create_login' => '1',
                'login_email' => 'blocked@dugsi.edu.sl',
            ])
            ->assertSessionHasErrors('role_label');
    }

    public function test_admin_can_create_teacher_user_and_link_staff(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Linkable Teacher',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subject_specialty' => 'History',
                'status' => StaffStatus::Active->value,
                'create_login' => '1',
                'login_email' => 'linkable@dugsi.edu.sl',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'linkable@dugsi.edu.sl',
            'role' => UserRole::Teacher->value,
        ]);
        $this->assertNotNull(User::query()->where('email', 'linkable@dugsi.edu.sl')->value('staff_id'));
    }

    public function test_staff_create_persists_profile_fields_and_login_for_custom_role(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        \App\Models\Role::query()->create([
            'key' => 'form_master',
            'name' => 'Form Master',
            'is_system' => false,
            'sort_order' => 40,
        ]);

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Adnan Test',
                'role_label' => 'form_master',
                'subject_specialty' => 'Mathematics',
                'phone' => '+252634111222',
                'gender' => \App\Enums\Gender::Male->value,
                'qualification' => "Bachelor's Degree",
                'date_joined' => '2024-09-01',
                'dob' => '1990-05-15',
                'fixed_salary_usd' => 650,
                'status' => StaffStatus::Active->value,
                'create_login' => '1',
                'login_email' => 'adnan.test@dugsi.edu.sl',
            ])
            ->assertRedirect()
            ->assertSessionHas('login_credentials');

        $staff = \App\Models\Staff::query()->where('full_name', 'Adnan Test')->first();
        $this->assertNotNull($staff);
        $this->assertSame('form_master', $staff->roleKey());
        $this->assertSame('Mathematics', $staff->subject_specialty);
        $this->assertSame('+252634111222', $staff->phone);
        $this->assertSame(['+252634111222'], $staff->phoneList());
        $this->assertSame("Bachelor's Degree", $staff->qualification);
        $this->assertSame('2024-09-01', $staff->date_joined?->toDateString());
        $this->assertSame('1990-05-15', $staff->dob?->toDateString());
        $this->assertEquals(650.0, (float) $staff->fixed_salary_usd);
        $this->assertNotNull($staff->user);
        $this->assertSame('adnan.test@dugsi.edu.sl', $staff->user->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $staff->user->password));
    }

    public function test_teacher_cannot_access_staff(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get(route('staff.index'))
            ->assertForbidden();
    }

    public function test_staff_profile_overview(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-200',
            'full_name' => 'Profile Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Chemistry',
            'fixed_salary_usd' => 610,
            'status' => StaffStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Profile Teacher')
            ->assertSee('Chemistry')
            ->assertSee('$610.00')
            ->assertSee('Edit')
            ->assertDontSee('Check in');

        $user = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $staff->refresh();

        $this->actingAs($admin)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Phone check-in')
            ->assertSee($user->email)
            ->assertDontSee('>Check in</', false);
    }

    public function test_admin_can_create_staff_with_multiple_phones(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Multi Phone Staff',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subjects' => ['Mathematics'],
                'work_days' => ['sat', 'sun', 'mon', 'tue', 'wed'],
                'phones' => ['+252634111001', '+252634111002', ''],
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Multi Phone Staff')->first();
        $this->assertNotNull($staff);
        $this->assertSame('+252634111001', $staff->phone);
        $this->assertSame(['+252634111001', '+252634111002'], $staff->phones);
        $this->assertSame(['+252634111001', '+252634111002'], $staff->phoneList());
    }

    public function test_admin_can_create_teacher_with_multiple_subjects_and_work_days(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Multi Subject Teacher',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subjects' => ['Mathematics', 'Physics', 'Chemistry'],
                'work_schedule' => [
                    'sat' => ['first', 'second'],
                    'mon' => ['first'],
                    'wed' => ['second'],
                ],
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Multi Subject Teacher')->first();
        $this->assertNotNull($staff);
        $this->assertSame('Mathematics', $staff->subject_specialty);
        $this->assertSame(['Mathematics', 'Physics', 'Chemistry'], $staff->subjectNames());
        $this->assertSame(['sat', 'mon', 'wed'], $staff->workDayList());
        $this->assertSame(['first', 'second'], $staff->shiftsOn('sat'));
        $this->assertSame(['first'], $staff->shiftsOn('mon'));
        $this->assertSame(['second'], $staff->shiftsOn('wed'));
        $this->assertTrue($staff->worksOn('sat'));
        $this->assertFalse($staff->worksOn('sun'));
        $this->assertTrue($staff->worksShift('mon', 'first'));
        $this->assertFalse($staff->worksShift('mon', 'second'));
        $this->assertSame(3, \App\Models\TeacherSubjectAssignment::query()->where('staff_id', $staff->id)->count());
    }

    public function test_admin_can_assign_classes_when_creating_teacher(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = \App\Support\AcademicYear::current();
        $classA = \App\Models\SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => \App\Enums\ClassStatus::Active,
        ]);
        $classB = \App\Models\SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => \App\Enums\ClassStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('staff.store'), [
                'full_name' => 'Class Assigned Teacher',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subjects' => ['English'],
                'work_schedule' => \App\Models\Staff::defaultWorkSchedule(),
                'class_ids' => [$classA->id, $classB->id],
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Class Assigned Teacher')->first();
        $this->assertNotNull($staff);
        $this->assertEqualsCanonicalizing([$classA->id, $classB->id], $staff->assignedClasses()->pluck('classes.id')->all());

        $this->actingAs($admin)
            ->put(route('staff.classes.update', $staff), [
                'class_ids' => [$classA->id],
            ])
            ->assertRedirect(route('staff.show', ['staff' => $staff, 'tab' => 'overview']));

        $this->assertEqualsCanonicalizing([$classA->id], $staff->fresh()->assignedClasses()->pluck('classes.id')->all());
    }

    public function test_teacher_create_requires_at_least_one_subject(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->from(route('staff.index'))
            ->post(route('staff.store'), [
                'full_name' => 'No Subjects Teacher',
                'role_label' => StaffRoleLabel::Teacher->value,
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect(route('staff.index'))
            ->assertSessionHasErrors(['subjects']);
    }

    public function test_staff_payroll_tab_shows_member_history(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-PAYH',
            'full_name' => 'History Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'History',
            'fixed_salary_usd' => 450,
            'status' => StaffStatus::Active,
        ]);
        $other = Staff::query()->create([
            'employee_code' => 'EMP-OTHER',
            'full_name' => 'Other Staff',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Math',
            'fixed_salary_usd' => 400,
            'status' => StaffStatus::Active,
        ]);

        $run = PayrollRun::query()->create([
            'billing_month' => now()->startOfMonth()->toDateString(),
            'status' => PayrollRunStatus::Confirmed,
            'staff_count' => 2,
            'total_amount' => 850,
            'generated_by' => $admin->id,
            'generated_at' => now(),
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $staff->id,
            'employee_code' => $staff->employee_code,
            'full_name' => $staff->full_name,
            'role_label' => $staff->roleKey(),
            'salary_usd' => 450,
            'payslip_number' => 'PS-'.$staff->employee_code.'-001',
        ]);
        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $other->id,
            'employee_code' => $other->employee_code,
            'full_name' => $other->full_name,
            'role_label' => $other->roleKey(),
            'salary_usd' => 400,
            'payslip_number' => 'PS-'.$other->employee_code.'-001',
        ]);

        $this->actingAs($admin)
            ->get(route('staff.show', ['staff' => $staff, 'tab' => 'payroll']))
            ->assertOk()
            ->assertSee('Payroll History')
            ->assertSee('PS-'.$staff->employee_code.'-001')
            ->assertSee('$450.00')
            ->assertDontSee('PS-'.$other->employee_code.'-001');
    }

    public function test_super_admin_can_update_staff(): void
    {
        $super = User::factory()->role(UserRole::SuperAdmin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-201',
            'full_name' => 'Old Name',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'History',
            'status' => StaffStatus::Active,
        ]);

        $this->actingAs($super)
            ->put(route('staff.update', $staff), [
                'full_name' => 'Updated Name',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subject_specialty' => 'Geography',
                'phone' => '+252634222222',
                'fixed_salary_usd' => 575,
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect(route('staff.show', $staff));

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'full_name' => 'Updated Name',
            'subject_specialty' => 'Geography',
        ]);
    }

    public function test_staff_role_change_syncs_linked_user_role(): void
    {
        $super = User::factory()->role(UserRole::SuperAdmin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-202',
            'full_name' => 'Role Sync Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'English',
            'status' => StaffStatus::Active,
        ]);
        $user = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
            'name' => 'Role Sync Teacher',
        ]);

        $this->actingAs($super)
            ->put(route('staff.update', $staff), [
                'full_name' => 'Role Sync Teacher',
                'role_label' => StaffRoleLabel::Finance->value,
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame(UserRole::Finance->value, $user->fresh()->role);
    }

    public function test_resigned_staff_deactivates_linked_login(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-203',
            'full_name' => 'Leaving Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Physics',
            'status' => StaffStatus::Active,
        ]);
        $user = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('staff.update', $staff), [
                'full_name' => 'Leaving Teacher',
                'role_label' => StaffRoleLabel::Teacher->value,
                'subject_specialty' => 'Physics',
                'status' => StaffStatus::Resigned->value,
            ])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_cannot_change_linked_staff_to_admin_role(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-204',
            'full_name' => 'Teacher Staff',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'History',
            'status' => StaffStatus::Active,
        ]);
        User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('staff.update', $staff), [
                'full_name' => 'Teacher Staff',
                'role_label' => StaffRoleLabel::Admin->value,
                'subject_specialty' => 'History',
                'status' => StaffStatus::Active->value,
            ])
            ->assertSessionHasErrors('role_label');
    }
}
