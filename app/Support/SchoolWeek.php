<?php

namespace App\Support;

class SchoolWeek
{
    public const WEEKLY_PERIODS_SETTING_KEY = 'timetable_weekly_periods';

    public const DAY_STRUCTURE_SETTING_KEY = 'timetable_day_structure';

    public const MIN_PERIODS_PER_DAY = 1;

    public const MAX_PERIODS_PER_DAY = 8;

    /** @return list<string> */
    public static function days(): array
    {
        return ['sat', 'sun', 'mon', 'tue', 'wed'];
    }

    public static function dayLabel(string $day): string
    {
        return match ($day) {
            'sat' => 'Saturday',
            'sun' => 'Sunday',
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            default => strtoupper($day),
        };
    }

    /**
     * School-week day key for a calendar date (Sat–Wed). Null on Thu/Fri.
     */
    public static function dayKey(?\Carbon\CarbonInterface $date = null): ?string
    {
        $date ??= now();

        return match ($date->dayOfWeek) {
            \Carbon\CarbonInterface::SATURDAY => 'sat',
            \Carbon\CarbonInterface::SUNDAY => 'sun',
            \Carbon\CarbonInterface::MONDAY => 'mon',
            \Carbon\CarbonInterface::TUESDAY => 'tue',
            \Carbon\CarbonInterface::WEDNESDAY => 'wed',
            default => null,
        };
    }

    /**
     * Saturday–Wednesday dates for the school week containing $around.
     * Thu/Fri map to the preceding school week.
     *
     * @return array{saturday: \Carbon\Carbon, days: list<array{key: string, label: string, date: \Carbon\Carbon}>}
     */
    public static function weekContaining(?\Carbon\CarbonInterface $around = null): array
    {
        $around = \Carbon\Carbon::parse($around ?? now())->startOfDay();
        $saturday = $around->copy();

        $saturday = match ($around->dayOfWeek) {
            \Carbon\CarbonInterface::SATURDAY => $saturday,
            \Carbon\CarbonInterface::SUNDAY => $saturday->subDay(),
            \Carbon\CarbonInterface::MONDAY => $saturday->subDays(2),
            \Carbon\CarbonInterface::TUESDAY => $saturday->subDays(3),
            \Carbon\CarbonInterface::WEDNESDAY => $saturday->subDays(4),
            \Carbon\CarbonInterface::THURSDAY => $saturday->subDays(5),
            default => $saturday->subDays(6), // Friday
        };

        $days = [];
        foreach (self::days() as $i => $key) {
            $days[] = [
                'key' => $key,
                'label' => self::dayLabel($key),
                'date' => $saturday->copy()->addDays($i),
            ];
        }

        return [
            'saturday' => $saturday->copy(),
            'days' => $days,
        ];
    }

    /**
     * Factory default period time slots (6 periods).
     *
     * @return list<array{period: int, start: string, end: string}>
     */
    public static function defaultPeriodDefinitions(): array
    {
        return [
            ['period' => 1, 'start' => '08:00', 'end' => '08:45'],
            ['period' => 2, 'start' => '08:45', 'end' => '09:30'],
            ['period' => 3, 'start' => '09:45', 'end' => '10:30'],
            ['period' => 4, 'start' => '10:30', 'end' => '11:15'],
            ['period' => 5, 'start' => '11:30', 'end' => '12:15'],
            ['period' => 6, 'start' => '13:30', 'end' => '14:15'],
            ['period' => 7, 'start' => '14:15', 'end' => '15:00'],
            ['period' => 8, 'start' => '15:00', 'end' => '15:45'],
        ];
    }

    /**
     * Factory default day lengths (sums to 34/week).
     *
     * @return array<string, int>
     */
    public static function defaultPeriodsPerDay(): array
    {
        return [
            'sat' => 7,
            'sun' => 7,
            'mon' => 7,
            'tue' => 7,
            'wed' => 6,
        ];
    }

