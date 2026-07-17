<?php

namespace App\Enums;

enum StaffAttendanceSource: string
{
    case Manual = 'manual';
    case Webauthn = 'webauthn';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'via admin',
            self::Webauthn, self::Mobile => 'via mobile',
        };
    }
}
