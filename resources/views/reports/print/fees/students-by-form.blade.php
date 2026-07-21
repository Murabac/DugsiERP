@extends('layouts.print', ['printOrientation' => 'A4 portrait'])

@section('title', $labels['page_title'])
@section('back_url', route('reports.fees.students-by-form', request()->except('print')))
@section('doc_pill', $labels['page_title'])
@section('meta')
    {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }}
    · AY {{ $academicYear }}
@endsection
@section('counts')
    {{ $labels['total'] }} {{ $totals['total'] }} ({{ \App\Support\Money::format($totals['total_amount']) }})
    · {{ $labels['paid'] }} {{ $totals['paid'] }} ({{ \App\Support\Money::format($totals['paid_amount']) }})
    · {{ $labels['partial'] }} {{ $totals['partial'] }} ({{ \App\Support\Money::format($totals['partial_amount']) }})
    · {{ $labels['unpaid'] }} {{ $totals['unpaid'] }} ({{ \App\Support\Money::format($totals['unpaid_amount']) }})
@endsection

@section('content')
<div style="background:#1e3a6e;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">
    {{ $labels['section'] }}
</div>
<table class="data" style="border-top:0;border-radius:0 0 4px 4px;">
    <thead>
        <tr>
            <th style="width:28%">{{ $labels['class'] }}</th>
            <th style="width:18%" class="num">{{ $labels['total'] }}</th>
            <th style="width:18%" class="num">{{ $labels['paid'] }}</th>
            <th style="width:18%" class="num">{{ $labels['partial'] }}</th>
            <th style="width:18%" class="num">{{ $labels['unpaid'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="num">
                    {{ $row['total'] }}
                    <div style="font-size:11px;font-weight:500;color:#64748b;">{{ \App\Support\Money::format($row['total_amount']) }}</div>
                </td>
                <td class="num">
                    {{ $row['paid'] }}
                    <div style="font-size:11px;font-weight:500;color:#15803d;">{{ \App\Support\Money::format($row['paid_amount']) }}</div>
                </td>
                <td class="num">
                    {{ $row['partial'] }}
                    <div style="font-size:11px;font-weight:500;color:#b45309;">{{ \App\Support\Money::format($row['partial_amount']) }}</div>
                </td>
                <td class="num">
                    {{ $row['unpaid'] }}
                    <div style="font-size:11px;font-weight:500;color:#b91c1c;">{{ \App\Support\Money::format($row['unpaid_amount']) }}</div>
                </td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>{{ $labels['grand_total'] }}</td>
            <td class="num">
                {{ $totals['total'] }}
                <div style="font-size:11px;">{{ \App\Support\Money::format($totals['total_amount']) }}</div>
            </td>
            <td class="num">
                {{ $totals['paid'] }}
                <div style="font-size:11px;">{{ \App\Support\Money::format($totals['paid_amount']) }}</div>
            </td>
            <td class="num">
                {{ $totals['partial'] }}
                <div style="font-size:11px;">{{ \App\Support\Money::format($totals['partial_amount']) }}</div>
            </td>
            <td class="num">
                {{ $totals['unpaid'] }}
                <div style="font-size:11px;">{{ \App\Support\Money::format($totals['unpaid_amount']) }}</div>
            </td>
        </tr>
    </tfoot>
</table>
<p style="margin-top:8px;font-size:11px;color:#64748b;">{{ $labels['note'] }}</p>

<div style="margin-top:18px;overflow:hidden;border:1px solid #e2e8f0;border-radius:6px;">
    <div style="background:#334155;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;">
        {{ $labels['info_section'] }}
    </div>
    <table class="data" style="border:0;border-radius:0;margin:0;">
        <thead>
            <tr>
                <th style="width:50%">{{ $labels['info_col_item'] }}</th>
                <th style="width:20%" class="num">{{ $labels['info_col_students'] }}</th>
                <th style="width:30%" class="num">{{ $labels['info_col_amount'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $line)
                <tr @if (! empty($line['emphasize'])) style="font-weight:700;background:#f8fafc;" @endif>
                    <td>{{ $line['label'] }}</td>
                    <td class="num">{{ $line['students'] !== null ? $line['students'] : '—' }}</td>
                    <td class="num">{{ $line['amount'] !== null ? \App\Support\Money::format($line['amount']) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
