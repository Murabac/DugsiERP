@extends('layouts.app')

@section('title', 'Grades — Dugsi ERP')

@section('content')
@php
    use App\Enums\LetterGrade;
@endphp

<div class="space-y-4">
    <x-section-header
        title="Grades"
        :sub="'Enter marks (out of term max) · teachers can edit for '.$gradeEditWindowDays.' day'.($gradeEditWindowDays === 1 ? '' : 's').' after first save · '.$academicYear"
    >
        <x-slot:action>
            @if ($schoolClass && $subject)
                <x-btn variant="secondary" href="{{ route('grades.sheet.print', ['class' => $schoolClass->id, 'subject' => $subject->id, 'term' => $term->value]) }}">Print mark sheet</x-btn>
            @endif
            @if ($canGenerateReports)
                <x-btn variant="secondary" :href="route('grades.report', array_filter([
                        'class' => $canGenerateReportForClass ? $schoolClass?->id : null,
                        'term' => $term->value,
                    ]))">Student Report</x-btn>
            @endif
        </x-slot:action>
    </x-section-header>

    <div class="flex gap-1 border-b border-slate-200 text-sm">
        <span class="border-b-2 border-dugsi-primary px-3 py-2 font-semibold text-dugsi-primary">Grade Entry</span>
        @if ($canGenerateReports)
            <a href="{{ route('grades.report', array_filter([
                    'class' => $canGenerateReportForClass ? $schoolClass?->id : null,
                    'term' => $term->value,
                ])) }}" class="px-3 py-2 text-slate-500 hover:text-slate-800">Student Report</a>
        @endif
    </div>

    @if ($classes->isEmpty() || $subjects->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            @if ($subjects->isEmpty())
                Subjects are not seeded yet. Run the database seeder first.
            @elseif (auth()->user()->isTeacher())
                No classes on your timetable yet. Ask an admin to assign you periods first.
            @else
                Create active classes for {{ $academicYear }} before entering grades.
            @endif
        </div>
    @else
        <form method="GET" action="{{ route('grades.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                    <select name="class" onchange="this.form.querySelector('[name=subject]').disabled = true; this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Subject</label>
                    <select name="subject" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($subjects as $s)
                            <option value="{{ $s->id }}" @selected($subject?->id === $s->id)>{{ $s->name }}</option>
                        @endforeach
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

        @if ($rows->isEmpty())
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                No active students enrolled in {{ $schoolClass->displayName() }}.
            </div>
        @else
            <form method="POST" action="{{ route('grades.store') }}" id="grades-form">
                @csrf
                <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">
                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                <input type="hidden" name="term" value="{{ $term->value }}">

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                            {{ $schoolClass->displayName() }} — {{ $subject->name }} · {{ $term->label() }}
                            <span class="font-medium normal-case text-slate-400">(out of {{ number_format($termMax, $termMax == (int) $termMax ? 0 : 1) }})</span>
                        </h3>
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($classAverage !== null)
                                <div class="text-xs text-slate-500">
                                    Class average:
                                    <span class="font-semibold text-slate-800">{{ number_format($classAverageMarks, 1) }}/{{ number_format($termMax, $termMax == (int) $termMax ? 0 : 1) }}</span>
                                    <span class="text-slate-400">({{ number_format($classAverage, 1) }}%)</span>
                                    @if ($classAverageLetter)
                                        <span class="ml-1 inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold {{ $classAverageLetter->badgeClass() }}">{{ $classAverageLetter->value }}</span>
                                    @endif
                                </div>
                            @endif
                            <x-btn type="submit" size="sm">Save Grades</x-btn>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[780px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50">
                                    @foreach (array_filter(['#', 'Student', 'Score', '%', 'Grade', 'Remarks', 'Edit note', $canViewEditHistory ? 'History' : null]) as $h)
                                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        /** @var \App\Models\Student $student */
                                        $student = $row['student'];
                                        $letter = $row['letter'];
                                        $locked = $row['locked'];
                                        $needsNote = $row['needs_note'];
                                    @endphp
                                    <tr class="border-b border-slate-50 grade-row {{ $locked ? 'bg-slate-50/80' : '' }}" data-student="{{ $student->id }}">
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $row['roll'] }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">
                                                    {{ $student->initials() }}
                                                </div>
                                                <div>
                                                    <div class="font-medium text-slate-900">{{ $student->full_name }}</div>
                                                    @if ($locked)
                                                        <div class="text-[10px] font-medium text-slate-500">Locked</div>
                                                    @elseif ($row['unlock_until'])
                                                        <div class="text-[10px] text-slate-400">Editable until {{ $row['unlock_until']->format('j M Y') }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @if ($locked)
                                                <input type="hidden" name="scores[{{ $student->id }}]" value="{{ $row['score'] }}">
                                                <span class="font-medium text-slate-800">{{ $row['score'] !== '' ? number_format((float) $row['score'], 1) : '—' }}</span>
                                            @else
                                                <input type="number" name="scores[{{ $student->id }}]" value="{{ $row['score'] }}"
                                                    min="0" max="{{ $termMax }}" step="0.01" inputmode="decimal"
                                                    class="grade-score w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                                                    placeholder="0–{{ number_format($termMax, $termMax == (int) $termMax ? 0 : 1) }}">
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="grade-percent text-slate-700">{{ $row['percent'] !== null ? number_format($row['percent'], 1).'%' : '—' }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="grade-letter inline-flex min-w-[1.5rem] justify-center rounded px-1.5 py-0.5 text-xs font-bold {{ $letter ? $letter->badgeClass() : 'bg-slate-50 text-slate-400' }}">
                                                {{ $letter?->value ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @if ($locked)
                                                <span class="text-xs text-slate-500">{{ $row['remarks'] !== '' ? $row['remarks'] : '—' }}</span>
                                            @else
                                                <input type="text" name="remarks[{{ $student->id }}]" value="{{ $row['remarks'] }}"
                                                    maxlength="255"
                                                    class="w-full min-w-[120px] rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                                                    placeholder="Optional">
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @if ($locked)
                                                <span class="text-xs text-slate-400">—</span>
                                            @elseif ($needsNote)
                                                <input type="text" name="edit_notes[{{ $student->id }}]" value="{{ $row['edit_note'] }}"
                                                    maxlength="255"
                                                    class="w-full min-w-[140px] rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary {{ $errors->has('edit_notes.'.$student->id) ? 'border-red-400' : '' }}"
                                                    placeholder="{{ $isAdminGrader ? 'Required: why was this changed?' : 'Required if score changes' }}">
                                                @error('edit_notes.'.$student->id)
                                                    <p class="mt-1 text-[10px] text-red-600">{{ $message }}</p>
                                                @enderror
                                            @else
                                                <span class="text-[10px] text-slate-400">Not needed yet</span>
                                            @endif
                                        </td>
                                        @if ($canViewEditHistory)
                                            <td class="px-4 py-2.5 text-xs text-slate-500">
                                                @if ($row['edit_logs']->isEmpty())
                                                    —
                                                @else
                                                    <details>
                                                        <summary class="cursor-pointer text-blue-700 hover:underline">
                                                            {{ $row['edit_logs']->count() }} edit{{ $row['edit_logs']->count() === 1 ? '' : 's' }}
                                                        </summary>
                                                        <ul class="mt-1 max-w-[220px] space-y-1 text-[11px]">
                                                            @foreach ($row['edit_logs']->take(5) as $log)
                                                                <li class="rounded bg-slate-50 px-1.5 py-1">
                                                                    <div class="font-medium text-slate-700">
                                                                        {{ $log->editor?->name ?? 'User' }}
                                                                        · {{ $log->created_at?->format('j M H:i') }}
                                                                    </div>
                                                                    <div>
                                                                        {{ $log->old_score !== null ? number_format((float) $log->old_score, 1) : '—' }}
                                                                        →
                                                                        {{ $log->new_score !== null ? number_format((float) $log->new_score, 1) : '—' }}
                                                                    </div>
                                                                    @if (($log->old_remarks ?? null) !== ($log->new_remarks ?? null))
                                                                        <div class="text-slate-500">
                                                                            Remarks:
                                                                            {{ $log->old_remarks ?: '—' }}
                                                                            →
                                                                            {{ $log->new_remarks ?: '—' }}
                                                                        </div>
                                                                    @endif
                                                                    @if ($log->note)
                                                                        <div class="italic text-slate-500">“{{ $log->note }}”</div>
                                                                    @endif
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </details>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($classAverage !== null)
                                <tfoot>
                                    <tr class="border-t-2 border-slate-200 bg-slate-50">
                                        <td colspan="2" class="px-4 py-2.5 text-sm font-semibold text-slate-700">Class Average</td>
                                        <td class="px-4 py-2.5 font-bold text-slate-900">{{ number_format($classAverageMarks, 1) }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ number_format($classAverage, 1) }}%</td>
                                        <td class="px-4 py-2.5">
                                            @if ($classAverageLetter)
                                                <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-bold {{ $classAverageLetter->badgeClass() }}">{{ $classAverageLetter->value }}</span>
                                            @endif
                                        </td>
                                        <td colspan="{{ $canViewEditHistory ? 3 : 2 }}"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-400">
                            Enter the actual mark for this term (max {{ number_format($termMax, $termMax == (int) $termMax ? 0 : 1) }}). Percentage and letter grade are calculated automatically.
                            @unless ($isAdminGrader)
                                After day 1, changing a score or remarks needs an edit note. After {{ $gradeEditWindowDays }} days, grades lock (admins can still correct with a note). Clearing a saved score is not allowed.
                            @else
                                Changing an existing score or remarks requires a note (why). History shows who edited and their reason.
                            @endunless
                        </p>
                        <x-btn type="submit">Save Grades</x-btn>
                    </div>
                </div>
            </form>
        @endif
    @endif
</div>

@push('scripts')
<script>
(() => {
    const boundaries = @json($boundaryJs);
    const termMax = {{ (float) $termMax }};
    const badgeFallback = 'bg-slate-50 text-slate-400';

    function percentFor(marks) {
        if (marks === '' || marks === null || Number.isNaN(Number(marks)) || termMax <= 0) return null;
        return Math.round((Number(marks) / termMax) * 10000) / 100;
    }

    function letterFor(percent) {
        if (percent === null || Number.isNaN(Number(percent))) return null;
        const n = Math.max(0, Math.min(100, Number(percent)));
        return boundaries.find(b => n >= b.min && n <= b.max) || null;
    }

    document.querySelectorAll('.grade-score').forEach(input => {
        input.addEventListener('input', () => {
            const row = input.closest('.grade-row');
            const percentEl = row.querySelector('.grade-percent');
            const badge = row.querySelector('.grade-letter');
            const percent = percentFor(input.value);
            percentEl.textContent = percent !== null ? percent.toFixed(1) + '%' : '—';
            const match = letterFor(percent);
            badge.className = 'grade-letter inline-flex min-w-[1.5rem] justify-center rounded px-1.5 py-0.5 text-xs font-bold ' + (match ? match.cls : badgeFallback);
            badge.textContent = match ? match.letter : '—';
        });
    });
})();
</script>
@endpush
@endsection
