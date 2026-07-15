<?php

namespace App\Http\Controllers;

use App\Enums\StaffRoleLabel;
use App\Enums\UserRole;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'users');
        if (! in_array($tab, ['users', 'school', 'academic'], true)) {
            $tab = 'users';
        }

        $actor = $request->user();

        $users = User::query()
            ->with('staff')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (User $u) => match ($u->role) {
                UserRole::SuperAdmin => 0,
                UserRole::Admin => 1,
                UserRole::Finance => 2,
                UserRole::Teacher => 3,
            })
            ->values();

        $unlinkedStaff = Staff::query()
            ->whereDoesntHave('user')
            ->whereIn('role_label', ['teacher', 'admin', 'finance'])
            ->orderBy('full_name')
            ->get();

        return view('settings.index', [
            'tab' => $tab,
            'users' => $users,
            'assignableRoles' => $actor->assignableRoles(),
            'unlinkedStaff' => $unlinkedStaff,
            'isSuperAdmin' => $actor->isSuperAdmin(),
            'academicYear' => \App\Support\AcademicYear::current(),
            'gradeEditWindowDays' => \App\Models\SchoolSetting::gradeEditWindowDays(),
            'schoolProfile' => [
                'name' => \App\Models\SchoolSetting::schoolName(),
                'location' => \App\Models\SchoolSetting::schoolLocation(),
                'tagline' => \App\Models\SchoolSetting::schoolTagline(),
            ],
        ]);
    }

    public function updateSchoolProfile(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:120'],
            'school_location' => ['required', 'string', 'max:120'],
            'school_tagline' => ['nullable', 'string', 'max:120'],
        ]);

        \App\Models\SchoolSetting::set('school_name', trim($data['school_name']));
        \App\Models\SchoolSetting::set('school_location', trim($data['school_location']));
        \App\Models\SchoolSetting::set(
            'school_tagline',
            trim((string) ($data['school_tagline'] ?? '')) ?: 'Secondary School'
        );

        return redirect()
            ->route('settings.index', ['tab' => 'school'])
            ->with('status', 'School profile updated. Printable documents will use the new name.');
    }

    public function updateGradeEditWindow(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'grade_edit_window_days' => ['required', 'integer', 'min:1', 'max:14'],
        ]);

        \App\Models\SchoolSetting::set('grade_edit_window_days', (string) $data['grade_edit_window_days']);

        return redirect()
            ->route('settings.index', ['tab' => 'academic'])
            ->with('status', 'Teacher grade edit window updated to '.$data['grade_edit_window_days'].' day(s).');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $assignable = array_map(fn (UserRole $r) => $r->value, $actor->assignableRoles());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'role' => ['required', Rule::in($assignable)],
            'staff_id' => [
                'nullable',
                'integer',
                'exists:staff,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (! $value) {
                        return;
                    }

                    if (User::query()->where('staff_id', $value)->exists()) {
                        $fail('That staff member already has a login account.');

                        return;
                    }

                    $staff = Staff::query()->find($value);
                    if (! $staff) {
                        return;
                    }

                    $expectedRole = $staff->role_label instanceof StaffRoleLabel
                        ? $staff->role_label->toUserRole()
                        : StaffRoleLabel::tryFrom((string) $staff->role_label)?->toUserRole();

                    if ($expectedRole === null) {
                        $fail('This staff role cannot have a system login.');

                        return;
                    }

                    $selectedRole = UserRole::tryFrom((string) $request->input('role'));
                    if ($selectedRole === null || $selectedRole !== $expectedRole) {
                        $fail('User role must match staff role ('.$staff->role_label->label().' → '.$expectedRole->label().').');
                    }
                },
            ],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make('password'),
            'role' => $data['role'],
            'is_active' => true,
            'staff_id' => $data['staff_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'users'])
            ->with('status', "User {$user->name} created. Temporary password: password");
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);

        $user->update(['is_active' => ! $user->is_active]);

        $state = $user->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('settings.index', ['tab' => 'users'])
            ->with('status', "{$user->name} {$state}.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);
        abort_if($user->isSuperAdmin(), 403);

        $name = $user->name;
        $user->update([
            'is_active' => false,
            'staff_id' => null,
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'users'])
            ->with('status', "{$name} removed (account deactivated).");
    }
}
