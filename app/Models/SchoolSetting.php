<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SchoolSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember('school_setting:'.$key, 60, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value ?? $default;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget('school_setting:'.$key);
    }

    public static function gradeEditWindowDays(): int
    {
        $days = (int) (static::get('grade_edit_window_days', '5') ?? '5');

        return max(1, min(14, $days));
    }

    public static function schoolName(): string
    {
        return trim((string) (static::get('school_name', 'Qudus Secondary School') ?? 'Qudus Secondary School'))
            ?: 'Qudus Secondary School';
    }

    public static function schoolLocation(): string
    {
        return trim((string) (static::get('school_location', 'Somaliland') ?? 'Somaliland'))
            ?: 'Somaliland';
    }

    public static function schoolTagline(): string
    {
        return trim((string) (static::get('school_tagline', 'Secondary School') ?? 'Secondary School'))
            ?: 'Secondary School';
    }

    /**
     * Short letterhead subtitle, e.g. "Secondary School · Somaliland".
     */
    public static function schoolLetterheadSub(): string
    {
        return self::schoolTagline().' · '.self::schoolLocation();
    }

    /** School-wide monthly tuition (USD). Same for all forms/classes. */
    public static function monthlyFeeUsd(): float
    {
        return Money::round(self::get('monthly_fee_usd', '45') ?? '45');
    }

    public static function siblingDiscountPercent(): int
    {
        return max(0, min(100, (int) (self::get('sibling_discount_percent', '10') ?? '10')));
    }

    public static function needBasedDiscountPercent(): int
    {
        return max(0, min(100, (int) (self::get('need_based_discount_percent', '20') ?? '20')));
    }

    public static function transportFeeUsd(): float
    {
        return Money::round(self::get('transport_fee_usd', '15') ?? '15');
    }

    public static function staffAttendanceAllowedCidrs(): string
    {
        return (string) (self::get('staff_attendance_allowed_cidrs', '') ?? '');
    }

    public static function staffAttendanceLateAfter(): string
    {
        return \App\Support\StaffAttendancePunch::lateAfterTime();
    }

    /**
     * @return array{monthly_fee_usd: float, sibling_discount_percent: int, need_based_discount_percent: int, transport_fee_usd: float}
     */
    public static function feeSettings(): array
    {
        return [
            'monthly_fee_usd' => self::monthlyFeeUsd(),
            'sibling_discount_percent' => self::siblingDiscountPercent(),
            'need_based_discount_percent' => self::needBasedDiscountPercent(),
            'transport_fee_usd' => self::transportFeeUsd(),
        ];
    }
}
