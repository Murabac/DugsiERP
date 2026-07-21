@extends('layouts.app')

@section('title', 'Fee Collection — Dugsi ERP')

@section('content')
@php
    $rate = \App\Support\Money::percentOf($totals['paid'], $totals['due']);
@endphp
<div class="space-y-4">
    <x-section-header title="Fee Collection" :sub="'Class fee sheet · Academic Year '.$academicYear">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('finance.fee-collection.print', request()->query()) }}">Print sheet</x-btn>
            @if (auth()->user()->hasPermission('fees.generate'))
                <x-btn variant="secondary" href="{{ route('settings.index', ['tab' => 'fees']) }}">Fee Settings</x-btn>
            @endif
            @if ($canEnsureMonth)
                <form method="POST" action="{{ route('finance.fee-collection.generate') }}" class="inline"
                    data-dugsi-confirm="Create any missing invoices for {{ $billingMonth->format('F Y') }}? Existing invoices are skipped."
                    data-dugsi-confirm-title="Generate Fee Sheet"
                    data-dugsi-confirm-ok="Generate">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    @if ($monthNeedsEnsure)
                        <x-btn type="submit">Generate Fee Sheet</x-btn>
                    @else
                        <x-btn type="submit" variant="secondary">Sync missing invoices</x-btn>
                    @endif
                </form>
            @endif
        </x-slot:action>
    </x-section-header>

    <p class="text-xs text-slate-500">
        School monthly fee: <strong>{{ \App\Support\Money::format($monthlyFee) }}</strong> (same for all classes).
        Select <strong>Class + Month</strong>, then generate the sheet. Payments can cover several unpaid months — oldest first.
    </p>

    @if (! $feeConfigured)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Monthly fee is not set.
            @if (auth()->user()->hasPermission('fees.generate'))
                <a href="{{ route('settings.index', ['tab' => 'fees']) }}" class="font-medium underline">Set it in Settings → Monthly Fee</a> before invoices can be created.
            @else
                Ask an admin to set it under Settings → Monthly Fee.
            @endif
        </div>
    @elseif ($monthNeedsEnsure)
        <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
            No invoices for {{ $billingMonth->format('F Y') }} yet. Run <strong>Create month invoices</strong> (or wait for the 1st-of-month scheduler).
        </div>
    @endif

    <form method="GET" action="{{ route('finance.fee-collection') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                <select name="class" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="">All classes</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($classId === $c->id)>{{ $c->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Month</label>
                <div class="flex items-center gap-1">
                    @if ($prevMonth)
                        <a href="{{ route('finance.fee-collection', array_filter(['month' => $prevMonth, 'class' => $classId ?: null, 'status' => $statusFilter ?: null, 'q' => $q ?: null])) }}"
                            class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">‹</a>
                    @endif
                    <input type="month" name="month" value="{{ $month }}" min="{{ $monthBounds['min'] }}" max="{{ $monthBounds['max'] }}"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    @if ($nextMonth)
                        <a href="{{ route('finance.fee-collection', array_filter(['month' => $nextMonth, 'class' => $classId ?: null, 'status' => $statusFilter ?: null, 'q' => $q ?: null])) }}"
                            class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">›</a>
                    @endif
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                <select name="status" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st['value'] }}" @selected($statusFilter === $st['value'])>{{ $st['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[10rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-slate-700">Student</label>
                <input type="search" name="q" value="{{ $q }}" placeholder="Name or ID" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">
            </div>
            <x-btn type="submit" variant="secondary">Apply filters</x-btn>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Due" :value="\App\Support\Money::format($totals['due'])" :sub="$billingMonth->format('F Y')" icon="file-text" accent />
        <x-stat-card label="Collected" :value="\App\Support\Money::format($totals['paid'])" icon="dollar-sign" />
        <x-stat-card label="Balance" :value="\App\Support\Money::format($totals['balance'])" icon="credit-card" />
        <x-stat-card label="Collection Rate" :value="\App\Support\Money::formatPercent($rate)" :sub="$invoices->count().' students on sheet'" icon="bar-chart" />
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="mb-2 flex items-center justify-between text-xs">
            <span class="font-semibold uppercase tracking-wider text-slate-500">Collection rate</span>
            <span class="font-medium text-slate-700">{{ \App\Support\Money::formatPercent($rate) }}</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-dugsi-primary transition-all" style="width: {{ min(100, max(0, $rate)) }}%"></div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Fee Sheet — {{ $billingMonth->format('F Y') }}</h3>
            <a href="{{ route('finance.fee-collection.print', request()->query()) }}" class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline">
                <x-icon name="printer" :size="12" /> Print sheet
            </a>
        </div>
        <table class="w-full min-w-[800px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">ID</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Class</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Due</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Paid</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    @php
                        $latestPayment = $invoice->payments->first();
                        $open = $studentOpen[$invoice->student_id] ?? ['total' => $invoice->balance(), 'months' => []];
                        $openTotal = (float) ($open['total'] ?? $invoice->balance());
                        $openMonths = $open['months'] ?? [];
                        $stu = $invoice->student;
                    @endphp
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">
                                    {{ $stu?->initials() ?? '?' }}
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900">{{ $stu?->full_name }}</div>
                                    @if ($invoice->discount_reason)
                                        <div class="text-[10px] text-slate-400">{{ $invoice->discount_reason }}</div>
                                    @endif
                                    @if ((float) $invoice->transport_fee > 0)
                                        <div class="text-[10px] text-slate-400">incl. transport {{ \App\Support\Money::format($invoice->transport_fee) }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $stu?->student_code }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $invoice->schoolClass?->displayName() }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($invoice->amount_due) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($invoice->amount_paid) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                        <td class="px-4 py-2.5">
                            <x-status-badge :status="$invoice->status" />
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="inline-flex items-center justify-end gap-3">
                                @if ($openTotal > 0)
                                    <button type="button"
                                        class="text-xs font-medium text-blue-700 hover:underline"
                                        data-dugsi-open="#pay-modal-{{ $invoice->id }}"
                                        data-dugsi-title="Record payment">
                                        Pay
                                    </button>
                                @endif
                                @if ($latestPayment)
                                    <a href="{{ route('finance.payments.receipt', $latestPayment) }}"
                                        class="text-xs font-medium text-slate-600 hover:underline"
                                        target="_blank"
                                        rel="noopener">
                                        Print
                                    </a>
                                @endif
                                @if ($openTotal <= 0 && ! $latestPayment)
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">
                            No invoices for {{ $billingMonth->format('F Y') }}.
                            @if (! $feeConfigured)
                                Set the monthly fee in Settings first.
                            @elseif ($isFutureMonth)
                                Future months are billed automatically on the 1st.
                            @else
                                Use <strong>Create month invoices</strong> above, or wait for the scheduler.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach ($invoices as $invoice)
    @php
        $open = $studentOpen[$invoice->student_id] ?? ['total' => $invoice->balance(), 'months' => []];
        $openTotal = (float) ($open['total'] ?? $invoice->balance());
        $openMonths = $open['months'] ?? [];
    @endphp
    @if ($openTotal > 0)
    <div id="pay-modal-{{ $invoice->id }}" class="hidden" data-dugsi-width="28rem">
        <form method="POST" action="{{ route('finance.payments.store') }}" class="space-y-3 p-5">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <input type="hidden" name="return_month" value="{{ $month }}">
            <input type="hidden" name="return_class" value="{{ $classId }}">
            <input type="hidden" name="return_status" value="{{ $statusFilter }}">
            <input type="hidden" name="return_q" value="{{ $q }}">
            <p class="text-sm text-slate-600">
                <strong>{{ $invoice->student?->full_name }}</strong>
                · This month {{ \App\Support\Money::format($invoice->balance()) }}
                · All open {{ \App\Support\Money::format($openTotal) }}
            </p>
            @if (count($openMonths) > 1)
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
                    <div class="font-medium text-slate-700">Unpaid months (oldest first)</div>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($openMonths as $row)
                            <li>{{ $row['month'] }} — {{ \App\Support\Money::format($row['balance']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Amount (USD)</label>
                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $openTotal }}" required
                    value="{{ old('amount', $openTotal) }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                <p class="mt-1 text-[11px] text-slate-400">Max {{ \App\Support\Money::format($openTotal) }}. Extra beyond open balances is not accepted (no prepaid credit yet).</p>
            </div>
            @if (count($openMonths) > 1)
                <label class="flex items-start gap-2 text-xs text-slate-700">
                    <input type="hidden" name="allocate_arrears" value="0">
                    <input type="checkbox" name="allocate_arrears" value="1" class="mt-0.5" checked>
                    <span>Apply across unpaid months (oldest first). Uncheck to pay <strong>this month only</strong> (max {{ \App\Support\Money::format($invoice->balance()) }}).</span>
                </label>
            @else
                <input type="hidden" name="allocate_arrears" value="1">
            @endif
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Method</label>
                <select name="method" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    @foreach ($methods as $m)
                        <option value="{{ $m['value'] }}" @selected(old('method', 'cash') === $m['value'])>{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Notes</label>
                <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Payment</button>
            </div>
        </form>
    </div>
    @endif
@endforeach
@endsection
