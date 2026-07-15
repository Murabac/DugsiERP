<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_displayed(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Dugsi ERP')
            ->assertSee('Email / Phone');
    }

    public function test_users_can_authenticate_with_email(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create([
            'email' => 'admin@dugsi.edu.sl',
        ]);

        $this->post(route('login'), [
            'login' => 'admin@dugsi.edu.sl',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_with_phone(): void
    {
        $user = User::factory()->role(UserRole::Teacher)->create([
            'phone' => '+252634000004',
        ]);

        $this->post(route('login'), [
            'login' => '+252634000004',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->role(UserRole::Admin)->inactive()->create([
            'email' => 'inactive@dugsi.edu.sl',
        ]);

        $this->post(route('login'), [
            'login' => 'inactive@dugsi.edu.sl',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_teacher_cannot_access_settings(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    public function test_finance_sees_finance_dashboard_shell(): void
    {
        $finance = User::factory()->role(UserRole::Finance)->create();

        $this->actingAs($finance)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Finance Dashboard');
    }

    public function test_teacher_sees_teacher_dashboard_shell(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create([
            'name' => 'Abdirahman Farah Jama',
        ]);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Abdirahman')
            ->assertSee('Periods Today');
    }
}
