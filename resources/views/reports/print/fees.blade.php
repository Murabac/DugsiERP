@extends('layouts.print', ['printOrientation' => 'A4 portrait'])

@section('title', 'Fee Collection Report')
@section('back_url', route('reports.fees', request()->query()))
@section('doc_pill', 'Fee Collection Report')
@section('meta')
    {{ \Illuminate\Support\Carbon::parse($from)->format('M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('M Y') }}
    · AY {{ $academicYear }}
@endsection
@section('counts')
    Due {{ \App\Support\Money::format($totalDue) }}
    · Collected {{ \App\Support\Money::format($totalPaid) }}
    · Outstanding {{ \App\Support\Money::format($totalOutstanding) }}
    · Rate {{ \App\Support\Money::formatPercent($rate) }}
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:28%">Month</th>
            <th style="width:18%" class="num">Due</th>
            <th style="width:18%" class="num">Collected</th>
            <th style="width:18%" class="num">Outstanding</th>
            <th style="width:18%" class="num">Rate</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['full_label'] ?? $row['label'] }}</td>
                <td class="num">{{ \App\Support\Money::format($row['due']) }}</td>
                <td class="num">{{ \App\Support\Money::format($row['paid']) }}</td>
                <td class="num">{{ \App\Support\Money::format($row['outstanding']) }}</td>
                <td class="num">{{ number_format($row['pct'], 1) }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No fee data for this range.</td></tr>
        @endforelse
    </tbody>
    @if (count($rows) > 0)
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ \App\Support\Money::format($totalDue) }}</td>
                <td class="num">{{ \App\Support\Money::format($totalPaid) }}</td>
                <td class="num">{{ \App\Support\Money::format($totalOutstanding) }}</td>
                <td class="num">{{ \App\Support\Money::formatPercent($rate) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
@endsection
