@extends('layouts.app')

@section('title', $schoolClass->displayName().' Roster — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Classes', 'url' => route('classes.index')],
        ['label' => $schoolClass->displayName()],
        ['label' => 'Student Roster'],
    ]" />

    <x-section-header
        :title="$schoolClass->displayName().' — Student Roster'"
        :sub="($schoolClass->enrolled_count ?? $enrollments->count()).' enrolled · Capacity '.$schoolClass->capacity.(($schoolClass->waitlist_count ?? $waitlist->count()) > 0 ? ' · '.($schoolClass->waitlist_count ?? $waitlist->count()).' on waitlist' : '')"
    >
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('classes.roster.print', $schoolClass) }}">
                <x-icon name="download" :size="14" /> Print roster
            </x-btn>
            @if ($canAdd)
                <x-btn variant="secondary" type="button" data-dugsi-open="#bulk-upload-students-modal" data-dugsi-width="32rem">
                    <x-icon name="upload" :size="14" /> Bulk upload
                </x-btn>
                <x-btn href="{{ route('students.create', ['class' => $schoolClass->id]) }}">
                    <x-icon name="plus" :size="14" /> Add Student
                </x-btn>
            @endif
        </x-slot:action>
    </x-section-header>

    @if (session('bulk_errors'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <div class="font-semibold">Some rows were skipped</div>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                @foreach (session('bulk_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @error('file')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Form Master</div>
            @if ($schoolClass->homeroomTeacher)
                <div class="mt-0.5 truncate text-sm font-medium text-slate-900">
                    {{ $schoolClass->homeroomTeacher->full_name }}
                    <span class="font-mono text-xs font-normal text-slate-400">({{ $schoolClass->homeroomTeacher->employee_code }})</span>
                </div>
            @else
                <div class="mt-0.5 text-sm text-slate-500">No Form Master assigned</div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($schoolClass->homeroomTeacher && auth()->user()?->hasPermission('staff.view'))
                <a href="{{ route('staff.show', $schoolClass->homeroomTeacher) }}"
                    class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline">
                    <x-icon name="eye" :size="12" /> View profile
                </a>
            @endif
            @if ($canAssignFormMaster)
                <button type="button" data-dugsi-open="#assign-form-master-modal" data-dugsi-width="24rem"
                    class="inline-flex items-center gap-1.5 rounded-md {{ $schoolClass->homeroomTeacher ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'bg-dugsi-primary text-white hover:bg-[#162d56]' }} px-3 py-1.5 text-xs font-semibold">
                    {{ $schoolClass->homeroomTeacher ? 'Change' : 'Assign Form Master' }}
                </button>
            @endif
        </div>
    </div>

    @if ($waitlist->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50/60">
            <div class="flex items-center justify-between border-b border-amber-200 px-4 py-3">
                <div>
                    <h3 class="text-sm font-semibold text-amber-900">Waitlist</h3>
                    <p class="mt-0.5 text-xs text-amber-800/80">
                        {{ $waitlist->count() }} waiting · {{ max(0, $schoolClass->capacity - ($schoolClass->enrolled_count ?? 0)) }} open seat(s)
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="border-b border-amber-100 bg-amber-50">
                        @foreach (['#', 'Name', 'Guardian', 'Added', ''] as $h)
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-amber-800/70">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($waitlist as $entry)
                        @php $s = $entry->student; $g = $s->primaryGuardian; @endphp
                        <tr class="border-b border-amber-50">
                            <td class="px-4 py-2.5 font-mono text-xs text-amber-700">{{ $entry->position }}</td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-slate-900">{{ $s->full_name }}</div>
                                <div class="font-mono text-[11px] text-slate-400">{{ $s->student_code }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $g?->full_name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $entry->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('students.show', $s) }}" class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline"><x-icon name="eye" :size="12" /> View</a>
                                    @if ($canEnrollWaitlist)
                                        <form method="POST" action="{{ route('classes.waitlist.enroll', [$schoolClass, $entry]) }}">
                                            @csrf
                                            <button type="submit"
                                                class="text-xs font-semibold text-dugsi-primary hover:underline"
                                                @disabled(($schoolClass->enrolled_count ?? 0) >= $schoolClass->capacity)
                                                title="{{ ($schoolClass->enrolled_count ?? 0) >= $schoolClass->capacity ? 'Increase capacity first' : 'Enroll into class' }}">
                                                Enroll
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white">
        <form method="GET" class="flex flex-col gap-2 border-b border-slate-200 px-3 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:px-4">
            <div class="relative w-full min-w-0 flex-1 sm:min-w-48">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400">⌕</span>
                <input type="search" name="q" value="{{ $search }}"
                    placeholder="Search name or ID within this class…"
                    class="w-full rounded-md border border-slate-300 py-1.5 pr-3 pl-8 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
            </div>
            <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary sm:w-auto">
                <option value="">All Status</option>
                <option value="active" @selected($statusFilter === 'active')>Active</option>
                <option value="waitlisted" @selected($statusFilter === 'waitlisted')>Waitlisted</option>
                <option value="transferred" @selected($statusFilter === 'transferred')>Transferred</option>
                <option value="graduated" @selected($statusFilter === 'graduated')>Graduated</option>
                <option value="suspended" @selected($statusFilter === 'suspended')>Suspended</option>
            </select>
            <button type="submit" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 sm:w-auto">Filter</button>
        </form>

        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    @foreach (['Roll', 'Name', 'Gender', 'City', 'Guardian', 'Status', ''] as $h)
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    @php $s = $enrollment->student; $g = $s->primaryGuardian; @endphp
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-400">{{ str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">
                                    @if ($s->photoUrl())
                                        <img src="{{ $s->photoUrl() }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        {{ $s->initials() }}
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900">{{ $s->full_name }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ $s->student_code }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $s->gender->label() }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $s->city ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $g?->full_name ?? '—' }}</td>
                        <td class="px-4 py-2.5"><x-status-badge :status="$s->status" /></td>
                        <td class="px-4 py-2.5">
                            <a href="{{ route('students.show', $s) }}" class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline"><x-icon name="eye" :size="12" /> View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">
                            @if (request()->filled('q') || request()->filled('status'))
                                No students match your filters.
                            @else
                                No students enrolled in this class yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">
            Showing {{ $enrollments->count() }} students in {{ $schoolClass->displayName() }}
        </div>
    </div>
</div>

@if ($canAdd)
    <div id="bulk-upload-students-modal" class="hidden" data-dugsi-width="32rem">
        <div class="p-5">
            <h3 class="mb-1 text-sm font-semibold text-slate-900">Bulk upload students</h3>
            <p class="mb-4 text-xs text-slate-500">
                Download the Excel template, fill one student per row, then upload to enroll into
                <span class="font-medium text-slate-700">{{ $schoolClass->displayName() }}</span>.
                Student codes are assigned automatically. If the class is full, extra students go on the waitlist.
                Re-uploading the same name + date of birth is skipped for this academic year.
            </p>

            <a href="{{ route('classes.roster.bulk-template', $schoolClass) }}"
                class="mb-4 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <x-icon name="download" :size="14" /> Download Excel template
            </a>

            <form method="POST" action="{{ route('classes.roster.bulk-upload', $schoolClass) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Filled file (.xlsx or .csv, max {{ \App\Support\StudentBulkImport::MAX_ROWS }} rows)</label>
                    <input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-dugsi-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-[#162d56]">
                </div>
                <p class="text-[11px] text-slate-400">
                    Use the dropdowns for gender, city, and relationship. Dates should use Excel date format.
                    See the <span class="font-medium">Instructions</span> sheet in the template. The server still rejects invalid rows.
                </p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Upload students</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($canAssignFormMaster)
    <div id="assign-form-master-modal" class="hidden" data-dugsi-width="24rem">
        <form method="POST" action="{{ route('classes.update', $schoolClass) }}" class="p-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_to" value="roster">
            <input type="hidden" name="form_level" value="{{ $schoolClass->form_level }}">
            <input type="hidden" name="section" value="{{ $schoolClass->section }}">
            <input type="hidden" name="academic_year" value="{{ $schoolClass->academic_year }}">
            <input type="hidden" name="capacity" value="{{ $schoolClass->capacity }}">
            <input type="hidden" name="room" value="{{ $schoolClass->classroom() }}">

            <h3 class="mb-1 text-sm font-semibold text-slate-900">
                {{ $schoolClass->homeroomTeacher ? 'Change Form Master' : 'Assign Form Master' }}
            </h3>
            <p class="mb-4 text-xs text-slate-500">
                The Form Master heads {{ $schoolClass->displayName() }} and can generate grade reports for this class.
            </p>

            <label class="mb-1 block text-xs font-medium text-slate-700">Teacher</label>
            <select name="homeroom_teacher_id" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                <option value="">— Select —</option>
                @foreach ($formMasters as $teacher)
                    <option value="{{ $teacher->id }}" @selected((string) old('homeroom_teacher_id', $schoolClass->homeroom_teacher_id) === (string) $teacher->id)>
                        {{ $teacher->full_name }} ({{ $teacher->employee_code }})
                    </option>
                @endforeach
            </select>
            @error('homeroom_teacher_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Save</button>
            </div>
        </form>
    </div>
@endif
@endsection
