@extends('layouts.app')

@section('title', 'Timetable — Dugsi ERP')

@section('content')
@php
    $dayLabels = collect($days)->mapWithKeys(fn ($d) => [$d => \App\Support\SchoolWeek::dayLabel($d)]);
@endphp

<div class="space-y-4">
    <x-section-header
        title="Timetable"
        :sub="'Week: Saturday – Wednesday · '.$academicYear.' · '.count($periods).' periods/day'.($mode === 'admin' ? ' · Drag cells to rearrange' : '')"
    >
        <x-slot:action>
            @if ($mode === 'admin')
                <form method="GET" action="{{ route('timetable.index') }}" class="flex items-center gap-2">
                    <select name="class" onchange="this.form.submit()" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                        @forelse ($classes as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                        @empty
                            <option value="">No classes</option>
                        @endforelse
                    </select>
                </form>
                @if ($schoolClass)
                    <x-btn variant="secondary" type="button" data-dugsi-open="#generate-modal" data-dugsi-width="32rem">Generate</x-btn>
                    <x-btn href="{{ route('timetable.print', ['class' => $schoolClass->id]) }}" target="_blank" rel="noopener">
                        <x-icon name="printer" :size="14" /> Print / PDF
                    </x-btn>
                @endif
            @else
                <span class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-500">Read-only</span>
                <x-btn href="{{ route('timetable.print') }}" target="_blank" rel="noopener">
                    <x-icon name="printer" :size="14" /> Print / PDF
                </x-btn>
            @endif
        </x-slot:action>
    </x-section-header>

    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
        @if ($mode === 'admin')
            Showing: <strong>{{ $schoolClass?->displayName() ?? 'No class' }}</strong>
            — Academic Year {{ $academicYear }}
        @else
            Showing: <strong>My schedule</strong> ({{ $staff->full_name }}) — Academic Year {{ $academicYear }}. Only admins can create or change the timetable.
        @endif
    </div>

    @if ($mode === 'admin' && ! $schoolClass)
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            Create active classes for {{ $academicYear }} before building a timetable.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="tt-grid w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-dugsi-sidebar text-white">
                        <th class="w-28 px-3 py-3 text-left text-xs font-semibold">Period</th>
                        @foreach ($days as $day)
                            <th class="px-2 py-3 text-center text-xs font-semibold">{{ $dayLabels[$day] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periods as $period)
                        <tr class="border-b border-slate-100">
                            <td class="border-r border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="text-xs font-bold text-slate-700">P{{ $period['period'] }}</div>
                                <div class="font-mono text-[11px] text-slate-400">{{ $period['label'] }}</div>
                            </td>
                            @foreach ($days as $day)
                                @php $slot = $grid[$period['period']][$day] ?? null; @endphp
                                <td class="tt-drop-cell px-2 py-2 align-top"
                                    @if ($canEdit)
                                        data-drop-cell
                                        data-day="{{ $day }}"
                                        data-period="{{ $period['period'] }}"
                                    @endif>
                                    @if ($slot)
                                        @php
                                            $color = $subjectColors[$slot->subject?->name] ?? 'bg-slate-50 border-slate-200 text-slate-900';
                                        @endphp
                                        <div
                                            class="tt-slot min-h-[56px] rounded-md border px-2 py-1.5 {{ $color }} {{ $canEdit ? 'is-draggable cursor-grab active:cursor-grabbing select-none' : '' }}"
                                            @if ($canEdit)
                                                draggable="true"
                                                data-drag-slot
                                                data-day="{{ $day }}"
                                                data-period="{{ $period['period'] }}"
                                                data-subject="{{ $slot->subject?->name }}"
                                            @endif>
                                            @if ($canEdit)
                                                <div class="tt-drag-hint mb-0.5 flex items-center gap-1 text-[9px] font-medium uppercase tracking-wide text-slate-400">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                                                    Drag
                                                </div>
                                            @endif
                                            <div class="text-xs font-semibold leading-tight">{{ $slot->subject?->name ?? '—' }}</div>
                                            @if ($mode === 'teacher')
                                                <div class="mt-0.5 text-[10px] opacity-70">{{ $slot->schoolClass?->displayName() }}</div>
                                            @else
                                                <div class="mt-0.5 text-[10px] opacity-70">{{ $slot->teacher?->full_name }}</div>
                                                <div class="text-[10px] opacity-60">{{ $schoolClass->displayName() }}</div>
                                            @endif
                                            @if ($canEdit)
                                                <div class="tt-actions mt-1 flex gap-2" data-no-drag>
                                                    <button type="button"
                                                        class="text-[10px] font-medium text-blue-700 hover:underline"
                                                        data-slot-edit
                                                        data-day="{{ $day }}"
                                                        data-period="{{ $period['period'] }}"
                                                        data-subject-id="{{ $slot->subject_id }}"
                                                        data-teacher-id="{{ $slot->teacher_id }}">Edit</button>
                                                    <form method="POST" action="{{ route('timetable.clear') }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">
                                                        <input type="hidden" name="day_of_week" value="{{ $day }}">
                                                        <input type="hidden" name="period_number" value="{{ $period['period'] }}">
                                                        <button type="submit" class="text-[10px] text-red-500 hover:underline">Clear</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    @elseif ($canEdit)
                                        <div data-empty-drop class="tt-empty flex min-h-[56px] w-full flex-col items-center justify-center rounded-md border-2 border-dashed border-slate-100 text-xs text-slate-300">
                                            <button type="button"
                                                data-slot-edit
                                                data-day="{{ $day }}"
                                                data-period="{{ $period['period'] }}"
                                                data-subject-id=""
                                                data-teacher-id=""
                                                class="tt-add-btn w-full py-3 hover:text-dugsi-primary">+ Add</button>
                                            <span data-drop-label class="pointer-events-none hidden pb-2 text-[10px] font-semibold uppercase tracking-wide">Drop here</span>
                                        </div>
                                    @else
                                        <div class="flex min-h-[56px] items-center justify-center rounded-md border-2 border-dashed border-slate-100 text-xs text-slate-200">—</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($subjectColors as $name => $cls)
                <span class="inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium {{ $cls }}">{{ $name }}</span>
            @endforeach
        </div>
    @endif
</div>

@if ($canEdit && $schoolClass)
<div id="slot-modal" class="hidden" data-dugsi-width="28rem">
    <form method="POST" action="{{ route('timetable.upsert') }}" class="p-5" id="slot-form">
        @csrf
        <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">
        <input type="hidden" name="day_of_week" id="slot-day">
        <input type="hidden" name="period_number" id="slot-period">
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Edit Timetable Slot</h3>
        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Subject</label>
                <select name="subject_id" id="slot-subject" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select subject</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Teacher</label>
                <select name="teacher_id" id="slot-teacher" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select teacher</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $schoolClass->displayName() }}</div>
                <p class="mt-1 text-[11px] text-slate-400">Students stay in this class — teachers come to them</p>
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Slot</button>
        </div>
    </form>
