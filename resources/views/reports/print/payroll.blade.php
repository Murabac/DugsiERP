@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Payroll Report '.$month)
@section('back_url', route('reports.payroll', request()->query()))
@section('doc_pill', 'Payroll Report')
@section('meta')
    {{ \Illuminate\Support\Carbon::parse($month.'-01')->format('F Y') }} · AY {{ $academicYear }}
@endsection
@section('counts')
    @if ($stats)
        {{ $stats['staff'] }} staff · {{ \App\Support\Money::format($stats['total']) }}
        @if (!empty($stats['preview'])) · Preview (not confirmed) @endif
        @if ($stats['status']) · {{ $stats['status']->label() }} @endif
    @endif
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:40%">Name</th>
            <th style="width:30%">Role</th>
            <th style="width:30%" class="num">Salary</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $item)
            <tr>
                <td>{{ $item->full_name }}</td>
                <td>{{ is_object($item->role_label ?? null) ? $item->role_label->label() : (string) ($item->role_label ?? '—') }}</td>
                <td class="num">{{ \App\Support\Money::format($item->salary_usd) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty">No payroll data for this month.</td></tr>
        @endforelse
    </tbody>
    @if ($items->isNotEmpty() && $stats)
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ \App\Support\Money::format($stats['total']) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
@endsection
