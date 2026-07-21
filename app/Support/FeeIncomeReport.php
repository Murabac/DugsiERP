<?php

namespace App\Support;

use App\Enums\ClassStatus;
use App\Models\Payment;
use App\Models\SchoolClass;
use Illuminate\Support\Carbon;

/**
 * Cash-basis fee income for one calendar month (Forms 1–4 secondary).
 *
 * Lines mirror a classic school income sheet (without primary / Grade 8 extras):
 * 1) Current-month student fees
 * 2) Arrears collected (prior billing months)
 * 3) Transfer-in students (previous_school set) — excluded from lines 1–2 so totals do not double-count
 */
class FeeIncomeReport
{
    /**
     * @return array{
     *     lines: list<array{key: string, label: string, amount: float}>,
     *     total: float
     * }
     */
    public static function rows(string $academicYear, string $billingMonthYm, string $lang = 'so'): array
    {
        $lang = $lang === 'en' ? 'en' : 'so';
        $labels = self::labels()[$lang];
        $monthStart = Carbon::createFromFormat('!Y-m', $billingMonthYm)->startOfMonth();
        $rangeStart = $monthStart->copy()->startOfDay();
        $rangeEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $formClassIds = SchoolClass::query()
            ->where('academic_year', $academicYear)
            ->where('status', ClassStatus::Active)
            ->whereBetween('form_level', [1, 4])
            ->pluck('id');

        $base = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->where('invoices.academic_year', $academicYear)
            ->whereIn('invoices.class_id', $formClassIds->isEmpty() ? [0] : $formClassIds)
            ->whereBetween('payments.paid_at', [$rangeStart, $rangeEnd]);

        $isTransfer = "students.previous_school IS NOT NULL AND TRIM(students.previous_school) != ''";

        $currentFees = (float) (clone $base)
            ->whereDate('invoices.billing_month', $monthStart->toDateString())
            ->whereRaw("NOT ({$isTransfer})")
            ->sum('payments.amount');

        $arrears = (float) (clone $base)
            ->whereDate('invoices.billing_month', '<', $monthStart->toDateString())
            ->whereRaw("NOT ({$isTransfer})")
            ->sum('payments.amount');

        $transferIn = (float) (clone $base)
            ->whereRaw($isTransfer)
            ->sum('payments.amount');

        $currentFees = Money::round($currentFees);
        $arrears = Money::round($arrears);
        $transferIn = Money::round($transferIn);
        $total = Money::round($currentFees + $arrears + $transferIn);

        $lines = [
            [
                'key' => 'current',
                'label' => $labels['line_current'],
                'amount' => $currentFees,
            ],
            [
                'key' => 'arrears',
                'label' => $labels['line_arrears'],
                'amount' => $arrears,
            ],
            [
                'key' => 'transfer',
                'label' => $labels['line_transfer'],
                'amount' => $transferIn,
            ],
        ];

        return [
            'lines' => $lines,
            'total' => $total,
        ];
    }

    /**
     * @return array{so: array<string, string>, en: array<string, string>}
     */
    public static function labels(): array
    {
        return [
            'so' => [
                'page_title' => 'Warbixinta dakhliga',
                'section' => 'Qaybta 2: Fahfaahinta dakhliga (secondary)',
                'description' => 'Sharaxaad',
                'amount' => 'Qiimaha',
                'line_current' => 'Lacagta ardayda (Forms 1–4)',
                'line_arrears' => 'Bishii hore — ardayda aan bixin (la soo ururiyey)',
                'line_transfer' => 'Lacagta ardayda kadhaafka / ka soo wareegay',
                'grand_total' => 'WADARTA DAKHLIGA',
                'month' => 'Bisha',
                'apply' => 'Sifee',
                'print' => 'Daabac',
                'back' => 'Warbixinnada lacagaha',
                'sub' => 'Lacagta la soo ururiyey bishan · Forms 1–4',
                'note' => 'Kadhaafka = arday leh dugsi hore (previous school). Ma jirto lacag Grade 8 / Khamiis.',
            ],
            'en' => [
                'page_title' => 'Income report',
                'section' => 'Section 2: Income details (secondary)',
                'description' => 'Description',
                'amount' => 'Amount',
                'line_current' => 'Student fees (Forms 1–4)',
                'line_arrears' => 'Prior months — unpaid collected this month',
                'line_transfer' => 'Transfer-in / late-join student fees',
                'grand_total' => 'TOTAL INCOME',
                'month' => 'Month',
                'apply' => 'Apply',
                'print' => 'Print',
                'back' => 'Fee reports',
                'sub' => 'Cash collected this month · Forms 1–4',
                'note' => 'Transfer-in uses students with a previous school on file. No Grade 8 / Thursday special fees.',
            ],
        ];
    }
}
