@extends('layouts.app')

@section('title', 'Dashboard — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header
        :title="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.explode(' ', $user->name)[0]"
        :sub="now()->format('l, j F Y').' · Academic Year '.$academicYear"
    />

    @unless ($hasStaffLink)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Your login is not linked to a staff record, so timetable data cannot load. Ask an admin to link your account in Settings → Users.
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ($stats as $i => $stat)
            <x-stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :sub="$stat['sub']"
                :icon="$stat['icon']"
                :accent="$i === 0 || !empty($stat['accent'])"
            />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">
                Today's Timetable — {{ $dayLabel }}
            </h3>
            @if (! $dayKey)
                <p class="py-8 text-center text-sm text-slate-400">No school periods on {{ now()->format('l') }} (school week is Sat–Wed).</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($todayRows as $row)
                        <div class="flex flex-col gap-1 rounded-md border p-2.5 sm:flex-row sm:items-center sm:gap-3 {{ $row['subject'] ? 'border-blue-100 bg-blue-50' : 'border-slate-100 bg-slate-50' }}">
                            <div class="w-auto flex-shrink-0 font-mono text-[11px] text-slate-400 sm:w-24">{{ $row['label'] }}</div>
                            @if ($row['subject'])
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-800">{{ $row['subject'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['class'] }}</div>
                                </div>
                            @else
                                <div class="text-sm italic text-slate-300">Free period</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('timetable.index') }}" class="mt-3 inline-block text-xs font-medium text-blue-700 hover:underline">Open full timetable →</a>
            @endif
        </div>
        <div class="space-y-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">My Classes</h3>
                @if ($myClasses->isEmpty())
                    <p class="py-6 text-center text-sm text-slate-400">No classes on your timetable yet.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($myClasses as $c)
                            <a href="{{ $c['url'] }}" class="flex items-center justify-between py-2 hover:bg-slate-50">
                                <div class="min-w-0 pr-3">
                                    <div class="text-sm font-medium text-slate-800">{{ $c['name'] }}</div>
                                    <div class="truncate text-xs text-slate-400">{{ $c['subjects'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-slate-700">{{ $c['students'] }}</div>
                                    <div class="text-[11px] text-slate-400">students</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <x-btn href="{{ route('attendance.index') }}" class="w-full py-3">
                <x-icon name="check-circle" :size="15" />
                Mark Attendance Now
            </x-btn>
        </div>
    </div>
</div>
@endsection
