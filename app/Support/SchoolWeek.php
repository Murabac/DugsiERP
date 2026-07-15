<?php

namespace App\Support;

class SchoolWeek
{
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
     * Fixed 6-period day schedule (students stay in their classroom).
     *
     * @return list<array{period: int, start: string, end: string, label: string}>
     */
    public static function periods(): array
    {
        return [
            ['period' => 1, 'start' => '08:00', 'end' => '08:45', 'label' => '8:00–8:45'],
            ['period' => 2, 'start' => '08:45', 'end' => '09:30', 'label' => '8:45–9:30'],
            ['period' => 3, 'start' => '09:45', 'end' => '10:30', 'label' => '9:45–10:30'],
            ['period' => 4, 'start' => '10:30', 'end' => '11:15', 'label' => '10:30–11:15'],
            ['period' => 5, 'start' => '11:30', 'end' => '12:15', 'label' => '11:30–12:15'],
            ['period' => 6, 'start' => '13:30', 'end' => '14:15', 'label' => '1:30–2:15'],
        ];
    }

    public static function periodCount(): int
    {
        return count(self::periods());
    }

    public static function weeklyCapacity(): int
    {
        return self::periodCount() * count(self::days());
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
     * Default periods per subject per week (sums to weekly capacity = 30).
     *
     * @return array<string, int>
     */
    public static function defaultWeeklyPeriods(): array
    {
        return [
            'Somali Language' => 3,
            'Arabic Language' => 3,
            'English' => 4,
            'Mathematics' => 5,
            'Islamic Studies' => 3,
            'Geography' => 2,
            'History' => 2,
            'Physics' => 3,
            'Chemistry' => 3,
            'Biology' => 2,
        ];
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
