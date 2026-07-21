<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('group', 64);
            $table->string('label', 120);
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        $now = now();

        foreach (PermissionCatalog::grouped() as $group => $perms) {
            foreach ($perms as $key => $label) {
                DB::table('permissions')->insert([
                    'key' => $key,
                    'group' => $group,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        $systemRoles = [
            ['key' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Full access including roles', 'sort_order' => 0],
            ['key' => 'admin', 'name' => 'Admin', 'description' => 'School administration', 'sort_order' => 1],
            ['key' => 'finance', 'name' => 'Finance', 'description' => 'Fees, expenses, payroll', 'sort_order' => 2],
            ['key' => 'teacher', 'name' => 'Teacher', 'description' => 'Classes, attendance, grades', 'sort_order' => 3],
        ];

        foreach ($systemRoles as $role) {
            $roleId = DB::table('roles')->insertGetId([
                'key' => $role['key'],
                'name' => $role['name'],
                'description' => $role['description'],
                'is_system' => true,
                'sort_order' => $role['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (PermissionCatalog::defaultsFor($role['key']) as $permKey) {
                $permissionId = $permissionIds[$permKey] ?? null;
                if ($permissionId === null) {
                    continue;
                }
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
