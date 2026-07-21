@extends('layouts.app')

@section('title', 'Apps — Dugsi ERP')

@section('content')
@php
    use App\Support\Modules;
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Dugsi ERP</p>
            <h1 class="text-2xl font-bold tracking-tight text-white">Apps</h1>
            <p class="mt-1 text-sm text-white/70">
                Welcome, {{ $user->name }} · {{ $user->roleLabel() }} · {{ $academicYear }}
            </p>
        </div>
        <p class="text-xs text-white/50">{{ $schoolName }}</p>
    </div>

    <div id="module-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($modules as $module)
            <a href="{{ route($module['route'], ['app' => $module['key']]) }}"
                class="group flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-white/40">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ Modules::toneClasses($module['tone']) }} transition group-hover:scale-105">
                    <x-icon :name="$module['icon']" :size="20" class="shrink-0" />
                </div>
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-slate-900">{{ $module['label'] }}</div>
                    <div class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">{{ $module['description'] }}</div>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
