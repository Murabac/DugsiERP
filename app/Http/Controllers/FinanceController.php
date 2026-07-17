<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Support\AcademicYear;
use App\Support\FeeCalculator;
use App\Support\Money;
use App\Support\MonthlyInvoiceGenerator;
use App\Support\PaymentAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class FinanceController extends Controller
{
    public function dashboard(Request $request): View
    {
        $year = AcademicYear::current();
        $bounds = AcademicYear::feeMonthBounds();
        $thisMonth = now()->startOfMonth();

        $invoices = Invoice::query()
            ->where('academic_year', $year)
            ->get();

        $due = Money::round($invoices->sum(fn (Invoice $i) => (float) $i->amount_due));
        $paid = Money::round($invoices->sum(fn (Invoice $i) => (float) $i->amount_paid));
        $outstanding = Money::round(max(0, $due - $paid));
        $openCount = $invoices
            ->filter(fn (Invoice $i) => in_array($i->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true))
            ->count();
        $paidCount = $invoices->where('status', InvoiceStatus::Paid)->count();
        $collectionRate = Money::percentOf($paid, $due);

        $monthInvoices = $invoices->filter(
            fn (Invoice $i) => $i->billing_month->format('Y-m') === $thisMonth->format('Y-m')
        );
        $monthDue = Money::round($monthInvoices->sum(fn (Invoice $i) => (float) $i->amount_due));
        $monthPaid = Money::round($monthInvoices->sum(fn (Invoice $i) => (float) $i->amount_paid));

        $paymentsThisMonth = Money::round(
            Payment::query()
                ->where('paid_at', '>=', $thisMonth)
                ->whereHas('invoice', fn ($q) => $q->where('academic_year', $year))
                ->sum('amount')
        );

        $studentsInArrears = $invoices
            ->filter(fn (Invoice $i) => $i->balance() > 0.001)
            ->pluck('student_id')
            ->unique()
            ->count();

        $stats = [
            [
                'label' => 'Collected',
                'value' => Money::format($paid),
                'sub' => Money::formatPercent($collectionRate).' of '.Money::format($due).' billed · AY '.$year,
                'icon' => 'dollar-sign',
                'accent' => true,
            ],
            [
                'label' => 'Outstanding',
                'value' => Money::format($outstanding),
                'sub' => $studentsInArrears.' student'.($studentsInArrears === 1 ? '' : 's').' with open balance',
                'icon' => 'credit-card',
                'accent' => false,
            ],
            [
                'label' => 'This Month',
                'value' => Money::format($monthPaid),
                'sub' => 'Of '.Money::format($monthDue).' due · '.Money::format($paymentsThisMonth).' recorded in '.now()->format('M'),
                'icon' => 'calendar',
                'accent' => false,
            ],
            [
                'label' => 'Open Invoices',
                'value' => (string) $openCount,
                'sub' => $paidCount.' paid · '.$invoices->count().' total this year',
                'icon' => 'file-text',
                'accent' => false,
            ],
        ];

        $monthly = $this->monthlyBars($year, $bounds['min'], $bounds['max']);

        $monthlyChart = [
            'type' => 'bar',
            'currency' => true,
            'legend' => true,
            'labels' => array_column($monthly, 'label'),
            'datasets' => [
                [
                    'label' => 'Due',
                    'data' => array_column($monthly, 'due'),
                    'backgroundColor' => '#cbd5e1',
                ],
                [
                    'label' => 'Collected',
                    'data' => array_column($monthly, 'paid'),
                    'backgroundColor' => '#1e3a6e',
                ],
            ],
        ];

        $statusChart = [
            'type' => 'doughnut',
            'legend' => true,
            'labels' => ['Paid', 'Partial', 'Unpaid'],
            'datasets' => [
                [
                    'data' => [
                        $invoices->where('status', InvoiceStatus::Paid)->count(),
                        $invoices->where('status', InvoiceStatus::Partial)->count(),
                        $invoices->where('status', InvoiceStatus::Unpaid)->count(),
                    ],
                    'backgroundColor' => ['#16a34a', '#d97706', '#dc2626'],
                ],
            ],
        ];

        $recent = Invoice::query()
            ->with(['student', 'schoolClass'])
            ->where('academic_year', $year)
            ->latest('billing_month')
            ->latest('id')
            ->limit(10)
            ->get();

        $recentPayments = Payment::query()
            ->with(['student', 'invoice'])
            ->whereHas('invoice', fn ($q) => $q->where('academic_year', $year))
            ->latest('paid_at')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('finance.fees-dashboard', [
            'academicYear' => $year,
            'stats' => $stats,
            'monthly' => $monthly,
            'monthlyChart' => $monthlyChart,
            'statusChart' => $statusChart,
            'recent' => $recent,
            'recentPayments' => $recentPayments,
            'summary' => [
                'due' => $due,
                'paid' => $paid,
                'outstanding' => $outstanding,
                'collection_rate' => $collectionRate,
            ],
        ]);
    }

    public function collection(Request $request): View
    {
        $year = AcademicYear::current();
        $bounds = AcademicYear::feeMonthBounds();
        $month = $request->query('month', now()->format('Y-m'));
        try {
            $billingMonth = FeeCalculator::billingMonthStart($month.'-01');
            $month = $billingMonth->format('Y-m');
            if ($month < $bounds['min']) {
                $billingMonth = FeeCalculator::billingMonthStart($bounds['min'].'-01');
                $month = $bounds['min'];
            } elseif ($month > $bounds['max']) {
                $billingMonth = FeeCalculator::billingMonthStart($bounds['max'].'-01');
                $month = $bounds['max'];
            }
        } catch (\Throwable) {
            $billingMonth = now()->startOfMonth();
            $month = $billingMonth->format('Y-m');
        }

        $statusFilter = (string) $request->query('status', '');
        $classId = (int) $request->query('class', 0);
        $q = trim((string) $request->query('q', ''));

        $classes = SchoolClass::query()
            ->where('academic_year', $year)
            ->where('status', ClassStatus::Active)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $feeConfigured = SchoolSetting::monthlyFeeUsd() > 0;
        $isFutureMonth = $billingMonth->format('Y-m') > now()->format('Y-m');

        $query = Invoice::query()
            ->with(['student', 'schoolClass', 'payments' => fn ($q) => $q->latest('paid_at')->latest('id')])
            ->where('academic_year', $year)
            ->whereDate('billing_month', $billingMonth->toDateString());

        if ($statusFilter !== '' && InvoiceStatus::tryFrom($statusFilter)) {
            $query->where('status', $statusFilter);
        }
        if ($classId > 0) {
            $query->where('class_id', $classId);
        }
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->whereHas('student', fn ($s) => $s->where('full_name', 'like', $like)
                ->orWhere('student_code', 'like', $like));
        }

        $invoices = $query
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->orderBy('students.full_name')
            ->select('invoices.*')
            ->get();

        $studentOpen = [];
        foreach ($invoices->pluck('student_id')->unique()->filter() as $studentId) {
            $studentOpen[(int) $studentId] = [
                'total' => PaymentAllocator::studentOpenBalance((int) $studentId, $year),
                'months' => PaymentAllocator::studentOpenInvoiceSummaries((int) $studentId, $year),
            ];
        }

        $invoiceCountForMonth = Invoice::query()
            ->where('academic_year', $year)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->count();

        $prevMonth = $billingMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $billingMonth->copy()->addMonth()->format('Y-m');

        return view('finance.fee-collection', [
            'academicYear' => $year,
            'month' => $month,
            'billingMonth' => $billingMonth,
            'monthBounds' => $bounds,
            'prevMonth' => $prevMonth >= $bounds['min'] ? $prevMonth : null,
            'nextMonth' => $nextMonth <= $bounds['max'] ? $nextMonth : null,
            'classes' => $classes,
            'classId' => $classId,
            'statusFilter' => $statusFilter,
            'q' => $q,
            'invoices' => $invoices,
            'studentOpen' => $studentOpen,
            'statuses' => InvoiceStatus::options(),
            'methods' => PaymentMethod::options(),
            'feeConfigured' => $feeConfigured,
            'monthlyFee' => SchoolSetting::monthlyFeeUsd(),
            'isFutureMonth' => $isFutureMonth,
            'canEnsureMonth' => $feeConfigured && ! $isFutureMonth,
            'monthNeedsEnsure' => $feeConfigured && ! $isFutureMonth && $invoiceCountForMonth === 0,
            'totals' => [
                'due' => Money::round($invoices->sum(fn (Invoice $i) => (float) $i->amount_due)),
                'paid' => Money::round($invoices->sum(fn (Invoice $i) => (float) $i->amount_paid)),
                'balance' => Money::round($invoices->sum(fn (Invoice $i) => $i->balance())),
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $billingMonth = FeeCalculator::billingMonthStart($data['month'].'-01');

        try {
            $result = MonthlyInvoiceGenerator::generate($billingMonth);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'month' => $e->getMessage(),
            ]);
        }

        $message = 'Generated '.$result['created'].' invoice'.($result['created'] === 1 ? '' : 's').' for '.$billingMonth->format('F Y').'.';
        if ($result['skipped'] > 0) {
            $message .= ' '.$result['skipped'].' skipped (already billed).';
        }

        return redirect()
            ->route('finance.fee-collection', ['month' => $billingMonth->format('Y-m')])
            ->with('status', $message);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'allocate_arrears' => ['sometimes', 'boolean'],
        ]);

        $invoice = Invoice::query()->findOrFail($data['invoice_id']);
        $allocate = $request->boolean('allocate_arrears', true);
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

        $payment = PaymentAllocator::record(
            $invoice,
            (float) $data['amount'],
            PaymentMethod::from($data['method']),
            $request->user(),
            $allocate,
            $paidAt,
            trim((string) ($data['notes'] ?? '')) ?: null,
        );

        $batchTotal = Money::round(
            Payment::query()->where('receipt_number', $payment->receipt_number)->sum('amount')
        );

        return redirect()
            ->route('finance.payments.receipt', $payment)
            ->with('status', 'Payment of '.Money::format($batchTotal).' recorded.');
    }

    public function receipt(Payment $payment): View
    {
        $payment->load(['student.primaryGuardian', 'invoice.schoolClass', 'recordedBy']);

        abort_unless(
            $payment->invoice && $payment->invoice->academic_year === AcademicYear::current(),
            404
        );

        $batch = Payment::query()
            ->with(['invoice.schoolClass'])
            ->where('receipt_number', $payment->receipt_number)
            ->orderBy('id')
            ->get();

        return view('finance.receipt', [
            'payment' => $payment,
            'batch' => $batch,
            'batchTotal' => Money::round($batch->sum(fn (Payment $p) => (float) $p->amount)),
            'invoice' => $payment->invoice,
            'student' => $payment->student,
            'schoolName' => SchoolSetting::schoolName(),
            'schoolLetterheadSub' => SchoolSetting::schoolLetterheadSub(),
        ]);
    }

    /**
     * @return list<array{label: string, due: float, paid: float, pct: float, key: string}>
     */
    private function monthlyBars(string $year, ?string $minYm = null, ?string $maxYm = null): array
    {
        $rows = Invoice::query()
            ->where('academic_year', $year)
            ->get()
            ->groupBy(fn (Invoice $i) => $i->billing_month->format('Y-m'));

        $end = $maxYm
            ? Carbon::createFromFormat('!Y-m', $maxYm)->startOfMonth()
            : now()->startOfMonth();
        $start = $minYm
            ? Carbon::createFromFormat('!Y-m', $minYm)->startOfMonth()
            : $end->copy()->subMonths(5);

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        // Full academic-year span through the current month (Sept → now).
        $cursor = $start->copy()->startOfMonth();
        $end = $end->copy()->startOfMonth();
        $keys = [];
        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $months = collect();
        foreach ($keys as $key) {
            $m = Carbon::createFromFormat('!Y-m', $key)->startOfMonth();
            $group = $rows->get($key, collect());
            $due = Money::round($group->sum(fn (Invoice $inv) => (float) $inv->amount_due));
            $paid = Money::round($group->sum(fn (Invoice $inv) => (float) $inv->amount_paid));
            $months->push([
                'key' => $key,
                'label' => $m->format('M'),
                'due' => $due,
                'paid' => $paid,
                'pct' => Money::percentOf($paid, $due),
                'pct_label' => Money::formatPercent(Money::percentOf($paid, $due)),
            ]);
        }

        return $months->all();
    }
}
