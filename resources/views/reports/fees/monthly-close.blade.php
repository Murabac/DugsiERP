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
            <x-lang-toggle route="reports.fees.monthly-close" :lang="$lang" />
            <x-btn variant="secondary" :href="route('reports.fees.monthly-close.print', request()->query())" target="_blank">{{ $labels['print'] }}</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('reports.fees.monthly-close') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="lang" value="{{ $lang }}">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ $labels['month'] }}</label>
                <div class="flex items-center gap-1">
                    @if ($prevMonth)
                        <a href="{{ route('reports.fees.monthly-close', ['month' => $prevMonth, 'lang' => $lang]) }}"
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
                        <a href="{{ route('reports.fees.monthly-close', ['month' => $nextMonth, 'lang' => $lang]) }}"
                            class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 hover:bg-slate-50">›</a>
                    @endif
                </div>
            </div>
            <div class="flex items-end sm:col-span-2">
                <x-btn type="submit" class="w-full sm:w-auto">{{ $labels['apply'] }}</x-btn>
            </div>
        </div>
    </form>

    <div class="space-y-4">
        @include('reports.fees.partials.monthly-close-sections')
    </div>
</div>
@endsection
