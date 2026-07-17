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
            <x-btn variant="secondary" href="{{ route('classes.roster', ['schoolClass' => $schoolClass, 'export' => 1]) }}" onclick="window.print(); return false;">
                <x-icon name="download" :size="14" /> Export
            </x-btn>
            @if ($canAdd)
                <x-btn href="{{ route('students.create', ['class' => $schoolClass->id]) }}">
                    <x-icon name="plus" :size="14" /> Add Student
                </x-btn>
            @endif
        </x-slot:action>
    </x-section-header>

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
@endsection