</div>

<div id="generate-modal" class="hidden" data-dugsi-width="32rem">
    <form method="POST" action="{{ route('timetable.generate') }}" id="generate-form"
        data-dugsi-confirm="Replace the current timetable for this class?"
        data-dugsi-confirm-title="Generate timetable"
        data-dugsi-confirm-ok="Generate">
        @csrf
        <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 p-4 sm:p-5">
            <div>
                <h3 class="font-semibold text-slate-900">Generate Timetable</h3>
                <p class="mt-0.5 text-xs text-slate-500">Set periods per subject per week · {{ $schoolClass->displayName() }}</p>
            </div>
            <button type="button" data-dugsi-close class="text-slate-400 hover:text-slate-700" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="max-h-[60vh] space-y-3 overflow-y-auto p-4 sm:p-5">
            <div id="generate-capacity-banner" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Total periods available: <strong>{{ $weeklyCapacity }}</strong> ({{ $periodCount }} periods × 5 days). Currently set: <strong id="generate-total">0</strong>
            </div>
            @foreach ($subjects as $subject)
                @php $default = $defaultWeeklyPeriods[$subject->name] ?? 0; @endphp
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium {{ $subjectColors[$subject->name] ?? 'bg-slate-50 border-slate-200 text-slate-800' }}">{{ $subject->name }}</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-period-delta="-1" data-subject="{{ $subject->name }}"
                            class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50">−</button>
                        <input type="number" name="periods[{{ $subject->name }}]" id="period-{{ md5($subject->name) }}"
                            value="{{ old('periods.'.$subject->name, $default) }}" min="0" max="{{ $weeklyCapacity }}"
                            data-period-input data-subject-name="{{ $subject->name }}"
                            class="w-10 border-0 bg-transparent text-center text-sm font-semibold text-slate-900 focus:outline-none focus:ring-0">
                        <button type="button" data-period-delta="1" data-subject="{{ $subject->name }}"
                            class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50">+</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <button type="button" id="generate-reset" class="text-left text-xs text-slate-500 underline hover:text-slate-800">Reset to defaults</button>
            <div class="flex gap-2">
                <button type="button" data-dugsi-close
                    class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 sm:flex-none">Cancel</button>
                <button type="submit" id="generate-submit"
                    class="flex-1 rounded-lg bg-dugsi-primary px-4 py-2 text-sm font-medium text-white hover:bg-[#162d56] sm:flex-none">Generate</button>
            </div>
        </div>
    </form>
