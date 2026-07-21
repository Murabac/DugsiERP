<?php

namespace App\Http\Controllers;

use App\Enums\AcademicTerm;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\LetterGrade;
use App\Enums\StudentStatus;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\PayrollRun;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\FeeCollectionReport;
use App\Support\FeeExpensesReport;
use App\Support\FeeIncomeReport;
use App\Support\FeeMonthlyCloseReport;
use App\Support\FeeNetIncomeReport;
use App\Support\FeeStudentsByFormReport;
use App\Support\GradeScale;
use App\Support\Money;
use App\Support\PayrollGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $cards = $this->availableCards($user);

        return view('reports.index', [
            'cards' => $cards,
            'academicYear' => AcademicYear::current(),
        ]);
    }

    public function attendance(Request $request): View
    {
        $this->authorizeAdminReports($request->user());

        $year = AcademicYear::current();
        $classes = $this->activeClasses($year);
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());
        $classId = (int) $request->query('class', 0);
        $studentId = (int) $request->query('student', 0);
        $applied = $request->boolean('apply') || $request->has('from') || $request->has('class');

        $schoolClass = $classId > 0 ? $classes->firstWhere('id', $classId) : null;
        $students = collect();
        if ($schoolClass) {
            $students = Student::query()
                ->whereHas('enrollments', fn ($q) => $q
                    ->where('class_id', $schoolClass->id)
                    ->where('academic_year', $year)
                    ->where('status', StudentStatus::Active))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'student_code']);
        }

        $rows = collect();
        $stats = null;
        $chart = null;

        if ($applied) {
            $query = AttendanceRecord::query()
                ->with(['student', 'schoolClass'])
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to);

            if ($schoolClass) {
                $query->where('class_id', $schoolClass->id);
            } else {
                $query->whereIn('class_id', $classes->pluck('id'));
            }
            if ($studentId > 0) {
                $query->where('student_id', $studentId);
            }

            $records = $query->get();
            $byStudent = $records->groupBy('student_id');

            $rows = $byStudent->map(function ($group) {
                $student = $group->first()->student;
                $present = $group->where('status', AttendanceStatus::Present)->count();
                $late = $group->where('status', AttendanceStatus::Late)->count();
                $absent = $group->where('status', AttendanceStatus::Absent)->count();
                $suspended = $group->where('status', AttendanceStatus::Suspended)->count();
                $total = $group->count();
                $rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : null;

                return [
                    'student' => $student,
                    'class' => $group->first()->schoolClass?->displayName(),
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'suspended' => $suspended,
                    'total' => $total,
                    'rate' => $rate,
                ];
            })->sortBy(fn ($r) => $r['student']?->full_name ?? '')->values();

            $totalMarks = $records->count();
            $presentish = $records->filter(fn ($r) => in_array($r->status, [AttendanceStatus::Present, AttendanceStatus::Late], true))->count();
            $stats = [
                'rate' => $totalMarks > 0 ? round(($presentish / $totalMarks) * 100, 1) : null,
                'present' => $records->where('status', AttendanceStatus::Present)->count(),
                'late' => $records->where('status', AttendanceStatus::Late)->count(),
                'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                'students' => $rows->count(),
            ];

            $byDay = $records->groupBy(fn ($r) => $r->date->format('Y-m-d'))->sortKeys();
            $chart = [
                'type' => 'bar',
                'legend' => true,
                'labels' => $byDay->keys()->map(fn ($d) => Carbon::parse($d)->format('j M'))->values()->all(),
                'datasets' => [
                    [
                        'label' => 'Present+Late',
                        'data' => $byDay->map(fn ($g) => $g->filter(fn ($r) => in_array($r->status, [AttendanceStatus::Present, AttendanceStatus::Late], true))->count())->values()->all(),
                        'backgroundColor' => '#1e3a6e',
                    ],
                    [
                        'label' => 'Absent',
                        'data' => $byDay->map(fn ($g) => $g->where('status', AttendanceStatus::Absent)->count())->values()->all(),
                        'backgroundColor' => '#ef4444',
                    ],
                ],
            ];
        }

        if ($this->wantsPrint($request)) {
            abort_unless($applied, 404);

            return view('reports.print.attendance', [
                'academicYear' => $year,
                'from' => $from,
                'to' => $to,
                'schoolClass' => $schoolClass,
                'rows' => $rows,
                'stats' => $stats,
            ]);
        }

        return view('reports.attendance', [
            'academicYear' => $year,
            'classes' => $classes,
            'students' => $students,
            'schoolClass' => $schoolClass,
            'studentId' => $studentId,
            'from' => $from,
            'to' => $to,
            'applied' => $applied,
            'rows' => $rows,
            'stats' => $stats,
            'chart' => $chart,
        ]);
    }

    public function academic(Request $request): View
    {
        $this->authorizeAdminReports($request->user());

        $year = AcademicYear::current();
        $classes = $this->activeClasses($year);
        $subjects = Subject::query()->orderBy('sort_order')->get();
        $terms = AcademicTerm::options();

        $classId = (int) $request->query('class', $classes->first()?->id ?? 0);
        $subjectId = (int) $request->query('subject', 0);
        $applied = $request->boolean('apply')
            || $request->has('class')
            || $request->has('term')
            || $request->has('terms')
            || $request->boolean('terms_submitted');

        $termsError = null;
        if ($request->boolean('terms_submitted') || ($request->boolean('apply') && ($request->has('terms') || $request->has('term')))) {
            $selectedTerms = $this->resolveAcademicTerms($request, requireSelection: true);
            if ($selectedTerms === []) {
                $termsError = 'Select at least one term.';
                $selectedTerms = [];
            }
        } else {
            $selectedTerms = $this->resolveAcademicTerms($request, requireSelection: false);
        }

        $schoolClass = $classes->firstWhere('id', $classId);
        $subject = $subjectId > 0 ? $subjects->firstWhere('id', $subjectId) : null;
        $combined = count($selectedTerms) > 1;
        $termLabel = collect($selectedTerms)->map(fn (AcademicTerm $t) => $t->label())->implode(' + ');

        $rows = collect();
        $stats = null;
        $chart = null;

        if ($applied && $schoolClass && $selectedTerms !== [] && $termsError === null) {
            $termValues = array_map(fn (AcademicTerm $t) => $t->value, $selectedTerms);

            $activeStudentIds = Enrollment::query()
                ->where('class_id', $schoolClass->id)
                ->where('academic_year', $year)
                ->where('status', StudentStatus::Active)
                ->pluck('student_id');

            $gradeQuery = Grade::query()
                ->with(['student', 'subject'])
                ->where('class_id', $schoolClass->id)
                ->whereIn('term', $termValues)
                ->where('academic_year', $year)
                ->whereIn('student_id', $activeStudentIds->isNotEmpty() ? $activeStudentIds : [0]);

            if ($subject) {
                $gradeQuery->where('subject_id', $subject->id);
            }

            $grades = $gradeQuery->get();
            $byStudent = $grades->groupBy('student_id');

            $rows = $byStudent->map(function ($group) use ($selectedTerms, $subject, $combined) {
                $student = $group->first()->student;
                $termScores = [];

                foreach ($selectedTerms as $term) {
                    $termGrades = $group->filter(
                        fn (Grade $g) => $g->term === $term && $g->score_percent !== null
                    );

                    if ($subject) {
                        $termScores[$term->value] = $termGrades->isNotEmpty()
                            ? round((float) $termGrades->first()->score_percent, 1)
                            : null;
                    } else {
                        $termScores[$term->value] = $termGrades->isNotEmpty()
                            ? round((float) $termGrades->avg('score_percent'), 1)
                            : null;
                    }
                }

                $available = collect($termScores)->filter(fn ($s) => $s !== null);
                $combinedScore = $available->isNotEmpty() ? round((float) $available->avg(), 1) : null;

                $scope = $subject
                    ? $subject->name.($combined ? ' · combined' : '')
                    : 'All subjects'.($combined ? ' · combined' : ' ('.$group->filter(fn ($g) => $g->score_percent !== null)->count().')');

                return [
                    'student' => $student,
                    'subject' => $scope,
                    'term_scores' => $termScores,
                    'score' => $combinedScore,
                    'letter' => $combinedScore !== null ? GradeScale::letterFor($combinedScore) : null,
                ];
            })->sortByDesc(fn ($r) => $r['score'] ?? -1)->values();

            $scored = $rows->filter(fn ($r) => $r['score'] !== null);
            $avg = $scored->isNotEmpty() ? round($scored->avg('score'), 1) : null;
            $passMark = 40;
            $passCount = $scored->filter(fn ($r) => $r['score'] >= $passMark)->count();

            $stats = [
                'average' => $avg,
                'average_letter' => $avg !== null ? GradeScale::letterFor($avg) : null,
                'students' => $rows->count(),
                'pass_rate' => $scored->isNotEmpty() ? round(($passCount / $scored->count()) * 100, 1) : null,
            ];

            $dist = collect(LetterGrade::cases())->mapWithKeys(fn (LetterGrade $l) => [
                $l->value => $scored->filter(fn ($r) => $r['letter'] === $l)->count(),
            ]);

            $chart = [
                'type' => 'bar',
                'legend' => false,
                'labels' => $dist->keys()->all(),
                'datasets' => [[
                    'label' => 'Students',
                    'data' => $dist->values()->all(),
                    'backgroundColor' => '#1e3a6e',
                ]],
            ];
        }

        if ($this->wantsPrint($request)) {
            abort_unless($applied && $termsError === null && $schoolClass && $selectedTerms !== [], 404);

            return view('reports.print.academic', [
                'academicYear' => $year,
                'selectedTerms' => $selectedTerms,
                'schoolClass' => $schoolClass,
                'subject' => $subject,
                'combined' => $combined,
                'termLabel' => $termLabel,
                'rows' => $rows,
                'stats' => $stats,
            ]);
        }

        return view('reports.academic', [
            'academicYear' => $year,
            'classes' => $classes,
            'subjects' => $subjects,
            'terms' => $terms,
            'selectedTerms' => $selectedTerms,
            'schoolClass' => $schoolClass,
            'subject' => $subject,
            'combined' => $combined,
            'termLabel' => $termLabel,
            'termsError' => $termsError,
            'applied' => $applied && $termsError === null,
            'rows' => $rows,
            'stats' => $stats,
            'chart' => $chart,
        ]);
    }

    /**
     * @return list<AcademicTerm>
     */
    private function resolveAcademicTerms(Request $request, bool $requireSelection = false): array
    {
        $raw = $request->query('terms', $request->query('term'));

        if ($raw === null || $raw === '' || $raw === []) {
            return $requireSelection ? [] : [AcademicTerm::Term2];
        }

        $values = is_array($raw) ? $raw : [$raw];
        $resolved = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $term = AcademicTerm::tryFrom((string) $value);
            if ($term && ! in_array($term, $resolved, true)) {
                $resolved[] = $term;
            }
        }

        if ($resolved !== []) {
            return $resolved;
        }

        return $requireSelection ? [] : [AcademicTerm::Term2];
    }

    public function feesIndex(Request $request): View|RedirectResponse
    {
        // Legacy bookmarks: old /reports/fees was the collection report (filters + CSV).
        if ($request->filled('export') || $request->filled('class') || $request->has('from') || $request->has('to')) {
            return redirect()->route('reports.fees.collection', $request->query());
        }

        return view('reports.fees.index', [
            'academicYear' => AcademicYear::current(),
            'cards' => [
                [
                    'title' => 'Monthly accounting close',
                    'description' => 'Xisaab-xidhka bil\'le — all fee sections in one report',
                    'icon' => 'file-text',
                    'route' => 'reports.fees.monthly-close',
                    'tone' => 'border-indigo-200 bg-indigo-50',
                ],
                [
                    'title' => 'Fee collection',
                    'description' => 'Collected vs outstanding by month',
                    'icon' => 'dollar-sign',
                    'route' => 'reports.fees.collection',
                    'tone' => 'border-emerald-200 bg-emerald-50',
                ],
                [
                    'title' => 'Students by form (paid / unpaid)',
                    'description' => 'Tirada ardayda fasal kasta — all sections combined',
                    'icon' => 'users',
                    'route' => 'reports.fees.students-by-form',
                    'tone' => 'border-blue-200 bg-blue-50',
                ],
                [
                    'title' => 'Income report',
                    'description' => 'Fahfaahinta dakhliga — secondary Forms 1–4',
                    'icon' => 'bar-chart',
                    'route' => 'reports.fees.income',
                    'tone' => 'border-teal-200 bg-teal-50',
                ],
                [
                    'title' => 'Expense report',
                    'description' => 'Fahfaahinta kharashaadka — payroll + expenses',
                    'icon' => 'credit-card',
                    'route' => 'reports.fees.expenses',
                    'tone' => 'border-amber-200 bg-amber-50',
                ],
                [
                    'title' => 'Net income',
                    'description' => 'Dakhliga ah — income minus expenses',
                    'icon' => 'layers',
                    'route' => 'reports.fees.net-income',
                    'tone' => 'border-violet-200 bg-violet-50',
                ],
            ],
        ]);
    }

    public function feesCollection(Request $request): View|StreamedResponse
    {
        $year = AcademicYear::current();
        $bounds = AcademicYear::feeMonthBounds();
        $classes = $this->activeClasses($year);

        $from = $this->queryDate($request, 'from', $bounds['min'].'-01');
        $to = $this->queryDate($request, 'to', now()->toDateString());
        $classId = (int) $request->query('class', 0);

        $fromYm = Carbon::parse($from)->format('Y-m');
        $toYm = Carbon::parse($to)->format('Y-m');
        $classId = $classId > 0 ? $classId : null;

        $rows = FeeCollectionReport::monthlyRows($year, $fromYm, $toYm, $classId);
        $totalDue = Money::round(array_sum(array_column($rows, 'due')));
        $totalPaid = Money::round(array_sum(array_column($rows, 'paid')));
        $totalOutstanding = Money::round(max(0, $totalDue - $totalPaid));
        $rate = Money::percentOf($totalPaid, $totalDue);

        if ($request->query('export') === 'csv') {
            return $this->feesCsv($rows, $totalDue, $totalPaid, $totalOutstanding, $rate);
        }

        $chart = [
            'type' => 'bar',
            'currency' => true,
            'legend' => true,
            'labels' => array_column($rows, 'label'),
            'datasets' => [
                [
                    'label' => 'Due',
                    'data' => array_column($rows, 'due'),
                    'backgroundColor' => '#cbd5e1',
                ],
                [
                    'label' => 'Collected',
                    'data' => array_column($rows, 'paid'),
                    'backgroundColor' => '#1e3a6e',
                ],
            ],
        ];

        if ($this->wantsPrint($request)) {
            return view('reports.print.fees', [
                'academicYear' => $year,
                'from' => $from,
                'to' => $to,
                'rows' => $rows,
                'totalDue' => $totalDue,
                'totalPaid' => $totalPaid,
                'totalOutstanding' => $totalOutstanding,
                'rate' => $rate,
            ]);
        }

        return view('reports.fees', [
            'academicYear' => $year,
            'classes' => $classes,
            'from' => $from,
            'to' => $to,
            'classId' => $classId,
            'rows' => $rows,
            'totalDue' => $totalDue,
            'totalPaid' => $totalPaid,
            'totalOutstanding' => $totalOutstanding,
            'rate' => $rate,
            'chart' => $chart,
        ]);
    }

    public function feesStudentsByForm(Request $request): View
    {
        $year = AcademicYear::current();
        $ctx = $this->feeReportMonthContext($request);
        $report = FeeStudentsByFormReport::rows($year, $ctx['month'], $ctx['lang']);
        $labels = FeeStudentsByFormReport::labels()[$ctx['lang']];

        if ($this->wantsPrint($request)) {
            return view('reports.print.fees.students-by-form', [
                'academicYear' => $year,
                'month' => $ctx['month'],
                'lang' => $ctx['lang'],
                'labels' => $labels,
                'rows' => $report['rows'],
                'totals' => $report['totals'],
                'summary' => $report['summary'],
            ]);
        }

        return view('reports.fees.students-by-form', [
            'academicYear' => $year,
            'month' => $ctx['month'],
            'monthOptions' => $ctx['monthOptions'],
            'prevMonth' => $ctx['prevMonth'],
            'nextMonth' => $ctx['nextMonth'],
            'lang' => $ctx['lang'],
            'labels' => $labels,
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'summary' => $report['summary'],
        ]);
    }

    public function feesIncome(Request $request): View
    {
        $year = AcademicYear::current();
        $ctx = $this->feeReportMonthContext($request);
        $report = FeeIncomeReport::rows($year, $ctx['month'], $ctx['lang']);
        $labels = FeeIncomeReport::labels()[$ctx['lang']];

        if ($this->wantsPrint($request)) {
            return view('reports.print.fees.income', [
                'academicYear' => $year,
                'month' => $ctx['month'],
                'lang' => $ctx['lang'],
                'labels' => $labels,
                'lines' => $report['lines'],
                'total' => $report['total'],
            ]);
        }

        return view('reports.fees.income', [
            'academicYear' => $year,
            'month' => $ctx['month'],
            'monthOptions' => $ctx['monthOptions'],
            'prevMonth' => $ctx['prevMonth'],
            'nextMonth' => $ctx['nextMonth'],
            'lang' => $ctx['lang'],
            'labels' => $labels,
            'lines' => $report['lines'],
            'total' => $report['total'],
        ]);
    }

    public function feesExpenses(Request $request): View
    {
        $year = AcademicYear::current();
        $ctx = $this->feeReportMonthContext($request);
        $report = FeeExpensesReport::rows($ctx['month'], $ctx['lang']);
        $labels = FeeExpensesReport::labels()[$ctx['lang']];

        if ($this->wantsPrint($request)) {
            return view('reports.print.fees.expenses', [
                'academicYear' => $year,
                'month' => $ctx['month'],
                'lang' => $ctx['lang'],
                'labels' => $labels,
                'lines' => $report['lines'],
                'total' => $report['total'],
            ]);
        }

        return view('reports.fees.expenses', [
            'academicYear' => $year,
            'month' => $ctx['month'],
            'monthOptions' => $ctx['monthOptions'],
            'prevMonth' => $ctx['prevMonth'],
            'nextMonth' => $ctx['nextMonth'],
            'lang' => $ctx['lang'],
            'labels' => $labels,
            'lines' => $report['lines'],
            'total' => $report['total'],
        ]);
    }

    public function feesNetIncome(Request $request): View
    {
        $year = AcademicYear::current();
        $ctx = $this->feeReportMonthContext($request);
        $report = FeeNetIncomeReport::build($year, $ctx['month'], $ctx['lang']);
        $labels = FeeNetIncomeReport::labels()[$ctx['lang']];

        if ($this->wantsPrint($request)) {
            return view('reports.print.fees.net-income', [
                'academicYear' => $year,
                'month' => $ctx['month'],
                'lang' => $ctx['lang'],
                'labels' => $labels,
                'incomeLines' => $report['income']['lines'],
                'incomeTotal' => $report['income']['total'],
                'expenseLines' => $report['expenses']['lines'],
                'expenseTotal' => $report['expenses']['total'],
                'net' => $report['net'],
            ]);
        }

        return view('reports.fees.net-income', [
            'academicYear' => $year,
            'month' => $ctx['month'],
            'monthOptions' => $ctx['monthOptions'],
            'prevMonth' => $ctx['prevMonth'],
            'nextMonth' => $ctx['nextMonth'],
            'lang' => $ctx['lang'],
            'labels' => $labels,
            'incomeLines' => $report['income']['lines'],
            'incomeTotal' => $report['income']['total'],
            'expenseLines' => $report['expenses']['lines'],
            'expenseTotal' => $report['expenses']['total'],
            'net' => $report['net'],
        ]);
    }

    public function feesMonthlyClose(Request $request): View
    {
        $year = AcademicYear::current();
        $ctx = $this->feeReportMonthContext($request);
        $report = FeeMonthlyCloseReport::build($year, $ctx['month'], $ctx['lang']);
        $labels = FeeMonthlyCloseReport::labels()[$ctx['lang']];
        $viewData = $this->monthlyCloseViewData($year, $ctx, $report, $labels);

        if ($this->wantsPrint($request)) {
            return view('reports.print.fees.monthly-close', $viewData);
        }

        return view('reports.fees.monthly-close', $viewData);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $report
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    private function monthlyCloseViewData(string $year, array $ctx, array $report, array $labels): array
    {
        return [
            'academicYear' => $year,
            'month' => $ctx['month'],
            'monthOptions' => $ctx['monthOptions'],
            'prevMonth' => $ctx['prevMonth'],
            'nextMonth' => $ctx['nextMonth'],
            'lang' => $ctx['lang'],
            'labels' => $labels,
            'studentRows' => $report['students']['rows'],
            'studentTotals' => $report['students']['totals'],
            'incomeLines' => $report['income']['lines'],
            'incomeTotal' => $report['income']['total'],
            'expenseLines' => $report['expenses']['lines'],
            'expenseTotal' => $report['expenses']['total'],
            'net' => $report['net'],
            'unpaidRows' => $report['unpaid_rows'],
            'unpaidTotals' => $report['unpaid_totals'],
            'overview' => $report['overview'],
        ];
    }

    public function payroll(Request $request): View
    {
        $year = AcademicYear::current();
        $month = (string) $request->query('month', now()->format('Y-m'));
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $month = now()->format('Y-m');
        }

        $run = PayrollRun::query()
            ->with(['items' => fn ($q) => $q->orderBy('full_name'), 'confirmedBy'])
            ->whereDate('billing_month', $month.'-01')
            ->first();

        $items = $run?->items ?? collect();
        $stats = null;
        if ($run) {
            $stats = [
                'staff' => $run->staff_count,
                'total' => (float) $run->total_amount,
                'status' => $run->status,
                'confirmed_at' => $run->confirmed_at,
            ];
        } else {
            try {
                $preview = PayrollGenerator::preview($month);
                $stats = [
                    'staff' => $preview['count'],
                    'total' => $preview['total'],
                    'status' => null,
                    'confirmed_at' => null,
                    'preview' => true,
                ];
                $items = $preview['staff']->map(fn ($s) => (object) [
                    'full_name' => $s->full_name,
                    'role_label' => $s->role_label?->label() ?? (string) $s->role_label,
                    'salary_usd' => (float) $s->fixed_salary_usd,
                    'payslip_number' => null,
                    'staff_id' => $s->id,
                    'is_preview' => true,
                ]);
            } catch (\Throwable) {
                $stats = null;
            }
        }

        if ($this->wantsPrint($request)) {
            return view('reports.print.payroll', [
                'academicYear' => $year,
                'month' => $month,
                'items' => $items,
                'stats' => $stats,
            ]);
        }

        return view('reports.payroll', [
            'academicYear' => $year,
            'month' => $month,
            'run' => $run,
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    public function enrollment(Request $request): View
    {
        $this->authorizeAdminReports($request->user());

        $year = AcademicYear::current();
        $classes = $this->activeClasses($year);
        $formFilter = (int) $request->query('form', 0);
        $statusFilter = (string) $request->query('status', '');

        $enrollments = Enrollment::query()
            ->with('schoolClass')
            ->where('academic_year', $year)
            ->when($formFilter > 0, fn ($q) => $q->whereHas('schoolClass', fn ($c) => $c->where('form_level', $formFilter)))
            ->when(
                $statusFilter !== '' && StudentStatus::tryFrom($statusFilter),
                fn ($q) => $q->where('status', $statusFilter)
            )
            ->get();

        $byClass = $classes
            ->when($formFilter > 0, fn ($c) => $c->where('form_level', $formFilter))
            ->map(function (SchoolClass $class) use ($enrollments, $statusFilter) {
                $group = $enrollments->where('class_id', $class->id);
                if ($statusFilter !== '' && StudentStatus::tryFrom($statusFilter)) {
                    $group = $group->where('status', StudentStatus::from($statusFilter));
                }

                $counts = [];
                foreach (StudentStatus::cases() as $status) {
                    $counts[$status->value] = $group->where('status', $status)->count();
                }

                return [
                    'class' => $class,
                    'counts' => $counts,
                    'total' => $group->count(),
                ];
            })
            ->values();

        $totals = [];
        foreach (StudentStatus::cases() as $status) {
            $totals[$status->value] = $byClass->sum(fn ($r) => $r['counts'][$status->value]);
        }

        $chart = [
            'type' => 'doughnut',
            'legend' => true,
            'labels' => array_map(fn (StudentStatus $s) => $s->label(), StudentStatus::cases()),
            'datasets' => [[
                'data' => array_values($totals),
                'backgroundColor' => ['#1e3a6e', '#94a3b8', '#f59e0b', '#22c55e', '#64748b'],
            ]],
        ];

        if ($this->wantsPrint($request)) {
            return view('reports.print.enrollment', [
                'academicYear' => $year,
                'rows' => $byClass,
                'totals' => $totals,
                'formFilter' => $formFilter,
                'statusFilter' => $statusFilter,
                'statuses' => StudentStatus::cases(),
            ]);
        }

        return view('reports.enrollment', [
            'academicYear' => $year,
            'classes' => $classes,
            'rows' => $byClass,
            'totals' => $totals,
            'formFilter' => $formFilter,
            'statusFilter' => $statusFilter,
            'statuses' => StudentStatus::cases(),
            'chart' => $chart,
        ]);
    }

    /**
     * @param  list<array{key: string, label: string, full_label: string, due: float, paid: float, outstanding: float, pct: float}>  $rows
     */
    private function feesCsv(array $rows, float $due, float $paid, float $outstanding, float $rate): StreamedResponse
    {
        $filename = 'fee-collection-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $due, $paid, $outstanding, $rate) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Month', 'Total Due', 'Collected', 'Outstanding', 'Rate %']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['full_label'],
                    number_format($row['due'], 2, '.', ''),
                    number_format($row['paid'], 2, '.', ''),
                    number_format($row['outstanding'], 2, '.', ''),
                    number_format($row['pct'], 1, '.', ''),
                ]);
            }
            fputcsv($out, [
                'TOTAL',
                number_format($due, 2, '.', ''),
                number_format($paid, 2, '.', ''),
                number_format($outstanding, 2, '.', ''),
                number_format($rate, 1, '.', ''),
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeAdminReports(?User $user): void
    {
        abort_unless($user && $user->hasPermission('reports.academic'), 403);
    }

    private function wantsPrint(Request $request): bool
    {
        return $request->routeIs('*.print') || $request->boolean('print');
    }

    private function queryDate(Request $request, string $key, string $fallback): string
    {
        $raw = $request->query($key);
        if (! is_string($raw) || trim($raw) === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Shared month + language context for fee sub-reports.
     *
     * @return array{
     *     lang: string,
     *     month: string,
     *     monthOptions: list<array{value: string, label: string}>,
     *     prevMonth: ?string,
     *     nextMonth: ?string
     * }
     */
    private function feeReportMonthContext(Request $request): array
    {
        $lang = $request->query('lang', 'so') === 'en' ? 'en' : 'so';
        $bounds = AcademicYear::feeMonthBounds();
        $month = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $legacyFrom = $request->query('from');
            if (is_string($legacyFrom) && trim($legacyFrom) !== '') {
                try {
                    $month = Carbon::parse($legacyFrom)->format('Y-m');
                } catch (Throwable) {
                    $month = now()->format('Y-m');
                }
            } else {
                $month = now()->format('Y-m');
            }
        }
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $month = now()->format('Y-m');
        }
        if ($month < $bounds['min']) {
            $month = $bounds['min'];
        }
        if ($month > $bounds['max']) {
            $month = $bounds['max'];
        }

        $monthOptions = [];
        $cursor = Carbon::createFromFormat('!Y-m', $bounds['min'])->startOfMonth();
        $end = Carbon::createFromFormat('!Y-m', $bounds['max'])->startOfMonth();
        while ($cursor->lte($end)) {
            $monthOptions[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('F Y'),
            ];
            $cursor->addMonth();
        }

        $current = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $prevMonth = $current->copy()->subMonth()->format('Y-m');
        $nextMonth = $current->copy()->addMonth()->format('Y-m');

        return [
            'lang' => $lang,
            'month' => $month,
            'monthOptions' => $monthOptions,
            'prevMonth' => $prevMonth < $bounds['min'] ? null : $prevMonth,
            'nextMonth' => $nextMonth > $bounds['max'] ? null : $nextMonth,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function activeClasses(string $year)
    {
        return SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();
    }

    /**
     * @return list<array{key: string, title: string, description: string, icon: string, route: string, tone: string}>
     */
    private function availableCards(User $user): array
    {
        $all = [
            [
                'key' => 'attendance',
                'title' => 'Attendance Report',
                'description' => 'By class, date range, or student',
                'icon' => 'calendar',
                'route' => 'reports.attendance',
                'tone' => 'border-blue-200 bg-blue-50',
                'permission' => 'reports.academic',
            ],
            [
                'key' => 'academic',
                'title' => 'Academic Performance',
                'description' => 'By class, subject, or term',
                'icon' => 'graduation-cap',
                'route' => 'reports.academic',
                'tone' => 'border-indigo-200 bg-indigo-50',
                'permission' => 'reports.academic',
            ],
            [
                'key' => 'fees',
                'title' => 'Fee Reports',
                'description' => 'Collection by month and students paid/unpaid by form',
                'icon' => 'dollar-sign',
                'route' => 'reports.fees',
                'tone' => 'border-emerald-200 bg-emerald-50',
                'permission' => 'reports.finance',
            ],
            [
                'key' => 'payroll',
                'title' => 'Payroll Report',
                'description' => 'Staff salary summary by month',
                'icon' => 'users',
                'route' => 'reports.payroll',
                'tone' => 'border-amber-200 bg-amber-50',
                'permission' => 'reports.finance',
            ],
            [
                'key' => 'enrollment',
                'title' => 'Enrollment Report',
                'description' => 'Student counts by class and status',
                'icon' => 'bar-chart',
                'route' => 'reports.enrollment',
                'tone' => 'border-slate-200 bg-slate-50',
                'permission' => 'reports.academic',
            ],
        ];

        return array_values(array_filter(
            $all,
            fn (array $card) => $user->hasPermission($card['permission'])
        ));
    }
}
