<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\AcademicYear;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'staff_id', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'role' => UserRole::class,
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($last !== $first ? $last : ''));
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin, UserRole::SuperAdmin);
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
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
     * Admins: any subject. Teachers: only subjects they teach on that class timetable.
     */
    public function canEnterGradesForSubject(SchoolClass $schoolClass, Subject $subject): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isTeacher() || ! $this->canViewSchoolClass($schoolClass)) {
            return false;
        }

        return in_array((int) $subject->id, $this->taughtSubjectIdsForClass($schoolClass), true);
    }

    public function canViewSchoolClass(SchoolClass $schoolClass): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isTeacher()) {
            return false;
        }

        return in_array((int) $schoolClass->id, $this->taughtClassIds($schoolClass->academic_year), true);
    }

    public function canViewStudent(Student $student): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isTeacher()) {
            return false;
        }

        $taught = $this->taughtClassIds();
        if ($taught === []) {
            return false;
        }

        return $student->enrollments()
            ->where('academic_year', AcademicYear::current())
            ->whereIn('class_id', $taught)
            ->exists();
    }

    /**
     * Class headmaster (homeroom) for this school class — not merely a subject teacher.
     */
    public function isHomeroomTeacherOf(SchoolClass $schoolClass): bool
    {
        if (! $this->isTeacher() || ! $this->staff_id || ! $schoolClass->homeroom_teacher_id) {
            return false;
        }

        return (int) $this->staff_id === (int) $schoolClass->homeroom_teacher_id;
    }

    /**
     * Student grade reports / report cards: Admin, Super Admin, or the class headmaster only.
     */
    public function canGenerateGradeReport(SchoolClass $schoolClass): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->isHomeroomTeacherOf($schoolClass);
    }

    /**
     * Classes this teacher heads (homeroom) for an academic year.
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
        if ($this->isAdmin()) {
            return true;
        }

        return $this->homeroomClassIds($academicYear) !== [];
    }

    /**
     * Roles this actor may assign when creating/editing users.
     *
     * @return list<UserRole>
     */
    public function assignableRoles(): array
    {
        if ($this->isSuperAdmin()) {
            return [UserRole::Admin, UserRole::Finance, UserRole::Teacher];
        }

        if ($this->role === UserRole::Admin) {
            return [UserRole::Finance, UserRole::Teacher];
        }

        return [];
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

        if ($this->role === UserRole::Admin) {
            return in_array($target->role, [UserRole::Finance, UserRole::Teacher], true);
        }

        return false;
    }
}
