@extends('layouts.print', ['printOrientation' => 'A4 landscape', 'hideSignatures' => false])

@section('title', 'Expenses')
@section('back_url', route('finance.expenses', ['from' => $from, 'to' => $to, 'category' => $categoryId ?: null]))
@section('doc_pill', 'Expenses Register')
@section('meta')
    {{ \Illuminate\Support\Carbon::parse($from)->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('j M Y') }}
    @if ($categoryId > 0)
        · {{ $categories->firstWhere('id', $categoryId)?->name ?? 'Category' }}
    @endif
@endsection
@section('counts')
    Total {{ \App\Support\Money::format($total) }} · {{ $expenses->count() }} entr{{ $expenses->count() === 1 ? 'y' : 'ies' }}
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:12%">Date</th>
            <th style="width:18%">Category</th>
            <th style="width:32%">Description</th>
            <th style="width:14%">Method</th>
            <th style="width:12%" class="num">Amount</th>
            <th style="width:12%">Recorded by</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($expenses as $expense)
            <tr>
                <td>{{ $expense->expense_date?->format('j M Y') }}</td>
                <td>{{ $expense->category?->name ?? '—' }}</td>
                <td>{{ $expense->description ?: '—' }}</td>
                <td>{{ $expense->payment_method?->label() ?? '—' }}</td>
                <td class="num">{{ \App\Support\Money::format($expense->amount) }}</td>
                <td style="font-size:10px;">{{ $expense->recorder?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No expenses in this range.</td></tr>
        @endforelse
    </tbody>
    @if ($expenses->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="4">Total</td>
                <td class="num">{{ \App\Support\Money::format($total) }}</td>
                <td></td>
            </tr>
        </tfoot>
    @endif
</table>
@endsection
