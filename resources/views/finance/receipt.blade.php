<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #0f172a; margin: 0; padding: 24px; background: #f8fafc; }
        .sheet { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; padding: 28px 32px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e3a6e; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { font-size: 20px; font-weight: 700; color: #1e3a6e; }
        .sub { font-size: 12px; color: #64748b; margin-top: 2px; }
        .title { font-size: 16px; font-weight: 700; text-align: right; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; font-size: 13px; margin-bottom: 18px; }
        .label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
        .amount-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; margin: 16px 0; background: #f8fafc; }
        .amount-box .amt { font-size: 28px; font-weight: 700; color: #1e3a6e; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
        td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .footer { margin-top: 28px; text-align: center; font-size: 11px; color: #94a3b8; }
        .actions { max-width: 560px; margin: 0 auto 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .actions a, .actions button {
            padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block;
        }
        .btn-primary { background: #1e3a6e; color: #fff; }
        .btn-secondary { background: #fff; color: #1e3a6e; border: 1px solid #cbd5e1 !important; }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .sheet { border: 0; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
@php
    $batch = $batch ?? collect([$payment]);
    $batchTotal = $batchTotal ?? (float) $payment->amount;
@endphp
<div class="actions no-print">
    <button type="button" class="btn-primary" onclick="window.print()">Print receipt</button>
    <a href="{{ route('finance.fee-collection', ['month' => $invoice->billing_month->format('Y-m')]) }}" class="btn-secondary">Back to Fee Collection</a>
    <a href="{{ route('modules.home') }}" class="btn-secondary">Apps</a>
</div>

<div class="sheet">
    <div class="header" style="flex-direction:column;align-items:center;text-align:center;gap:6px;">
        <div>
            <div class="brand">{{ $schoolName }}</div>
            <div class="sub">{{ $schoolLetterheadSub }}</div>
        </div>
        <div style="display:inline-block;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;">OFFICIAL RECEIPT</div>
        <div class="sub">{{ $payment->receipt_number }}</div>
    </div>

    <div class="meta">
        <div>
            <div class="label">Student</div>
            <div>{{ $student->full_name }}</div>
        </div>
        <div>
            <div class="label">Student ID</div>
            <div>{{ $student->student_code }}</div>
        </div>
        <div>
            <div class="label">Class</div>
            <div>{{ $invoice->schoolClass?->displayName() ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Paid at</div>
            <div>{{ $payment->paid_at?->format('j F Y, H:i') }}</div>
        </div>
        <div>
            <div class="label">Method</div>
            <div>{{ $payment->method->label() }}</div>
        </div>
        <div>
            <div class="label">Recorded by</div>
            <div>{{ $payment->recordedBy?->name ?? '—' }}</div>
        </div>
    </div>

    <div class="amount-box">
        <div class="label">Amount paid</div>
        <div class="amt">{{ \App\Support\Money::format($batchTotal) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Applied to</th>
                <th>Invoice</th>
                <th style="text-align:right;">USD</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($batch as $line)
                <tr>
                    <td>{{ $line->invoice?->billing_month?->format('F Y') ?? '—' }}</td>
                    <td>{{ $line->invoice?->invoice_number ?? '—' }}</td>
                    <td style="text-align:right;">{{ \App\Support\Money::format($line->amount) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2"><strong>Total</strong></td>
                <td style="text-align:right;"><strong>{{ \App\Support\Money::format($batchTotal) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if ($payment->notes)
        <p style="margin-top:16px;font-size:12px;color:#64748b;"><strong>Notes:</strong> {{ $payment->notes }}</p>
    @endif

    <div class="footer">Thank you. Keep this receipt for your records.</div>
</div>
</body>
</html>
