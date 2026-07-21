<?php

namespace App\Models;

use App\Enums\LetterGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GradeBoundary extends Model
{
    protected $fillable = [
        'letter',
        'min_percent',
        'max_percent',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'letter' => LetterGrade::class,
            'min_percent' => 'integer',
            'max_percent' => 'integer',
        ];
    }

    /**
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::query()
            ->orderByDesc('min_percent')
            ->get();
    }

    public static function letterForScore(float $score): ?LetterGrade
    {
        $score = max(0, min(100, $score));

        // Match from the top band down by min only so decimals between integer
        // cutoffs (e.g. 69.6 between C≤69 and B≥70) still resolve — here to C.
        foreach (static::ordered() as $boundary) {
            if ($score >= (float) $boundary->min_percent) {
                return $boundary->letter;
            }
        }

        return null;
    }

    /**
     * Default school scale from /design-reference.
     *
     * @return list<array{letter: string, min_percent: int, max_percent: int, remark: string}>
     */
    public static function defaults(): array
    {
        return [
            ['letter' => 'A', 'min_percent' => 85, 'max_percent' => 100, 'remark' => 'Excellent'],
            ['letter' => 'B', 'min_percent' => 70, 'max_percent' => 84, 'remark' => 'Good'],
            ['letter' => 'C', 'min_percent' => 55, 'max_percent' => 69, 'remark' => 'Satisfactory'],
            ['letter' => 'D', 'min_percent' => 40, 'max_percent' => 54, 'remark' => 'Pass'],
            ['letter' => 'F', 'min_percent' => 0, 'max_percent' => 39, 'remark' => 'Fail'],
        ];
    }

    public static function seedDefaults(): void
    {
        foreach (static::defaults() as $row) {
            static::query()->updateOrCreate(
                ['letter' => $row['letter']],
                [
                    'min_percent' => $row['min_percent'],
                    'max_percent' => $row['max_percent'],
                    'remark' => $row['remark'],
                ]
            );
        }
    }
}
