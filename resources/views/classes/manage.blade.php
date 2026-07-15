@extends('layouts.app')

@section('title', 'Manage Classes — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Classes', 'url' => route('classes.index')],
        ['label' => 'Manage Classes'],
    ]" />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Manage Classes</h2>
            <p class="mt-0.5 text-xs text-slate-500">Create, edit, or archive class sections</p>
        </div>
        <button type="button" data-dugsi-open="#add-class-modal"
            class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56] sm:w-auto">
            + Add Class
        </button>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Classes</div>
            <div class="mt-1 text-2xl font-bold text-dugsi-primary">{{ $classes->count() }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Enrolled</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $totalStudents }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Capacity</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $totalCapacity }}</div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    @foreach (['Class', 'Form', 'Section', 'Year', 'Headmaster', 'Enrolled', 'Waitlist', 'Capacity', 'Fill Rate', 'Status', 'Actions'] as $h)
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $class)
                    @php
                        $enrolled = $class->enrolled_count ?? 0;
                        $waiting = $class->waitlist_count ?? 0;
                        $pct = $class->capacity > 0 ? (int) round(($enrolled / $class->capacity) * 100) : 0;
                        $bar = $pct >= 95 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-green-500');
                    @endphp
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ $class->displayName() }}</td>
                        <td class="px-4 py-2.5 text-slate-500">Form {{ $class->form_level }}</td>
                        <td class="px-4 py-2.5 text-slate-500">Section {{ $class->section }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $class->academic_year }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $class->homeroomTeacher?->full_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-medium text-slate-700">{{ $enrolled }}</td>
                        <td class="px-4 py-2.5">
                            @if ($waiting > 0)
                                <a href="{{ route('classes.roster', $class) }}" class="font-medium text-amber-700 hover:underline">{{ $waiting }}</a>
                            @else
                                <span class="text-slate-400">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $class->capacity }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 max-w-20 flex-1 rounded-full bg-slate-200">
                                    <div class="h-1.5 rounded-full {{ $bar }}" style="width: {{ min($pct, 100) }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5"><x-status-badge :status="$class->status" /></td>
                        <td class="px-4 py-2.5">
                            <div class="flex gap-3">
                                <button type="button"
                                    data-dugsi-open="#edit-class-{{ $class->id }}"
                                    class="text-xs text-blue-700 hover:underline">Edit</button>
                                @if ($class->status->value === 'active')
                                    <form method="POST" action="{{ route('classes.archive', $class) }}"
                                        data-dugsi-confirm="{{ $enrolled > 0 ? 'Archive this class? Students are still enrolled.' : 'Archive this class?' }}"
                                        data-dugsi-confirm-title="Archive class"
                                        data-dugsi-confirm-ok="Archive"
                                        data-dugsi-danger>
                                        @csrf
                                        <button type="submit" class="text-xs text-red-500 hover:underline">Archive</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-4 py-10 text-center text-sm text-slate-400">No classes created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach ($classes as $class)
    <div id="edit-class-{{ $class->id }}" class="hidden" data-dugsi-width="28rem">
        <form method="POST" action="{{ route('classes.update', $class) }}" class="p-5">
            @csrf
            @method('PUT')
            <h3 class="mb-4 text-sm font-semibold text-slate-900">Edit Class</h3>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Form Level</label>
                    <select name="form_level" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                        @foreach ([1,2,3,4] as $f)
                            <option value="{{ $f }}" @selected(old('form_level', $class->form_level) == $f)>Form {{ $f }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Section</label>
                    <select name="section" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                        @foreach (['A','B','C','D'] as $s)
                            <option value="{{ $s }}" @selected(old('section', $class->section) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Academic Year</label>
                    @if (($class->enrolled_count ?? 0) > 0)
                        <input type="hidden" name="academic_year" value="{{ $class->academic_year }}">
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $class->academic_year }}</div>
                        <p class="mt-1 text-[11px] text-slate-400">Locked while students are enrolled</p>
                    @else
                        <select name="academic_year" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                            @foreach ($academicYears as $y)
                                <option value="{{ $y }}" @selected(old('academic_year', $class->academic_year) === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('academic_year')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Capacity</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $class->capacity) }}" min="1" max="100" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Class Headmaster</label>
                    <select name="homeroom_teacher_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        <option value="">— None —</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected((string) old('homeroom_teacher_id', $class->homeroom_teacher_id) === (string) $teacher->id)>
                                {{ $teacher->full_name }} ({{ $teacher->employee_code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400">Only the headmaster (or Admin) can generate student grade reports for this class.</p>
                    @error('homeroom_teacher_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Save</button>
            </div>
        </form>
    </div>
@endforeach

<div id="add-class-modal" class="hidden" data-dugsi-width="28rem">
    <form method="POST" action="{{ route('classes.store') }}" class="p-5">
        @csrf
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Add New Class</h3>
        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Form Level <span class="text-red-500">*</span></label>
                <select name="form_level" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                    <option value="">Select form</option>
                    @foreach ([1,2,3,4] as $f)
                        <option value="{{ $f }}" @selected(old('form_level') == $f)>Form {{ $f }}</option>
                    @endforeach
                </select>
                @error('form_level')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Section <span class="text-red-500">*</span></label>
                <select name="section" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                    @foreach (['A','B','C','D'] as $s)
                        <option value="{{ $s }}" @selected(old('section', 'A') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                @error('section')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Academic Year</label>
                <select name="academic_year" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
                    @foreach ($academicYears as $y)
                        <option value="{{ $y }}" @selected(old('academic_year', $academicYear) === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Capacity</label>
                <input type="number" name="capacity" value="{{ old('capacity', 30) }}" min="1" max="100" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" required>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Classroom <span class="text-red-500">*</span></label>
                <input type="text" name="room" value="{{ old('room') }}" maxlength="32" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary" placeholder="e.g. R-1A">
                <p class="mt-1 text-[11px] text-slate-400">Assigned at registration — teachers come to this room</p>
                @error('room')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Class Headmaster</label>
                <select name="homeroom_teacher_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    <option value="">— Optional —</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) old('homeroom_teacher_id') === (string) $teacher->id)>
                            {{ $teacher->full_name }} ({{ $teacher->employee_code }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-400">Headmasters can generate grade report cards for this class.</p>
                @error('homeroom_teacher_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Create Class</button>
        </div>
    </form>
</div>

@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#add-class-modal'));
</script>
@endif
@endsection
