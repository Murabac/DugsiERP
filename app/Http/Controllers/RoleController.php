<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('roles.manage'), 403);

        $data = $this->validated($request, creating: true);

        $role = Role::query()->create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'sort_order' => 50,
        ]);

        $role->syncPermissionKeys($data['permissions']);

        return redirect()
            ->route('settings.index', ['tab' => 'roles'])
            ->with('status', "Role {$role->name} created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('roles.manage'), 403);
        abort_if($role->isSuperAdmin(), 403);

        $data = $this->validated($request, creating: false, role: $role);

        if (! $role->is_system) {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
        }

        $permissions = $data['permissions'];
        if ($role->key === 'admin') {
            $permissions = array_values(array_filter(
                $permissions,
                fn (string $key) => $key !== 'roles.manage'
            ));
        }

        $role->syncPermissionKeys($permissions);

        User::query()->where('role', $role->key)->get()->each->forgetPermissionCache();

        return redirect()
            ->route('settings.index', ['tab' => 'roles'])
            ->with('status', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('roles.manage'), 403);
        abort_if($role->is_system, 403);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Reassign users before deleting this role.',
            ]);
        }

        $name = $role->name;
        $role->delete();

        return redirect()
            ->route('settings.index', ['tab' => 'roles'])
            ->with('status', "Role {$name} deleted.");
    }

    /**
     * @return array{key?: string, name: string, description?: ?string, permissions: list<string>}
     */
    private function validated(Request $request, bool $creating, ?Role $role = null): array
    {
        $allowed = PermissionCatalog::allKeys();
        // Only Super Admin role may hold roles.manage — never assign via UI ticks to others.
        $allowed = array_values(array_filter($allowed, fn (string $key) => $key !== 'roles.manage'));

        $rules = [
            'name' => [$creating || ! ($role?->is_system) ? 'required' : 'nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in($allowed)],
        ];

        if ($creating) {
            $rules['key'] = [
                'nullable',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'key'),
            ];
        }

        $data = $request->validate($rules);

        if ($creating) {
            $key = trim((string) ($data['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($data['name'], '_');
            }
            $key = Str::limit($key, 32, '');
            if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw ValidationException::withMessages([
                    'name' => 'Could not build a valid role key from that name. Use letters and numbers.',
                ]);
            }
            if (Role::query()->where('key', $key)->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'A role with a similar name already exists.',
                ]);
            }
            $data['key'] = $key;
        }

        $data['permissions'] = array_values(array_unique($data['permissions']));

        return $data;
    }
}
