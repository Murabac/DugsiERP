<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * @deprecated Use NotificationDispatcher::sendAbsenceAlert — kept as thin wrapper for call sites/tests.
 */
class AbsenceSmsStub
{
    public static function messageBody(string $studentName, CarbonInterface $date): string
    {
        $formatted = $date->format('j F Y');

        return "Dear parent, your child {$studentName} was absent from school on {$formatted}. Please contact the school.";
    }

    /**
     * @return bool True when a new log row was created
     */
    public static function log(Student $student, AttendanceRecord $record, ?string $phone): bool
    {
        return NotificationDispatcher::sendAbsenceAlert($student, $record, $phone);
    }
}
