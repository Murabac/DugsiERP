@extends('layouts.app')

@section('title', 'Staff attendance — Dugsi ERP')

@section('content')
@php use App\Enums\StaffAttendanceStatus; @endphp

<div class="mx-auto max-w-3xl space-y-4">
    <x-section-header title="Staff attendance" sub="Mark the day or share phone check-in links">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('staff-attendance.history') }}">History</x-btn>
            <x-btn variant="secondary" href="{{ route('settings.index', ['tab' => 'school']) }}">Wi‑Fi settings</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('staff-attendance.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <label class="mb-1 block text-xs font-medium text-slate-700">Date</label>
        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
            class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm">
    </form>

    @if ($rows->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            No active staff to mark.
        </div>
    @else
        <form method="POST" action="{{ route('staff-attendance.store') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ $dateLabel }}
                        @if ($alreadyMarked)
                            <span class="ml-1 font-medium normal-case text-slate-400">(editing)</span>
                        @endif
                    </h3>
                    <div class="flex flex-wrap gap-3 text-xs font-medium">
                        <span class="text-green-700">Present: {{ $counts['present'] }}</span>
                        <span class="text-amber-700">Late: {{ $counts['late'] }}</span>
                        <span class="text-red-700">Absent: {{ $counts['absent'] }}</span>
                        <span class="text-slate-600">Leave: {{ $counts['on_leave'] }}</span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        @php $sid = $row['staff']->id; @endphp
                        <div class="px-4 py-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-800">
                                        {{ $row['staff']->initials() }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('staff.show', $row['staff']) }}" class="truncate text-sm font-medium text-blue-700 hover:underline">{{ $row['staff']->full_name }}</a>
                                        <div class="text-[11px] text-slate-400">
                                            @if ($row['source'])
                                                {{ $row['source']->label() }}
                                            @else
                                                {{ $row['staff']->role_label->label() }}
                                            @endif
                                            @if ($row['check_in_at'])
                                                · in {{ $row['check_in_at']->format('g:i A') }}
                                            @endif
                                            @if ($row['check_out_at'])
                                                · out {{ $row['check_out_at']->format('g:i A') }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1 sm:justify-end">
                                    @foreach ($statuses as $status)
                                        @php $active = $row['status'] === $status->value; @endphp
                                        <label class="cursor-pointer">
                                            <input type="radio" name="statuses[{{ $sid }}]" value="{{ $status->value }}" class="peer sr-only" @checked($active) required>
                                            <span class="inline-block rounded-md border px-2 py-1 text-[11px] font-medium
                                                peer-checked:border-dugsi-primary peer-checked:bg-dugsi-primary peer-checked:text-white
                                                border-slate-200 text-slate-600 hover:border-slate-300">
                                                {{ $status->label() }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-4 py-3">
                    <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                        Save attendance
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
