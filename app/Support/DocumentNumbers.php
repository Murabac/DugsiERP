<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class DocumentNumbers
{
    public static function nextInvoiceNumber(Carbon $billingMonth): string
    {
        $prefix = 'INV-'.$billingMonth->format('Y-m').'-';

        $latest = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->value('invoice_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        // Include microseconds entropy when multiple creators race before first row locks.
        if ($seq === 1 && $latest === null) {
            // Still sequential from 1; callers retry on unique violation.
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function nextReceiptNumber(?Carbon $paidAt = null): string
    {
        $paidAt ??= now();
        $prefix = 'RCP-'.$paidAt->format('Ymd').'-';

        $latest = Payment::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->lockForUpdate()
            ->value('receipt_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function nextPayslipNumber(Carbon $billingMonth): string
    {
        $prefix = 'PSL-'.$billingMonth->format('Y-m').'-';

        $latest = \App\Models\PayrollItem::query()
            ->where('payslip_number', 'like', $prefix.'%')
            ->orderByDesc('payslip_number')
            ->lockForUpdate()
            ->value('payslip_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function nextDocumentNumber(?Carbon $at = null): string
    {
        $at ??= now();
        $prefix = 'DOC-'.$at->format('Ymd').'-';

        $latest = \App\Models\DocumentLog::query()
            ->where('document_number', 'like', $prefix.'%')
            ->orderByDesc('document_number')
            ->lockForUpdate()
            ->value('document_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
