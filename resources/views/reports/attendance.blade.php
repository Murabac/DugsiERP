@extends('layouts.app')

@section('title', 'Attendance Report — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Attendance Report'],
    ]" />

    <x-section-header title="Attendance Report" :sub="'By class, date range, or student · AY '.$academicYear" />

    <form method="GET" action="{{ route('reports.attendance') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="apply" value="1">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-field label="From" name="from" type="date" :value="$from" />
            <x-field label="To" name="to" type="date" :value="$to" />
            <x-select label="Class" name="class">
                <option value="">All classes</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </x-select>
            <x-select label="Student" name="student" :disabled="$students->isEmpty()">
                <option value="">All students</option>
                @foreach ($students as $s)
                    <option value="{{ $s->id }}" @selected($studentId === $s->id)>{{ $s->full_name }}</option>
                @endforeach
            </x-select>
            <div class="flex items-end">
                <x-btn type="submit" class="w-full">Generate Report</x-btn>
            </div>
        </div>
    </form>

    @unless ($applied)
        <div class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-16 text-center">
            <p class="text-sm text-slate-500">Apply filters to generate the attendance report. Results appear as a table and chart.</p>
            <a href="{{ route('reports.index') }}" class="mt-3 inline-block text-xs font-medium text-blue-700 hover:underline">Back to Reports</a>
        </div>
    @else
        @if ($stats)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Attendance Rate" :value="($stats['rate'] !== null ? $stats['rate'].'%' : '—')" icon="calendar" :accent="true" />
                <x-stat-card label="Present" :value="(string) $stats['present']" icon="users" />
                <x-stat-card label="Late" :value="(string) $stats['late']" icon="calendar" />
                <x-stat-card label="Absent" :value="(string) $stats['absent']" :sub="$stats['students'].' students in range'" icon="file-text" />
            </div>
        @endif

        @if ($chart && count($chart['labels'] ?? []) > 0)
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Daily marks</h3>
                <div class="relative h-56 w-full">
                    <canvas data-dugsi-chart='@json($chart)'></canvas>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Class</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Present</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Late</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Absent</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Suspended</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-slate-50">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['student']?->full_name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $row['class'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-green-700">{{ $row['present'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-amber-700">{{ $row['late'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-red-700">{{ $row['absent'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">{{ $row['suspended'] }}</td>
                                <td class="px-4 py-2.5 text-right font-medium">{{ $row['rate'] !== null ? $row['rate'].'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">No attendance records for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless
</div>
@endsection
