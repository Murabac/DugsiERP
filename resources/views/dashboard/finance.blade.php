@extends('layouts.app')

@section('title', 'Finance Dashboard — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Finance Dashboard" :sub="now()->format('F Y').' · Academic Year '.$academicYear">
        <x-slot:action>
            <x-btn href="{{ route('finance.fee-collection') }}">
                <x-icon name="dollar-sign" :size="14" /> Record Payment
            </x-btn>
        </x-slot:action>
    </x-section-header>

    @include('partials.staff-checkin-tab')

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Fees overview</h3>
            <div class="relative mx-auto h-52 w-full max-w-[14rem]">
                <canvas data-dugsi-chart='@json($feesChart)'></canvas>
            </div>
            <a href="{{ route('finance.fees-dashboard') }}" class="mt-3 inline-block text-xs font-medium text-blue-700 hover:underline">Open Fees Dashboard →</a>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Recent Invoices</h3>
                <a href="{{ route('finance.fee-collection') }}" class="text-xs text-blue-700 hover:underline">View all</a>
            </div>
            @if ($recentInvoices->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-slate-400">No invoices yet — they are created automatically at the start of each month.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Invoice</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Class</th>
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Amount</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentInvoices as $invoice)
                                <tr class="border-b border-slate-50">
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $invoice->invoice_number }}</td>
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $invoice->student?->full_name }}</td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $invoice->schoolClass?->displayName() ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($invoice->amount_due) }}</td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge :status="$invoice->status" />
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600">{{ $invoice->billing_month?->format('M Y') }}</td>
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
