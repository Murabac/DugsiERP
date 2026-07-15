@extends('layouts.app')

@section('title', 'Dashboard — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold text-slate-900">Dashboard</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            {{ now()->format('l, j F Y') }} · Academic Year {{ $academicYear }}
            @if ($user->isSuperAdmin())
                · Super Admin
            @endif
        </p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $stat['label'] }}</div>
                        <div class="mt-1 text-2xl font-bold {{ $stat['accent'] ? 'text-dugsi-primary' : 'text-slate-900' }}">{{ $stat['value'] }}</div>
                        <div class="mt-0.5 text-xs text-slate-400">{{ $stat['sub'] }}</div>
                    </div>
                    <div class="rounded-md {{ $stat['accent'] ? 'bg-blue-50 text-dugsi-primary' : 'bg-slate-50 text-slate-500' }} p-2">
                        <x-icon :name="$stat['icon']" :size="17" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Class fill — {{ $academicYear }}</h3>
            @if ($classFill->isEmpty())
                <p class="py-10 text-center text-sm text-slate-400">No active classes yet.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($classFill as $row)
                        <a href="{{ $row['url'] }}" class="block rounded-md border border-slate-100 px-3 py-2 hover:bg-slate-50">
                            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span class="font-medium text-slate-800">{{ $row['name'] }}</span>
                                <span class="text-xs text-slate-500">{{ $row['enrolled'] }}/{{ $row['capacity'] }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $row['pct'] >= 95 ? 'bg-red-500' : ($row['pct'] >= 80 ? 'bg-amber-500' : 'bg-dugsi-primary') }}"
                                    style="width: {{ min($row['pct'], 100) }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
                @if ($classFillTotal > $classFill->count())
                    <a href="{{ route('classes.index') }}" class="mt-3 inline-block text-xs font-medium text-blue-700 hover:underline">
                        View all {{ $classFillTotal }} classes →
                    </a>
                @endif
            @endif
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Quick Actions</h3>
            <div class="space-y-1.5">
                <a href="{{ route('students.create') }}" class="flex w-full items-center gap-2 rounded-md bg-blue-50 px-3 py-2 text-sm font-medium text-dugsi-primary hover:bg-blue-100">Add Student</a>
                <a href="{{ route('staff.index') }}" class="flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Add Staff</a>
                <a href="{{ route('timetable.index') }}" class="flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Timetable</a>
                <a href="{{ route('classes.manage') }}" class="flex w-full items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Manage Classes</a>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Recent Activity</h3>
        @if ($activity->isEmpty())
            <p class="py-6 text-center text-sm text-slate-400">No recent activity yet.</p>
        @else
            <div class="space-y-3">
                @foreach ($activity as $item)
                    @php
                        $tone = match ($item['type']) {
                            'admission', 'enrollment' => 'bg-blue-100 text-blue-700',
                            'staff' => 'bg-indigo-100 text-indigo-700',
                            'timetable' => 'bg-amber-100 text-amber-700',
                            default => 'bg-slate-100 text-slate-600',
                        };
                        $icon = match ($item['type']) {
                            'admission', 'enrollment' => 'users',
                            'staff' => 'briefcase',
                            'timetable' => 'calendar',
                            default => 'bell',
                        };
                    @endphp
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full {{ $tone }}">
                            <x-icon :name="$icon" :size="12" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-700">{{ $item['text'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $item['time'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
