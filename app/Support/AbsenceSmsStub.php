<?php

namespace App\Support;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\AttendanceRecord;
use App\Models\NotificationLog;
use App\Models\Student;
use Carbon\CarbonInterface;

class AbsenceSmsStub
{
    public static function messageBody(string $studentName, CarbonInterface $date): string
    {
        $formatted = $date->format('j F Y');

        return "Dear parent, your child {$studentName} was absent from school on {$formatted}. Please contact the school. — Dugsi ERP";
    }

    /**
     * Log intended absence SMS (real Telesom send arrives in Week 9).
     * Idempotent per attendance record — re-saving the same absence does not duplicate stubs.
     *
     * @return bool True when a new stub row was created
     */
    public static function log(Student $student, AttendanceRecord $record, ?string $phone): bool
    {
        $alreadyLogged = NotificationLog::query()
            ->where('related_attendance_id', $record->id)
            ->where('type', NotificationType::AbsenceAlert)
            ->exists();

        if ($alreadyLogged) {
            return false;
        }

        $phone = $phone ? trim($phone) : null;

        NotificationLog::query()->create([
            'type' => NotificationType::AbsenceAlert,
            'recipient_phone' => $phone,
            'recipient_email' => null,
            'message_body' => self::messageBody($student->full_name, $record->date),
            'status' => NotificationStatus::Stubbed,
            'related_student_id' => $student->id,
            'related_attendance_id' => $record->id,
            'sent_at' => null,
            'error' => $phone
                ? 'SMS gateway not connected yet (Week 9). Logged for later delivery.'
                : 'No guardian phone on file; SMS not queued.',
        ]);

        return true;
    }
}
