<?php

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Resigned = 'resigned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On Leave',
            self::Resigned => 'Resigned',
        };
    }
}
