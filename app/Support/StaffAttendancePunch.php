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
    ): array
    {
        $now = $now ?? now();
        $date = $now->toDateString();

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
            $record->check_out_at = $now;
            $record->source = $source;
            $record->save();

            return ['action' => 'check_out', 'record' => $record];
        }

        $lateAfter = self::lateAfterTime();
        $status = $now->format('H:i') > $lateAfter
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

    public static function lateAfterTime(): string
    {
        $raw = trim((string) (SchoolSetting::get('staff_attendance_late_after', '08:00') ?? '08:00'));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
            return '08:00';
        }
        [$h, $m] = array_map('intval', explode(':', $raw));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return '08:00';
        }

        return sprintf('%02d:%02d', $h, $m);
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
}
