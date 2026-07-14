@extends('layouts.app')

@section('title', 'Dashboard — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold text-slate-900">Dashboard</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            {{ now()->format('l, j F Y') }} · Academic Year 2024–25
            @if ($user->isSuperAdmin())
                · Super Admin
            @endif
        </p>
    </div>

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            ['Total Students', '—', 'Data in Week 2', 'users', true],
            ['Total Staff', '—', 'Data in Week 3', 'briefcase', false],
            ['Fees Collected', '—', 'Data in Week 7', 'dollar-sign', false],
            ['Attendance Today', '—', 'Data in Week 5', 'check-circle', false],
        ] as [$label, $value, $sub, $icon, $accent])
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-bold {{ $accent ? 'text-dugsi-primary' : 'text-slate-900' }}">{{ $value }}</div>
                        <div class="mt-0.5 text-xs text-slate-400">{{ $sub }}</div>
                    </div>
                    <div class="rounded-md {{ $accent ? 'bg-blue-50 text-dugsi-primary' : 'bg-slate-50 text-slate-500' }} p-2">
                        <x-icon :name="$icon" :size="17" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Fee Collection</h3>
            <div class="flex h-40 items-center justify-center rounded-md border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400">
                Chart placeholder — Week 7
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Quick Actions</h3>
            <div class="space-y-1.5">
                <a href="{{ route('classes.index') }}" class="flex w-full items-center gap-2 rounded-md bg-blue-50 px-3 py-2 text-sm font-medium text-dugsi-primary hover:bg-blue-100">Add Student</a>
                <a href="{{ route('staff.index') }}" class="flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Add Staff</a>
                <a href="{{ route('documents.index') }}" class="flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Generate Document</a>
                <a href="{{ route('attendance.index') }}" class="flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Mark Attendance</a>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Recent Activity</h3>
        <p class="py-6 text-center text-sm text-slate-400">No activity yet — modules will populate this feed as they are built.</p>
    </div>
</div>
@endsection
