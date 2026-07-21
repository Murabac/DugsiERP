@extends('layouts.print', ['printOrientation' => 'A4 portrait'])

@section('title', $labels['page_title'])
@section('back_url', route('reports.fees.monthly-close', request()->except('print')))
@section('doc_pill', $labels['page_title'])
@section('meta')
    {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }}
    · AY {{ $academicYear }}
@endsection
@section('counts')
    {{ $labels['profit_loss'] }} {{ \App\Support\Money::format($net) }}
    · {{ $labels['income_total'] }} {{ \App\Support\Money::format($incomeTotal) }}
    · {{ $labels['expense_total'] }} {{ \App\Support\Money::format($expenseTotal) }}
@endsection

@section('content')
@include('reports.fees.partials.monthly-close-sections', ['print' => true])
@endsection
