<?php

namespace App\Support;

use App\Enums\StaffAttendanceSource;
use App\Enums\StaffAttendanceStatus;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class StaffAttendancePunch
{
    /**
     * Apply a biometric / self-service punch for today.
     *
     * @return array{action: string, record: StaffAttendanceRecord}
     */
    public static function punch(
        Staff $staff,
        ?Carbon $now = null,
        StaffAttendanceSource $source = StaffAttendanceSource::Webauthn,
    ): array {
        $now = $now ?? now();
        $date = $now->toDateString();
        $clock = $now->format('H:i');

        $record = StaffAttendanceRecord::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();

        if (! $record) {
            $record = new StaffAttendanceRecord([
                'staff_id' => $staff->id,
                'date' => $date,
            ]);
        }

        if ($record->check_out_at) {
            throw ValidationException::withMessages([
                'punch' => 'Already checked out for today.',
            ]);
        }

        if ($record->exists && $record->check_in_at) {
            $checkoutFrom = self::checkoutTime();
            if ($clock < $checkoutFrom) {
                throw ValidationException::withMessages([
                    'punch' => 'Check-out opens at '.$checkoutFrom.'.',
                ]);
            }

            $record->check_out_at = $now;
            $record->source = $source;
            $record->save();

            return ['action' => 'check_out', 'record' => $record];
        }

        $checkinStart = self::checkinStartTime();
        if ($clock < $checkinStart) {
            throw ValidationException::withMessages([
                'punch' => 'Check-in opens at '.$checkinStart.'.',
            ]);
        }

        $dayKey = SchoolWeek::dayKey($now);
        if ($dayKey === null || ! $staff->worksOn($dayKey)) {
            throw ValidationException::withMessages([
                'punch' => $dayKey === null
                    ? 'School is closed today (staff check-in is Sat–Wed).'
                    : 'You are not scheduled to attend on '.SchoolWeek::dayLabel($dayKey).'.',
            ]);
        }

        $lateAfter = self::lateAfterTime();
        $status = $clock > $lateAfter
            ? StaffAttendanceStatus::Late
            : StaffAttendanceStatus::Present;

        $record->fill([
            'status' => $status,
            'check_in_at' => $now,
            'check_out_at' => null,
            'source' => $source,
            'marked_by' => null,
        ]);
        $record->save();

        return ['action' => 'check_in', 'record' => $record];
    }

    public static function checkinStartTime(): string
    {
        return self::normalizeTime(
            SchoolSetting::get('staff_attendance_checkin_start', '07:00'),
            '07:00'
        );
    }

    public static function lateAfterTime(): string
    {
        return self::normalizeTime(
            SchoolSetting::get('staff_attendance_late_after', '08:00'),
            '08:00'
        );
    }

    public static function checkoutTime(): string
    {
        return self::normalizeTime(
            SchoolSetting::get('staff_attendance_checkout_time', '16:00'),
            '16:00'
        );
    }

    public static function nextAction(Staff $staff, ?string $date = null): string
    {
        $date = $date ?? now()->toDateString();
        $record = StaffAttendanceRecord::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();

        if (! $record || ! $record->check_in_at) {
            return 'check_in';
        }
        if (! $record->check_out_at) {
            return 'check_out';
        }

        return 'done';
    }

    private static function normalizeTime(?string $raw, string $fallback): string
    {
        $raw = trim((string) ($raw ?? $fallback));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
            return $fallback;
        }
        [$h, $m] = array_map('intval', explode(':', $raw));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return $fallback;
        }

        return sprintf('%02d:%02d', $h, $m);
    }
}
