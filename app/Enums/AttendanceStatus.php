<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::Suspended => 'Suspended',
        };
    }

    public function toneClass(): string
    {
        return match ($this) {
            self::Present => 'text-green-700',
            self::Late => 'text-amber-700',
            self::Absent => 'text-red-700',
            self::Suspended => 'text-slate-600',
        };
    }

    public function requiresReason(): bool
    {
        return match ($this) {
            self::Absent, self::Suspended => true,
            default => false,
        };
    }
}
