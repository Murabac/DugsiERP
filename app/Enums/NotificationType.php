<?php

namespace App\Enums;

enum NotificationType: string
{
    case AbsenceAlert = 'absence_alert';
    case FeeReminder = 'fee_reminder';
    case FeeOverdue = 'fee_overdue';

    public function label(): string
    {
        return match ($this) {
            self::AbsenceAlert => 'Absence Alert',
            self::FeeReminder => 'Fee Due Reminder',
            self::FeeOverdue => 'Fee Overdue Notice',
        };
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
