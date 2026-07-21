@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Fee Collection '.$billingMonth->format('F Y'))
@section('back_url', route('finance.fee-collection', request()->query()))
@section('doc_pill', 'Fee Collection Sheet')
@section('meta')
    {{ $billingMonth->format('F Y') }} · AY {{ $academicYear }}
    @if ($classId > 0)
        · {{ $classes->firstWhere('id', $classId)?->displayName() ?? 'Class' }}
    @endif
@endsection
@section('counts')
    Due {{ \App\Support\Money::format($totals['due']) }}
    · Paid {{ \App\Support\Money::format($totals['paid']) }}
    · Balance {{ \App\Support\Money::format($totals['balance']) }}
    · {{ $invoices->count() }} invoice{{ $invoices->count() === 1 ? '' : 's' }}
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:22%">Student</th>
            <th style="width:12%">ID</th>
            <th style="width:12%">Class</th>
            <th style="width:10%" class="num">Due</th>
            <th style="width:10%" class="num">Paid</th>
            <th style="width:10%" class="num">Balance</th>
            <th style="width:12%" class="ctr">Status</th>
            <th style="width:12%">Receipt</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoices as $invoice)
            @php $last = $invoice->payments->first(); @endphp
            <tr>
                <td>{{ $invoice->student?->full_name ?? '—' }}</td>
                <td style="font-family:ui-monospace,monospace;font-size:10px;">{{ $invoice->student?->student_code ?? '—' }}</td>
                <td>{{ $invoice->schoolClass?->displayName() ?? '—' }}</td>
                <td class="num">{{ \App\Support\Money::format($invoice->amount_due) }}</td>
                <td class="num">{{ \App\Support\Money::format($invoice->amount_paid) }}</td>
                <td class="num">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                <td class="ctr">{{ $invoice->status->label() }}</td>
                <td style="font-size:10px;">{{ $last?->receipt_number ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No invoices for this month.</td></tr>
        @endforelse
    </tbody>
    @if ($invoices->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3">Totals</td>
                <td class="num">{{ \App\Support\Money::format($totals['due']) }}</td>
                <td class="num">{{ \App\Support\Money::format($totals['paid']) }}</td>
                <td class="num">{{ \App\Support\Money::format($totals['balance']) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>
@endsection
