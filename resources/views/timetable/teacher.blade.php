@extends('layouts.app')

@section('title', 'Timetable — Dugsi ERP')

@section('content')
@php
    $dayLabels = collect($days)->mapWithKeys(fn ($d) => [$d => \App\Support\SchoolWeek::dayLabel($d)]);
    $view = $teacherView; // mine|class
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Timetable</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                Week: Saturday – Wednesday · {{ $academicYear }} · {{ count($periods) }} periods/day · Read-only
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($view === 'class' && $schoolClass)
                <a href="{{ route('timetable.print', ['view' => 'class', 'class' => $schoolClass->id]) }}" target="_blank"
                    class="rounded-md bg-dugsi-primary px-3 py-2 text-xs font-semibold text-white hover:bg-[#162d56]">Print class</a>
            @else
                <a href="{{ route('timetable.print', ['view' => 'mine']) }}" target="_blank"
                    class="rounded-md bg-dugsi-primary px-3 py-2 text-xs font-semibold text-white hover:bg-[#162d56]">Print my schedule</a>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-px">
        <a href="{{ route('timetable.index', ['view' => 'mine']) }}"
            class="rounded-t-md px-3 py-2 text-sm font-medium {{ $view === 'mine' ? 'border border-b-white border-slate-200 bg-white text-dugsi-primary' : 'text-slate-500 hover:text-slate-800' }}">
            My schedule
        </a>
        <a href="{{ route('timetable.index', ['view' => 'class', 'class' => $schoolClass?->id]) }}"
            class="rounded-t-md px-3 py-2 text-sm font-medium {{ $view === 'class' ? 'border border-b-white border-slate-200 bg-white text-dugsi-primary' : 'text-slate-500 hover:text-slate-800' }}">
            Class timetable
        </a>
    </div>

    @if ($view === 'mine')
        <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
            Showing: <strong>My schedule</strong> ({{ $staff->full_name }}) — subject and class for each of your periods.
            No two classes share the same period for you.
        </div>

        @if (collect($myGrid)->flatten()->filter()->isEmpty())
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                No periods assigned yet. Ask an admin to generate class timetables.
            </div>
        @else
            @include('timetable._grid-readonly', [
                'grid' => $myGrid,
                'modeCell' => 'teacher',
                'subjectColors' => $mySubjectColors,
                'highlightTeacherId' => null,
            ])

            <div class="flex flex-wrap gap-2">
                @foreach ($mySubjectColors as $name => $cls)
                    <span class="inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium {{ $cls }}">{{ $name }}</span>
                @endforeach
            </div>
        @endif
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                Showing: full <strong>class timetable</strong> — your periods are highlighted.
            </div>
            @if ($teachingClasses->isNotEmpty())
                <form method="GET" action="{{ route('timetable.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="view" value="class">
                    <label class="text-xs text-slate-500">Class</label>
                    <select name="class" onchange="this.form.submit()" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($teachingClasses as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($teachingClasses->isEmpty())
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                You are not assigned to any class timetable yet.
            </div>
        @elseif ($schoolClass)
            @include('timetable._grid-readonly', [
                'grid' => $classGrid,
                'modeCell' => 'class',
                'schoolClass' => $schoolClass,
                'subjectColors' => $classSubjectColors,
                'highlightTeacherId' => $staff->id,
            ])

            <div class="flex flex-wrap gap-2">
                @foreach ($classSubjectColors as $name => $cls)
                    <span class="inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium {{ $cls }}">{{ $name }}</span>
                @endforeach
            </div>
        @endif
    @endif
</div>
@endsection
