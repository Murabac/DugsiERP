@extends('layouts.app')

@section('title', 'Documents — Dugsi ERP')

@section('content')
@php
    $selectedType = old('document_type', request('type', 'report_card'));
    $selectedClassId = (int) old('class_id', request('class', 0));
    $selectedStudentId = (int) old('student_id', request('student', 0));
@endphp
<div class="space-y-4">
    <x-section-header title="Documents" :sub="'Generate printable certificates and records · Academic Year '.$academicYear" />

    <x-tabs :active="$tab" :tabs="[
        ['key' => 'generate', 'label' => 'Generate', 'href' => route('documents.index', ['tab' => 'generate'])],
        ['key' => 'history', 'label' => 'History', 'href' => route('documents.index', ['tab' => 'history'])],
    ]" />

    @if ($tab === 'generate')
        <form method="POST" action="{{ route('documents.store') }}" id="doc-form" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @csrf
            <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 lg:col-span-1">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Document Options</h3>

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <x-select label="Class" name="class_id" id="class_id" required>
                    <option value="">Select class…</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClassId === $class->id)>{{ $class->displayName() }}</option>
                    @endforeach
                </x-select>

                @php $studentSelectLocked = $selectedClassId === 0; @endphp
                <x-select label="Student" name="student_id" id="student_id" required :disabled="$studentSelectLocked">
                    <option value="">{{ $studentSelectLocked ? 'Select a class first…' : 'Select student…' }}</option>
                    @foreach ($students as $s)
                        @php
                            $enrolledClassId = (int) ($s->enrollments->first()?->class_id ?? 0);
                            $inSelectedClass = $selectedClassId > 0 && $enrolledClassId === $selectedClassId;
                        @endphp
                        <option
                            value="{{ $s->id }}"
                            data-class="{{ $enrolledClassId }}"
                            data-name="{{ $s->full_name }}"
                            data-code="{{ $s->student_code }}"
                            @selected($selectedStudentId === $s->id && $inSelectedClass)
                            @if (! $inSelectedClass) hidden disabled @endif
                        >
                            {{ $s->full_name }} ({{ $s->student_code }})
                        </option>
                    @endforeach
                </x-select>

                <div>
                    <div class="mb-1 text-xs font-medium text-slate-700">Document type</div>
                    <div class="space-y-1" id="type-buttons">
                        @foreach ($types as $type)
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors {{ $selectedType === $type->value ? 'border-dugsi-primary bg-blue-50 text-dugsi-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                                <input type="radio" name="document_type" value="{{ $type->value }}" class="sr-only" @checked($selectedType === $type->value)>
                                <span class="font-medium">{{ $type->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div data-doc-field="term" class="hidden">
                    <x-select label="Term" name="term">
                        <option value="all" @selected(old('term', 'Term 2') === 'all')>All Terms</option>
                        @foreach ($terms as $term)
                            <option value="{{ $term->value }}" @selected(old('term', 'Term 2') === $term->value)>{{ $term->label() }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div data-doc-field="transfer" class="hidden space-y-3">
                    <x-field label="Reason for leaving" name="reason" :value="old('reason')" />
                    <x-field label="Date of leaving" name="date_of_leaving" type="date" :value="old('date_of_leaving', now()->toDateString())" />
                    <x-field label="Conduct" name="conduct" :value="old('conduct', 'Good')" />
                    <x-field label="Academic progress" name="academic_progress" :value="old('academic_progress', 'Satisfactory')" />
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <x-btn type="submit" name="intent" value="print"><x-icon name="printer" :size="14" /> Generate &amp; Print</x-btn>
                    <x-btn type="submit" name="intent" value="pdf" variant="secondary">
                        <x-icon name="download" :size="14" /> PDF
                    </x-btn>
                </div>
                <p class="text-[11px] text-slate-400">PDF opens the print dialog (Save as PDF). Duplicate clicks reuse the same document for 15 minutes.</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Preview — <span id="preview-type-label">Report Card</span></h3>
                    <span class="text-[11px] text-slate-400">Real student data · print creates a log entry</span>
                </div>
                <div id="doc-preview" class="min-h-[420px] rounded-md border border-slate-100 bg-slate-50 p-4 text-sm">
                    <div class="flex h-96 items-center justify-center text-sm text-slate-400">Select a class and student to preview.</div>
                </div>
            </div>
        </form>

        <script>
            (function () {
                const previewUrl = @json(route('documents.preview'));
                const typeLabels = @json(collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()]));
                const form = document.getElementById('doc-form');
                const preview = document.getElementById('doc-preview');
                const typeLabelEl = document.getElementById('preview-type-label');
                const classEl = document.getElementById('class_id');
                const studentEl = document.getElementById('student_id');
                let previewSeq = 0;

                function selectedType() {
                    return form.querySelector('input[name="document_type"]:checked')?.value || 'report_card';
                }
                function esc(value) {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }
                function emptyState(message) {
                    preview.innerHTML = `<div class="flex h-96 items-center justify-center px-6 text-center text-sm text-slate-400">${esc(message)}</div>`;
                }
                function syncStudents() {
                    const classId = classEl.value;
                    const prev = studentEl.value;
                    let keep = false;
                    Array.from(studentEl.options).forEach((opt, i) => {
                        if (i === 0) {
                            opt.textContent = classId ? 'Select student…' : 'Select a class first…';
                            opt.hidden = false;
                            opt.disabled = false;
                            return;
                        }
                        const match = classId && opt.dataset.class === classId;
                        opt.hidden = !match;
                        opt.disabled = !match;
                        if (match && opt.value === prev) keep = true;
                    });
                    studentEl.disabled = !classId;
                    studentEl.value = keep ? prev : '';
                    renderPreview();
                }
                function syncFields() {
                    const t = selectedType();
                    document.querySelectorAll('[data-doc-field]').forEach((el) => {
                        const key = el.getAttribute('data-doc-field');
                        let show = false;
                        if (key === 'term') show = t === 'report_card';
                        if (key === 'transfer') show = t === 'transfer_certificate';
                        el.classList.toggle('hidden', !show);
                    });
                    document.querySelectorAll('#type-buttons label').forEach((label) => {
                        const checked = label.querySelector('input')?.checked;
                        label.className = 'flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ' +
                            (checked ? 'border-dugsi-primary bg-blue-50 text-dugsi-primary' : 'border-slate-200 text-slate-700 hover:bg-slate-50');
                    });
                    typeLabelEl.textContent = typeLabels[t] || t;
                    renderPreview();
                }
                function formParams() {
                    const params = new URLSearchParams();
                    params.set('document_type', selectedType());
                    if (classEl.value) params.set('class_id', classEl.value);
                    if (studentEl.value) params.set('student_id', studentEl.value);
                    const term = form.querySelector('[name="term"]')?.value;
                    if (term) params.set('term', term);
                    ['reason', 'date_of_leaving', 'conduct', 'academic_progress'].forEach((name) => {
                        const el = form.querySelector(`[name="${name}"]`);
                        if (el && el.value) params.set(name, el.value);
                    });
                    return params;
                }
                async function renderPreview() {
                    const seq = ++previewSeq;
                    if (!classEl.value || !studentEl.value) {
                        emptyState('Select a class and student to preview.');
                        return;
                    }
                    preview.innerHTML = `<div class="flex h-96 items-center justify-center text-sm text-slate-400">Loading preview…</div>`;
                    try {
                        const res = await fetch(`${previewUrl}?${formParams().toString()}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json();
                        if (seq !== previewSeq) return;
                        if (!res.ok || !data.ok) {
                            emptyState(data.message || 'Unable to load preview for this selection.');
                            return;
                        }
                        paintPreview(data);
                    } catch (e) {
                        if (seq !== previewSeq) return;
                        emptyState('Unable to load preview. Try again.');
                    }
                }
                function paintPreview(d) {
                    const name = esc(d.student?.name);
                    const code = esc(d.student?.code);
                    const schoolName = esc(d.school_name);
                    const schoolSub = esc(d.school_sub);
                    const year = esc(d.academic_year);
                    const className = esc(d.class || '—');

                    if (d.type === 'certificate_completion') {
                        const formLabel = d.form_level ? `Form ${esc(d.form_level)}` : className;
                        preview.innerHTML = `<div class="relative overflow-hidden rounded-lg bg-white p-8 text-center" style="box-shadow:0 0 0 2px #1e3a6e,0 0 0 5px #f1f5f9,0 0 0 8px #1e3a6e">
                            <div class="text-[10px] uppercase tracking-[0.3em] text-slate-400">Republic of Somaliland</div>
                            <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">${schoolName}</div>
                            <div class="mx-auto my-4 flex h-16 w-16 items-center justify-center rounded-full border-2 border-[#1e3a6e] bg-blue-50 text-xs font-bold text-[#1e3a6e]">Seal</div>
                            <div class="font-serif text-2xl font-bold text-[#0f2744]">Certificate of Completion</div>
                            <div class="mt-4 text-[11px] uppercase tracking-wider text-slate-400">This is to certify that</div>
                            <div class="mt-2 font-serif text-2xl font-bold text-[#1e3a6e]">${name}</div>
                            <p class="mx-auto mt-3 max-w-sm text-xs leading-relaxed text-slate-500">has successfully completed the Secondary Education programme and is awarded this certificate.</p>
                            <div class="mt-4 font-serif text-sm font-semibold text-[#0f2744]">${formLabel} · ${className} · Academic Year ${year}</div>
                        </div>`;
                        return;
                    }
                    if (d.type === 'transfer_certificate') {
                        preview.innerHTML = `<div class="relative overflow-hidden rounded-lg border border-slate-200 bg-white p-6">
                            <div class="absolute inset-y-0 left-0 w-1 bg-[#1e3a6e]"></div>
                            <div class="mb-4 flex items-start justify-between border-b-2 border-[#1e3a6e] pb-3 pl-2">
                                <div><div class="text-base font-bold text-[#1e3a6e]">${schoolName}</div><div class="text-xs text-slate-500">${schoolSub}</div></div>
                                <div class="font-serif text-sm font-semibold text-[#1e3a6e]">Transfer Certificate</div>
                            </div>
                            <p class="pl-2 text-sm text-slate-700">This is to certify that <strong>${name}</strong> (ID <span class="font-mono">${code}</span>) was a bona fide student of <strong>${className}</strong>.</p>
                            <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 pl-2 text-xs">
                                <div><span class="text-slate-400">Reason</span> · ${esc(d.reason)}</div>
                                <div><span class="text-slate-400">Conduct</span> · ${esc(d.conduct)}</div>
                                <div><span class="text-slate-400">Progress</span> · ${esc(d.academic_progress)}</div>
                                <div><span class="text-slate-400">Leaving</span> · ${esc(d.date_of_leaving)}</div>
                            </div>
                        </div>`;
                        return;
                    }
                    if (d.type === 'student_id_card') {
                        preview.innerHTML = `<div class="mx-auto w-72 overflow-hidden rounded-xl bg-white shadow-lg">
                            <div class="bg-gradient-to-r from-[#0f2744] to-[#1e3a6e] px-4 py-3 text-white"><div class="text-sm font-bold">${schoolName}</div><div class="text-[10px] opacity-80">${schoolSub}</div></div>
                            <div class="grid grid-cols-[72px_1fr] gap-3 p-4">
                                <div class="flex h-24 items-center justify-center rounded-lg bg-blue-50 text-lg font-bold text-[#1e3a6e]">${esc(d.student?.initials || 'ID')}</div>
                                <div>
                                    <div class="font-bold text-slate-900">${name}</div>
                                    <div class="mt-2 space-y-1 text-xs text-slate-500">
                                        <div class="font-mono">${code}</div>
                                        <div>${className}</div>
                                        <div>AY ${year}</div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                        return;
                    }

                    const rows = (d.rows || []).map((row) => {
                        if (d.all_terms) {
                            const termCells = (d.terms || []).map((label) => {
                                const score = row.term_scores ? row.term_scores[label] : null;
                                return `<td class="px-2 py-1 text-center">${score != null ? Number(score).toFixed(1) : '—'}</td>`;
                            }).join('');
                            const total = row.average != null ? Number(row.average).toFixed(1) : '—';
                            const pct = row.average != null ? `${Number(row.average).toFixed(1)}%` : '—';
                            const letter = row.letter || '—';
                            return `<tr class="border-b border-slate-100">
                                <td class="px-2 py-1">${esc(row.subject)}</td>
                                ${termCells}
                                <td class="px-2 py-1 text-center">${esc(total)}</td>
                                <td class="px-2 py-1 text-center">${esc(pct)}</td>
                                <td class="px-2 py-1 text-center font-bold">${esc(letter)}</td>
                            </tr>`;
                        }
                        const marks = row.marks != null ? Number(row.marks).toFixed(1) : '—';
                        const percent = row.percent != null ? `${Number(row.percent).toFixed(1)}%` : '—';
                        const letter = row.letter || '—';
                        return `<tr class="border-b border-slate-100">
                            <td class="px-2 py-1">${esc(row.subject)}</td>
                            <td class="px-2 py-1 text-center">${esc(marks)}</td>
                            <td class="px-2 py-1 text-center">${esc(percent)}</td>
                            <td class="px-2 py-1 text-center font-bold">${esc(letter)}</td>
                            <td class="px-2 py-1 text-slate-500">${esc(row.remarks || '—')}</td>
                        </tr>`;
                    }).join('');
                    const avg = d.average != null
                        ? (d.all_terms
                            ? `${Number(d.average).toFixed(1)}/100 (${Number(d.average).toFixed(1)}%)${d.average_letter ? ' (' + esc(d.average_letter) + ')' : ''}`
                            : `${d.average_marks != null ? Number(d.average_marks).toFixed(1) : '—'} (${Number(d.average).toFixed(1)}%)${d.average_letter ? ' (' + esc(d.average_letter) + ')' : ''}`)
                        : '—';
                    const rank = d.rank != null ? `${d.rank} of ${d.class_size}` : '—';
                    const attendance = d.attendance_rate != null ? `${d.attendance_rate}%` : '—';
                    const roll = d.roll ? ` · Roll ${esc(d.roll)}` : '';
                    const headCols = d.all_terms
                        ? `<th class="px-2 py-1.5 text-left">Subject</th>${(d.terms || []).map((t) => `<th class="px-2 py-1.5 text-center">${esc(t)}</th>`).join('')}<th class="px-2 py-1.5 text-center">Total</th><th class="px-2 py-1.5 text-center">%</th><th class="px-2 py-1.5 text-center">Grade</th>`
                        : `<th class="px-2 py-1.5 text-left">Subject</th><th class="px-2 py-1.5 text-center">Score</th><th class="px-2 py-1.5 text-center">%</th><th class="px-2 py-1.5 text-center">Grade</th><th class="px-2 py-1.5 text-left">Remarks</th>`;
                    const emptyColspan = d.all_terms ? (3 + (d.terms || []).length) : 5;
                    const avgLabel = d.all_terms ? 'Overall average' : 'Term average';

                    preview.innerHTML = `<div class="rounded-lg border border-slate-200 bg-white p-6">
                        <div class="mb-4 flex items-start justify-between border-b border-slate-200 pb-4">
                            <div><div class="text-base font-bold text-[#1e3a6e]">${schoolName}</div><div class="text-xs text-slate-500">${schoolSub}</div></div>
                            <div class="text-right"><div class="text-xs font-bold uppercase tracking-wider text-slate-700">Report Card</div><div class="text-xs text-slate-400">${esc(d.term)} · ${year}</div></div>
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-2 text-xs">
                            <div><span class="text-slate-400">Name</span> <span class="font-medium text-slate-800">${name}</span></div>
                            <div><span class="text-slate-400">ID</span> <span class="font-mono font-medium">${code}</span></div>
                            <div><span class="text-slate-400">Class</span> <span class="font-medium">${className}${roll}</span></div>
                            <div><span class="text-slate-400">Guardian</span> <span class="font-medium">${esc(d.guardian || '—')}</span></div>
                            <div><span class="text-slate-400">Attendance</span> <span class="font-medium">${esc(attendance)}</span></div>
                        </div>
                        <div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr class="bg-[#1e3a6e] text-white">${headCols}</tr></thead>
                        <tbody>${rows || `<tr><td colspan="${emptyColspan}" class="px-2 py-6 text-center text-slate-400">No subjects / scores yet</td></tr>`}</tbody></table></div>
                        <div class="mt-4 flex justify-between border-t border-slate-200 pt-3 text-xs">
                            <div><span class="text-slate-400">${avgLabel}</span> <span class="font-semibold text-slate-800">${avg}</span></div>
                            <div><span class="text-slate-400">Class rank</span> <span class="font-semibold text-slate-800">${esc(rank)}</span></div>
                        </div>
                    </div>`;
                }

                form.querySelectorAll('input[name="document_type"]').forEach((el) => el.addEventListener('change', syncFields));
                classEl.addEventListener('change', syncStudents);
                studentEl.addEventListener('change', renderPreview);
                form.querySelector('[name="term"]')?.addEventListener('change', renderPreview);
                ['reason', 'date_of_leaving', 'conduct', 'academic_progress'].forEach((name) => {
                    form.querySelector(`[name="${name}"]`)?.addEventListener('input', renderPreview);
                });
                syncStudents();
                syncFields();
            })();
        </script>
    @else
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Document History</h3>
                <form method="GET" action="{{ route('documents.index') }}" class="flex gap-2">
                    <input type="hidden" name="tab" value="history">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search student or doc ID…"
                        class="w-56 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    <x-btn type="submit" variant="secondary" size="sm"><x-icon name="search" :size="12" /> Search</x-btn>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Doc ID</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Type</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Generated</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">By</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $doc)
                            <tr class="border-b border-slate-50">
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $doc->document_number }}</td>
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $doc->student?->full_name ?? '—' }}</td>
                                <td class="px-4 py-2.5"><x-status-badge status="info" :label="$doc->document_type->label()" /></td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $doc->generated_at?->format('j M Y, H:i') }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $doc->generatedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <a href="{{ route('documents.print', $doc) }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline">
                                        <x-icon name="printer" :size="12" /> Reprint
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No documents generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
