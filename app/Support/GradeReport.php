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
    /**
     * @return array{
     *     rows: Collection,
     *     average: ?float,
     *     average_letter: ?LetterGrade,
     *     rank: ?int,
     *     class_size: int,
     *     attendance_rate: ?float
     * }
     */
    public static function for(Student $student, SchoolClass $schoolClass, AcademicTerm $term, string $year): array
    {
        $subjects = Subject::query()->orderBy('sort_order')->get();

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->where('term', $term)
            ->where('academic_year', $year)
            ->get()
            ->keyBy('subject_id');

        $rows = $subjects->map(function (Subject $subject) use ($grades) {
            $grade = $grades->get($subject->id);

            return [
                'subject' => $subject,
                'score' => $grade?->score_percent !== null ? (float) $grade->score_percent : null,
                'letter' => $grade?->letter_grade,
                'remarks' => $grade?->remarks,
            ];
        });

        $scored = $rows->filter(fn ($r) => $r['score'] !== null);
        $average = $scored->isNotEmpty() ? round($scored->avg('score'), 1) : null;
        $averageLetter = $average !== null ? GradeScale::letterFor($average) : null;

        $averages = self::classTermAverages($schoolClass->id, $term, $year);
        $rank = null;
        $classSize = $averages->count();
        if ($average !== null && $classSize > 0) {
            $better = $averages->filter(fn ($avg) => $avg > $average)->count();
            $rank = $better + 1;
        }

        return [
            'rows' => $rows,
            'average' => $average,
            'average_letter' => $averageLetter,
            'rank' => $rank,
            'class_size' => $classSize,
            'attendance_rate' => self::attendanceRateFor($student->id, $schoolClass->id),
        ];
    }

    /**
     * @return Collection<int, float>
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
