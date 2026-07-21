<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\StaffStatus;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
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
            ->with(['user', 'subjectAssignments.subject'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('phones', 'like', "%{$search}%")
                        ->orWhere('subject_specialty', 'like', "%{$search}%");
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
            ...$this->formOptions($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedRoleKeys = $this->assignableStaffRoleKeys($request->user());

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phones' => ['nullable', 'array', 'max:5'],
            'phones.*' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_label' => ['required', 'string', Rule::in($allowedRoleKeys)],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['string', Rule::in(Subjects::all())],
            'subject_specialty' => ['nullable', 'string', Rule::in(Subjects::all())],
            'work_schedule' => ['nullable', 'array'],
            'work_schedule.*' => ['nullable', 'array'],
            'work_schedule.*.*' => ['string', Rule::in(SchoolWeek::shifts())],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', Rule::in(SchoolWeek::days())],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'date_joined' => ['nullable', 'date'],
            'fixed_salary_usd' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'status' => ['required', Rule::enum(StaffStatus::class)],
            'create_login' => ['sometimes', 'boolean'],
            'login_email' => ['nullable', 'required_if:create_login,1', 'email', 'max:255', 'unique:users,email'],
            'login_phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
        ]);

        $phones = $this->phonesFromRequest($data);
        $subjects = $this->subjectsFromRequest($data);
        $workSchedule = $this->workScheduleFromRequest($data);
        $classIds = $this->classIdsFromRequest($data);

        $roleKey = (string) $data['role_label'];
        $createLogin = (bool) ($data['create_login'] ?? false);

        if ($roleKey === UserRole::Admin->value && ! $request->user()->isSuperAdmin()) {
            return back()->withInput()->withErrors([
                'role_label' => 'Only Super Admins can create Admin staff records.',
            ]);
        }

        if ($createLogin && $roleKey === UserRole::Admin->value && ! $request->user()->isSuperAdmin()) {
            return back()->withInput()->withErrors([
                'create_login' => 'Only Super Admins can create Admin login accounts.',
            ]);
        }

        if ($roleKey === UserRole::Teacher->value && $subjects === []) {
            return back()->withInput()->withErrors([
                'subjects' => 'Select at least one subject for teachers.',
            ]);
        }

        if ($roleKey === UserRole::Finance->value) {
            $subjects = [];
            $workSchedule = [];
            $classIds = [];
        } elseif ($roleKey === UserRole::Teacher->value && $workSchedule === []) {
            $workSchedule = Staff::defaultWorkSchedule();
        }

        $staff = DB::transaction(function () use ($data, $roleKey, $createLogin, $phones, $subjects, $workSchedule, $classIds) {
            $staff = Staff::query()->create([
                'employee_code' => Staff::nextEmployeeCode(),
                'full_name' => $data['full_name'],
                'dob' => $data['dob'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $phones[0] ?? null,
                'phones' => $phones !== [] ? $phones : null,
                'qualification' => filled($data['qualification'] ?? null) ? (string) $data['qualification'] : null,
                'subject_specialty' => $subjects[0] ?? null,
                'work_days' => $workSchedule !== [] ? $workSchedule : null,
                'date_joined' => $data['date_joined'] ?? null,
                'fixed_salary_usd' => $data['fixed_salary_usd'] ?? null,
                'role_label' => $roleKey,
                'status' => $data['status'],
            ]);

            $this->syncSubjectAssignments($staff, $subjects);
            $this->syncClassAssignments($staff, $classIds);

            if ($createLogin) {
                $plainPassword = 'password';
                User::query()->create([
                    'name' => $staff->full_name,
                    'email' => $data['login_email'],
                    'phone' => filled($data['login_phone'] ?? null)
                        ? trim((string) $data['login_phone'])
                        : ($phones[0] ?? null),
                    'password' => Hash::make($plainPassword),
                    'role' => $roleKey,
                    'is_active' => true,
                    'staff_id' => $staff->id,
                    'email_verified_at' => now(),
                ]);
                $staff->setAttribute('_temp_login_password', $plainPassword);
                $staff->setAttribute('_temp_login_email', $data['login_email']);
            }

            return $staff;
        });

        $message = 'Staff member saved.';
        $loginCredentials = null;
        if ($createLogin) {
            $loginCredentials = [
                'email' => (string) $staff->getAttribute('_temp_login_email'),
                'password' => (string) $staff->getAttribute('_temp_login_password'),
            ];
            $message .= ' Login created — email '.$loginCredentials['email'].', temporary password: '.$loginCredentials['password'].'.';
        }

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', $message)
            ->with('login_credentials', $loginCredentials);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $allowedRoleKeys = $this->assignableStaffRoleKeys($request->user(), $staff);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phones' => ['nullable', 'array', 'max:5'],
            'phones.*' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_label' => ['required', 'string', Rule::in($allowedRoleKeys)],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['string', Rule::in(Subjects::all())],
            'subject_specialty' => ['nullable', 'string', Rule::in(Subjects::all())],
            'work_schedule' => ['nullable', 'array'],
            'work_schedule.*' => ['nullable', 'array'],
            'work_schedule.*.*' => ['string', Rule::in(SchoolWeek::shifts())],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', Rule::in(SchoolWeek::days())],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'date_joined' => ['nullable', 'date'],
            'fixed_salary_usd' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'status' => ['required', Rule::enum(StaffStatus::class)],
        ]);

        $phones = $this->phonesFromRequest($data);
        $subjects = $this->subjectsFromRequest($data);
        $workSchedule = $this->workScheduleFromRequest($data);
        $classIds = $this->classIdsFromRequest($data);

        $roleKey = (string) $data['role_label'];
        $status = StaffStatus::from($data['status']);

        if ($roleKey === UserRole::Admin->value && ! $request->user()->isSuperAdmin()
            && $staff->roleKey() !== UserRole::Admin->value) {
            return back()->withInput()->withErrors([
                'role_label' => 'Only Super Admins can set staff role to Admin.',
            ]);
        }

        if ($roleKey === UserRole::Teacher->value && $subjects === []) {
            return back()->withInput()->withErrors([
                'subjects' => 'Select at least one subject for teachers.',
            ]);
        }

        if ($roleKey === UserRole::Finance->value) {
            $subjects = [];
            $workSchedule = [];
            $classIds = [];
        } elseif ($roleKey === UserRole::Teacher->value && $workSchedule === []) {
            $workSchedule = Staff::defaultWorkSchedule();
        }

        $linkedUser = $staff->user;

        if ($linkedUser && ! $linkedUser->isSuperAdmin() && $roleKey !== $linkedUser->roleKey()) {
            $assignableKeys = $request->user()->assignableRoleKeys();
            if (! $request->user()->isSuperAdmin() && ! in_array($roleKey, $assignableKeys, true)) {
                $roleName = Role::query()->where('key', $roleKey)->value('name') ?? $roleKey;

                return back()->withInput()->withErrors([
                    'role_label' => 'You cannot change this staff member to '.$roleName.' while they have a login. Ask a Super Admin, or change the login role in Settings first.',
                ]);
            }
        }

        $notes = [];

        try {
            DB::transaction(function () use ($staff, $data, $roleKey, $status, $phones, $subjects, $workSchedule, $classIds, &$notes) {
                $staff->update([
                    'full_name' => $data['full_name'],
                    'dob' => $data['dob'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'phone' => $phones[0] ?? null,
                    'phones' => $phones !== [] ? $phones : null,
                    'qualification' => $data['qualification'] ?? null,
                    'subject_specialty' => $subjects[0] ?? null,
                    'work_days' => $workSchedule !== [] ? $workSchedule : null,
                    'date_joined' => $data['date_joined'] ?? null,
                    'fixed_salary_usd' => $data['fixed_salary_usd'] ?? null,
                    'role_label' => $roleKey,
                    'status' => $status,
                ]);

                $this->syncSubjectAssignments($staff, $subjects);
                if ($roleKey === UserRole::Finance->value) {
                    $this->syncClassAssignments($staff, []);
                } elseif (array_key_exists('class_ids', $data)) {
                    $this->syncClassAssignments($staff, $classIds);
                }

                $user = $staff->user()->lockForUpdate()->first();
                if (! $user || $user->isSuperAdmin()) {
                    return;
                }

                $userData = ['name' => $staff->full_name];

                $newPhone = $phones[0] ?? null;
                if ($newPhone !== null && $newPhone !== '') {
                    $phoneTaken = User::query()
                        ->where('phone', $newPhone)
                        ->where('id', '!=', $user->id)
                        ->exists();

                    if (! $phoneTaken) {
                        $userData['phone'] = $newPhone;
                    }
                }

                if ($roleKey !== $user->roleKey()) {
                    $userData['role'] = $roleKey;
                    $roleName = Role::query()->where('key', $roleKey)->value('name') ?? $roleKey;
                    $notes[] = 'Linked login role updated to '.$roleName.'.';
                    $user->forgetPermissionCache();
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

    public function resetLoginPassword(Request $request, Staff $staff): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('staff.manage'), 403);

        $user = $staff->user;
        abort_unless($user !== null && ! $user->isSuperAdmin(), 404);

        $user->update(['password' => Hash::make('password')]);

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', "Password for {$user->email} reset to: password")
            ->with('login_credentials', [
                'email' => $user->email,
                'password' => 'password',
            ]);
    }

    public function updateClasses(Request $request, Staff $staff): RedirectResponse
    {
        if (! in_array($staff->roleKey(), [UserRole::Teacher->value, 'form_master'], true)) {
            return back()->withErrors([
                'class_ids' => 'Only teachers can be assigned to classes.',
            ]);
        }

        $data = $request->validate([
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $classIds = $this->classIdsFromRequest($data);
        $this->syncClassAssignments($staff, $classIds);

        return redirect()
            ->route('staff.show', ['staff' => $staff, 'tab' => 'overview'])
            ->with('status', 'Class assignments updated.');
    }

    public function show(Request $request, Staff $staff): View
    {
        $staff->load(['user', 'subjectAssignments.subject', 'assignedClasses']);

        $canSeeSalary = $request->user()->isAdmin();
        $tab = (string) $request->query('tab', 'overview');
        if ($tab === 'payroll' && ! $canSeeSalary) {
            $tab = 'overview';
        }

        $payrollHistory = collect();
        if ($tab === 'payroll' && $canSeeSalary) {
            $payrollHistory = $staff->payrollItems()
                ->with('payrollRun')
                ->latest('id')
                ->get();
        }

        $assignableClasses = $this->assignableClasses();

        return view('staff.show', [
            'staff' => $staff,
            'tab' => $tab,
            'payrollHistory' => $payrollHistory,
            'canSeeSalary' => $canSeeSalary,
            'canEdit' => $request->user()->isAdmin(),
            'assignableClasses' => $assignableClasses,
            'assignedClassIds' => $staff->assignedClasses->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'checkinAction' => $staff->user
                && filled($staff->checkin_token)
                && (int) $request->user()->staff_id === (int) $staff->id
                ? \App\Support\StaffAttendancePunch::nextAction($staff)
                : null,
            ...$this->formOptions($request->user(), $staff),
        ]);
    }

    /**
     * Prefer work_schedule[day][] shifts; fall back to legacy work_days[] day list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function workScheduleFromRequest(array $data): array
    {
        if (array_key_exists('work_schedule', $data) && is_array($data['work_schedule'])) {
            return Staff::normalizeWorkSchedule($data['work_schedule']);
        }

        if (array_key_exists('work_days', $data) && is_array($data['work_days'])) {
            return Staff::normalizeWorkSchedule($data['work_days']);
        }

        return [];
    }

    /**
     * Prefer subjects[] from the form; fall back to legacy subject_specialty.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function subjectsFromRequest(array $data): array
    {
        if (array_key_exists('subjects', $data) && is_array($data['subjects'])) {
            $names = [];
            foreach ($data['subjects'] as $name) {
                $name = trim((string) $name);
                if ($name === '' || ! in_array($name, Subjects::all(), true) || in_array($name, $names, true)) {
                    continue;
                }
                $names[] = $name;
            }

            return $names;
        }

        if (filled($data['subject_specialty'] ?? null)) {
            $name = trim((string) $data['subject_specialty']);

            return in_array($name, Subjects::all(), true) ? [$name] : [];
        }

        return [];
    }

    /**
     * Sync global (non-class) teacher–subject assignments used by the timetable.
     *
     * @param  list<string>  $subjectNames
     */
    private function syncSubjectAssignments(Staff $staff, array $subjectNames): void
    {
        $subjectIds = [];
        foreach ($subjectNames as $index => $name) {
            $catalogIndex = array_search($name, Subjects::all(), true);
            $subject = Subject::query()->firstOrCreate(
                ['name' => $name],
                ['sort_order' => $catalogIndex === false ? ($index + 1) : ($catalogIndex + 1)]
            );
            $subjectIds[] = $subject->id;

            TeacherSubjectAssignment::query()->updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'subject_id' => $subject->id,
                ],
                ['class_id' => null]
            );
        }

        $query = TeacherSubjectAssignment::query()
            ->where('staff_id', $staff->id)
            ->whereNull('class_id');

        if ($subjectIds !== []) {
            $query->whereNotIn('subject_id', $subjectIds);
        }

        $query->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function classIdsFromRequest(array $data): array
    {
        if (! array_key_exists('class_ids', $data) || ! is_array($data['class_ids'])) {
            return [];
        }

        $ids = [];
        foreach ($data['class_ids'] as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        return SchoolClass::query()
            ->whereIn('id', $ids)
            ->where('academic_year', AcademicYear::current())
            ->where('status', ClassStatus::Active)
            ->orderBy('form_level')
            ->orderBy('section')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $classIds
     */
    private function syncClassAssignments(Staff $staff, array $classIds): void
    {
        $staff->assignedClasses()->sync($classIds);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function assignableClasses()
    {
        return SchoolClass::query()
            ->where('academic_year', AcademicYear::current())
            ->where('status', ClassStatus::Active)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();
    }

    /**
     * Prefer phones[] from the form; fall back to legacy single phone for older clients/tests.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function phonesFromRequest(array $data): array
    {
        if (array_key_exists('phones', $data) && is_array($data['phones'])) {
            return Staff::normalizePhones($data['phones']);
        }

        if (filled($data['phone'] ?? null)) {
            return Staff::normalizePhones([(string) $data['phone']]);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?User $actor = null, ?Staff $staff = null): array
    {
        $roles = Role::query()
            ->where('key', '!=', UserRole::SuperAdmin->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($actor && ! $actor->isSuperAdmin()) {
            $roles = $roles->filter(function (Role $role) use ($staff) {
                if ($role->key !== UserRole::Admin->value) {
                    return true;
                }

                return $staff !== null && $staff->roleKey() === UserRole::Admin->value;
            })->values();
        }

        // Keep a legacy role visible when editing older librarian/driver records.
        if ($staff && ! $roles->contains(fn (Role $role) => $role->key === $staff->roleKey())) {
            $roles = $roles->prepend(new Role([
                'key' => $staff->roleKey(),
                'name' => $staff->roleDisplayName(),
            ]));
        }

        return [
            'roles' => $roles,
            'statuses' => StaffStatus::cases(),
            'genders' => Gender::cases(),
            'subjects' => Subjects::all(),
            'weekDays' => SchoolWeek::days(),
            'classes' => $this->assignableClasses(),
            'qualifications' => [
                "Bachelor's Degree",
                "Master's Degree",
                'Diploma',
                'Certificate',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function assignableStaffRoleKeys(?User $actor, ?Staff $staff = null): array
    {
        $keys = Role::query()
            ->where('key', '!=', UserRole::SuperAdmin->value)
            ->orderBy('sort_order')
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->all();

        if ($actor && ! $actor->isSuperAdmin()) {
            $keys = array_values(array_filter(
                $keys,
                fn (string $key) => $key !== UserRole::Admin->value
                    || ($staff !== null && $staff->roleKey() === UserRole::Admin->value)
            ));
        }

        if ($staff && ! in_array($staff->roleKey(), $keys, true)) {
            $keys[] = $staff->roleKey();
        }

        return $keys;
    }
}
