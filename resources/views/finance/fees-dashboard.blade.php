@extends('layouts.app')

@section('title', 'Fees Dashboard — Dugsi ERP')

@section('content')
@php
    $hasChartData = collect($monthly)->sum(fn ($m) => $m['due'] + $m['paid']) > 0;
@endphp
<div class="space-y-4">
    <x-section-header title="Fees Dashboard" :sub="now()->format('F Y').' · Academic Year '.$academicYear">
        <x-slot:action>
            <x-btn href="{{ route('finance.fee-collection') }}">
                <x-icon name="dollar-sign" :size="14" /> Record Payment
            </x-btn>
        </x-slot:action>
    </x-section-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $i => $stat)
            <x-stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :sub="$stat['sub']"
                :icon="$stat['icon']"
                :accent="$i === 0 || !empty($stat['accent'])"
            />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Collected vs Due</h3>
                <span class="text-[11px] text-slate-400">Academic year {{ $academicYear }}</span>
            </div>
            @if (! $hasChartData)
                <p class="py-10 text-center text-sm text-slate-400">No fee invoices in this academic year yet.</p>
            @else
                <div class="relative h-64 w-full">
                    <canvas data-dugsi-chart='@json($monthlyChart)'></canvas>
                </div>
                <p class="mt-3 text-[11px] text-slate-500">
                    Total billed {{ \App\Support\Money::format($summary['due']) }}
                    · Collected {{ \App\Support\Money::format($summary['paid']) }}
                    · Outstanding {{ \App\Support\Money::format($summary['outstanding']) }}
                </p>
            @endif
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Invoice status</h3>
            @if (! $hasChartData)
                <p class="py-10 text-center text-sm text-slate-400">No invoices yet.</p>
            @else
                <div class="relative mx-auto h-56 w-full max-w-[16rem]">
                    <canvas data-dugsi-chart='@json($statusChart)'></canvas>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Recent Invoices</h3>
                <a href="{{ route('finance.fee-collection') }}" class="text-xs text-blue-700 hover:underline">Fee Collection</a>
            </div>
            @if ($recent->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-slate-400">No invoices yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Month</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $invoice)
                                <tr class="border-b border-slate-50">
                                    <td class="px-4 py-2.5">
                                        <div class="font-medium text-slate-900">{{ $invoice->student?->full_name }}</div>
                                        <div class="font-mono text-[10px] text-slate-400">{{ $invoice->invoice_number }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $invoice->billing_month->format('M Y') }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums font-medium">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Recent Payments</h3>
                <a href="{{ route('finance.fee-collection', ['month' => now()->format('Y-m')]) }}" class="text-xs text-blue-700 hover:underline">This month</a>
            </div>
            @if ($recentPayments->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-slate-400">No payments recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">When</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentPayments as $payment)
                                <tr class="border-b border-slate-50">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $payment->student?->full_name }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $payment->paid_at?->format('j M Y') }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums font-medium text-dugsi-primary">{{ \App\Support\Money::format($payment->amount) }}</td>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('finance.payments.receipt', $payment) }}" class="font-mono text-xs text-blue-700 hover:underline" target="_blank" rel="noopener">{{ $payment->receipt_number }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
