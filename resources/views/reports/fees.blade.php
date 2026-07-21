@extends('layouts.app')

@section('title', 'Fee Collection Report — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Fee Collection Report'],
    ]" />

    <x-section-header title="Fee Collection Report" :sub="'Collected vs outstanding by month · AY '.$academicYear">
        <x-slot:action>
            <x-btn variant="secondary" :href="route('reports.fees.print', request()->query())" target="_blank">Print</x-btn>
            <x-btn variant="secondary" href="{{ route('reports.fees', array_filter(['from' => $from, 'to' => $to, 'class' => $classId, 'export' => 'csv'])) }}">
                <x-icon name="download" :size="14" /> Export CSV
            </x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('reports.fees') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <x-field label="From" name="from" type="date" :value="$from" />
            <x-field label="To" name="to" type="date" :value="$to" />
            <x-select label="Class" name="class">
                <option value="">All classes</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" @selected($classId === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </x-select>
            <div class="flex items-end">
                <x-btn type="submit" class="w-full sm:w-auto">Apply Filters</x-btn>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Total Collected" :value="\App\Support\Money::format($totalPaid)" icon="dollar-sign" :accent="true" />
        <x-stat-card label="Total Due" :value="\App\Support\Money::format($totalDue)" icon="file-text" />
        <x-stat-card label="Collection Rate" :value="\App\Support\Money::formatPercent($rate)" :sub="\App\Support\Money::format($totalOutstanding).' outstanding'" icon="bar-chart" />
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Collected vs Due</h3>
        @if (collect($rows)->sum(fn ($r) => $r['due'] + $r['paid']) <= 0)
            <p class="py-10 text-center text-sm text-slate-400">No invoice data for this range.</p>
        @else
            <div class="relative h-64 w-full">
                <canvas data-dugsi-chart='@json($chart)'></canvas>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Month</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Due</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Collected</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Outstanding</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-slate-50">
                            <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['full_label'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($row['due']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-green-700">{{ \App\Support\Money::format($row['paid']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($row['outstanding']) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <x-status-badge status="info" :label="\App\Support\Money::formatPercent($row['pct'])" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">No months in range.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($rows))
                    <tfoot>
                        <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                            <td class="px-4 py-2.5">Total</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($totalDue) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($totalPaid) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($totalOutstanding) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ \App\Support\Money::formatPercent($rate) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
