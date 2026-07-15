@extends('layouts.app')

@section('title', 'Student Grade Report — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Student Grade Report</h2>
            <p class="mt-0.5 text-xs text-slate-500">Per-student term report with class rank · headmasters &amp; admins only · {{ $academicYear }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($schoolClass && $student)
                <a href="{{ route('grades.print', ['class' => $schoolClass->id, 'student' => $student->id, 'term' => $term->value]) }}" target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <x-icon name="file-text" :size="14" /> Print Report Card
                </a>
            @endif
            <a href="{{ route('grades.index', array_filter(['class' => $schoolClass?->id, 'term' => $term->value])) }}"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Grade Entry
            </a>
        </div>
    </div>

    <div class="flex gap-1 border-b border-slate-200 text-sm">
        <a href="{{ route('grades.index', array_filter(['class' => $schoolClass?->id, 'term' => $term->value])) }}" class="px-3 py-2 text-slate-500 hover:text-slate-800">Grade Entry</a>
        <a href="{{ route('grades.boundaries') }}" class="px-3 py-2 text-slate-500 hover:text-slate-800">Grade Boundaries</a>
        <span class="border-b-2 border-dugsi-primary px-3 py-2 font-semibold text-dugsi-primary">Student Report</span>
    </div>

    @if ($classes->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            No accessible classes for {{ $academicYear }}.
        </div>
    @else
        <form method="GET" action="{{ route('grades.report') }}" class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                    <select name="class" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Student</label>
                    <select name="student" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @forelse ($students as $s)
                            <option value="{{ $s->id }}" @selected($student?->id === $s->id)>{{ $s->full_name }}</option>
                        @empty
                            <option value="">No students</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Term</label>
                    <select name="term" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($terms as $t)
                            <option value="{{ $t->value }}" @selected($term === $t)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        @if (! $student || ! $report)
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                Select a student to view their grade report.
            </div>
        @else
            @php
                $enrollment = $student->currentEnrollment;
                $guardian = $student->primaryGuardian;
            @endphp
            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-sm font-bold text-dugsi-primary">{{ $schoolName }}</div>
                        <div class="text-xs text-slate-500">{{ $schoolLetterheadSub }}</div>
                    </div>
                    <div class="text-left sm:text-right">
                        <div class="text-sm font-semibold text-slate-900">Grade Report Card</div>
                        <div class="text-xs text-slate-500">{{ $term->label() }} · {{ $academicYear }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 border-b border-slate-100 px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Student</div>
                        <div class="font-medium text-slate-900">{{ $student->full_name }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">ID / Class</div>
                        <div class="font-medium text-slate-900">{{ $student->student_code }} · {{ $schoolClass->displayName() }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Guardian</div>
                        <div class="font-medium text-slate-900">{{ $guardian?->full_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Attendance</div>
                        <div class="font-medium text-slate-900">{{ $report['attendance_rate'] !== null ? $report['attendance_rate'].'%' : '—' }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr class="bg-[#1e3a6e] text-white">
                                @foreach (['Subject', 'Score', 'Grade', 'Remarks'] as $h)
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['rows'] as $row)
                                <tr class="border-b border-slate-50">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['subject']->name }}</td>
                                    <td class="px-4 py-2.5">{{ $row['score'] !== null ? number_format($row['score'], 1).'%' : '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($row['letter'])
                                            <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-bold {{ $row['letter']->badgeClass() }}">{{ $row['letter']->value }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ $row['remarks'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm">
                        <span class="text-slate-500">Term Average:</span>
                        @if ($report['average'] !== null)
                            <span class="ml-1 font-semibold text-slate-900">{{ number_format($report['average'], 1) }}%</span>
                            @if ($report['average_letter'])
                                <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-xs font-bold {{ $report['average_letter']->badgeClass() }}">{{ $report['average_letter']->value }}</span>
                            @endif
                        @else
                            <span class="ml-1 text-slate-400">No scores yet</span>
                        @endif
                    </div>
                    <div class="text-sm">
                        <span class="text-slate-500">Class Rank:</span>
                        @if ($report['rank'] !== null)
                            <span class="ml-1 font-semibold text-slate-900">{{ $report['rank'] }} of {{ $report['class_size'] }}</span>
                        @else
                            <span class="ml-1 text-slate-400">—</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
