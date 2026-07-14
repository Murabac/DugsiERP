<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Enrolled = 'enrolled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Enrolled => 'Enrolled',
            self::Cancelled => 'Cancelled',
        };
    }
}
