<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocator
{
    /**
     * Record a payment against one invoice, or allocate across the student's unpaid
     * invoices oldest-first (same receipt number for the whole batch).
     *
     * @return Payment The first payment row — use for receipt redirect
     */
    public static function record(
        Invoice $anchorInvoice,
        float $amount,
        PaymentMethod $method,
        User $recorder,
        bool $allocateArrears = true,
        ?Carbon $paidAt = null,
        ?string $notes = null,
    ): Payment {
        $amount = Money::round($amount);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $paidAt ??= now();

        return DB::transaction(function () use ($anchorInvoice, $amount, $method, $recorder, $allocateArrears, $paidAt, $notes) {
            $anchor = Invoice::query()
                ->whereKey($anchorInvoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($anchor->academic_year === AcademicYear::current(), 404);

            $targets = self::lockTargets($anchor, $allocateArrears);
            $totalOpen = Money::round($targets->sum(fn (Invoice $i) => $i->balance()));

            if ($amount > $totalOpen + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount cannot exceed the open balance of '.Money::format($totalOpen)
                        .($allocateArrears && $targets->count() > 1
                            ? ' across unpaid months (oldest first).'
                            : ' for this invoice.'),
                ]);
            }

            $receiptNumber = DocumentNumbers::nextReceiptNumber($paidAt);
            $remaining = $amount;
            $firstPayment = null;

            foreach ($targets as $invoice) {
                if ($remaining <= 0.001) {
                    break;
                }

                $apply = Money::round(min($remaining, $invoice->balance()));
                if ($apply <= 0) {
                    continue;
                }

                $payment = Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'amount' => $apply,
                    'method' => $method,
                    'receipt_number' => $receiptNumber,
                    'paid_at' => $paidAt,
                    'recorded_by' => $recorder->id,
                    'notes' => $notes,
                ]);

                $invoice->refreshStatusFromPayments();
                $remaining = Money::round($remaining - $apply);
                $firstPayment ??= $payment;
            }

            if (! $firstPayment) {
                throw ValidationException::withMessages([
                    'amount' => 'No open invoice balance to apply this payment to.',
                ]);
            }

            return $firstPayment;
        });
    }

    /**
     * @return Collection<int, Invoice>
     */
    private static function lockTargets(Invoice $anchor, bool $allocateArrears): Collection
    {
        if (! $allocateArrears) {
            return collect([$anchor])->filter(fn (Invoice $i) => $i->balance() > 0.001)->values();
        }

        return Invoice::query()
            ->where('student_id', $anchor->student_id)
            ->where('academic_year', $anchor->academic_year)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->orderBy('billing_month')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (Invoice $i) => $i->balance() > 0.001)
            ->values();
    }

    public static function studentOpenBalance(int $studentId, ?string $academicYear = null): float
    {
        $year = $academicYear ?? AcademicYear::current();

        return Money::round(
            Invoice::query()
                ->where('student_id', $studentId)
                ->where('academic_year', $year)
                ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
                ->get()
                ->sum(fn (Invoice $i) => $i->balance())
        );
    }

    /**
     * @return list<array{month: string, balance: float, invoice_number: string}>
     */
    public static function studentOpenInvoiceSummaries(int $studentId, ?string $academicYear = null): array
    {
        $year = $academicYear ?? AcademicYear::current();

        return Invoice::query()
            ->where('student_id', $studentId)
            ->where('academic_year', $year)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->orderBy('billing_month')
            ->get()
            ->filter(fn (Invoice $i) => $i->balance() > 0.001)
            ->map(fn (Invoice $i) => [
                'month' => $i->billing_month->format('M Y'),
                'balance' => $i->balance(),
                'invoice_number' => $i->invoice_number,
            ])
            ->values()
            ->all();
    }
}
