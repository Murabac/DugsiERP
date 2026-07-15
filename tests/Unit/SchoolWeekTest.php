<?php

namespace Tests\Unit;

use App\Support\SchoolWeek;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchoolWeekTest extends TestCase
{
    #[DataProvider('dayKeyProvider')]
    public function test_day_key_maps_school_week_days(string $date, ?string $expected): void
    {
        $this->assertSame($expected, SchoolWeek::dayKey(Carbon::parse($date)));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function dayKeyProvider(): array
    {
        return [
            'saturday' => ['2026-07-11', 'sat'],
            'sunday' => ['2026-07-12', 'sun'],
            'monday' => ['2026-07-13', 'mon'],
            'tuesday' => ['2026-07-14', 'tue'],
            'wednesday' => ['2026-07-15', 'wed'],
            'thursday' => ['2026-07-16', null],
            'friday' => ['2026-07-17', null],
        ];
    }
}
