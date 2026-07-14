@extends('layouts.app')

@section('title', 'Classes — Dugsi ERP')

@section('content')
@php
    $formColors = [
        1 => 'bg-blue-50 border-blue-200 text-blue-900',
        2 => 'bg-indigo-50 border-indigo-200 text-indigo-900',
        3 => 'bg-violet-50 border-violet-200 text-violet-900',
        4 => 'bg-purple-50 border-purple-200 text-purple-900',
    ];
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Classes</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ $academicYear }} · {{ $classes->count() }} classes · {{ $totalStudents }} students enrolled</p>
        </div>
        @if ($canManage)
            <div class="flex gap-2">
                <a href="{{ route('classes.manage') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <x-icon name="settings" :size="14" /> Manage Classes
                </a>
                <a href="{{ route('students.create') }}" class="inline-flex items-center gap-1.5 rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    + Add Student
                </a>
            </div>
        @endif
    </div>

    @if ($classes->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center">
            <p class="text-sm font-medium text-slate-700">No classes yet</p>
            <p class="mt-1 text-xs text-slate-500">No active classes for academic year {{ $academicYear }}. Create Form + Section under Manage Classes.</p>
            @if ($canManage)
                <a href="{{ route('classes.manage') }}" class="mt-4 inline-flex rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Manage Classes</a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach ($classes as $class)
                @php
                    $enrolled = $class->enrolled_count ?? 0;
                    $pct = $class->capacity > 0 ? (int) round(($enrolled / $class->capacity) * 100) : 0;
                    $color = $formColors[$class->form_level] ?? 'bg-slate-50 border-slate-200 text-slate-900';
                    $bar = $pct >= 95 ? 'bg-red-400' : ($pct >= 80 ? 'bg-amber-400' : 'bg-green-500');
                @endphp
                <a href="{{ route('classes.roster', $class) }}"
                    class="group rounded-xl border p-4 text-left transition-all hover:shadow-md {{ $color }}">
                    <div class="mb-3 flex items-start justify-between">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white shadow-sm">
                            <x-icon name="layers" :size="16" class="text-dugsi-primary" />
                        </div>
                        <span class="mt-0.5 text-slate-400 transition-colors group-hover:text-dugsi-primary">›</span>
                    </div>
                    <div class="text-sm font-bold">{{ $class->displayName() }}</div>
                    <div class="mt-0.5 text-xs opacity-60">Section {{ $class->section }} · Form {{ $class->form_level }}</div>
                    @if (($class->waitlist_count ?? 0) > 0)
                        <div class="mt-1 text-[11px] font-medium text-amber-700">{{ $class->waitlist_count }} on waitlist</div>
                    @endif
                    <div class="mt-3">
                        <div class="mb-1 flex justify-between text-xs opacity-70">
                            <span>{{ $enrolled }} students</span>
                            <span>{{ $pct }}% full</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-white/60">
                            <div class="h-1.5 rounded-full {{ $bar }}" style="width: {{ min($pct, 100) }}%"></div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
