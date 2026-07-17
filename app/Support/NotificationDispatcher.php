<?php

namespace App\Support;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NotificationDispatcher
{
    /** Hours before the same fee notice may be resent for an invoice. */
    public const FEE_NOTICE_COOLDOWN_HOURS = 24;

    /**
     * Send (or attempt) an absence alert for an attendance record.
     * Idempotent per attendance record (DB unique + check).
     *
     * @return bool True when a new log row was created
     */
    public static function sendAbsenceAlert(Student $student, AttendanceRecord $record, ?string $phone): bool
    {
        return DB::transaction(function () use ($student, $record, $phone) {
            $alreadyLogged = NotificationLog::query()
                ->where('related_attendance_id', $record->id)
                ->where('type', NotificationType::AbsenceAlert)
                ->lockForUpdate()
                ->exists();

            if ($alreadyLogged) {
                return false;
            }

            $classLabel = $record->schoolClass?->displayName()
                ?? $student->currentEnrollment?->schoolClass?->displayName()
                ?? '—';

            $body = self::render(NotificationType::AbsenceAlert, [
                'student_name' => $student->full_name,
                'class' => $classLabel,
                'date' => $record->date->format('j F Y'),
            ], fallback: AbsenceSmsStub::messageBody($student->full_name, $record->date));

            try {
                self::dispatch(
                    type: NotificationType::AbsenceAlert,
                    phone: $phone,
                    body: $body,
                    studentId: $student->id,
                    attendanceId: $record->id,
                );
            } catch (QueryException $e) {
                if (self::isUniqueViolation($e)) {
                    return false;
                }

                throw $e;
            }

            return true;
        });
    }

    /**
     * Fee due reminder for a student/invoice.
     * Blocked for FEE_NOTICE_COOLDOWN_HOURS after a successful send for the same invoice.
     */
    public static function sendFeeReminder(Student $student, Invoice $invoice, ?string $phone = null): NotificationLog
    {
        if ($blocked = self::recentFeeNotice($invoice, NotificationType::FeeReminder)) {
            return $blocked;
        }

        $phone ??= $student->primaryGuardian?->phone;
        $due = Money::format(max(0, (float) $invoice->amount_due - (float) $invoice->amount_paid));

        $body = self::render(NotificationType::FeeReminder, [
            'student_name' => $student->full_name,
            'amount' => $due,
            'due_date' => $invoice->billing_month->copy()->endOfMonth()->format('j F Y'),
        ], fallback: "Dear parent, fee reminder for {$student->full_name}: {$due} due by "
            .$invoice->billing_month->copy()->endOfMonth()->format('j F Y').'.');

        return self::dispatch(
            type: NotificationType::FeeReminder,
            phone: $phone,
            body: $body,
            studentId: $student->id,
            invoiceId: $invoice->id,
        );
    }

    /**
     * Fee overdue notice.
     */
    public static function sendFeeOverdue(Student $student, Invoice $invoice, int $daysOverdue, ?string $phone = null): NotificationLog
    {
        if ($blocked = self::recentFeeNotice($invoice, NotificationType::FeeOverdue)) {
            return $blocked;
        }

        $phone ??= $student->primaryGuardian?->phone;
        $due = Money::format(max(0, (float) $invoice->amount_due - (float) $invoice->amount_paid));

        $body = self::render(NotificationType::FeeOverdue, [
            'student_name' => $student->full_name,
            'amount' => $due,
            'days' => (string) $daysOverdue,
        ], fallback: "Dear parent, fee for {$student->full_name} of {$due} is overdue by {$daysOverdue} days. Please pay soon.");

        return self::dispatch(
            type: NotificationType::FeeOverdue,
            phone: $phone,
            body: $body,
            studentId: $student->id,
            invoiceId: $invoice->id,
        );
    }

    /**
     * @param  array<string, string|int|float|null>  $vars
     */
    public static function render(NotificationType $type, array $vars, ?string $fallback = null): string
    {
        $template = NotificationTemplate::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $template->render($vars);
        }

        return $fallback ?? '';
    }

    private static function recentFeeNotice(Invoice $invoice, NotificationType $type): ?NotificationLog
    {
        return NotificationLog::query()
            ->where('related_invoice_id', $invoice->id)
            ->where('type', $type)
            ->where('status', NotificationStatus::Sent)
            ->where('created_at', '>=', now()->subHours(self::FEE_NOTICE_COOLDOWN_HOURS))
            ->latest('id')
            ->first();
    }

    private static function dispatch(
        NotificationType $type,
        ?string $phone,
        string $body,
        ?int $studentId = null,
        ?int $attendanceId = null,
        ?int $invoiceId = null,
    ): NotificationLog {
        $phone = $phone ? trim($phone) : null;

        if (! $phone) {
            return NotificationLog::query()->create([
                'type' => $type,
                'recipient_phone' => null,
                'recipient_email' => null,
                'message_body' => $body,
                'status' => NotificationStatus::Failed,
                'related_student_id' => $studentId,
                'related_attendance_id' => $attendanceId,
                'related_invoice_id' => $invoiceId,
                'sent_at' => null,
                'error' => 'No guardian phone on file; SMS not sent.',
            ]);
        }

        $result = SmsGateway::send($phone, $body);

        return NotificationLog::query()->create([
            'type' => $type,
            'recipient_phone' => $phone,
            'recipient_email' => null,
            'message_body' => $body,
            'status' => $result['ok'] ? NotificationStatus::Sent : NotificationStatus::Failed,
            'related_student_id' => $studentId,
            'related_attendance_id' => $attendanceId,
            'related_invoice_id' => $invoiceId,
            'sent_at' => $result['ok'] ? now() : null,
            'error' => $result['ok'] ? null : ($result['error'] ?? 'SMS send failed.'),
        ]);
    }

    private static function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());

        return $code === '1062' || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
