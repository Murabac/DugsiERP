<?php

namespace App\Support;

use App\Enums\ClassStatus;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use Illuminate\Support\Carbon;

/**
 * Form-level student counts + fee amounts for one billing month.
 */
class FeeStudentsByFormReport
{
    /**
     * @param  string  $billingMonth  Y-m or Y-m-d (normalized to month start)
     * @return array{
     *     rows: list<array{
     *         form_level: int,
     *         label: string,
     *         total: int,
     *         paid: int,
     *         partial: int,
     *         unpaid: int,
     *         total_amount: float,
     *         paid_amount: float,
     *         partial_amount: float,
     *         unpaid_amount: float
     *     }>,
     *     totals: array{
     *         total: int,
     *         paid: int,
     *         partial: int,
     *         unpaid: int,
     *         total_amount: float,
     *         paid_amount: float,
     *         partial_amount: float,
     *         unpaid_amount: float
     *     }
     * }
     */
    public static function rows(string $academicYear, string $billingMonth, string $lang = 'so'): array
    {
        $month = Carbon::parse($billingMonth)->startOfMonth();
        $lang = $lang === 'en' ? 'en' : 'so';
        $formPrefix = self::labels()[$lang]['form'];

        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->get(['id', 'form_level']);

        $classForm = $classes->mapWithKeys(fn (SchoolClass $c) => [(int) $c->id => (int) $c->form_level]);

        $enrollments = Enrollment::query()
            ->where('academic_year', $academicYear)
            ->where('status', StudentStatus::Active)
            ->whereIn('class_id', $classes->pluck('id'))
            ->get(['student_id', 'class_id']);

        $studentForm = [];
        foreach ($enrollments as $enrollment) {
            $form = $classForm[(int) $enrollment->class_id] ?? null;
            if ($form === null || $form < 1 || $form > 4) {
                continue;
            }
            $studentForm[(int) $enrollment->student_id] = $form;
        }

        $invoices = Invoice::query()
            ->where('academic_year', $academicYear)
            ->whereDate('billing_month', $month->toDateString())
            ->whereIn('student_id', array_keys($studentForm) ?: [0])
            ->get(['student_id', 'status', 'amount_due', 'amount_paid']);

        /** @var array<int, list<array{status: InvoiceStatus, due: float, paid: float}>> $invoiceRowsByStudent */
        $invoiceRowsByStudent = [];
        foreach ($invoices as $invoice) {
            $sid = (int) $invoice->student_id;
            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status
                : InvoiceStatus::from((string) $invoice->status);
            $invoiceRowsByStudent[$sid][] = [
                'status' => $status,
                'due' => (float) $invoice->amount_due,
                'paid' => (float) $invoice->amount_paid,
            ];
        }

        $emptyBucket = [
            'total' => 0,
            'paid' => 0,
            'partial' => 0,
            'unpaid' => 0,
            'total_amount' => 0.0,
            'paid_amount' => 0.0,
            'partial_amount' => 0.0,
            'unpaid_amount' => 0.0,
        ];
        $buckets = [];
        for ($form = 1; $form <= 4; $form++) {
            $buckets[$form] = $emptyBucket;
        }

        foreach ($studentForm as $studentId => $form) {
            $buckets[$form]['total']++;

            $invoiceRows = $invoiceRowsByStudent[$studentId] ?? [];
            if ($invoiceRows === []) {
                continue;
            }

            $due = Money::round(array_sum(array_column($invoiceRows, 'due')));
            $paidAmt = Money::round(array_sum(array_column($invoiceRows, 'paid')));
            $buckets[$form]['total_amount'] = Money::round($buckets[$form]['total_amount'] + $due);

            $statuses = array_column($invoiceRows, 'status');
            $allPaid = collect($statuses)->every(fn (InvoiceStatus $s) => $s === InvoiceStatus::Paid);
            $allUnpaid = collect($statuses)->every(fn (InvoiceStatus $s) => $s === InvoiceStatus::Unpaid);

            if ($allPaid) {
                $buckets[$form]['paid']++;
                $buckets[$form]['paid_amount'] = Money::round($buckets[$form]['paid_amount'] + $paidAmt);
            } elseif ($allUnpaid) {
                $buckets[$form]['unpaid']++;
                $buckets[$form]['unpaid_amount'] = Money::round($buckets[$form]['unpaid_amount'] + $due);
            } else {
                $buckets[$form]['partial']++;
                $buckets[$form]['partial_amount'] = Money::round($buckets[$form]['partial_amount'] + $paidAmt);
            }
        }

        $rows = [];
        $totals = $emptyBucket;
        for ($form = 1; $form <= 4; $form++) {
            $row = [
                'form_level' => $form,
                'label' => $formPrefix.' '.$form,
                'total' => $buckets[$form]['total'],
                'paid' => $buckets[$form]['paid'],
                'partial' => $buckets[$form]['partial'],
                'unpaid' => $buckets[$form]['unpaid'],
                'total_amount' => Money::round($buckets[$form]['total_amount']),
                'paid_amount' => Money::round($buckets[$form]['paid_amount']),
                'partial_amount' => Money::round($buckets[$form]['partial_amount']),
                'unpaid_amount' => Money::round($buckets[$form]['unpaid_amount']),
            ];
            $rows[] = $row;
            foreach (['total', 'paid', 'partial', 'unpaid'] as $key) {
                $totals[$key] += $row[$key];
                $totals[$key.'_amount'] = Money::round($totals[$key.'_amount'] + $row[$key.'_amount']);
            }
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'summary' => self::buildSummary($totals, $lang),
        ];
    }

