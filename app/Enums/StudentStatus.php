<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Waitlisted = 'waitlisted';
    case Transferred = 'transferred';
    case Graduated = 'graduated';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Waitlisted => 'Waitlisted',
            self::Transferred => 'Transferred',
            self::Graduated => 'Graduated',
            self::Suspended => 'Suspended',
        };
    }
}
