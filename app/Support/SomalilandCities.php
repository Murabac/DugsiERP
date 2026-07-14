<?php

namespace App\Support;

class SomalilandCities
{
    /**
     * Principal cities and towns of Somaliland (English spellings used in admin UI).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $cities = [
            'Ainabo',
            'Arabsiyo',
            'Badhan',
            'Baligubadle',
            'Berbera',
            'Borama',
            'Burao',
            'Buuhoodle',
            'Caynabo',
            'Dilla',
            'Erigavo',
            'Gabiley',
            'Hargeisa',
            'Las Anod',
            'Lughaya',
            'Odweyne',
            'Salahley',
            'Sheikh',
            'Wajaale',
            'Zeila',
        ];

        return $cities;
    }

    public static function default(): string
    {
        return 'Hargeisa';
    }
}
