@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Payroll '.$run->billing_month->format('F Y'))
@section('back_url', route('payroll.show', $run))
@section('doc_pill', 'Payroll Run Summary')
@section('meta')
    {{ $run->billing_month->format('F Y') }} · {{ $run->status->label() }} · AY {{ $academicYear }}
@endsection
@section('counts')
    {{ $run->staff_count }} staff · Total {{ \App\Support\Money::format($run->total_amount) }}
    @if ($run->confirmed_at)
        · Confirmed {{ $run->confirmed_at->format('j M Y') }} by {{ $run->confirmedBy?->name ?? '—' }}
    @endif
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:12%">Payslip</th>
            <th style="width:14%">Employee</th>
            <th style="width:28%">Name</th>
            <th style="width:18%">Role</th>
            <th style="width:14%" class="num">Salary</th>
            <th style="width:14%" class="ctr">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($run->items as $item)
            <tr>
                <td style="font-family:ui-monospace,monospace;font-size:10px;">{{ $item->payslip_number }}</td>
                <td style="font-family:ui-monospace,monospace;font-size:10px;">{{ $item->employee_code }}</td>
                <td>{{ $item->full_name }}</td>
                <td>{{ is_object($item->role_label ?? null) ? $item->role_label->label() : \Illuminate\Support\Str::headline((string) ($item->role_label ?? '—')) }}</td>
                <td class="num">{{ \App\Support\Money::format($item->salary_usd) }}</td>
                <td class="ctr">Paid</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">Total</td>
            <td class="num">{{ \App\Support\Money::format($run->total_amount) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endsection
