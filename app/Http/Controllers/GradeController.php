<?php

namespace App\Http\Controllers;

use App\Enums\AcademicTerm;
use App\Enums\ClassStatus;
use App\Enums\LetterGrade;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeBoundary;
use App\Models\GradeEditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Support\AcademicYear;
use App\Support\GradeEditRules;
use App\Support\GradeReport;
use App\Support\GradeScale;
use App\Support\TermMarks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();
        $classes = $this->accessibleClasses($user, $year);
        $allSubjects = Subject::query()->orderBy('sort_order')->get();
        $terms = AcademicTerm::options();

        $schoolClass = $this->resolveClass($request, $classes);
        $subjects = $schoolClass
            ? $this->gradableSubjects($user, $schoolClass, $allSubjects)
            : ($user->isAdmin() ? $allSubjects : collect());
        $subject = $this->resolveSubject($request, $subjects);
        if ($schoolClass && $subject) {
            abort_unless($user->canEnterGradesForSubject($schoolClass, $subject), 403);
        }
        $term = $this->resolveTerm($request);

        $rows = collect();
        $classAverage = null;
        $classAverageMarks = null;
        $classAverageLetter = null;
        $termMax = TermMarks::maxFor($term);

        if ($schoolClass && $subject) {
            $enrollments = $schoolClass->activeEnrollments()
                ->with('student')
                ->orderBy('roll_number')
                ->get();

            $existing = Grade::query()
                ->with(['editLogs.editor'])
                ->where('class_id', $schoolClass->id)
                ->where('subject_id', $subject->id)
                ->where('term', $term)
                ->where('academic_year', $year)
                ->get()
                ->keyBy('student_id');

            $rows = $enrollments->map(function (Enrollment $enrollment) use ($existing, $user, $term, $termMax) {
                $student = $enrollment->student;
                $grade = $existing->get($student->id);
                $scoreOld = old('scores.'.$student->id);
                if ($scoreOld !== null) {
                    $score = $scoreOld;
                } elseif ($grade?->score_marks !== null) {
                    $score = (string) $grade->score_marks;
                } elseif ($grade?->score_percent !== null) {
                    $score = (string) TermMarks::marksFromPercent((float) $grade->score_percent, $term);
                } else {
                    $score = '';
                }

                $percent = is_numeric($score)
                    ? TermMarks::percentFromMarks((float) $score, $term)
                    : null;
                $letter = $percent !== null ? GradeScale::letterFor($percent) : null;
                $locked = GradeEditRules::isLockedFor($user, $grade);
                $needsNote = GradeEditRules::requiresEditNote($user, $grade);
                $unlockUntil = $grade ? GradeEditRules::unlockUntil($grade) : null;

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'roll' => str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT),
                    'score' => $score,
                    'percent' => $percent,
                    'letter' => $letter,
                    'remarks' => old('remarks.'.$student->id, $grade?->remarks ?? ''),
                    'edit_note' => old('edit_notes.'.$student->id, ''),
                    'locked' => $locked,
                    'needs_note' => $needsNote,
                    'first_entered_at' => $grade?->first_entered_at,
                    'unlock_until' => $unlockUntil,
                    'edit_logs' => $grade?->editLogs ?? collect(),
                    'grade_id' => $grade?->id,
                ];
            });

            $numericScores = $rows
                ->pluck('score')
                ->filter(fn ($s) => $s !== '' && is_numeric($s))
                ->map(fn ($s) => (float) $s);

            if ($numericScores->isNotEmpty()) {
                $classAverageMarks = round($numericScores->avg(), 1);
                $classAverage = round(TermMarks::percentFromMarks($classAverageMarks, $term), 1);
                $classAverageLetter = GradeScale::letterFor($classAverage);
            }
        }

        return view('grades.index', [
            'classes' => $classes,
            'subjects' => $subjects,
            'terms' => $terms,
            'schoolClass' => $schoolClass,
            'subject' => $subject,
            'term' => $term,
            'termMax' => $termMax,
            'rows' => $rows,
            'classAverage' => $classAverage,
            'classAverageMarks' => $classAverageMarks,
            'classAverageLetter' => $classAverageLetter,
            'academicYear' => $year,
            'boundaries' => GradeBoundary::ordered(),
            'boundaryJs' => GradeBoundary::ordered()->map(fn (GradeBoundary $b) => [
                'letter' => $b->letter->value,
                'min' => $b->min_percent,
                'max' => $b->max_percent,
                'cls' => $b->letter->badgeClass(),
            ])->values()->all(),
            'canGenerateReports' => $user->canGenerateAnyGradeReport($year),
            'canGenerateReportForClass' => $schoolClass
                ? $user->canGenerateGradeReport($schoolClass)
                : false,
            'gradeEditWindowDays' => GradeEditRules::windowDays(),
            'canViewEditHistory' => $user->isAdmin(),
            'isAdminGrader' => $user->isAdmin(),
        ]);
    }

    public function printSheet(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();
        $classes = $this->accessibleClasses($user, $year);
        $allSubjects = Subject::query()->orderBy('sort_order')->get();

        $schoolClass = $this->resolveClass($request, $classes);
        abort_unless($schoolClass !== null, 404);

        $subjects = $this->gradableSubjects($user, $schoolClass, $allSubjects);
        $subject = $this->resolveSubject($request, $subjects);
        abort_unless($subject !== null, 404);
        abort_unless($user->canEnterGradesForSubject($schoolClass, $subject), 403);

        $term = $this->resolveTerm($request);
        $termMax = TermMarks::maxFor($term);

        $enrollments = $schoolClass->activeEnrollments()
            ->with('student')
            ->orderBy('roll_number')
            ->get();

        $existing = Grade::query()
            ->where('class_id', $schoolClass->id)
            ->where('subject_id', $subject->id)
            ->where('term', $term)
            ->where('academic_year', $year)
            ->get()
            ->keyBy('student_id');

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($existing, $term) {
            $student = $enrollment->student;
            $grade = $existing->get($student->id);
            if ($grade?->score_marks !== null) {
                $score = (string) $grade->score_marks;
            } elseif ($grade?->score_percent !== null) {
                $score = (string) TermMarks::marksFromPercent((float) $grade->score_percent, $term);
            } else {
                $score = '';
            }

            $percent = is_numeric($score)
                ? TermMarks::percentFromMarks((float) $score, $term)
                : null;

            return [
                'student' => $student,
                'roll' => str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT),
                'score' => $score,
                'percent' => $percent,
                'letter' => $percent !== null ? GradeScale::letterFor($percent) : null,
                'remarks' => $grade?->remarks ?? '',
            ];
        });

        $numeric = $rows->pluck('percent')->filter(fn ($p) => $p !== null);
        $classAverage = $numeric->isNotEmpty() ? round((float) $numeric->avg(), 1) : null;

        return view('grades.print-sheet', [
            'schoolClass' => $schoolClass,
            'subject' => $subject,
            'term' => $term,
            'termMax' => $termMax,
            'rows' => $rows,
            'classAverage' => $classAverage,
            'classAverageLetter' => $classAverage !== null ? GradeScale::letterFor($classAverage) : null,
            'academicYear' => $year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $year = AcademicYear::current();

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'term' => ['required', Rule::enum(AcademicTerm::class)],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:255'],
            'edit_notes' => ['nullable', 'array'],
            'edit_notes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $schoolClass = SchoolClass::query()->findOrFail($data['class_id']);
        abort_unless($user->canViewSchoolClass($schoolClass), 403);
        abort_unless($schoolClass->academic_year === $year && $schoolClass->status === ClassStatus::Active, 404);

        $subject = Subject::query()->findOrFail($data['subject_id']);
        abort_unless($user->canEnterGradesForSubject($schoolClass, $subject), 403);

        $term = AcademicTerm::from($data['term']);
        $termMax = TermMarks::maxFor($term);
        $scores = $data['scores'] ?? [];
        $remarks = $data['remarks'] ?? [];
        $editNotes = $data['edit_notes'] ?? [];

        $activeStudentIds = $schoolClass->activeEnrollments()->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        // Pass 1: validate every intended change before writing anything.
        $errors = [];
        $intended = [];

        foreach ($scores as $studentId => $scoreValue) {
            $studentId = (int) $studentId;
            if (! in_array($studentId, $activeStudentIds, true)) {
                continue;
            }

            $existing = Grade::query()
                ->where('student_id', $studentId)
                ->where('class_id', $schoolClass->id)
                ->where('subject_id', $subject->id)
                ->where('term', $term)
                ->where('academic_year', $year)
                ->first();

            $raw = is_string($scoreValue) ? trim($scoreValue) : $scoreValue;
            $isClear = $raw === null || $raw === '';
            $remark = trim((string) ($remarks[$studentId] ?? ''));
            $note = trim((string) ($editNotes[$studentId] ?? ''));

            $existingMarks = $existing
                ? ($existing->score_marks !== null
                    ? (float) $existing->score_marks
                    : TermMarks::marksFromPercent((float) $existing->score_percent, $term))
                : null;

            if (GradeEditRules::isLockedFor($user, $existing)) {
                if ($existing) {
                    $oldScore = (string) $existingMarks;
                    $newScore = $isClear ? '' : (string) round((float) $raw, 2);
                    $remarkChanged = ($existing->remarks ?? '') !== ($remark !== '' ? $remark : '');
                    if ($oldScore !== $newScore || $remarkChanged) {
                        $intended[] = ['type' => 'skip_locked'];
                    }
                }

                continue;
            }

            if ($isClear) {
                if ($existing) {
                    $errors['scores.'.$studentId] = 'Clearing a saved grade is not allowed. Enter a score from 0–'.$termMax.'.';
                }

                continue;
            }

            $marks = round((float) $raw, 2);
            if ($marks > $termMax) {
                $errors['scores.'.$studentId] = 'Score must be between 0 and '.$termMax.' for '.$term->label().'.';

                continue;
            }

            $percent = TermMarks::percentFromMarks($marks, $term);
            $letter = GradeScale::letterFor($percent);
            if ($letter === null) {
                $errors['scores.'.$studentId] = 'Score is outside the grading scale.';

                continue;
            }

            if ($existing) {
                $scoreChanged = round((float) $existingMarks, 2) !== $marks;
                $newRemark = $remark !== '' ? $remark : null;
                $remarkChanged = ($existing->remarks ?? '') !== ($newRemark ?? '');

                if (! $scoreChanged && ! $remarkChanged) {
                    continue;
                }

                if (GradeEditRules::requiresEditNote($user, $existing) && $note === '') {
                    $errors['edit_notes.'.$studentId] = $user->isAdmin()
                        ? 'Add a short note explaining why this grade was changed.'
                        : 'An edit note is required when changing a grade after the first day.';

                    continue;
                }

                $intended[] = [
                    'type' => 'update',
                    'student_id' => $studentId,
                    'marks' => $marks,
                    'percent' => $percent,
                    'letter' => $letter,
                    'remark' => $newRemark,
                    'note' => $note !== '' ? $note : null,
                    'score_changed' => $scoreChanged,
                    'remark_changed' => $remarkChanged,
                ];
            } else {
                $intended[] = [
                    'type' => 'create',
                    'student_id' => $studentId,
                    'marks' => $marks,
                    'percent' => $percent,
                    'letter' => $letter,
                    'remark' => $remark !== '' ? $remark : null,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $skippedLocked = 0;
        $saved = 0;

        DB::transaction(function () use (
            $intended,
            $schoolClass,
            $subject,
            $term,
            $year,
            $user,
            &$skippedLocked,
            &$saved
        ) {
            foreach ($intended as $item) {
                if ($item['type'] === 'skip_locked') {
                    $skippedLocked++;

                    continue;
                }

                if ($item['type'] === 'create') {
                    Grade::query()->create([
                        'student_id' => $item['student_id'],
                        'class_id' => $schoolClass->id,
                        'subject_id' => $subject->id,
                        'term' => $term,
                        'academic_year' => $year,
                        'score_marks' => $item['marks'],
                        'score_percent' => $item['percent'],
                        'letter_grade' => $item['letter'],
                        'remarks' => $item['remark'],
                        'entered_by' => $user->id,
                        'first_entered_at' => now(),
                    ]);
                    $saved++;

                    continue;
                }

                $existing = Grade::query()
                    ->where('student_id', $item['student_id'])
                    ->where('class_id', $schoolClass->id)
                    ->where('subject_id', $subject->id)
                    ->where('term', $term)
                    ->where('academic_year', $year)
                    ->lockForUpdate()
                    ->first();

                if (! $existing) {
                    continue;
                }

                // Re-check lock after acquiring the row lock.
                if (GradeEditRules::isLockedFor($user, $existing)) {
                    $skippedLocked++;

                    continue;
                }

                $oldMarks = $existing->score_marks !== null
                    ? (float) $existing->score_marks
                    : TermMarks::marksFromPercent((float) $existing->score_percent, $term);

                GradeEditLog::query()->create([
                    'grade_id' => $existing->id,
                    'edited_by' => $user->id,
                    'old_score' => $oldMarks,
                    'new_score' => $item['marks'],
                    'old_letter' => $existing->letter_grade?->value,
                    'new_letter' => $item['letter']->value,
                    'old_remarks' => $existing->remarks,
                    'new_remarks' => $item['remark'],
                    'note' => $item['note'],
                ]);

                $existing->update([
                    'score_marks' => $item['marks'],
                    'score_percent' => $item['percent'],
                    'letter_grade' => $item['letter'],
                    'remarks' => $item['remark'],
                    'entered_by' => $user->id,
                ]);
                $saved++;
            }
        });

        $message = 'Grades saved for '.$schoolClass->displayName().' — '.$subject->name.', '.$term->label().'.';
        if ($saved > 0) {
            $message .= ' '.$saved.' change'.($saved === 1 ? '' : 's').' applied.';
        }
        if ($skippedLocked > 0) {
            $message .= ' '.$skippedLocked.' locked grade'.($skippedLocked === 1 ? ' was' : 's were').' left unchanged (teacher edit window is '.GradeEditRules::windowDays().' days).';
        }

        return redirect()
            ->route('grades.index', [
                'class' => $schoolClass->id,
                'subject' => $subject->id,
                'term' => $term->value,
            ])
            ->with('status', $message);
    }

    public function report(Request $request): View
    {
        $user = $request->user();
        $year = AcademicYear::current();
        abort_unless($user->canGenerateAnyGradeReport($year), 403);

        $classes = $this->reportAccessibleClasses($user, $year);
        $terms = AcademicTerm::options();

        $schoolClass = $this->resolveClass($request, $classes);
        if ($schoolClass) {
            abort_unless($user->canGenerateGradeReport($schoolClass), 403);
        }

        $term = $this->resolveTerm($request);

        $students = collect();
        if ($schoolClass) {
            $students = $schoolClass->activeEnrollments()
                ->with(['student.primaryGuardian'])
                ->orderBy('roll_number')
                ->get()
                ->pluck('student');
        }

        $requestedStudentId = (int) $request->query('student', 0);
        $student = null;
        if ($requestedStudentId > 0) {
            $student = $students->firstWhere('id', $requestedStudentId);
            abort_unless($student !== null, 403);
        } else {
            $student = $students->first();
        }

        $report = null;
        if ($schoolClass && $student) {
            $report = GradeReport::for($student, $schoolClass, $term, $year);
        }

        return view('grades.report', [
            'classes' => $classes,
            'schoolClass' => $schoolClass,
            'students' => $students,
            'student' => $student,
            'term' => $term,
            'terms' => $terms,
            'academicYear' => $year,
            'report' => $report,
            'canGenerateReports' => true,
        ]);
    }

    public function print(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $year = AcademicYear::current();

        // Read query string explicitly (print opens in a new tab via GET).
        $payload = [
            'class' => $request->query('class', $request->input('class')),
            'student' => $request->query('student', $request->input('student')),
            'term' => $request->query('term', $request->input('term')),
        ];

        try {
            $data = validator($payload, [
                'class' => ['required', 'integer', 'exists:classes,id'],
                'student' => ['required', 'integer', 'exists:students,id'],
                'term' => ['required', Rule::enum(AcademicTerm::class)],
            ])->validate();
        } catch (ValidationException $e) {
            return redirect()
                ->route('grades.report', array_filter([
                    'class' => $payload['class'] ?: null,
                    'student' => $payload['student'] ?: null,
                    'term' => is_string($payload['term'] ?? null) ? $payload['term'] : null,
                ]))
                ->withErrors($e->errors());
        }

        $schoolClass = SchoolClass::query()->findOrFail($data['class']);
        abort_unless($user->canGenerateGradeReport($schoolClass), 403);

        $student = Student::query()->with('primaryGuardian')->findOrFail($data['student']);

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->first();
        abort_unless($enrollment !== null, 404);

        $term = AcademicTerm::from($data['term']);
        $report = GradeReport::for($student, $schoolClass, $term, $year);

        return view('grades.print', [
            'schoolClass' => $schoolClass,
            'student' => $student,
            'enrollment' => $enrollment,
            'term' => $term,
            'academicYear' => $year,
            'report' => $report,
            'issuedAt' => now(),
        ]);
    }

    /**
     * Subjects the actor may grade for this class.
     *
     * @param  \Illuminate\Support\Collection<int, Subject>  $allSubjects
     * @return \Illuminate\Support\Collection<int, Subject>
     */
    private function gradableSubjects($user, SchoolClass $schoolClass, $allSubjects)
    {
        if ($user->isAdmin() || $user->hasPermission('classes.manage')) {
            return $allSubjects;
        }

        if ($user->isHomeroomTeacherOf($schoolClass)) {
            return $allSubjects;
        }

        $ids = $user->taughtSubjectIdsForClass($schoolClass);

        return $allSubjects->whereIn('id', $ids ?: [0])->values();
    }

    /**
     * Classes for marks entry: admins all; staff see timetable + Form Master classes.
     *
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function accessibleClasses($user, string $year)
    {
        $query = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->orderBy('form_level')
            ->orderBy('section');

        if (! $user->isAdmin() && ! $user->hasPermission('classes.manage')) {
            $ids = array_values(array_unique(array_merge(
                $user->taughtClassIds($year),
                $user->homeroomClassIds($year),
            )));

            if ($user->staff_id || $user->isTeacher()) {
                $query->whereIn('id', $ids ?: [0]);
            }
        }

        return $query->get();
    }

    /**
     * Classes available for student report cards (admins: all; Form Masters: their classes).
     *
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function reportAccessibleClasses($user, string $year)
    {
        $query = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->orderBy('form_level')
            ->orderBy('section');

        if (! $user->isAdmin() && ! $user->hasPermission('classes.manage')) {
            $ids = $user->homeroomClassIds($year);
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SchoolClass>  $classes
     */
    private function resolveClass(Request $request, $classes): ?SchoolClass
    {
        $requestedClassId = (int) $request->query('class', 0);
        if ($requestedClassId > 0) {
            $schoolClass = $classes->firstWhere('id', $requestedClassId);
            abort_unless($schoolClass !== null, 403);

            return $schoolClass;
        }

        return $classes->first();
    }

    /**
     * Pick the requested subject when the actor may grade it; otherwise the first allowed one.
     * Avoids 403 when switching class while an old subject id is still in the query string.
     *
     * @param  \Illuminate\Support\Collection<int, Subject>  $subjects
     */
    private function resolveSubject(Request $request, $subjects): ?Subject
    {
        $requestedId = (int) $request->query('subject', 0);
        if ($requestedId > 0) {
            $match = $subjects->firstWhere('id', $requestedId);
            if ($match !== null) {
                return $match;
            }
        }

        return $subjects->first();
    }

    private function resolveTerm(Request $request): AcademicTerm
    {
        $raw = (string) $request->query('term', AcademicTerm::Term2->value);

        return AcademicTerm::tryFrom($raw) ?? AcademicTerm::Term2;
    }
}
