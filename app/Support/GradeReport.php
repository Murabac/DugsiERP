<?php

namespace App\Support;

use App\Enums\AcademicTerm;
use App\Enums\AttendanceStatus;
use App\Enums\LetterGrade;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;

class GradeReport
{
    public const ALL_TERMS = 'all';

    /**
     * @return array{
     *     all_terms: false,
     *     terms: list<AcademicTerm>,
     *     term_max: float,
     *     rows: Collection,
     *     average: ?float,
     *     average_marks: ?float,
     *     average_letter: ?LetterGrade,
     *     rank: ?int,
     *     class_size: int,
     *     attendance_rate: ?float
     * }
     */
    public static function for(Student $student, SchoolClass $schoolClass, AcademicTerm $term, string $year): array
    {
        $subjects = Subject::query()->orderBy('sort_order')->get();
        $termMax = TermMarks::maxFor($term);

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->where('term', $term)
            ->where('academic_year', $year)
            ->get()
            ->keyBy('subject_id');

        $rows = $subjects->map(function (Subject $subject) use ($grades, $term) {
            $grade = $grades->get($subject->id);
            $marks = $grade ? self::marksOf($grade, $term) : null;
            $percent = $grade?->score_percent !== null
                ? (float) $grade->score_percent
                : ($marks !== null ? TermMarks::percentFromMarks($marks, $term) : null);

            return [
                'subject' => $subject,
                'marks' => $marks,
                'score' => $percent,
                'percent' => $percent,
                'letter' => $grade?->letter_grade,
                'remarks' => $grade?->remarks,
            ];
        });

        $scored = $rows->filter(fn ($r) => $r['percent'] !== null);
        $average = $scored->isNotEmpty() ? round((float) $scored->avg('percent'), 1) : null;
        $averageMarks = $scored->isNotEmpty() ? round((float) $scored->avg('marks'), 1) : null;
        $averageLetter = $average !== null ? GradeScale::letterFor($average) : null;

        $averages = self::classTermAverages($schoolClass->id, $term, $year);
        $rank = null;
        $classSize = $averages->count();
        if ($average !== null && $classSize > 0) {
            $better = $averages->filter(fn ($avg) => $avg > $average)->count();
            $rank = $better + 1;
        }

        return [
            'all_terms' => false,
            'terms' => [$term],
            'term_max' => $termMax,
            'rows' => $rows,
            'average' => $average,
            'average_marks' => $averageMarks,
            'average_letter' => $averageLetter,
            'rank' => $rank,
            'class_size' => $classSize,
            'attendance_rate' => self::attendanceRateFor($student->id, $schoolClass->id),
        ];
    }

