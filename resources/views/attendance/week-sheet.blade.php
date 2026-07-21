@extends('layouts.app')

@section('title', 'Week Sheet — Attendance — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Attendance" :sub="'Weekly register · Sat–Wed · '.$academicYear">
        <x-slot:action>
            @if ($schoolClass)
                <x-btn variant="secondary" :href="route('attendance.week-sheet', ['class' => $schoolClass->id, 'week' => $weekStart, 'fill' => $fill, 'print' => 1])" target="_blank" rel="noopener">
                    <x-icon name="printer" :size="14" /> Print Sheet
                </x-btn>
            @endif
        </x-slot:action>
    </x-section-header>

    @include('attendance.partials.tabs', ['active' => 'week-sheet', 'schoolClass' => $schoolClass])

    @if ($classes->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
            @if (auth()->user()->isTeacher())
                No classes on your timetable yet. Ask an admin to assign you periods first.
            @else
                Create active classes for {{ $academicYear }} before generating a week sheet.
            @endif
        </div>
    @else
        <form method="GET" action="{{ route('attendance.week-sheet') }}" class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Class</label>
                    <select name="class" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Any day in the week</label>
                    <input type="date" name="week" value="{{ $weekAnchor }}" onchange="this.form.submit()"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Sheet contents</label>
                    <select name="fill" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        <option value="empty" @selected($fill === 'empty')>Empty (blank boxes)</option>
                        <option value="marked" @selected($fill === 'marked')>Fill marked days</option>
                    </select>
                </div>
            </div>
        </form>

        @if ($students->isEmpty())
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                No active students enrolled in {{ $schoolClass->displayName() }}.
            </div>
        @else
            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                        {{ $schoolClass->displayName() }} — Week sheet
                        <span class="ml-1 font-medium normal-case text-slate-400">{{ $weekLabel }}</span>
                    </h3>
                    <p class="text-[11px] text-slate-400">
                        {{ $fill === 'marked' ? 'Shows saved marks · blank cells are unmarked' : 'Empty sheet for manual marking' }}
                        · ✓ Present · ◐ Late · ✗ Absent · ⊘ Suspended
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="border border-slate-300 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">#</th>
                                <th class="border border-slate-300 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                                @foreach ($days as $day)
                                    <th class="border border-slate-300 px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                        <div>{{ $day['label'] }}</div>
                                        <div class="mt-0.5 font-medium normal-case text-slate-400">{{ $day['date']->format('j M') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr>
                                    <td class="border border-slate-300 px-3 py-2 font-mono text-xs text-slate-500">{{ $student['roll'] }}</td>
                                    <td class="border border-slate-300 px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">
                                                {{ $student['initials'] }}
                                            </div>
                                            <span class="font-medium text-slate-900">{{ $student['name'] }}</span>
                                        </div>
                                    </td>
                                    @foreach ($days as $day)
                                        @php $mark = $student['days'][$day['key']] ?? null; @endphp
                                        <td class="border border-slate-300 px-2 py-2 text-center align-middle">
                                            @if ($mark && $mark['code'])
                                                <span class="inline-flex min-w-[1.75rem] justify-center text-sm font-bold {{ $mark['status']->toneClass() }}">{{ $mark['code'] }}</span>
                                            @else
                                                <span class="inline-block h-7 w-full max-w-[3.5rem]">&nbsp;</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
