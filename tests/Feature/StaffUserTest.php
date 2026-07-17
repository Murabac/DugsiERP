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
                'subject_specialty' => 'English',
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

    public function test_super_admin_can_create_admin_user(): void
    {
        $super = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($super)
            ->post(route('settings.users.store'), [
                'name' => 'New Admin',
                'email' => 'newadmin@dugsi.edu.sl',
                'role' => UserRole::Admin->value,
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'users']));

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@dugsi.edu.sl',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_admin_cannot_create_admin_user(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('settings.users.store'), [
                'name' => 'Blocked Admin',
                'email' => 'blocked@dugsi.edu.sl',
                'role' => UserRole::Admin->value,
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_admin_can_create_teacher_user_and_link_staff(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-100',
            'full_name' => 'Linkable Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'History',
            'status' => StaffStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('settings.users.store'), [
                'name' => 'Linkable Teacher',
                'email' => 'linkable@dugsi.edu.sl',
                'role' => UserRole::Teacher->value,
                'staff_id' => $staff->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'linkable@dugsi.edu.sl',
            'staff_id' => $staff->id,
        ]);
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
            ->assertSee('Edit');
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
            'role_label' => $staff->role_label->value,
            'salary_usd' => 450,
            'payslip_number' => 'PS-'.$staff->employee_code.'-001',
        ]);
        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $other->id,
            'employee_code' => $other->employee_code,
            'full_name' => $other->full_name,
            'role_label' => $other->role_label->value,
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

        $this->assertSame(UserRole::Finance, $user->fresh()->role);
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

    public function test_librarian_role_unlinks_and_deactivates_login(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-204',
            'full_name' => 'Now Librarian',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'History',
            'status' => StaffStatus::Active,
        ]);
        $user = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('staff.update', $staff), [
                'full_name' => 'Now Librarian',
                'role_label' => StaffRoleLabel::Librarian->value,
                'status' => StaffStatus::Active->value,
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNull($user->staff_id);
    }

    public function test_cannot_link_staff_with_mismatched_role(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-205',
            'full_name' => 'Teacher Staff',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Biology',
            'status' => StaffStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('settings.users.store'), [
                'name' => 'Wrong Role User',
                'email' => 'mismatch@dugsi.edu.sl',
                'role' => UserRole::Finance->value,
                'staff_id' => $staff->id,
            ])
            ->assertSessionHasErrors('staff_id');
    }
}
