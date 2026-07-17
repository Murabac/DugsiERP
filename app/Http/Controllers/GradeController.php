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
        $classAverageLetter = null;

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

            $rows = $enrollments->map(function (Enrollment $enrollment) use ($existing, $user) {
                $student = $enrollment->student;
                $grade = $existing->get($student->id);
                $scoreOld = old('scores.'.$student->id);
                $score = $scoreOld !== null
                    ? $scoreOld
                    : ($grade?->score_percent !== null ? (string) $grade->score_percent : '');

                $letter = is_numeric($score) ? GradeScale::letterFor((float) $score) : null;
                $locked = GradeEditRules::isLockedFor($user, $grade);
                $needsNote = GradeEditRules::requiresEditNote($user, $grade);
                $unlockUntil = $grade ? GradeEditRules::unlockUntil($grade) : null;

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'roll' => str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT),
                    'score' => $score,
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
                $classAverage = round($numericScores->avg(), 1);
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
            'rows' => $rows,
            'classAverage' => $classAverage,
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

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $year = AcademicYear::current();

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'term' => ['required', Rule::enum(AcademicTerm::class)],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

            if (GradeEditRules::isLockedFor($user, $existing)) {
                if ($existing) {
                    $oldScore = (string) $existing->score_percent;
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
                    $errors['scores.'.$studentId] = 'Clearing a saved grade is not allowed. Enter a score from 0–100.';
                }

                continue;
            }

            $score = round((float) $raw, 2);
            $letter = GradeScale::letterFor($score);
            if ($letter === null) {
                $errors['scores.'.$studentId] = 'Score is outside the grading scale.';

                continue;
            }

            if ($existing) {
                $scoreChanged = round((float) $existing->score_percent, 2) !== $score;
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
                    'score' => $score,
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
                    'score' => $score,
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
                        'score_percent' => $item['score'],
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

                GradeEditLog::query()->create([
                    'grade_id' => $existing->id,
                    'edited_by' => $user->id,
                    'old_score' => $existing->score_percent,
                    'new_score' => $item['score'],
                    'old_letter' => $existing->letter_grade?->value,
                    'new_letter' => $item['letter']->value,
                    'old_remarks' => $existing->remarks,
                    'new_remarks' => $item['remark'],
                    'note' => $item['note'],
                ]);

                $existing->update([
                    'score_percent' => $item['score'],
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

    public function boundaries(Request $request): View
    {
        $year = AcademicYear::current();

        return view('grades.boundaries', [
            'boundaries' => GradeBoundary::ordered(),
            'canEdit' => $request->user()->isAdmin(),
            'letters' => LetterGrade::cases(),
            'canGenerateReports' => $request->user()->canGenerateAnyGradeReport($year),
        ]);
    }

    public function updateBoundaries(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'boundaries' => ['required', 'array', 'min:1'],
            'boundaries.*.letter' => ['required', 'string', Rule::in(array_column(LetterGrade::cases(), 'value'))],
            'boundaries.*.min_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'boundaries.*.max_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'boundaries.*.remark' => ['nullable', 'string', 'max:64'],
        ]);

        GradeScale::assertContiguous($data['boundaries']);

        DB::transaction(function () use ($data) {
            $keep = [];
            foreach ($data['boundaries'] as $row) {
                $boundary = GradeBoundary::query()->updateOrCreate(
                    ['letter' => $row['letter']],
                    [
                        'min_percent' => (int) $row['min_percent'],
                        'max_percent' => (int) $row['max_percent'],
                        'remark' => trim((string) ($row['remark'] ?? '')) ?: null,
                    ]
                );
                $keep[] = $boundary->id;
            }

            GradeBoundary::query()->whereNotIn('id', $keep)->delete();
        });

        return redirect()
            ->route('grades.boundaries')
            ->with('status', 'Grade boundaries updated.');
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
        if ($user->isAdmin()) {
            return $allSubjects;
        }

        $ids = $user->taughtSubjectIdsForClass($schoolClass);

        return $allSubjects->whereIn('id', $ids ?: [0])->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function accessibleClasses($user, string $year)
    {
        $query = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->orderBy('form_level')
            ->orderBy('section');

        if ($user->isTeacher()) {
            $ids = $user->taughtClassIds($year);
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get();
    }

    /**
     * Classes available for student report cards (admins: all; teachers: headmaster only).
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

        if ($user->isTeacher()) {
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
     * @param  \Illuminate\Support\Collection<int, Subject>  $subjects
     */
    private function resolveSubject(Request $request, $subjects): ?Subject
    {
        $requestedId = (int) $request->query('subject', 0);
        if ($requestedId > 0) {
            return $subjects->firstWhere('id', $requestedId) ?? $subjects->first();
        }

        return $subjects->first();
    }

    private function resolveTerm(Request $request): AcademicTerm
    {
        $raw = (string) $request->query('term', AcademicTerm::Term2->value);

        return AcademicTerm::tryFrom($raw) ?? AcademicTerm::Term2;
    }
}