    /**
     * Simple calculated breakdown for the info table under the main grid.
     *
     * @param  array{
     *     total: int,
     *     paid: int,
     *     partial: int,
     *     unpaid: int,
     *     total_amount: float,
     *     paid_amount: float,
     *     partial_amount: float,
     *     unpaid_amount: float
     * }  $totals
     * @return list<array{key: string, label: string, students: int|null, amount: float|null, emphasize?: bool}>
     */
    public static function buildSummary(array $totals, string $lang = 'so'): array
    {
        $lang = $lang === 'en' ? 'en' : 'so';
        $labels = self::labels()[$lang];
        $withInvoice = $totals['paid'] + $totals['partial'] + $totals['unpaid'];
        $noInvoice = max(0, $totals['total'] - $withInvoice);
        $collected = Money::round($totals['paid_amount'] + $totals['partial_amount']);
        $outstanding = Money::round(max(0, $totals['total_amount'] - $collected));

        return [
            [
                'key' => 'students',
                'label' => $labels['info_students'],
                'students' => $totals['total'],
                'amount' => null,
            ],
            [
                'key' => 'paid',
                'label' => $labels['info_paid'],
                'students' => $totals['paid'],
                'amount' => $totals['paid_amount'],
            ],
            [
                'key' => 'partial',
                'label' => $labels['info_partial'],
                'students' => $totals['partial'],
                'amount' => $totals['partial_amount'],
            ],
            [
                'key' => 'unpaid',
                'label' => $labels['info_unpaid'],
                'students' => $totals['unpaid'],
                'amount' => $totals['unpaid_amount'],
            ],
            [
                'key' => 'no_invoice',
                'label' => $labels['info_no_invoice'],
                'students' => $noInvoice,
                'amount' => null,
            ],
            [
                'key' => 'due',
                'label' => $labels['info_due'],
                'students' => null,
                'amount' => $totals['total_amount'],
            ],
            [
                'key' => 'collected',
                'label' => $labels['info_collected'],
                'students' => null,
                'amount' => $collected,
            ],
            [
                'key' => 'outstanding',
                'label' => $labels['info_outstanding'],
                'students' => null,
                'amount' => $outstanding,
                'emphasize' => true,
            ],
        ];
    }

    /**
     * @return array{so: array<string, string>, en: array<string, string>}
     */
    public static function labels(): array
    {
        return [
            'so' => [
                'page_title' => 'Tirada ardayda fasal kasta',
                'section' => 'Qaybta 1: Tirada ardayda fasal kasta (lacagaha)',
                'info_section' => 'Xog kooban — xisaabinta warbixinta',
                'class' => 'Fasalka',
                'form' => 'Fasalka',
                'total' => 'Tirada Guud',
                'paid' => 'Ardayda bixisay',
                'partial' => 'Ardayda qayb bixiyay',
                'unpaid' => 'Ardayda aan bixin',
                'grand_total' => 'WADARTA',
                'month' => 'Bisha',
                'apply' => 'Sifee',
                'print' => 'Daabac',
                'back' => 'Warbixinnada lacagaha',
                'sub' => 'Ardayda fasalka oo dhan · bixiyay / qayb / aan bixin (hal bil)',
                'note' => 'Tirada kore · lacagta hoose. Paid/partial = lacagta la bixiyey; unpaid = lacagta la leeyahay. Ardayda aan invoice lahayn waa tirada guud kaliya.',
                'info_col_item' => 'Qaybta',
                'info_col_students' => 'Ardayda',
                'info_col_amount' => 'Lacagta',
                'info_students' => 'Wadarta ardayda',
                'info_paid' => 'Kuwa bixiyay (dhammaystiran)',
                'info_partial' => 'Kuwa qayb bixiyay (la soo ururiyey)',
                'info_unpaid' => 'Kuwa aan bixin (la leeyahay)',
                'info_no_invoice' => 'Aan invoice lahayn bishan',
                'info_due' => 'Wadarta lacagta la rabay',
                'info_collected' => 'Wadarta lacagta la soo ururiyey',
                'info_outstanding' => 'Haray / outstanding',
            ],
            'en' => [
                'page_title' => 'Students by class',
                'section' => 'Section 1: Student counts by class (fees)',
                'info_section' => 'Summary — report calculation',
                'class' => 'Class',
                'form' => 'Form',
                'total' => 'Total students',
                'paid' => 'Students who paid',
                'partial' => 'Students with partial payment',
                'unpaid' => 'Students who did not pay',
                'grand_total' => 'TOTAL',
                'month' => 'Month',
                'apply' => 'Apply',
                'print' => 'Print',
                'back' => 'Fee reports',
                'sub' => 'All sections combined · paid / partial / unpaid for one billing month',
                'note' => 'Count on top · money underneath. Paid/partial = amount collected; unpaid = amount due. Students with no invoice count in total only.',
                'info_col_item' => 'Item',
                'info_col_students' => 'Students',
                'info_col_amount' => 'Amount',
                'info_students' => 'Total students',
                'info_paid' => 'Fully paid (collected)',
                'info_partial' => 'Partial (collected so far)',
                'info_unpaid' => 'Unpaid (still due)',
                'info_no_invoice' => 'No invoice this month',
                'info_due' => 'Total amount due',
                'info_collected' => 'Total collected',
                'info_outstanding' => 'Outstanding balance',
            ],
        ];
    }
}
