<?php

namespace App\Support;

use App\Enums\AcademicTerm;
use App\Models\SchoolSetting;
use Illuminate\Validation\ValidationException;

class TermMarks
{
    /**
     * Default split of the year total (100).
     *
     * @return array<string, float>
     */
    public static function defaults(): array
    {
        return [
            AcademicTerm::Term1->value => 20.0,
            AcademicTerm::Term2->value => 20.0,
            AcademicTerm::Term3->value => 20.0,
            AcademicTerm::FinalExam->value => 40.0,
        ];
    }

    public static function settingKey(AcademicTerm $term): string
    {
        return match ($term) {
            AcademicTerm::Term1 => 'term_marks_term_1',
            AcademicTerm::Term2 => 'term_marks_term_2',
            AcademicTerm::Term3 => 'term_marks_term_3',
            AcademicTerm::FinalExam => 'term_marks_final_exam',
        };
    }

    /**
     * Max marks available for each term (should sum to 100).
     *
     * @return array<string, float>
     */
    public static function maxima(): array
    {
        $defaults = self::defaults();
        $out = [];
        foreach (AcademicTerm::options() as $term) {
            $raw = SchoolSetting::get(self::settingKey($term), (string) $defaults[$term->value]);
            $out[$term->value] = max(0.01, round((float) ($raw ?? $defaults[$term->value]), 2));
        }

        return $out;
    }

    public static function maxFor(AcademicTerm $term): float
    {
        return self::maxima()[$term->value];
    }

    public static function yearTotal(): float
    {
        return round(array_sum(self::maxima()), 2);
    }

    public static function percentFromMarks(float $marks, AcademicTerm $term): float
    {
        $max = self::maxFor($term);

        return round(($marks / $max) * 100, 2);
    }

    public static function marksFromPercent(float $percent, AcademicTerm $term): float
    {
        return round(($percent / 100) * self::maxFor($term), 2);
    }

    /**
     * @param  array<string, float|int|string>  $maxima  keyed by term value
     *
     * @throws ValidationException
     */
    public static function assertValidMaxima(array $maxima): void
    {
        $sum = 0.0;
        foreach (AcademicTerm::options() as $term) {
            if (! array_key_exists($term->value, $maxima)) {
                throw ValidationException::withMessages([
                    'term_marks' => 'Set marks for every term.',
                ]);
            }
            $value = (float) $maxima[$term->value];
            if ($value < 0.01 || $value > 100) {
                throw ValidationException::withMessages([
                    'term_marks.'.$term->value => $term->label().' marks must be between 0.01 and 100.',
                ]);
            }
            $sum += $value;
        }

        if (abs($sum - 100) > 0.01) {
            throw ValidationException::withMessages([
                'term_marks' => 'Term marks must add up to 100 (currently '.round($sum, 2).').',
            ]);
        }
    }
}
