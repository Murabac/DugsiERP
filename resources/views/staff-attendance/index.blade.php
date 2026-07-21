@extends('layouts.app')

@section('title', 'Staff attendance — Dugsi ERP')

@section('content')
@php use App\Enums\StaffAttendanceStatus; @endphp

<div class="mx-auto max-w-3xl space-y-4">
    <x-section-header title="Staff attendance" sub="Mark by role · phone check-in still updates the same day">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('staff-attendance.print', array_filter(['date' => $date, 'role' => $role])) }}">Print</x-btn>
            <x-btn variant="secondary" href="{{ route('staff-attendance.history') }}">History</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('staff-attendance.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Role</label>
                <select name="role" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    <option value="all" @selected($role === null)>All roles</option>
                    @foreach ($roleOptions as $option)
                        <option value="{{ $option['key'] }}" @selected($role === $option['key'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Date</label>
                <input type="date" name="date" value="{{ $date }}" max="{{ $today }}" onchange="this.form.submit()"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
            </div>
        </div>
    </form>

    @if ($isFuture)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Future dates cannot be marked. Choose today or an earlier date.
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            @if ($role)
                No active staff with this role.
            @else
                No active staff to show.
            @endif
        </div>
    @elseif ($isFuture)
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            Attendance for {{ $dateLabel }} is not available yet.
        </div>
    @else
        <form method="POST" action="{{ route('staff-attendance.store') }}" id="staff-attendance-form">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="role" value="{{ $role ?? 'all' }}">

            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ $dateLabel }}
                        @if ($alreadyMarked)
                            <span class="ml-1 font-medium normal-case text-slate-400">(editing saved marks)</span>
                        @endif
                    </h3>
                    <div class="flex flex-wrap gap-3 text-xs font-medium" id="staff-att-counts">
                        <span class="text-green-700">Present: <span data-count="present">{{ $counts['present'] }}</span></span>
                        <span class="text-amber-700">Late: <span data-count="late">{{ $counts['late'] }}</span></span>
                        <span class="text-red-700">Absent: <span data-count="absent">{{ $counts['absent'] }}</span></span>
                        <span class="text-slate-600">Leave: <span data-count="on_leave">{{ $counts['on_leave'] }}</span></span>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        @php $sid = $row['staff']->id; @endphp
                        <div class="staff-att-row px-4 py-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-800">
                                        {{ $row['staff']->initials() }}
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('staff.show', $row['staff']) }}" class="truncate text-sm font-medium text-blue-700 hover:underline">{{ $row['staff']->full_name }}</a>
                                        <div class="text-[11px] text-slate-400">
                                            {{ $row['staff']->roleDisplayName() }}
                                            @if ($row['source'])
                                                · {{ $row['source']->label() }}
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
                                <div class="flex flex-wrap gap-1 sm:justify-end" role="group" aria-label="Attendance status">
                                    @foreach ($statuses as $status)
                                        @php $active = $row['status'] === $status->value; @endphp
                                        <label class="cursor-pointer">
                                            <input type="radio"
                                                name="statuses[{{ $sid }}]"
                                                value="{{ $status->value }}"
                                                class="staff-att-status sr-only"
                                                @checked($active)
                                                @if ($loop->first) required @endif>
                                            <span class="att-btn {{ $active ? 'is-active' : '' }}" data-status="{{ $status->value }}">
                                                {{ $status->label() }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end border-t border-slate-200 px-4 py-3">
                    <button type="submit"
                        class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                        Save attendance
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>

@if ($rows->isNotEmpty() && ! $isFuture)
<script>
(() => {
    function selectedStatus(row) {
        return row.querySelector('input.staff-att-status:checked')?.value ?? null;
    }

    function syncRow(row) {
        const status = selectedStatus(row);
        row.querySelectorAll('.att-btn').forEach((btn) => {
            btn.classList.toggle('is-active', status !== null && btn.dataset.status === status);
        });
    }

    function refreshCounts() {
        const counts = { present: 0, late: 0, absent: 0, on_leave: 0 };
        document.querySelectorAll('.staff-att-row').forEach((row) => {
            const status = selectedStatus(row);
            if (status && counts[status] !== undefined) counts[status] += 1;
            syncRow(row);
        });
        document.querySelectorAll('[data-count]').forEach((el) => {
            el.textContent = String(counts[el.dataset.count] ?? 0);
        });
    }

    document.querySelectorAll('.staff-att-row').forEach((row) => {
        row.querySelectorAll('input.staff-att-status').forEach((input) => {
            input.addEventListener('change', refreshCounts);
        });
        syncRow(row);
    });
})();
</script>
@endif
@endsection
