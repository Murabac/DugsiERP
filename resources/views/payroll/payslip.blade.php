<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $item->payslip_number }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #0f172a; margin: 0; padding: 24px; background: #f8fafc; }
        .sheet { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; padding: 28px 32px; }
        .center { text-align: center; }
        .brand { font-size: 18px; font-weight: 700; color: #1e3a6e; }
        .sub { font-size: 12px; color: #64748b; margin-top: 2px; }
        .pill { display: inline-block; margin-top: 10px; background: #dbeafe; color: #1e40af; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
        .meta { margin: 18px 0; font-size: 13px; }
        .meta-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .label { color: #64748b; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin: 16px 0; }
        .box-h { background: #f8fafc; padding: 8px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        .box-b { padding: 12px; font-size: 13px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .total { border-top: 1px solid #e2e8f0; margin-top: 6px; padding-top: 8px; font-weight: 700; }
        .footer { margin-top: 24px; text-align: center; font-size: 11px; color: #94a3b8; }
        .actions { max-width: 560px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions a, .actions button { padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-primary { background: #1e3a6e; color: #fff; }
        .btn-secondary { background: #fff; color: #1e3a6e; border: 1px solid #cbd5e1 !important; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } .sheet { border: 0; } }
    </style>
</head>
<body>
@php
    $salary = (float) $item->salary_usd;
    $allowance = 0.0;
    $tax = 0.0;
    $gross = $salary + $allowance;
    $net = $gross - $tax;
@endphp
<div class="actions no-print">
    <button type="button" class="btn-primary" onclick="window.print()">Print payslip</button>
    <a href="{{ route('payroll.show', $run) }}" class="btn-secondary">Back to payroll run</a>
    <a href="{{ route('modules.home') }}" class="btn-secondary">Apps</a>
</div>

<div class="sheet">
    <div class="center" style="border-bottom:1px solid #e2e8f0;padding-bottom:16px;margin-bottom:8px;">
        <div class="brand">{{ $schoolName }}</div>
        <div class="sub">{{ $schoolLetterheadSub }}</div>
        <div class="pill">PAYSLIP — {{ $run->billing_month->format('F Y') }}</div>
    </div>

    <div class="meta">
        <div class="meta-row"><span class="label">Employee</span><span>{{ $item->full_name }}</span></div>
        <div class="meta-row"><span class="label">Employee ID</span><span>{{ $item->employee_code }}</span></div>
        <div class="meta-row"><span class="label">Role</span><span>{{ $item->role_label ?? '—' }}</span></div>
        <div class="meta-row"><span class="label">Payslip No.</span><span>{{ $item->payslip_number }}</span></div>
        <div class="meta-row"><span class="label">Pay Period</span><span>{{ $run->billing_month->format('F Y') }}</span></div>
        <div class="meta-row"><span class="label">Confirmed</span><span>{{ $run->confirmed_at?->format('j F Y') ?? '—' }}</span></div>
    </div>

    <div class="box">
        <div class="box-h">Earnings</div>
        <div class="box-b">
            <div class="row"><span>Basic Salary</span><span>{{ \App\Support\Money::format($salary) }}</span></div>
            <div class="row"><span>Transport Allowance</span><span>{{ \App\Support\Money::format($allowance) }}</span></div>
            <div class="row total"><span>Gross Pay</span><span style="color:#1e3a6e;">{{ \App\Support\Money::format($gross) }}</span></div>
        </div>
        <div class="box-h" style="border-top:1px solid #e2e8f0;">Deductions</div>
        <div class="box-b">
            <div class="row"><span>Income Tax</span><span style="color:#dc2626;">{{ \App\Support\Money::format($tax) }}</span></div>
            <div class="row total" style="color:#15803d;"><span>Net Pay</span><span>{{ \App\Support\Money::format($net) }}</span></div>
        </div>
    </div>

    <div class="footer">Generated via Dugsi ERP · Keep for your records.</div>
</div>
</body>
</html>
