<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\UserRole;
use App\Models\Staff;
use App\Models\User;
use App\Support\Subjects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        $staff = Staff::query()
            ->with('user')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($q) => $q->where('role_label', $role))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('full_name')
            ->get();

        return view('staff.index', [
            'staff' => $staff,
            'search' => $search,
            'roleFilter' => $role,
            'statusFilter' => $status,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_label' => ['required', Rule::enum(StaffRoleLabel::class)],
            'subject_specialty' => ['nullable', 'string', Rule::in(Subjects::all())],
            'qualification' => ['nullable', 'string', 'max:255'],
            'date_joined' => ['nullable', 'date'],
            'fixed_salary_usd' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'status' => ['required', Rule::enum(StaffStatus::class)],
            'create_login' => ['sometimes', 'boolean'],
            'login_email' => ['nullable', 'required_if:create_login,1', 'email', 'max:255', 'unique:users,email'],
            'login_phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
        ]);

        $roleLabel = StaffRoleLabel::from($data['role_label']);
        $createLogin = (bool) ($data['create_login'] ?? false);

        if ($createLogin) {
            $userRole = $roleLabel->toUserRole();
            if ($userRole === null) {
                return back()->withInput()->withErrors([
                    'create_login' => 'Librarian staff cannot have a system login. Create Teacher/Finance/Admin staff for login access.',
                ]);
            }

            if ($userRole === UserRole::Admin && ! $request->user()->isSuperAdmin()) {
                return back()->withInput()->withErrors([
                    'create_login' => 'Only Super Admins can create Admin login accounts.',
                ]);
            }
        }

        if ($roleLabel === StaffRoleLabel::Teacher && empty($data['subject_specialty'])) {
            return back()->withInput()->withErrors([
                'subject_specialty' => 'Subject specialty is required for teachers.',
            ]);
        }

        $staff = DB::transaction(function () use ($data, $roleLabel, $createLogin, $request) {
            $staff = Staff::query()->create([
                'employee_code' => Staff::nextEmployeeCode(),
                'full_name' => $data['full_name'],
                'dob' => $data['dob'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'subject_specialty' => $roleLabel === StaffRoleLabel::Teacher
                    ? ($data['subject_specialty'] ?? null)
                    : null,
                'date_joined' => $data['date_joined'] ?? null,
                'fixed_salary_usd' => $data['fixed_salary_usd'] ?? null,
                'role_label' => $roleLabel,
                'status' => $data['status'],
            ]);

            if ($createLogin) {
                $userRole = $roleLabel->toUserRole();
                User::query()->create([
                    'name' => $staff->full_name,
                    'email' => $data['login_email'],
                    'phone' => $data['login_phone'] ?? $staff->phone,
                    'password' => Hash::make('password'),
                    'role' => $userRole,
                    'is_active' => true,
                    'staff_id' => $staff->id,
                    'email_verified_at' => now(),
                ]);
            }

            return $staff;
        });

        $message = 'Staff member saved.';
        if ($createLogin) {
            $message .= ' Login created (temporary password: password).';
        }

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', $message);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_label' => ['required', Rule::enum(StaffRoleLabel::class)],
            'subject_specialty' => ['nullable', 'string', Rule::in(Subjects::all())],
            'qualification' => ['nullable', 'string', 'max:255'],
            'date_joined' => ['nullable', 'date'],
            'fixed_salary_usd' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'status' => ['required', Rule::enum(StaffStatus::class)],
        ]);

        $roleLabel = StaffRoleLabel::from($data['role_label']);
        $status = StaffStatus::from($data['status']);
        $subject = $data['subject_specialty'] ?? null;
        if ($subject === '') {
            $subject = null;
        }

        if ($roleLabel === StaffRoleLabel::Teacher && blank($subject)) {
            return back()->withInput()->withErrors([
                'subject_specialty' => 'Subject specialty is required for teachers.',
            ]);
        }

        $linkedUser = $staff->user;
        $mappedRole = $roleLabel->toUserRole();

        if ($linkedUser && ! $linkedUser->isSuperAdmin()) {
            if ($mappedRole === null) {
                // Librarian: login will be unlinked + deactivated in the transaction.
            } elseif ($mappedRole !== $linkedUser->role) {
                $assignable = $request->user()->assignableRoles();
                if (! in_array($mappedRole, $assignable, true)) {
                    return back()->withInput()->withErrors([
                        'role_label' => 'You cannot change this staff member to '.$roleLabel->label().' while they have a login. Ask a Super Admin, or change the login role in Settings first.',
                    ]);
                }
            }
        }

        $notes = [];

        try {
            DB::transaction(function () use ($staff, $data, $roleLabel, $status, $subject, &$notes) {
                $staff->update([
                    'full_name' => $data['full_name'],
                    'dob' => $data['dob'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'qualification' => $data['qualification'] ?? null,
                    'subject_specialty' => $roleLabel === StaffRoleLabel::Teacher ? $subject : null,
                    'date_joined' => $data['date_joined'] ?? null,
                    'fixed_salary_usd' => $data['fixed_salary_usd'] ?? null,
                    'role_label' => $roleLabel,
                    'status' => $status,
                ]);

                $user = $staff->user()->lockForUpdate()->first();
                if (! $user || $user->isSuperAdmin()) {
                    return;
                }

                $userData = ['name' => $staff->full_name];
                $mappedRole = $roleLabel->toUserRole();

                $newPhone = $staff->phone;
                if ($newPhone !== null && $newPhone !== '') {
                    $phoneTaken = User::query()
                        ->where('phone', $newPhone)
                        ->where('id', '!=', $user->id)
                        ->exists();

                    if (! $phoneTaken) {
                        $userData['phone'] = $newPhone;
                    }
                }

                if ($mappedRole === null) {
                    $userData['is_active'] = false;
                    $userData['staff_id'] = null;
                    $notes[] = 'Linked login was deactivated and unlinked (Librarian cannot have system access).';
                } else {
                    if ($mappedRole !== $user->role) {
                        $userData['role'] = $mappedRole;
                        $notes[] = 'Linked login role updated to '.$mappedRole->label().'.';
                    }

                    if (in_array($status, [StaffStatus::Resigned, StaffStatus::OnLeave], true)) {
                        if ($user->is_active) {
                            $userData['is_active'] = false;
                            $notes[] = 'Linked login deactivated because staff is '.$status->label().'.';
                        }
                    } elseif ($status === StaffStatus::Active && ! $user->is_active) {
                        $userData['is_active'] = true;
                        $notes[] = 'Linked login reactivated.';
                    }
                }

                $user->update($userData);
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'full_name' => 'Could not save staff changes. Please try again.',
            ]);
        }

        $message = 'Staff information updated.';
        if ($notes !== []) {
            $message .= ' '.implode(' ', $notes);
        }

        return redirect()
            ->route('staff.show', $staff->fresh())
            ->with('status', $message);
    }

    public function show(Staff $staff): View
    {
        $staff->load('user');

        return view('staff.show', [
            'staff' => $staff,
            'canSeeSalary' => request()->user()->isAdmin()
                || request()->user()->hasRole(UserRole::Finance),
            'canEdit' => request()->user()->isAdmin(),
            ...$this->formOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'roleLabels' => StaffRoleLabel::cases(),
            'statuses' => StaffStatus::cases(),
            'genders' => Gender::cases(),
            'subjects' => Subjects::all(),
            'qualifications' => [
                "Bachelor's Degree",
                "Master's Degree",
                'Diploma',
                'Certificate',
            ],
        ];
    }
}
