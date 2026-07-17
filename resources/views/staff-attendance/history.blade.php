@extends('layouts.app')

@section('title', 'Staff attendance history — Dugsi ERP')

@section('content')
<div class="mx-auto max-w-3xl space-y-4">
    <x-section-header title="Staff attendance history" sub="Daily totals">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('staff-attendance.index') }}">Mark day</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('staff-attendance.history') }}" class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white p-3">
        <div>
            <label class="mb-1 block text-[11px] font-medium text-slate-500">From</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-medium text-slate-500">To</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white">Filter</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[480px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Present</th>
                    <th class="px-3 py-2">Late</th>
                    <th class="px-3 py-2">Absent</th>
                    <th class="px-3 py-2">Leave</th>
                    <th class="px-3 py-2 text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($days as $day)
                    <tr class="border-b border-slate-50">
                        <td class="px-3 py-2.5 font-medium text-slate-800">{{ \Illuminate\Support\Carbon::parse($day->date)->format('D j M Y') }}</td>
                        <td class="px-3 py-2.5 text-green-700">{{ $day->present_count }}</td>
                        <td class="px-3 py-2.5 text-amber-700">{{ $day->late_count }}</td>
                        <td class="px-3 py-2.5 text-red-700">{{ $day->absent_count }}</td>
                        <td class="px-3 py-2.5 text-slate-600">{{ $day->leave_count }}</td>
                        <td class="px-3 py-2.5 text-right">
                            <a href="{{ route('staff-attendance.index', ['date' => $day->date]) }}" class="text-xs font-medium text-blue-700 hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">No staff attendance in this range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
