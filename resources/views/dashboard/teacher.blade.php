@extends('layouts.app')

@section('title', 'Dashboard — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold text-slate-900">Good morning, {{ explode(' ', $user->name)[0] }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ now()->format('l, j F Y') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        @foreach ([
            ['Periods Today', '—', 'Timetable in Week 4', 'calendar', true],
            ['Students Total', '—', 'Assigned classes in Week 2–3', 'users', false],
            ['Grades Pending', '—', 'Grades in Week 6', 'graduation-cap', false],
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

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Today's Timetable</h3>
            <p class="py-8 text-center text-sm text-slate-400">No timetable assigned yet — Week 4.</p>
        </div>
        <div class="space-y-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">My Classes</h3>
                <p class="py-6 text-center text-sm text-slate-400">Assigned classes appear after staff setup (Week 3–4).</p>
            </div>
            <a href="{{ route('attendance.index') }}" class="flex w-full items-center justify-center gap-2 rounded-lg bg-dugsi-primary py-3 text-sm font-semibold text-white transition-colors hover:bg-[#162d56]">
                <x-icon name="check-circle" :size="15" />
                Mark Attendance Now
            </a>
        </div>
    </div>
</div>
@endsection
