<?php

namespace App\Support;

use Carbon\CarbonInterface;

class AcademicYear
{
    /**
     * Current academic year label (e.g. 2025-26).
     * School year runs September–August.
     */
    public static function current(?CarbonInterface $date = null): string
    {
        $startYear = self::startYear($date);
        $endShort = substr((string) ($startYear + 1), -2);

        return "{$startYear}-{$endShort}";
    }

    /**
     * Calendar year when the current academic year began (September).
     */
    public static function startYear(?CarbonInterface $date = null): int
    {
        $date ??= now();

        return $date->month >= 9 ? $date->year : $date->year - 1;
    }

    /**
     * First and last billable months for the current academic year (through today).
     *
     * @return array{min: string, max: string} Y-m bounds for fee collection
     */
    public static function feeMonthBounds(?CarbonInterface $date = null): array
    {
        $date ??= now();
        $start = \Illuminate\Support\Carbon::create(self::startYear($date), 9, 1)->startOfMonth();
        $end = \Illuminate\Support\Carbon::parse($date)->startOfMonth();
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [
            'min' => $start->format('Y-m'),
            'max' => $end->format('Y-m'),
        ];
    }

    /**
     * Typical secondary birth-year window (~ages 12–19) relative to the school year.
     *
     * @return array{min: int, max: int}
     */
    public static function birthYearBounds(?CarbonInterface $date = null): array
    {
        $start = self::startYear($date);

        return [
            'min' => $start - 19,
            'max' => $start - 12,
        ];
    }

    public static function defaultDob(?CarbonInterface $date = null): string
    {
        $bounds = self::birthYearBounds($date);
        $mid = (int) floor(($bounds['min'] + $bounds['max']) / 2);

        return sprintf('%04d-06-15', $mid);
    }
}
