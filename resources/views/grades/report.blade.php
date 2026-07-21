@extends('layouts.app')

@section('title', 'Student Grade Report — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Student Grade Report" :sub="'Per-student term report with class rank · Form Masters & admins · '.$academicYear">
        <x-slot:action>
            @if ($schoolClass && $student)
                <x-btn variant="secondary" :href="route('grades.print', ['class' => $schoolClass->id, 'student' => $student->id, 'term' => $term->value])" target="_blank" rel="noopener">
                    <x-icon name="printer" :size="14" /> Print Report Card
                </x-btn>
            @endif
            <x-btn variant="secondary" :href="route('grades.index', array_filter(['class' => $schoolClass?->id, 'term' => $term->value]))">Grade Entry</x-btn>
        </x-slot:action>
    </x-section-header>

    <div class="flex gap-1 border-b border-slate-200 text-sm">
        <a href="{{ route('grades.index', array_filter(['class' => $schoolClass?->id, 'term' => $term->value])) }}" class="px-3 py-2 text-slate-500 hover:text-slate-800">Grade Entry</a>
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

                <div class="grid grid-cols-1 gap-x-8 gap-y-3 border-b border-slate-100 px-4 py-4 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Student</div>
                        <div class="font-medium text-slate-900">{{ $student->full_name }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Student ID</div>
                        <div class="font-medium text-slate-900">{{ $student->student_code }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Class</div>
                        <div class="font-medium text-slate-900">{{ $schoolClass->displayName() }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Guardian</div>
                        <div class="font-medium text-slate-900">{{ $guardian?->full_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-wider text-slate-400">Term / Year</div>
                        <div class="font-medium text-slate-900">{{ $term->label() }} · {{ $academicYear }}</div>
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
                                @foreach (['Subject', 'Score', '%', 'Grade', 'Remarks'] as $h)
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['rows'] as $row)
                                <tr class="border-b border-slate-50">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['subject']->name }}</td>
                                    <td class="px-4 py-2.5">{{ $row['marks'] !== null ? number_format($row['marks'], 1) : '—' }}</td>
                                    <td class="px-4 py-2.5">{{ $row['percent'] !== null ? number_format($row['percent'], 1).'%' : '—' }}</td>
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
                            <span class="ml-1 font-semibold text-slate-900">{{ number_format($report['average_marks'], 1) }}/{{ number_format($report['term_max'], $report['term_max'] == (int) $report['term_max'] ? 0 : 1) }}</span>
                            <span class="ml-1 text-slate-500">({{ number_format($report['average'], 1) }}%)</span>
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

                <div class="grid grid-cols-1 gap-6 border-t border-slate-100 px-4 py-6 sm:grid-cols-2">
                    <div>
                        <div class="mb-8 border-b border-slate-300"></div>
                        <div class="text-xs font-medium text-slate-700">Class Teacher</div>
                        <div class="text-[11px] text-slate-400">Signature &amp; date</div>
                    </div>
                    <div>
                        <div class="mb-8 border-b border-slate-300"></div>
                        <div class="text-xs font-medium text-slate-700">Headmaster</div>
                        <div class="text-[11px] text-slate-400">Signature &amp; date</div>
                    </div>
                </div>
                <div class="border-t border-slate-100 px-4 py-3 text-center text-[11px] text-slate-400">
                    Official grade report · {{ $schoolName }} · {{ $academicYear }}
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
