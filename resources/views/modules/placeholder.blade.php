@extends('layouts.app')

@section('title', $title.' — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">Module shell</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-8 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <x-icon name="layers" :size="22" />
        </div>
        <p class="text-sm font-medium text-slate-800">{{ $title }} is not built yet</p>
        <p class="mx-auto mt-1 max-w-md text-xs text-slate-500">{{ $note }}</p>
    </div>
</div>
@endsection
