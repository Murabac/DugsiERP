@extends('layouts.app')

@section('title', 'Finance Dashboard — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Finance Dashboard</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ now()->format('F Y') }} · Academic Year {{ $academicYear }}</p>
        </div>
        <a href="{{ route('finance.fee-collection') }}" class="inline-flex items-center justify-center rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
            Record Payment
        </a>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $stat['label'] }}</div>
                        <div class="mt-1 text-2xl font-bold {{ $stat['accent'] ? 'text-dugsi-primary' : 'text-slate-900' }}">{{ $stat['value'] }}</div>
                        <div class="mt-0.5 text-xs text-slate-400">{{ $stat['sub'] }}</div>
                    </div>
                    <div class="rounded-md {{ $stat['accent'] ? 'bg-blue-50 text-dugsi-primary' : 'bg-slate-50 text-slate-500' }} p-2">
                        <x-icon :name="$stat['icon']" :size="17" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Income: Collected vs. Due</h3>
        <div class="flex h-44 items-center justify-center rounded-md border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-400">
            Fee charts unlock in Week 7 — student and class counts above are live now.
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Recent Invoices</h3>
            <a href="{{ route('finance.fee-collection') }}" class="text-xs text-blue-700 hover:underline">View all</a>
        </div>
        <p class="px-4 py-8 text-center text-sm text-slate-400">No invoices yet — fee module arrives in Week 7.</p>
    </div>
</div>
@endsection
