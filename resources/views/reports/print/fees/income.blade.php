@extends('layouts.print', ['printOrientation' => 'A4 portrait'])

@section('title', $labels['page_title'])
@section('back_url', route('reports.fees.income', request()->except('print')))
@section('doc_pill', $labels['page_title'])
@section('meta')
    {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }}
    · AY {{ $academicYear }}
@endsection
@section('counts')
    {{ $labels['grand_total'] }} {{ \App\Support\Money::format($total) }}
@endsection

@section('content')
<div style="background:#1e3a6e;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">
    {{ $labels['section'] }}
</div>
<table class="data" style="border-top:0;border-radius:0 0 4px 4px;">
    <thead>
        <tr>
            <th style="width:70%">{{ $labels['description'] }}</th>
            <th style="width:30%" class="num">{{ $labels['amount'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line['label'] }}</td>
                <td class="num">{{ \App\Support\Money::format($line['amount']) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>{{ $labels['grand_total'] }}</td>
            <td class="num">{{ \App\Support\Money::format($total) }}</td>
        </tr>
    </tfoot>
</table>
<p style="margin-top:8px;font-size:11px;color:#64748b;">{{ $labels['note'] }}</p>
@endsection
