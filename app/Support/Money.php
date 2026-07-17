<?php

namespace App\Support;

class Money
{
    public static function format(float|int|string|null $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    public static function round(float|int|string|null $amount): float
    {
        return round((float) $amount, 2);
    }

    /**
     * Format a 0–100 percentage. Values under 1% keep decimals so they don't look like 0%.
     */
    public static function formatPercent(float|int|string|null $percent): string
    {
        $value = (float) $percent;
        if ($value <= 0) {
            return '0%';
        }
        if ($value >= 100) {
            return '100%';
        }
        if ($value < 1) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
        }
        if (abs($value - round($value)) >= 0.05) {
            return number_format($value, 1, '.', '').'%';
        }

        return ((int) round($value)).'%';
    }

    /** Ratio paid/due as 0–100, preserving decimals for small rates. */
    public static function percentOf(float|int|string|null $part, float|int|string|null $whole): float
    {
        $whole = (float) $whole;
        if ($whole <= 0) {
            return 0.0;
        }

        return min(100, round(((float) $part / $whole) * 100, 2));
    }
}
