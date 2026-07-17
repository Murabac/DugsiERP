<?php

namespace App\Enums;

enum StaffAttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::OnLeave => 'On leave',
        };
    }

    public function toneClass(): string
    {
        return match ($this) {
            self::Present => 'text-green-700',
            self::Late => 'text-amber-700',
            self::Absent => 'text-red-700',
            self::OnLeave => 'text-slate-600',
        };
    }
}
