@extends('layouts.app')

@section('title', 'Mark Attendance — Dugsi ERP')

@section('content')
@php
    use App\Enums\AttendanceStatus;
@endphp

<div class="mx-auto max-w-2xl space-y-4">
    <x-section-header title="Mark Attendance" :sub="'Select class and date, then mark each student · '.$academicYear">
        <x-slot:action>
            @if ($schoolClass)
                <x-btn variant="secondary" :href="route('attendance.print', ['class' => $schoolClass->id, 'date' => $date])" target="_blank" rel="noopener">
                    <x-icon name="printer" :size="14" /> Print View
                </x-btn>
            @endif
        </x-slot:action>
    </x-section-header>

    @include('attendance.partials.tabs', ['active' => 'mark', 'schoolClass' => $schoolClass])

    @if ($classes->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            @if (auth()->user()->isTeacher())
                No classes on your timetable yet. Ask an admin to assign you periods first.
            @else
                Create active classes for {{ $academicYear }} before marking attendance.
            @endif
        </div>
    @else
        <form method="GET" action="{{ route('attendance.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                    <select name="class" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
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
        @elseif (! $isSchoolDay)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ \Illuminate\Support\Carbon::parse($date)->format('l') }} is outside the school week (Sat–Wed). You can still record attendance if needed.
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                No active students enrolled in {{ $schoolClass->displayName() }}.
            </div>
        @elseif ($isFuture)
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                Attendance for {{ $dateLabel }} is not available yet.
            </div>
        @else
            <form method="POST" action="{{ route('attendance.store') }}" id="attendance-form">
                @csrf
                <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="send_sms" id="send-sms-input" value="0">

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                            {{ $schoolClass->displayName() }} — {{ $dateLabel }}
                            @if ($alreadyMarked)
                                <span class="ml-1 font-medium normal-case text-slate-400">(editing saved marks)</span>
                            @endif
                        </h3>
                        <div class="flex flex-wrap gap-3 text-xs font-medium" id="att-counts">
                            <span class="text-green-700">Present: <span data-count="present">0</span></span>
                            <span class="text-amber-700">Late: <span data-count="late">0</span></span>
                            <span class="text-red-700">Absent: <span data-count="absent">0</span></span>
                            <span class="text-slate-600">Suspended: <span data-count="suspended">0</span></span>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php $sid = $row['student']->id; @endphp
                            <div class="att-row px-4 py-3" data-student-id="{{ $sid }}" data-student-name="{{ $row['student']->full_name }}" data-phone="{{ $row['guardian_phone'] ?? '' }}">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <div class="w-5 flex-shrink-0 font-mono text-xs text-slate-400">{{ $row['roll'] }}</div>
                                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">
                                            {{ $row['student']->initials() }}
                                        </div>
                                        <span class="truncate text-sm font-medium text-slate-800">{{ $row['student']->full_name }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1 sm:justify-end" role="group" aria-label="Attendance status">
                                        @foreach ($statuses as $status)
                                            @php $active = $row['status'] === $status->value; @endphp
                                            <label class="cursor-pointer">
                                                <input type="radio"
                                                    name="statuses[{{ $sid }}]"
                                                    value="{{ $status->value }}"
                                                    class="att-status sr-only"
                                                    @checked($active)
                                                    @if ($loop->first) required @endif>
                                                <span class="att-btn {{ $active ? 'is-active' : '' }}" data-status="{{ $status->value }}">
                                                    {{ $status->value }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="att-reason mt-2 sm:ml-16 {{ in_array($row['status'], ['absent', 'suspended'], true) ? '' : 'hidden' }}">
                                    <input type="text" name="reasons[{{ $sid }}]" value="{{ $row['reason'] }}"
                                        placeholder="Reason (optional)"
                                        class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end border-t border-slate-200 px-4 py-3">
                        <button type="button" id="btn-save-attendance"
                            class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                            Save Attendance
                        </button>
                    </div>
                </div>
            </form>

            <div id="sms-modal" class="hidden" data-dugsi-width="28rem">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-900">Send Absence SMS Alerts</h3>
                </div>
                <div class="space-y-3 p-5">
                    <div class="flex items-start gap-2 text-sm text-slate-700">
                        <span class="mt-0.5 text-amber-500" aria-hidden="true">⚠</span>
                        <span><strong id="sms-count">0</strong> absence SMS will be sent to guardians via the configured SMS gateway (or logged as Failed if credentials are not set).</span>
                    </div>
                    <div id="sms-list" class="space-y-2 rounded-md border border-slate-200 bg-slate-50 p-3"></div>
                    <div class="rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-slate-600">
                        <div class="mb-1 font-semibold text-slate-700">SMS Preview (Absence Alert template):</div>
                        @if ($absenceSmsEnabled)
                            <p class="italic" id="sms-preview-text">""</p>
                            <p class="mt-1 text-[11px] text-slate-500" id="sms-preview-note" hidden>Example uses the first absent student; each SMS is personalized.</p>
                        @else
                            <p class="text-amber-800">Absence Alert template is inactive — SMS will not be sent. Enable it under Notifications → Templates.</p>
                            <p class="italic hidden" id="sms-preview-text"></p>
                            <p class="mt-1 text-[11px] text-slate-500 hidden" id="sms-preview-note"></p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end">
                    <button type="button" id="sms-skip" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Skip SMS, Save Only</button>
                    <button type="button" id="sms-confirm" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Confirm &amp; Send SMS</button>
                </div>
            </div>
        @endif
    @endif
</div>

@if ($classes->isNotEmpty() && $rows->isNotEmpty() && ! $isFuture)
<script>
(() => {
    const form = document.getElementById('attendance-form');
    const sendInput = document.getElementById('send-sms-input');
    const smsList = document.getElementById('sms-list');
    const smsCount = document.getElementById('sms-count');
    const smsPreview = document.getElementById('sms-preview-text');
    const smsPreviewNote = document.getElementById('sms-preview-note');
    const absenceTemplate = @json($absenceSmsTemplate);
    const absenceClass = @json($absenceSmsClass);
    const absenceDate = @json($absenceSmsDate);
    const absenceSmsEnabled = @json($absenceSmsEnabled);

    function selectedStatus(row) {
        return row.querySelector('input.att-status:checked')?.value ?? null;
    }

    function syncRow(row) {
        const status = selectedStatus(row);
        row.querySelectorAll('.att-btn').forEach((btn) => {
            btn.classList.toggle('is-active', status !== null && btn.dataset.status === status);
        });
        row.querySelector('.att-reason')?.classList.toggle('hidden', status !== 'absent' && status !== 'suspended');
    }

    function refreshCounts() {
        const counts = { present: 0, late: 0, absent: 0, suspended: 0 };
        document.querySelectorAll('.att-row').forEach((row) => {
            const status = selectedStatus(row);
            if (status && counts[status] !== undefined) counts[status] += 1;
            syncRow(row);
        });
        document.querySelectorAll('[data-count]').forEach((el) => {
            el.textContent = String(counts[el.dataset.count] ?? 0);
        });
    }

    function absences() {
        return [...document.querySelectorAll('.att-row')].filter((row) => selectedStatus(row) === 'absent');
    }

    function unmarkedRows() {
        return [...document.querySelectorAll('.att-row')].filter((row) => !selectedStatus(row));
    }

    function submitWithSms(send) {
        sendInput.value = send ? '1' : '0';
        form.submit();
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    function fillTemplate(template, vars) {
        return String(template)
            .replaceAll('{student_name}', vars.student_name ?? '')
            .replaceAll('{class}', vars.class ?? '')
            .replaceAll('{date}', vars.date ?? '');
    }

    function updateSmsPreview(list) {
        if (! smsPreview || ! absenceSmsEnabled) return;
        const first = list[0];
        const name = first?.dataset.studentName || 'Student';
        smsPreview.textContent = '"' + fillTemplate(absenceTemplate, {
            student_name: name,
            class: absenceClass,
            date: absenceDate,
        }) + '"';
        if (smsPreviewNote) {
            smsPreviewNote.hidden = list.length < 2;
        }
    }

    document.querySelectorAll('input.att-status').forEach((input) => {
        input.addEventListener('change', () => refreshCounts());
    });

    document.getElementById('btn-save-attendance')?.addEventListener('click', () => {
        const unmarked = unmarkedRows();
        if (unmarked.length > 0) {
            window.DugsiUI?.error('Mark a status for every student before saving.');
            unmarked[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const list = absences();
        if (list.length === 0) {
            submitWithSms(false);
            return;
        }
        smsCount.textContent = String(list.length);
        smsList.innerHTML = list.map((row) => {
            const name = escapeHtml(row.dataset.studentName || '');
            const phone = escapeHtml(row.dataset.phone || 'No phone');
            return `<div class="flex items-center justify-between gap-2 text-sm"><span class="text-slate-700">${name}</span><span class="font-mono text-xs text-slate-400">${phone}</span></div>`;
        }).join('');
        updateSmsPreview(list);
        window.DugsiUI?.openModal('#sms-modal');
    });

    document.getElementById('sms-skip')?.addEventListener('click', () => {
        window.DugsiUI?.close();
        submitWithSms(false);
    });
    document.getElementById('sms-confirm')?.addEventListener('click', () => {
        window.DugsiUI?.close();
        submitWithSms(true);
    });

    refreshCounts();
})();
</script>
@endif
@endsection
