@extends('layouts.app')

@section('title', 'Attendance History — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Attendance History" :sub="'Daily summaries by class · '.$academicYear" />

    @include('attendance.partials.tabs', ['active' => 'history', 'schoolClass' => $schoolClass])

    @if ($classes->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            No accessible classes for attendance history.
        </div>
    @else
        <form method="GET" action="{{ route('attendance.history') }}" class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                    <select name="class" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Filter</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        @foreach (['Date', 'Present', 'Late', 'Absent', 'Suspended', 'Rate', ''] as $h)
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr class="border-b border-slate-50 hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $day['date_label'] }}</td>
                            <td class="px-4 py-2.5 text-green-700">{{ $day['present'] }}</td>
                            <td class="px-4 py-2.5 text-amber-700">{{ $day['late'] }}</td>
                            <td class="px-4 py-2.5 text-red-700">{{ $day['absent'] }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $day['suspended'] }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $day['rate'] !== null ? $day['rate'].'%' : '—' }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex gap-3">
                                    <a href="{{ route('attendance.index', ['class' => $schoolClass->id, 'date' => $day['date']]) }}" class="text-xs text-blue-700 hover:underline">Edit</a>
                                    <a href="{{ route('attendance.print', ['class' => $schoolClass->id, 'date' => $day['date']]) }}" target="_blank" class="text-xs text-blue-700 hover:underline">Print</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">
                                No attendance recorded for {{ $schoolClass->displayName() }} in this date range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
