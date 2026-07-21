<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_apps(): void
    {
        $this->get(route('modules.home'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_full_module_grid(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertSee('Apps')
            ->assertSee('Finance')
            ->assertDontSee('>Fees</', false)
            ->assertDontSee('>Expenses</', false)
            ->assertDontSee('>Payroll</', false)
            ->assertSee('Settings')
            ->assertSee('Attendance');
    }

    public function test_teacher_sees_only_teaching_modules(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Grades')
            ->assertDontSee('Finance')
            ->assertDontSee('>Settings</', false)
            ->assertDontSee('Fees');
    }

    public function test_finance_sees_finance_modules_not_grades(): void
    {
        $finance = User::factory()->role(UserRole::Finance)->create();

        $this->actingAs($finance)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertSee('Finance')
            ->assertDontSee('Grades')
            ->assertDontSee('Settings');
    }

    public function test_apps_home_has_no_sidebar(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertDontSee('id="app-sidebar"', false)
            ->assertDontSee('id="module-search"', false);
    }

    public function test_module_page_shows_sidebar(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="app-sidebar"', false);
    }

    public function test_sidebar_scopes_to_opened_app_only(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('finance.fees-dashboard', ['app' => 'finance']))
            ->assertOk();

        $labels = collect(\App\Support\Navigation::for($admin))
            ->map(fn (array $item) => $item['label'])
            ->all();

        $this->assertSame(['Back to Apps', 'Finance'], $labels);

        $finance = collect(\App\Support\Navigation::for($admin))->firstWhere('label', 'Finance');
        $childLabels = collect($finance['children'] ?? [])->pluck('label')->all();
        $this->assertSame(['Fees Dashboard', 'Fee Collection', 'Expenses', 'Payroll'], $childLabels);

        $this->actingAs($admin)
            ->get(route('classes.index', ['app' => 'classes']))
            ->assertOk();

        $labels = collect(\App\Support\Navigation::for($admin))
            ->map(fn (array $item) => $item['label'])
            ->all();

        $this->assertSame(['Back to Apps', 'Classes'], $labels);
    }

    public function test_login_redirects_to_apps_home(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create([
            'email' => 'apps@dugsi.edu.sl',
        ]);

        $this->post(route('login'), [
            'login' => 'apps@dugsi.edu.sl',
            'password' => 'password',
        ])->assertRedirect(route('modules.home'));
    }

    public function test_linked_staff_sees_checkin_tab_then_checkout_after_punch(): void
    {
        $staff = \App\Models\Staff::query()->create([
            'employee_code' => \App\Models\Staff::nextEmployeeCode(),
            'full_name' => 'Checkin Teacher',
            'role_label' => \App\Enums\StaffRoleLabel::Teacher,
            'status' => \App\Enums\StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
        ]);

        $this->actingAs($teacher)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertSee('Check in')
            ->assertDontSee('Check out');

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Check in');

        $this->actingAs($teacher)
            ->get(route('staff-checkin.mine'))
            ->assertRedirect($staff->fresh()->checkinUrl());

        \App\Support\StaffAttendancePunch::punch($staff, now()->setTime(7, 30));

        $this->actingAs($teacher)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertSee('Check out')
            ->assertDontSee('>Check in</', false);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Check out');

        \App\Support\StaffAttendancePunch::punch($staff, now()->setTime(16, 0));

        $this->actingAs($teacher)
            ->get(route('modules.home'))
            ->assertOk()
            ->assertDontSee('Check in')
            ->assertDontSee('Check out');
    }
}
