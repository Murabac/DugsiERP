@extends('layouts.app')

@section('title', $title.' — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header :title="$title" sub="Coming in a later release" />

    <div class="rounded-lg border border-dashed border-slate-200 bg-white px-6 py-12 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
            <x-icon name="layers" :size="22" />
        </div>
        <p class="text-sm font-semibold text-slate-800">{{ $title }} is not available yet</p>
        <p class="mx-auto mt-1.5 max-w-md text-xs leading-relaxed text-slate-500">{{ $note }}</p>
        <p class="mt-4 text-[11px] text-slate-400">Use Fees Dashboard and Fee Collection for day-to-day billing.</p>
    </div>
</div>
@endsection
