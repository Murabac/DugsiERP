<?php

namespace App\Enums;

enum StaffRoleLabel: string
{
    case Teacher = 'teacher';
    case Admin = 'admin';
    case Finance = 'finance';
    case Librarian = 'librarian';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Teacher',
            self::Admin => 'Admin',
            self::Finance => 'Finance',
            self::Librarian => 'Librarian',
        };
    }

    public function toUserRole(): ?UserRole
    {
        return match ($this) {
            self::Teacher => UserRole::Teacher,
            self::Admin => UserRole::Admin,
            self::Finance => UserRole::Finance,
            self::Librarian => null,
        };
    }
}
