@extends('layouts.app')

@section('title', 'Transport assignments — Dugsi ERP')

@section('content')
@php
    $seatsFree = $selectedRoute
        ? max(0, $selectedRoute->capacity() - (int) $selectedRoute->active_assignments_count)
        : null;
@endphp

<div class="space-y-4">
    <x-section-header title="Assignments" :sub="'Assign riders · '.$academicYear">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('transport.index') }}">Buses</x-btn>
        </x-slot:action>
    </x-section-header>

    {{-- Filters: pick bus + narrow the student list --}}
    <form method="GET" action="{{ route('transport.assignments.index') }}"
        class="flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white p-3">
        <div class="min-w-[180px] flex-1">
            <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-500">Bus</label>
            <select name="route" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Select bus…</option>
                @foreach ($routes as $r)
                    <option value="{{ $r->id }}" @selected($routeId === $r->id)>
                        {{ $r->displayName() }} · {{ $r->active_assignments_count }}/{{ $r->capacity() }} seats
                    </option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-500">Class</label>
            <select name="class" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All classes</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" @selected($classId === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[160px] flex-1">
            <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-500">Search</label>
            <input type="search" name="q" value="{{ $q }}" placeholder="Name or code…"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white">Apply</button>
    </form>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
        {{-- Bulk assign --}}
        <div class="rounded-lg border border-slate-200 bg-white lg:col-span-3">
            <form method="POST" action="{{ route('transport.assignments.store') }}" id="bulk-assign-form">
                @csrf
                <input type="hidden" name="class" value="{{ $classId ?: '' }}">
                <input type="hidden" name="started_on" value="{{ now()->toDateString() }}">

                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Unassigned students</h3>
                        <p class="text-xs text-slate-500">
                            {{ $unassignedStudents->count() }} available
                            @if ($seatsFree !== null)
                                · <span class="font-medium text-slate-700">{{ $seatsFree }} seats free</span> on {{ $selectedRoute->displayName() }}
                            @endif
                        </p>
                    </div>
                    @if ($selectedRoute)
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700">
                            <input type="checkbox" id="select-all" class="rounded border-slate-300">
                            Select all visible
                        </label>
                    @endif
                </div>

                @if (! $selectedRoute)
                    <div class="px-4 py-12 text-center text-sm text-slate-400">
                        Choose a bus above to assign students.
                    </div>
                @elseif ($unassignedStudents->isEmpty())
                    <div class="px-4 py-12 text-center text-sm text-slate-400">
                        No unassigned students match these filters.
                    </div>
                @else
                    <input type="hidden" name="route_id" value="{{ $selectedRoute->id }}">
                    <div class="max-h-[28rem] overflow-y-auto">
                        <ul class="divide-y divide-slate-50">
                            @foreach ($unassignedStudents as $s)
                                <li>
                                    <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 hover:bg-slate-50">
                                        <input type="checkbox" name="student_ids[]" value="{{ $s->id }}"
                                            class="student-check rounded border-slate-300"
                                            @checked(collect(old('student_ids', []))->contains($s->id))>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-slate-900">{{ $s->full_name }}</span>
                                            <span class="block text-[11px] text-slate-400">
                                                <span class="font-mono">{{ $s->student_code }}</span>
                                                · {{ $s->currentEnrollment?->schoolClass?->displayName() ?? '—' }}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @error('student_ids')
                        <p class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('route_id')
                        <p class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror

                    <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3">
                        <p class="text-xs text-slate-600">
                            <span id="selected-count" class="font-semibold text-slate-900">0</span> selected
                            @if ($seatsFree !== null)
                                · {{ $seatsFree }} seats free
                            @endif
                        </p>
                        <button type="submit" id="assign-btn" disabled
                            class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56] disabled:cursor-not-allowed disabled:opacity-40">
                            Assign to {{ $selectedRoute->displayName() }}
                        </button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Current riders --}}
        <div class="rounded-lg border border-slate-200 bg-white lg:col-span-2">
            <div class="border-b border-slate-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-900">
                    @if ($selectedRoute)
                        On {{ $selectedRoute->displayName() }}
                    @else
                        Active assignments
                    @endif
                </h3>
                <p class="text-xs text-slate-500">{{ $assignments->count() }} rider{{ $assignments->count() === 1 ? '' : 's' }}</p>
            </div>
            <div class="max-h-[32rem] overflow-y-auto">
                <ul class="divide-y divide-slate-50">
                    @forelse ($assignments as $a)
                        <li class="flex items-start justify-between gap-2 px-4 py-2.5">
                            <div class="min-w-0">
                                <a href="{{ route('students.show', ['student' => $a->student, 'tab' => 'transport']) }}"
                                    class="text-sm font-medium text-blue-700 hover:underline">{{ $a->student?->full_name }}</a>
                                <div class="text-[11px] text-slate-400">
                                    {{ $a->student?->currentEnrollment?->schoolClass?->displayName() ?? '—' }}
                                    @unless ($selectedRoute)
                                        · {{ $a->route?->displayName() }}
                                    @endunless
                                </div>
                            </div>
                            <form method="POST" action="{{ route('transport.assignments.end', $a) }}"
                                data-dugsi-confirm="End transport for {{ $a->student?->full_name }}?"
                                data-dugsi-confirm-title="End assignment"
                                data-dugsi-confirm-ok="End"
                                data-dugsi-danger>
                                @csrf
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline">End</button>
                            </form>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-slate-400">
                            @if ($selectedRoute)
                                No students on this bus yet.
                            @else
                                Select a bus to see its riders, or assign students first.
                            @endif
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@if ($selectedRoute && $unassignedStudents->isNotEmpty())
@push('scripts')
<script>
(() => {
    const checks = [...document.querySelectorAll('.student-check')];
    const selectAll = document.getElementById('select-all');
    const countEl = document.getElementById('selected-count');
    const btn = document.getElementById('assign-btn');
    const seatsFree = {{ (int) $seatsFree }};

    function sync() {
        const n = checks.filter(c => c.checked).length;
        if (countEl) countEl.textContent = String(n);
        if (btn) btn.disabled = n === 0;
        if (selectAll) {
            selectAll.checked = n > 0 && n === checks.length;
            selectAll.indeterminate = n > 0 && n < checks.length;
        }
    }

    selectAll?.addEventListener('change', () => {
        let remaining = seatsFree;
        checks.forEach(c => {
            if (!selectAll.checked) {
                c.checked = false;
                return;
            }
            c.checked = remaining > 0;
            if (c.checked) remaining--;
        });
        sync();
    });

    checks.forEach(c => c.addEventListener('change', () => {
        if (c.checked && checks.filter(x => x.checked).length > seatsFree) {
            c.checked = false;
            window.DugsiUI?.warning(`Only ${seatsFree} seat${seatsFree === 1 ? '' : 's'} free on this bus.`);
        }
        sync();
    }));

    document.getElementById('bulk-assign-form')?.addEventListener('submit', async (e) => {
        const form = e.target;
        if (form.dataset.dugsiConfirmed === '1') {
            return;
        }

        e.preventDefault();
        const n = checks.filter(c => c.checked).length;
        if (n === 0) {
            return;
        }

        const ok = await window.DugsiUI?.confirm({
            title: 'Assign to bus',
            text: `Assign ${n} student${n === 1 ? '' : 's'} to this bus?`,
            confirmText: 'Assign',
            cancelText: 'Cancel',
            icon: 'question',
        });

        if (ok) {
            form.dataset.dugsiConfirmed = '1';
            form.requestSubmit();
        }
    });

    sync();
})();
</script>
@endpush
@endif
@endsection