</div>

<form id="swap-form" method="POST" action="{{ route('timetable.swap') }}" class="hidden">
    @csrf
    <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">
    <input type="hidden" name="from_day" id="swap-from-day">
    <input type="hidden" name="from_period" id="swap-from-period">
    <input type="hidden" name="to_day" id="swap-to-day">
    <input type="hidden" name="to_period" id="swap-to-period">
</form>

<script>
const teachersBySubject = @json($teachersBySubject);
const allTeachers = @json($teachers->map(fn ($t) => ['id' => $t->id, 'name' => $t->full_name])->values());
const defaultWeeklyPeriods = @json($defaultWeeklyPeriods);
const weeklyCapacity = {{ (int) $weeklyCapacity }};

function openSlotEditor(payload) {
    document.getElementById('slot-day').value = payload.day;
    document.getElementById('slot-period').value = payload.period;
    document.getElementById('slot-subject').value = payload.subject_id || '';
    filterTeachers(payload.subject_id || '');
    document.getElementById('slot-teacher').value = payload.teacher_id || '';
    window.DugsiUI?.openModal('#slot-modal');
}

document.querySelectorAll('[data-slot-edit]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        openSlotEditor({
            day: btn.dataset.day,
            period: btn.dataset.period,
            subject_id: btn.dataset.subjectId || '',
            teacher_id: btn.dataset.teacherId || '',
        });
    });
});

function filterTeachers(subjectId) {
    const select = document.getElementById('slot-teacher');
    const allowed = subjectId && teachersBySubject[subjectId]
        ? teachersBySubject[subjectId].map(String)
        : allTeachers.map(t => String(t.id));

    select.innerHTML = '<option value="">Select teacher</option>';
    allTeachers.forEach(t => {
        if (allowed.includes(String(t.id))) {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            select.appendChild(opt);
        }
    });
}

document.getElementById('slot-subject')?.addEventListener('change', (e) => {
    filterTeachers(e.target.value);
});

function periodInputFor(subject) {
    return document.querySelector(`[data-period-input][data-subject-name="${subject}"]`);
}

function updateGenerateTotal() {
    let total = 0;
    document.querySelectorAll('[data-period-input]').forEach((input) => {
        total += Number(input.value || 0);
    });
    const totalEl = document.getElementById('generate-total');
    const banner = document.getElementById('generate-capacity-banner');
    const submit = document.getElementById('generate-submit');
    if (totalEl) totalEl.textContent = String(total);
    if (banner) {
        banner.className = total > weeklyCapacity
            ? 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700'
            : 'rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700';
    }
    if (submit) submit.disabled = total < 1 || total > weeklyCapacity;
}

document.querySelectorAll('[data-period-delta]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = periodInputFor(btn.dataset.subject);
        if (!input) return;
        const next = Math.max(0, Math.min(weeklyCapacity, Number(input.value || 0) + Number(btn.dataset.periodDelta)));
        input.value = String(next);
        updateGenerateTotal();
    });
});

document.querySelectorAll('[data-period-input]').forEach((input) => {
    input.addEventListener('input', updateGenerateTotal);
});

document.getElementById('generate-reset')?.addEventListener('click', () => {
    Object.entries(defaultWeeklyPeriods).forEach(([name, count]) => {
        const input = periodInputFor(name);
        if (input) input.value = String(count);
    });
    updateGenerateTotal();
});

updateGenerateTotal();

