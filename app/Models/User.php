<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\AcademicYear;
use App\Support\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'staff_id', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string>|null */
    protected ?array $permissionKeyCache = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function accessRole(): ?Role
    {
        return Role::query()->where('key', $this->roleKey())->first();
    }

    public function roleKey(): string
    {
        return (string) $this->role;
    }

    public function roleEnum(): ?UserRole
    {
        return UserRole::tryFrom($this->roleKey());
    }

    public function roleLabel(): string
    {
        if ($enum = $this->roleEnum()) {
            return $enum->label();
        }

        return $this->accessRole()?->name ?? Str::headline($this->roleKey());
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($last !== $first ? $last : ''));
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $keys = array_map(
            fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role,
            $roles
        );

        return in_array($this->roleKey(), $keys, true);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        if ($this->permissionKeyCache !== null) {
            return $this->permissionKeyCache;
        }

        if ($this->isSuperAdmin()) {
            return $this->permissionKeyCache = PermissionCatalog::allKeys();
        }

        $role = $this->accessRole();
        if ($role) {
            $role->loadMissing('permissions');

            return $this->permissionKeyCache = $role->permissionKeys();
        }

        if ($enum = $this->roleEnum()) {
            return $this->permissionKeyCache = PermissionCatalog::defaultsFor($enum->value);
        }

        return $this->permissionKeyCache = [];
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionKeyCache = null;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionKeys(), true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleKey() === UserRole::SuperAdmin->value;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    public function isTeacher(): bool
    {
        return $this->roleKey() === UserRole::Teacher->value;
    }

    public function isFinance(): bool
    {
        return $this->roleKey() === UserRole::Finance->value;
    }

    /**
     * Class IDs this teacher appears on in the timetable for an academic year.
     *
     * @return list<int>
     */
    public function taughtClassIds(?string $academicYear = null): array
    {
        if (! $this->staff_id) {
            return [];
        }

        $academicYear ??= AcademicYear::current();

        return TimetableSlot::query()
            ->where('academic_year', $academicYear)
            ->where('teacher_id', $this->staff_id)
            ->distinct()
            ->orderBy('class_id')
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Subject IDs this teacher teaches for a class (from timetable).
     *
     * @return list<int>
     */
    public function taughtSubjectIdsForClass(SchoolClass $schoolClass): array
    {
        if (! $this->staff_id) {
            return [];
        }

        return TimetableSlot::query()
            ->where('class_id', $schoolClass->id)
            ->where('academic_year', $schoolClass->academic_year)
            ->where('teacher_id', $this->staff_id)
            ->distinct()
            ->orderBy('subject_id')
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Admins / class managers: any subject.
     * Form Masters (homeroom): all subjects for their assigned class.
     * Other teachers: only subjects they teach on that class timetable.
     */
    public function canEnterGradesForSubject(SchoolClass $schoolClass, Subject $subject): bool
    {
        if ($this->isAdmin() || $this->hasPermission('classes.manage')) {
            return true;
        }

        if (! $this->hasPermission('grades.enter')) {
            return false;
        }

        if (! $this->canViewSchoolClass($schoolClass)) {
            return false;
        }

        if ($this->isHomeroomTeacherOf($schoolClass)) {
            return true;
        }

        if (! $this->staff_id) {
            return false;
        }

        return in_array((int) $subject->id, $this->taughtSubjectIdsForClass($schoolClass), true);
    }

    public function canViewSchoolClass(SchoolClass $schoolClass): bool
    {
        if ($this->isAdmin() || $this->hasPermission('classes.manage')) {
            return true;
        }

        if ($this->isHomeroomTeacherOf($schoolClass)) {
            return true;
        }

        $taught = $this->taughtClassIds($schoolClass->academic_year);
        if ($this->staff_id && $taught !== []) {
            return in_array((int) $schoolClass->id, $taught, true);
        }

        if ($this->isTeacher()) {
            return false;
        }

        return $this->hasPermission('classes.view');
    }

    public function canViewStudent(Student $student): bool
    {
        if ($this->isAdmin() || $this->isFinance() || $this->hasPermission('students.manage')) {
            return true;
        }

        $year = AcademicYear::current();
        $scopedIds = array_values(array_unique(array_merge(
            $this->taughtClassIds($year),
            $this->homeroomClassIds($year),
        )));

        if ($this->staff_id && $scopedIds !== []) {
            return $student->enrollments()
                ->where('academic_year', $year)
                ->whereIn('class_id', $scopedIds)
                ->exists();
        }

        if ($this->isTeacher()) {
            return false;
        }

        return $this->hasPermission('students.view');
    }

    /**
     * Form Master (class head) for this school class — not merely a subject teacher.
     */
    public function isHomeroomTeacherOf(SchoolClass $schoolClass): bool
    {
        if (! $this->staff_id || ! $schoolClass->homeroom_teacher_id) {
            return false;
        }

        return (int) $this->staff_id === (int) $schoolClass->homeroom_teacher_id;
    }

    /**
     * Student grade reports / report cards: Admin, class managers, or that class's Form Master.
     */
    public function canGenerateGradeReport(SchoolClass $schoolClass): bool
    {
        if ($this->isAdmin() || $this->hasPermission('classes.manage')) {
            return true;
        }

        return $this->isHomeroomTeacherOf($schoolClass);
    }

    /**
     * Classes this staff heads as Form Master for an academic year.
     *
     * @return list<int>
     */
    public function homeroomClassIds(?string $academicYear = null): array
    {
        if (! $this->staff_id) {
            return [];
        }

        $academicYear ??= AcademicYear::current();

        return SchoolClass::query()
            ->where('academic_year', $academicYear)
            ->where('homeroom_teacher_id', $this->staff_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function canGenerateAnyGradeReport(?string $academicYear = null): bool
    {
        if ($this->isAdmin() || $this->hasPermission('classes.manage')) {
            return true;
        }

        return $this->homeroomClassIds($academicYear) !== [];
    }

    /**
     * Roles this actor may assign when creating users (role keys).
     *
     * @return list<Role>
     */
    public function assignableRoles(): array
    {
        $query = Role::query()->orderBy('sort_order')->orderBy('name');

        if ($this->isSuperAdmin()) {
            return $query->where('key', '!=', UserRole::SuperAdmin->value)->get()->all();
        }

        if ($this->roleKey() === UserRole::Admin->value || $this->hasPermission('settings.manage')) {
            return $query->whereIn('key', [UserRole::Finance->value, UserRole::Teacher->value])->get()->all();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function assignableRoleKeys(): array
    {
        return array_map(fn (Role $role) => $role->key, $this->assignableRoles());
    }

    public function canManageUser(User $target): bool
    {
        if ($target->id === $this->id) {
            return false;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->roleKey() === UserRole::Admin->value || $this->hasPermission('settings.manage')) {
            return in_array($target->roleKey(), [UserRole::Finance->value, UserRole::Teacher->value], true);
        }

        return false;
    }

    /**
     * Which dashboard view to render for this user.
     *
     * @return 'admin'|'finance'|'teacher'
     */
    public function dashboardKind(): string
    {
        return match ($this->roleEnum()) {
            UserRole::Finance => 'finance',
            UserRole::Teacher => 'teacher',
            UserRole::Admin, UserRole::SuperAdmin => 'admin',
            default => $this->inferCustomDashboardKind(),
        };
    }

    /**
     * @return 'admin'|'finance'|'teacher'
     */
    private function inferCustomDashboardKind(): string
    {
        $hasAcademic = $this->hasAnyPermission('classes.view', 'attendance.mark', 'grades.enter');
        $hasFinance = $this->hasAnyPermission('fees.view', 'expenses.view', 'payroll.view');
        $hasAdmin = $this->hasAnyPermission('staff.view', 'settings.manage', 'classes.manage');

        if ($hasFinance && ! $hasAcademic && ! $hasAdmin) {
            return 'finance';
        }

        if ($hasAcademic && ! $hasFinance && ! $hasAdmin) {
            return 'teacher';
        }

        return 'admin';
    }
}
