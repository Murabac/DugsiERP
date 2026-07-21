<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_roles_are_seeded_with_expected_permissions(): void
    {
        $this->assertDatabaseHas('roles', ['key' => 'admin', 'is_system' => 1]);
        $this->assertDatabaseHas('permissions', ['key' => 'fees.collect']);

        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->assertTrue($admin->hasPermission('classes.manage'));
        $this->assertFalse($admin->hasPermission('roles.manage'));

        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $this->assertTrue($teacher->hasPermission('grades.enter'));
        $this->assertFalse($teacher->hasPermission('fees.view'));

        $finance = User::factory()->role(UserRole::Finance)->create();
        $this->assertTrue($finance->hasPermission('fees.collect'));
        $this->assertFalse($finance->hasPermission('attendance.mark'));
    }

    public function test_super_admin_can_create_custom_role_and_assign_it(): void
    {
        $super = User::factory()->role(UserRole::SuperAdmin)->create();

        $response = $this->actingAs($super)->post(route('settings.roles.store'), [
            'name' => 'Registrar',
            'description' => 'Enrollment desk',
            'permissions' => [
                'overview.view',
                'classes.view',
                'classes.manage',
                'students.view',
                'students.manage',
            ],
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'roles']));
        $this->assertDatabaseHas('roles', ['key' => 'registrar', 'is_system' => 0]);

        $role = Role::query()->where('key', 'registrar')->first();
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(
            ['overview.view', 'classes.view', 'classes.manage', 'students.view', 'students.manage'],
            $role->permissionKeys()
        );

        $user = User::factory()->create(['role' => 'registrar']);
        $this->assertTrue($user->hasPermission('students.manage'));
        $this->assertFalse($user->hasPermission('fees.view'));

        $this->actingAs($user)->get(route('classes.manage'))->assertOk();
        $this->actingAs($user)->get(route('finance.fees-dashboard'))->assertForbidden();
    }

    public function test_admin_cannot_manage_roles(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('settings.roles.store'), [
                'name' => 'Hacker',
                'permissions' => ['overview.view'],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('settings.index', ['tab' => 'roles']))
            ->assertOk();

        // Tab falls back — roles data not exposed for non–role managers.
        $this->actingAs($admin)
            ->get(route('settings.index', ['tab' => 'users']))
            ->assertOk()
            ->assertDontSee('New Role');
    }

    public function test_custom_role_nav_and_modules_follow_permissions(): void
    {
        $role = Role::query()->create([
            'key' => 'transport_desk',
            'name' => 'Transport Desk',
            'is_system' => false,
            'sort_order' => 40,
        ]);
        $role->syncPermissionKeys(['overview.view', 'transport.view', 'transport.manage']);

        $user = User::factory()->create(['role' => 'transport_desk']);

        $this->actingAs($user)->get(route('modules.home'))
            ->assertOk()
            ->assertSee('Transport')
            ->assertDontSee('>Fees<')
            ->assertDontSee('Settings');

        $this->actingAs($user)->get(route('transport.index'))->assertOk();
        $this->actingAs($user)->get(route('staff.index'))->assertForbidden();
    }

    public function test_permission_catalog_covers_all_seeded_keys(): void
    {
        $this->assertEqualsCanonicalizing(
            PermissionCatalog::allKeys(),
            \App\Models\Permission::query()->pluck('key')->all()
        );
    }
}