// Drag & drop rearrange (admin)
const ttGrid = document.querySelector('.tt-grid');
let dragSrc = null;
let dragGhost = null;
let activeDropCell = null;

function clearDropTargets() {
    document.querySelectorAll('.tt-drop-cell.is-drop-target').forEach((cell) => {
        cell.classList.remove('is-drop-target');
        const empty = cell.querySelector('[data-empty-drop]');
        if (!empty) return;
        empty.classList.remove('is-drop-target');
        const addBtn = empty.querySelector('.tt-add-btn');
        const label = empty.querySelector('[data-drop-label]');
        if (addBtn) addBtn.classList.remove('hidden');
        if (label) label.classList.add('hidden');
    });
    activeDropCell = null;
}

function setDropTarget(cell) {
    if (activeDropCell === cell) return;
    clearDropTargets();
    if (!cell || !dragSrc) return;
    if (cell.dataset.day === dragSrc.day && String(cell.dataset.period) === String(dragSrc.period)) return;

    activeDropCell = cell;
    cell.classList.add('is-drop-target');

    const empty = cell.querySelector('[data-empty-drop]');
    if (empty) {
        empty.classList.add('is-drop-target');
        const addBtn = empty.querySelector('.tt-add-btn');
        const label = empty.querySelector('[data-drop-label]');
        if (addBtn) addBtn.classList.add('hidden');
        if (label) {
            label.textContent = 'Drop here';
            label.classList.remove('hidden');
        }
        return;
    }

    const targetSlot = cell.querySelector('[data-drag-slot]');
    if (targetSlot) {
        targetSlot.dataset.swapHint = '1';
    }
}

function cleanupDrag() {
    document.querySelectorAll('[data-drag-slot].is-dragging').forEach((el) => {
        el.classList.remove('is-dragging');
        el.querySelectorAll('.tt-actions').forEach((a) => a.classList.remove('invisible'));
    });
    ttGrid?.classList.remove('is-dragging');
    clearDropTargets();
    if (dragGhost) {
        dragGhost.remove();
        dragGhost = null;
    }
    dragSrc = null;
}

document.querySelectorAll('[data-drag-slot]').forEach((el) => {
    el.addEventListener('dragstart', (e) => {
        if (e.target.closest('[data-no-drag]')) {
            e.preventDefault();
            return;
        }

        dragSrc = {
            day: el.dataset.day,
            period: el.dataset.period,
            subject: el.dataset.subject || 'Subject',
        };

        el.classList.add('is-dragging');
        el.querySelectorAll('.tt-actions').forEach((a) => a.classList.add('invisible'));
        ttGrid?.classList.add('is-dragging');

        dragGhost = el.cloneNode(true);
        dragGhost.classList.add('tt-drag-ghost');
        dragGhost.classList.remove('is-dragging', 'cursor-grab', 'active:cursor-grabbing');
        dragGhost.querySelectorAll('[data-no-drag], .tt-drag-hint').forEach((n) => n.remove());
        document.body.appendChild(dragGhost);
        e.dataTransfer.setDragImage(dragGhost, 70, 28);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', `${dragSrc.day}|${dragSrc.period}`);
    });

    el.addEventListener('dragend', () => cleanupDrag());
});

document.querySelectorAll('[data-drop-cell]').forEach((cell) => {
    cell.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        setDropTarget(cell);
    });

    cell.addEventListener('dragleave', (e) => {
        if (cell.contains(e.relatedTarget)) return;
        if (activeDropCell === cell) clearDropTargets();
    });

    cell.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!dragSrc) return;

        const toDay = cell.dataset.day;
        const toPeriod = cell.dataset.period;
        if (dragSrc.day === toDay && String(dragSrc.period) === String(toPeriod)) {
            cleanupDrag();
            return;
        }

        cell.classList.add('is-drop-target');
        document.getElementById('swap-from-day').value = dragSrc.day;
        document.getElementById('swap-from-period').value = dragSrc.period;
        document.getElementById('swap-to-day').value = toDay;
        document.getElementById('swap-to-period').value = toPeriod;
        document.getElementById('swap-form').submit();
    });
});
</script>

@if ($errors->has('teacher_id') || $errors->has('subject_id') || $errors->has('period_number'))
    <script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#slot-modal'));</script>
@endif
@if ($errors->has('periods') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'periods.')))
    <script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#generate-modal'));</script>
@endif
@endif
@endsection