    /**
     * @return array{definitions: list<array{period: int, start: string, end: string}>, per_day: array<string, int>}
     */
    public static function dayStructure(): array
    {
        $defaults = self::normalizeDayStructure([
            'definitions' => self::defaultPeriodDefinitions(),
            'per_day' => self::defaultPeriodsPerDay(),
        ]);

        $raw = \App\Models\SchoolSetting::get(self::DAY_STRUCTURE_SETTING_KEY);
        if ($raw === null || $raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return self::normalizeDayStructure($decoded);
    }

    /**
     * @param  array<string, mixed>|null  $structure
     * @return array{definitions: list<array{period: int, start: string, end: string}>, per_day: array<string, int>}
     */
    public static function setDayStructure(?array $structure): array
    {
        if ($structure === null) {
            \App\Models\SchoolSetting::set(self::DAY_STRUCTURE_SETTING_KEY, null);

            return self::dayStructure();
        }

        $normalized = self::normalizeDayStructure($structure);
        \App\Models\SchoolSetting::set(self::DAY_STRUCTURE_SETTING_KEY, json_encode([
            'definitions' => $normalized['definitions'],
            'per_day' => $normalized['per_day'],
        ]));

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{definitions: list<array{period: int, start: string, end: string}>, per_day: array<string, int>}
     */
    public static function normalizeDayStructure(array $input): array
    {
        $defaultDefs = self::defaultPeriodDefinitions();
        $defsByPeriod = [];
        foreach ($defaultDefs as $def) {
            $defsByPeriod[$def['period']] = $def;
        }

        if (isset($input['definitions']) && is_array($input['definitions'])) {
            foreach ($input['definitions'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $n = (int) ($row['period'] ?? 0);
                if ($n < 1 || $n > self::MAX_PERIODS_PER_DAY) {
                    continue;
                }
                $start = self::normalizeTime((string) ($row['start'] ?? ''));
                $end = self::normalizeTime((string) ($row['end'] ?? ''));
                if ($start === null || $end === null) {
                    continue;
                }
                $defsByPeriod[$n] = ['period' => $n, 'start' => $start, 'end' => $end];
            }
        }

        $perDayInput = is_array($input['per_day'] ?? null) ? $input['per_day'] : [];
        $perDay = [];
        $maxUsed = self::MIN_PERIODS_PER_DAY;
        foreach (self::days() as $day) {
            $count = (int) ($perDayInput[$day] ?? self::defaultPeriodsPerDay()[$day]);
            $count = max(self::MIN_PERIODS_PER_DAY, min(self::MAX_PERIODS_PER_DAY, $count));
            $perDay[$day] = $count;
            $maxUsed = max($maxUsed, $count);
        }

        $definitions = [];
        for ($n = 1; $n <= $maxUsed; $n++) {
            $def = $defsByPeriod[$n] ?? ['period' => $n, 'start' => '08:00', 'end' => '08:45'];
            $definitions[] = [
                'period' => $n,
                'start' => $def['start'],
                'end' => $def['end'],
            ];
        }

        return [
            'definitions' => $definitions,
            'per_day' => $perDay,
        ];
    }

    private static function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $min);
    }

    private static function formatPeriodLabel(string $start, string $end): string
    {
        $pretty = static function (string $hm): string {
            [$h, $m] = array_map('intval', explode(':', $hm));
            if ($h === 0) {
                return '12:'.sprintf('%02d', $m);
            }
            if ($h < 13) {
                return $h.':'.sprintf('%02d', $m);
            }

            return ($h - 12).':'.sprintf('%02d', $m);
        };

        return $pretty($start).'–'.$pretty($end);
    }

    /**
     * Period rows for the timetable grid (1..max periods used any day).
     *
     * @return list<array{period: int, start: string, end: string, label: string}>
     */
    public static function periods(): array
    {
        return array_map(function (array $def) {
            return [
                'period' => $def['period'],
                'start' => $def['start'],
                'end' => $def['end'],
                'label' => self::formatPeriodLabel($def['start'], $def['end']),
            ];
        }, self::dayStructure()['definitions']);
    }

    /** @return array<string, int> */
    public static function periodsPerDay(): array
    {
        return self::dayStructure()['per_day'];
    }

    public static function periodsOn(string $day): int
    {
        return self::periodsPerDay()[$day] ?? 0;
    }

    public static function dayHasPeriod(string $day, int $periodNumber): bool
    {
        return $periodNumber >= 1 && $periodNumber <= self::periodsOn($day);
    }

    /**
     * @return list<array{period: int, start: string, end: string, label: string}>
     */
    public static function periodsForDay(string $day): array
    {
        $limit = self::periodsOn($day);

        return array_values(array_filter(
            self::periods(),
            fn (array $p) => $p['period'] <= $limit
        ));
    }

    public static function periodCount(): int
    {
        return count(self::periods());
    }

    /**
     * Teaching shifts within a school day.
     * First = first half of max daily periods, Second = remaining.
     *
     * @return list<string>
     */
    public static function shifts(): array
    {
        return ['first', 'second'];
    }

    public static function shiftLabel(string $shift): string
    {
        return match ($shift) {
            'first' => 'First shift',
            'second' => 'Second shift',
            default => ucfirst($shift),
        };
    }

    /**
     * Period numbers covered by a shift (1-based), based on max periods/day.
     *
     * @return list<int>
     */
    public static function shiftPeriods(string $shift): array
    {
        $all = range(1, max(1, self::periodCount()));
        $mid = (int) ceil(count($all) / 2);

        return match ($shift) {
            'first' => array_slice($all, 0, $mid),
            'second' => array_slice($all, $mid),
            default => [],
        };
    }

    public static function shiftHint(string $shift): string
    {
        $periods = self::shiftPeriods($shift);
        if ($periods === []) {
            return '';
        }

        return 'P'.reset($periods).'–P'.end($periods);
    }

    /** Which shift covers a period number (1-based). */
    public static function shiftForPeriod(int $period): ?string
    {
        if (in_array($period, self::shiftPeriods('first'), true)) {
            return 'first';
        }
        if (in_array($period, self::shiftPeriods('second'), true)) {
            return 'second';
        }

        return null;
    }

    public static function weeklyCapacity(): int
    {
        return (int) array_sum(self::periodsPerDay());
    }

    /** Full-time load = one teacher covering every weekly slot. */
    public static function fullTimePeriods(): int
    {
        return max(1, self::weeklyCapacity());
    }

    /** Part-time load = half a full week (rounded up). */
    public static function partTimePeriods(): int
    {
        return max(1, (int) ceil(self::weeklyCapacity() / 2));
    }

    public static function period(int $number): ?array
    {
        foreach (self::periods() as $period) {
            if ($period['period'] === $number) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Factory defaults: periods per subject per week (sums to 34 for the default day structure).
     *
     * @return array<string, int>
     */
    public static function defaultWeeklyPeriods(): array
    {
        return [
            'Somali Language' => 3,
            'Arabic Language' => 3,
            'English' => 5,
            'Mathematics' => 5,
            'Islamic Studies' => 3,
            'Geography' => 3,
            'History' => 3,
            'Physics' => 3,
            'Chemistry' => 3,
            'Biology' => 3,
        ];
    }

    /**
     * Active school plan: saved settings when present, otherwise factory defaults.
     *
     * @return array<string, int>
     */
    public static function weeklyPeriods(): array
    {
        $defaults = self::defaultWeeklyPeriods();
        $raw = \App\Models\SchoolSetting::get(self::WEEKLY_PERIODS_SETTING_KEY);
        if ($raw === null || $raw === '') {
            return self::normalizeWeeklyPeriods($defaults);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return self::normalizeWeeklyPeriods($defaults);
        }

        return self::normalizeWeeklyPeriods($decoded);
    }

    /**
     * Persist periods per subject. Unknown subjects are ignored.
     * Pass null to clear saved plan and revert to factory defaults.
     *
     * @param  array<string, int|string>|null  $periods
     * @return array<string, int>
     */
    public static function setWeeklyPeriods(?array $periods): array
    {
        if ($periods === null) {
            \App\Models\SchoolSetting::set(self::WEEKLY_PERIODS_SETTING_KEY, null);

            return self::weeklyPeriods();
        }

        $normalized = self::normalizeWeeklyPeriods($periods);
        \App\Models\SchoolSetting::set(self::WEEKLY_PERIODS_SETTING_KEY, json_encode($normalized));

        return $normalized;
    }

    /**
     * @param  array<string, int|string>  $periods
     * @return array<string, int>
     */
    public static function normalizeWeeklyPeriods(array $periods): array
    {
        $defaults = self::defaultWeeklyPeriods();
        $capacity = self::weeklyCapacity();
        $out = [];
        foreach ($defaults as $name => $defaultCount) {
            if (array_key_exists($name, $periods) && is_numeric($periods[$name])) {
                $out[$name] = max(0, min($capacity, (int) $periods[$name]));
            } else {
                $out[$name] = max(0, min($capacity, $defaultCount));
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function subjectColors(): array
    {
        return [
            'Mathematics' => 'bg-blue-50 border-blue-200 text-blue-900',
            'English' => 'bg-emerald-50 border-emerald-200 text-emerald-900',
            'Somali Language' => 'bg-amber-50 border-amber-200 text-amber-900',
            'Arabic Language' => 'bg-teal-50 border-teal-200 text-teal-900',
            'Islamic Studies' => 'bg-green-50 border-green-200 text-green-900',
            'Physics' => 'bg-indigo-50 border-indigo-200 text-indigo-900',
            'Chemistry' => 'bg-violet-50 border-violet-200 text-violet-900',
            'Biology' => 'bg-lime-50 border-lime-200 text-lime-900',
            'Geography' => 'bg-cyan-50 border-cyan-200 text-cyan-900',
            'History' => 'bg-orange-50 border-orange-200 text-orange-900',
        ];
    }

    /**
     * Default classroom label for a form/section (teachers come to the class).
     */
    public static function defaultClassRoom(int $formLevel, string $section): string
    {
        return 'R-'.$formLevel.strtoupper($section);
    }
}
