<?php

namespace App\Models;

use App\Enums\UserRole;
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
