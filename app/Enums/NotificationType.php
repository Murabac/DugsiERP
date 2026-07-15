<?php

namespace App\Enums;

enum NotificationType: string
{
    case AbsenceAlert = 'absence_alert';
    case FeeReminder = 'fee_reminder';

    public function label(): string
    {
        return match ($this) {
            self::AbsenceAlert => 'Absence Alert',
            self::FeeReminder => 'Fee Reminder',
        };
    }
}
