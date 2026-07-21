<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * @deprecated Prefer NotificationDispatcher::sendAbsenceAlert for sending.
 * Helpers remain for preview/fallback text.
 */
class AbsenceSmsStub
{
    public static function messageBody(string $studentName, CarbonInterface $date, ?string $classLabel = null): string
    {
        return NotificationDispatcher::render(NotificationType::AbsenceAlert, [
            'student_name' => $studentName,
            'class' => $classLabel ?? '—',
            'date' => $date->format('j F Y'),
        ], fallback: "Dear parent, your child {$studentName} was absent from school on {$date->format('j F Y')}. Please contact the school.");
    }

    /**
     * Active Absence Alert template body (with {placeholders}), or null when inactive/missing.
     */
    public static function templateBody(): ?string
    {
        return NotificationDispatcher::activeTemplate(NotificationType::AbsenceAlert)?->body;
    }

    public static function templateIsActive(): bool
    {
        return NotificationDispatcher::activeTemplate(NotificationType::AbsenceAlert) !== null;
    }

    /**
     * Preview text for the mark-attendance SMS confirm dialog.
     */
    public static function previewBody(CarbonInterface $date, ?string $classLabel = null, ?string $studentName = null): string
    {
        return NotificationDispatcher::render(NotificationType::AbsenceAlert, [
            'student_name' => $studentName ?: 'Student',
            'class' => $classLabel ?? '—',
            'date' => $date->format('j F Y'),
        ], fallback: self::messageBody($studentName ?: 'Student', $date, $classLabel));
    }

    /**
     * @return bool True when a new log row was created
     */
    public static function log(Student $student, AttendanceRecord $record, ?string $phone): bool
    {
        return NotificationDispatcher::sendAbsenceAlert($student, $record, $phone);
    }
}
