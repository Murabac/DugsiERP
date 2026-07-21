@extends('layouts.app')

@section('title', $labels['page_title'].' — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => $labels['back'], 'url' => route('reports.fees')],
        ['label' => $labels['page_title']],
    ]" />

    <x-section-header :title="$labels['page_title']" :sub="$labels['sub'].' · AY '.$academicYear">
        <x-slot:action>
            <x-lang-toggle route="reports.fees.students-by-form" :lang="$lang" />
            <x-btn variant="secondary" :href="route('reports.fees.students-by-form.print', request()->query())" target="_blank">{{ $labels['print'] }}</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('reports.fees.students-by-form') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="lang" value="{{ $lang }}">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $labels['month'] }}</label>
                <div class="flex items-center gap-1">
                    @if ($prevMonth)
                        <a href="{{ route('reports.fees.students-by-form', ['month' => $prevMonth, 'lang' => $lang]) }}"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            title="{{ $labels['month'] }}">‹</a>
                    @endif
                    <select name="month"
                        class="min-w-0 flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-dugsi-primary focus:outline-none focus:ring-1 focus:ring-dugsi-primary"
                        onchange="this.form.submit()">
                        @foreach ($monthOptions as $opt)
                            <option value="{{ $opt['value'] }}" @selected($month === $opt['value'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    @if ($nextMonth)
                        <a href="{{ route('reports.fees.students-by-form', ['month' => $nextMonth, 'lang' => $lang]) }}"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                            title="{{ $labels['month'] }}">›</a>
                    @endif
                </div>
            </div>
            <div class="flex items-end sm:col-span-2">
                <x-btn type="submit" class="w-full sm:w-auto">{{ $labels['apply'] }}</x-btn>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="bg-[#1e3a6e] px-4 py-2.5 text-center text-sm font-semibold text-white">
            {{ $labels['section'] }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['class'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['total'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['paid'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['partial'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['unpaid'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['label'] }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="tabular-nums font-medium text-slate-800">{{ $row['total'] }}</div>
                                <div class="mt-0.5 text-xs tabular-nums text-slate-500">{{ \App\Support\Money::format($row['total_amount']) }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="tabular-nums font-medium text-green-700">{{ $row['paid'] }}</div>
                                <div class="mt-0.5 text-xs tabular-nums text-green-700/80">{{ \App\Support\Money::format($row['paid_amount']) }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="tabular-nums font-medium text-amber-700">{{ $row['partial'] }}</div>
                                <div class="mt-0.5 text-xs tabular-nums text-amber-700/80">{{ \App\Support\Money::format($row['partial_amount']) }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="tabular-nums font-medium text-red-700">{{ $row['unpaid'] }}</div>
                                <div class="mt-0.5 text-xs tabular-nums text-red-700/80">{{ \App\Support\Money::format($row['unpaid_amount']) }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-300 bg-slate-50 font-bold">
                        <td class="px-4 py-2.5 text-slate-900">{{ $labels['grand_total'] }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="tabular-nums">{{ $totals['total'] }}</div>
                            <div class="mt-0.5 text-xs font-semibold tabular-nums text-slate-600">{{ \App\Support\Money::format($totals['total_amount']) }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="tabular-nums text-green-700">{{ $totals['paid'] }}</div>
                            <div class="mt-0.5 text-xs font-semibold tabular-nums text-green-700">{{ \App\Support\Money::format($totals['paid_amount']) }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="tabular-nums text-amber-700">{{ $totals['partial'] }}</div>
                            <div class="mt-0.5 text-xs font-semibold tabular-nums text-amber-700">{{ \App\Support\Money::format($totals['partial_amount']) }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="tabular-nums text-red-700">{{ $totals['unpaid'] }}</div>
                            <div class="mt-0.5 text-xs font-semibold tabular-nums text-red-700">{{ \App\Support\Money::format($totals['unpaid_amount']) }}</div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">{{ $labels['note'] }}</p>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="bg-slate-700 px-4 py-2.5 text-center text-sm font-semibold text-white">
            {{ $labels['info_section'] }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[420px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['info_col_item'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['info_col_students'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['info_col_amount'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $line)
                        <tr @class([
                            'border-b border-slate-100',
                            'bg-slate-50 font-semibold' => ! empty($line['emphasize']),
                        ])>
                            <td class="px-4 py-2.5 text-slate-800">{{ $line['label'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">
                                {{ $line['students'] !== null ? $line['students'] : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-900">
                                {{ $line['amount'] !== null ? \App\Support\Money::format($line['amount']) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