    /**
     * Report card covering every academic term. Subject total = sum of term marks (out of 100).
     *
     * @return array{
     *     all_terms: true,
     *     terms: list<AcademicTerm>,
     *     term_maxima: array<string, float>,
     *     rows: Collection,
     *     average: ?float,
     *     average_marks: ?float,
     *     average_letter: ?LetterGrade,
     *     rank: ?int,
     *     class_size: int,
     *     attendance_rate: ?float
     * }
     */
    public static function forAllTerms(Student $student, SchoolClass $schoolClass, string $year): array
    {
        $terms = AcademicTerm::options();
        $termMaxima = TermMarks::maxima();
        $subjects = Subject::query()->orderBy('sort_order')->get();

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->where('academic_year', $year)
            ->get();

        $rows = $subjects->map(function (Subject $subject) use ($grades, $terms) {
            $termMarks = [];
            $termPercents = [];
            foreach ($terms as $term) {
                $grade = $grades->first(
                    fn (Grade $g) => $g->subject_id === $subject->id && $g->term === $term
                );
                if ($grade) {
                    $marks = self::marksOf($grade, $term);
                    $termMarks[$term->value] = $marks;
                    $termPercents[$term->value] = $grade->score_percent !== null
                        ? (float) $grade->score_percent
                        : TermMarks::percentFromMarks($marks, $term);
                } else {
                    $termMarks[$term->value] = null;
                    $termPercents[$term->value] = null;
                }
            }

            $availableMarks = collect($termMarks)->filter(fn ($s) => $s !== null);
            // Year total for a subject = sum of term marks (max 100 when all terms entered).
            $totalMarks = $availableMarks->isNotEmpty() ? round((float) $availableMarks->sum(), 1) : null;
            $percent = $totalMarks; // out of 100

            return [
                'subject' => $subject,
                'term_scores' => $termMarks,
                'term_percents' => $termPercents,
                'average' => $percent,
                'average_marks' => $totalMarks,
                'letter' => $percent !== null ? GradeScale::letterFor($percent) : null,
            ];
        });

        $scored = $rows->filter(fn ($r) => $r['average'] !== null);
        $average = $scored->isNotEmpty() ? round((float) $scored->avg('average'), 1) : null;
        $averageMarks = $average;
        $averageLetter = $average !== null ? GradeScale::letterFor($average) : null;

        $averages = self::classAllTermAverages($schoolClass->id, $year);
        $rank = null;
        $classSize = $averages->count();
        if ($average !== null && $classSize > 0) {
            $better = $averages->filter(fn ($avg) => $avg > $average)->count();
            $rank = $better + 1;
        }

        return [
            'all_terms' => true,
            'terms' => $terms,
            'term_maxima' => $termMaxima,
            'rows' => $rows,
            'average' => $average,
            'average_marks' => $averageMarks,
            'average_letter' => $averageLetter,
            'rank' => $rank,
            'class_size' => $classSize,
            'attendance_rate' => self::attendanceRateFor($student->id, $schoolClass->id),
        ];
    }

    /**
     * @return Collection<int, float> student_id => average percent
     */
    public static function classTermAverages(int $classId, AcademicTerm $term, string $year): Collection
    {
        $grades = Grade::query()
            ->where('class_id', $classId)
            ->where('term', $term)
            ->where('academic_year', $year)
            ->get(['student_id', 'score_percent']);

        return $grades
            ->groupBy('student_id')
            ->map(fn ($group) => round($group->avg('score_percent'), 1))
            ->filter(fn ($avg) => $avg !== null);
    }

    /**
     * Per-student overall (mean of subject year totals / percentages).
     *
     * @return Collection<int, float>
     */
    public static function classAllTermAverages(int $classId, string $year): Collection
    {
        $grades = Grade::query()
            ->where('class_id', $classId)
            ->where('academic_year', $year)
            ->get(['student_id', 'subject_id', 'term', 'score_marks', 'score_percent']);

        return $grades
            ->groupBy('student_id')
            ->map(function ($studentGrades) {
                $subjectTotals = $studentGrades
                    ->groupBy('subject_id')
                    ->map(function ($group) {
                        return round((float) $group->sum(function (Grade $g) {
                            $term = $g->term instanceof AcademicTerm
                                ? $g->term
                                : AcademicTerm::from((string) $g->term);

                            return self::marksOf($g, $term);
                        }), 1);
                    });

                return $subjectTotals->isNotEmpty()
                    ? round((float) $subjectTotals->avg(), 1)
                    : null;
            })
            ->filter(fn ($avg) => $avg !== null);
    }

    public static function marksOf(Grade $grade, AcademicTerm $term): float
    {
        if ($grade->score_marks !== null) {
            return (float) $grade->score_marks;
        }

        return TermMarks::marksFromPercent((float) $grade->score_percent, $term);
    }

    /**
     * @return list<string>
     */
    public static function documentTermValues(): array
    {
        return [
            self::ALL_TERMS,
            ...array_map(fn (AcademicTerm $t) => $t->value, AcademicTerm::options()),
        ];
    }

    public static function attendanceRateFor(int $studentId, int $classId): ?float
    {
        $records = AttendanceRecord::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $presentish = $records->filter(fn ($r) => in_array($r->status, [
            AttendanceStatus::Present,
            AttendanceStatus::Late,
        ], true))->count();

        return round(($presentish / $records->count()) * 100, 1);
    }
}
