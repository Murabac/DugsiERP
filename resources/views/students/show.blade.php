@extends('layouts.app')

@section('title', $student->full_name.' — Dugsi ERP')

@section('content')
@php
    $activeTab = in_array($tab, ['overview', 'guardians', 'attendance', 'grades', 'fees', 'documents'], true) ? $tab : 'overview';
    $className = $schoolClass?->displayName() ?? '—';
@endphp

<div class="space-y-4">
    <x-breadcrumb :items="array_values(array_filter([
        ['label' => 'Classes', 'url' => route('classes.index')],
        $schoolClass ? ['label' => $className, 'url' => route('classes.roster', $schoolClass)] : null,
        $schoolClass ? ['label' => 'Student Roster', 'url' => route('classes.roster', $schoolClass)] : null,
        ['label' => $student->full_name],
    ]))" />

    @if ($waitlist)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            On waitlist for <strong>{{ $waitlist->schoolClass?->displayName() }}</strong>
            (position {{ $waitlist->position }}).
            An admin can enroll this student from the class roster after increasing capacity.
            <a href="{{ route('classes.roster', $waitlist->schoolClass) }}" class="ml-1 font-semibold underline">Open roster</a>
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-100 text-lg font-bold text-dugsi-primary">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" alt="{{ $student->full_name }}" class="h-full w-full object-cover">
                @else
                    {{ $student->initials() }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ $student->full_name }}</h3>
                        <div class="mt-0.5 font-mono text-xs text-slate-500">{{ $student->student_code }} · {{ $className }}</div>
                    </div>
                    <div class="flex flex-shrink-0 gap-2">
                        <x-status-badge :status="$student->status" />
                    </div>
                </div>
                <div class="mt-2.5 flex flex-wrap gap-4">
                    <span class="text-xs text-slate-500">DOB {{ $student->dob->format('Y-m-d') }}</span>
                    <span class="text-xs text-slate-500">{{ $student->city ?? '—' }}</span>
                    <span class="text-xs text-slate-500">{{ $student->primaryGuardian?->phone ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex w-fit gap-1 rounded-lg bg-slate-100 p-1">
        @foreach (['overview', 'guardians', 'attendance', 'grades', 'fees', 'documents'] as $t)
            <a href="{{ route('students.show', ['student' => $student, 'tab' => $t]) }}"
                class="rounded-md px-3 py-1.5 text-xs font-medium capitalize transition-colors {{ $activeTab === $t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                {{ $t }}
            </a>
        @endforeach
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        @if ($activeTab === 'overview')
            <div class="grid grid-cols-2 gap-6 text-sm">
                <div>
                    <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Personal Details</h4>
                    <div class="space-y-2">
                        @foreach ([
                            ['Full Name', $student->full_name],
                            ['Student ID', $student->student_code],
                            ['DOB', $student->dob->format('Y-m-d')],
                            ['Gender', $student->gender->label()],
                            ['City', $student->city ?? '—'],
                            ['Guardian', $student->primaryGuardian?->full_name ?? '—'],
                        ] as [$k, $v])
                            <div class="flex gap-2">
                                <span class="w-24 flex-shrink-0 text-xs text-slate-400">{{ $k }}</span>
                                <span class="text-xs font-medium text-slate-800">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Enrollment</h4>
                    <div class="space-y-2">
                        @foreach ([
                            ['Class', $className],
                            ['Academic Year', $enrollment?->academic_year ?? $waitlist?->academic_year ?? '—'],
                            ['Roll No.', $enrollment ? str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT) : ($waitlist ? 'Waitlist #'.$waitlist->position : '—')],
                            ['Enrolled', $enrollment?->enrollment_date?->format('d M Y') ?? '—'],
                            ['Status', $student->status->label()],
                        ] as [$k, $v])
                            <div class="flex gap-2">
                                <span class="w-24 flex-shrink-0 text-xs text-slate-400">{{ $k }}</span>
                                <span class="text-xs font-medium text-slate-800">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-[11px] font-medium tracking-wide text-slate-500 uppercase">Attendance</div>
                        <div class="mt-0.5 text-sm text-slate-400">Available in Week 5</div>
                    </div>
                </div>
            </div>
        @elseif ($activeTab === 'guardians')
            <div class="space-y-3">
                <h4 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">Guardians</h4>
                @foreach ($student->guardians as $guardian)
                    <div class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-dugsi-primary text-xs font-bold text-white">
                            {{ $guardian->initials() }}
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-slate-800">{{ $guardian->full_name }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $guardian->relationship->label() }}{{ $guardian->is_primary ? ' · Primary Contact' : '' }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ $guardian->phone }}</div>
                        </div>
                        @if ($guardian->is_primary)
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800">Primary</span>
                        @endif
                    </div>
                @endforeach

                <details class="rounded-lg border border-slate-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-dugsi-primary">+ Add Guardian</summary>
                    <form method="POST" action="{{ route('students.guardians.store', $student) }}" class="mt-3 grid grid-cols-2 gap-3">
                        @csrf
                        <div class="col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-700">Full Name</label>
                            <input name="full_name" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                            <input name="phone" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Relationship</label>
                            <select name="relationship" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @foreach (\App\Enums\GuardianRelationship::cases() as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="col-span-2 flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300">
                            Set as primary contact
                        </label>
                        <div class="col-span-2">
                            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Guardian</button>
                        </div>
                    </form>
                </details>
            </div>
        @else
            <div class="py-8 text-center text-slate-400">
                <p class="text-sm font-medium text-slate-500 capitalize">{{ $activeTab }} coming soon</p>
                <p class="mt-1 text-xs">This tab will be filled when the related module is built.</p>
            </div>
        @endif
    </div>
</div>
@endsection
