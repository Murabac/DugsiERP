<?php

namespace App\Support;

use App\Enums\LetterGrade;
use App\Models\GradeBoundary;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GradeScale
{
    /**
     * Resolve letter grade from current boundaries.
     */
    public static function letterFor(?float $score): ?LetterGrade
    {
        if ($score === null) {
            return null;
        }

        return GradeBoundary::letterForScore($score);
    }

    /**
     * Validate that boundary rows cover 0–100 without gaps or overlaps.
     *
     * @param  list<array{letter: string, min_percent: int|float|string, max_percent: int|float|string, remark?: ?string}>  $rows
     *
     * @throws ValidationException
     */
    public static function assertContiguous(array $rows): void
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'boundaries' => 'At least one grade boundary is required.',
            ]);
        }

        $sorted = collect($rows)
            ->map(fn ($row) => [
                'letter' => (string) $row['letter'],
                'min_percent' => (int) $row['min_percent'],
                'max_percent' => (int) $row['max_percent'],
            ])
            ->sortByDesc('min_percent')
            ->values();

        $letters = $sorted->pluck('letter');
        if ($letters->unique()->count() !== $letters->count()) {
            throw ValidationException::withMessages([
                'boundaries' => 'Each letter grade may appear only once.',
            ]);
        }

        foreach ($sorted as $row) {
            if ($row['min_percent'] < 0 || $row['max_percent'] > 100 || $row['min_percent'] > $row['max_percent']) {
                throw ValidationException::withMessages([
                    'boundaries' => "Invalid range for letter {$row['letter']}.",
                ]);
            }
        }

        /** @var Collection<int, array{letter: string, min_percent: int, max_percent: int}> $sorted */
        if ((int) $sorted->first()['max_percent'] !== 100) {
            throw ValidationException::withMessages([
                'boundaries' => 'The top grade range must end at 100%.',
            ]);
        }

        if ((int) $sorted->last()['min_percent'] !== 0) {
            throw ValidationException::withMessages([
                'boundaries' => 'The lowest grade range must start at 0%.',
            ]);
        }

        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $current = $sorted[$i];
            $next = $sorted[$i + 1];
            if ($current['min_percent'] !== $next['max_percent'] + 1) {
                throw ValidationException::withMessages([
                    'boundaries' => 'Grade ranges must be contiguous without gaps or overlaps (e.g. A 85–100, B 70–84).',
                ]);
            }
        }
    }
}
