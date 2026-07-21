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
            <x-lang-toggle route="reports.fees.expenses" :lang="$lang" />
            <x-btn variant="secondary" :href="route('reports.fees.expenses.print', request()->query())" target="_blank">{{ $labels['print'] }}</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('reports.fees.expenses') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="lang" value="{{ $lang }}">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $labels['month'] }}</label>
                <div class="flex items-center gap-1">
                    @if ($prevMonth)
                        <a href="{{ route('reports.fees.expenses', ['month' => $prevMonth, 'lang' => $lang]) }}"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 hover:bg-slate-50">‹</a>
                    @endif
                    <select name="month"
                        class="min-w-0 flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-dugsi-primary focus:outline-none focus:ring-1 focus:ring-dugsi-primary"
                        onchange="this.form.submit()">
                        @foreach ($monthOptions as $opt)
                            <option value="{{ $opt['value'] }}" @selected($month === $opt['value'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    @if ($nextMonth)
                        <a href="{{ route('reports.fees.expenses', ['month' => $nextMonth, 'lang' => $lang]) }}"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 hover:bg-slate-50">›</a>
                    @endif
                </div>
            </div>
            <div class="flex items-end sm:col-span-2">
                <x-btn type="submit" class="w-full sm:w-auto">{{ $labels['apply'] }}</x-btn>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="bg-[#1e3a6e] px-4 py-2.5 text-center text-sm font-semibold tracking-wide text-white">
            {{ $labels['section'] }}
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[480px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['description'] }}</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600">{{ $labels['amount'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $line['label'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-900">{{ \App\Support\Money::format($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-10 text-center text-sm text-slate-400">{{ $labels['empty'] }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($lines))
                    <tfoot>
                        <tr class="bg-[#1e3a6e] font-bold text-white">
                            <td class="px-4 py-3">{{ $labels['grand_total'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Money::format($total) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">{{ $labels['note'] }}</p>
    </div>
</div>
@endsection
