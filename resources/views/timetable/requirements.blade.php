@extends('layouts.app')

@section('title', 'Timetable Requirements — Dugsi ERP')

@section('content')
@php
    $emptyPerClass = (int) ($report['empty_periods_per_class'] ?? 0);
    $filledPerClass = (int) ($report['filled_periods_per_class'] ?? 0);
    $capacity = (int) ($report['weekly_capacity'] ?? 0);
    $classes = (int) ($report['active_classes'] ?? 0);
    $needFt = (int) ($report['ft_teachers_needed_overall'] ?? 0);
    $haveFt = (int) ($report['teachers_on_roster'] ?? 0);
    $shortFt = (int) ($report['teachers_short_overall'] ?? 0);
    $classFill = $report['class_fill'] ?? [];
    $subjectStaffing = $report['subject_staffing'] ?? [];
    $classChartHeight = max(120, count($classFill) * 32);
@endphp
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Timetable', 'url' => route('timetable.index')],
        ['label' => 'Requirements'],
    ]" />

    <x-section-header
        title="Requirements"
        :sub="$report['academic_year'].' · '.($report['day_structure_label'] ?? '')"
    >
        <x-slot:action>
            @if (auth()->user()?->hasPermission('settings.manage'))
                <x-btn variant="secondary" href="{{ route('settings.index', ['tab' => 'academic']) }}">
                    Edit periods
                </x-btn>
            @endif
            <x-btn variant="secondary" href="{{ route('timetable.index') }}">
                Timetable
            </x-btn>
        </x-slot:action>
    </x-section-header>

    {{-- Overall teachers: the main answer --}}
    <div class="rounded-xl border px-5 py-5 sm:px-6 {{ $shortFt > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }}">
        <div class="grid grid-cols-3 gap-4 text-center sm:text-left">
            <div>
                <div class="text-[11px] font-semibold tracking-wider uppercase {{ $shortFt > 0 ? 'text-amber-800' : 'text-emerald-800' }}">Need (FT)</div>
                <div class="mt-1 text-4xl font-bold tabular-nums {{ $shortFt > 0 ? 'text-amber-950' : 'text-emerald-950' }}">{{ $needFt }}</div>
            </div>
            <div>
                <div class="text-[11px] font-semibold tracking-wider uppercase {{ $shortFt > 0 ? 'text-amber-800' : 'text-emerald-800' }}">Have</div>
                <div class="mt-1 text-4xl font-bold tabular-nums {{ $shortFt > 0 ? 'text-amber-950' : 'text-emerald-950' }}">{{ $haveFt }}</div>
            </div>
            <div>
                <div class="text-[11px] font-semibold tracking-wider uppercase {{ $shortFt > 0 ? 'text-amber-800' : 'text-emerald-800' }}">Short</div>
                <div class="mt-1 text-4xl font-bold tabular-nums {{ $shortFt > 0 ? 'text-amber-950' : 'text-emerald-950' }}">{{ $shortFt }}</div>
            </div>
        </div>
        <p class="mt-3 text-xs {{ $shortFt > 0 ? 'text-amber-900' : 'text-emerald-900' }}">
            FT teachers needed if each subject has its own full-time teacher(s) for {{ $classes }} classes.
            {{ $shortFt > 0 ? 'Not enough teachers overall — empty periods and missing subjects follow from this.' : 'Roster covers the plan on paper.' }}
        </p>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <div class="rounded-xl border px-4 py-5 {{ $emptyPerClass > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' }}">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Empty / class</div>
            <div class="mt-1 text-4xl font-bold tabular-nums {{ $emptyPerClass > 0 ? 'text-amber-950' : 'text-slate-900' }}">
                {{ $emptyPerClass }}
            </div>
            <div class="mt-1 text-xs text-slate-500">{{ $filledPerClass }}/{{ $capacity }} filled</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white px-4 py-5">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Required / week</div>
            <div class="mt-1 text-4xl font-bold tabular-nums text-slate-900">{{ $capacity }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $report['day_structure_label'] ?? '' }}</div>
        </div>

        <div class="col-span-2 rounded-xl border border-slate-200 bg-white px-4 py-5 lg:col-span-1">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Classes</div>
            <div class="mt-1 text-4xl font-bold tabular-nums text-slate-900">{{ $classes }}</div>
            <div class="mt-1 text-xs text-slate-500">Active this year</div>
        </div>
    </div>

    @if (! empty($report['plan_message']))
        <p class="text-xs text-amber-800">{{ $report['plan_message'] }}</p>
    @endif

    {{-- Overall staffing chart + per-subject need/have --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Teachers overall</div>
            <div class="relative mx-auto mt-2 h-44 w-full max-w-[220px]">
                <canvas data-dugsi-chart='@json($report['staffing_chart'])'></canvas>
            </div>
            <p class="mt-1 text-center text-xs text-slate-500">{{ $haveFt }} of {{ $needFt }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white lg:col-span-2">
            <div class="border-b border-slate-200 px-4 py-3">
                <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">FT needed by subject</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Subject</th>
                            <th class="px-4 py-2.5 text-right">Lessons</th>
                            <th class="px-4 py-2.5 text-right">Need</th>
                            <th class="px-4 py-2.5 text-right">Have</th>
                            <th class="px-4 py-2.5 text-right">Short</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($subjectStaffing as $row)
                            <tr class="{{ $row['short'] > 0 ? 'bg-amber-50/50' : '' }}">
                                <td class="px-4 py-2.5 font-medium text-slate-900">
                                    {{ $row['subject'] }}
                                    <span class="font-normal text-slate-400">({{ $row['periods_per_class'] }})</span>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">{{ $row['lessons_needed'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-800">{{ $row['ft_needed'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-800">{{ $row['teachers_assigned'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $row['short'] > 0 ? 'font-semibold text-amber-900' : 'text-emerald-700' }}">
                                    {{ $row['short'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No classes yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Empty periods --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Fill / class</div>
            <div class="relative mx-auto mt-2 h-44 w-full max-w-[220px]">
                <canvas data-dugsi-chart='@json($report['week_fill_chart'])'></canvas>
            </div>
            <p class="mt-1 text-center text-xs text-slate-500">Must fill every period in Settings</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <div class="flex items-baseline justify-between gap-2">
                <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Empty periods by class</div>
                <div class="text-xs text-slate-400">of {{ $capacity }}</div>
            </div>
            @if (count($classFill) > 0)
                <div class="mt-2" style="height: {{ $classChartHeight }}px">
                    <canvas data-dugsi-chart='@json($report['class_empty_chart'])'></canvas>
                </div>
            @else
                <p class="mt-6 text-center text-sm text-slate-500">No classes</p>
            @endif
        </div>
    </div>

    @if (count($classFill) > 0)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-2.5">Class</th>
                            <th class="px-4 py-2.5 text-right">Filled</th>
                            <th class="px-4 py-2.5 text-right">Empty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($classFill as $row)
                            <tr class="{{ $row['empty'] > 0 ? 'bg-amber-50/50' : '' }}">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['class_name'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">{{ $row['filled'] }}/{{ $row['required'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $row['empty'] > 0 ? 'font-semibold text-amber-900' : 'text-emerald-700' }}">
                                    {{ $row['empty'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Compact 1 FT check --}}
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <div class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Is 1 FT enough? (max {{ $capacity }} lessons)</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-2.5">Subject</th>
                        <th class="px-4 py-2.5 text-right">Lessons</th>
                        <th class="px-4 py-2.5">1 FT?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['subject_ft_check'] ?? [] as $row)
                        <tr class="{{ $row['ft_enough'] ? '' : 'bg-amber-50/50' }}">
                            <td class="px-4 py-2.5 font-medium text-slate-900">
                                {{ $row['subject'] }}
                                <span class="font-normal text-slate-400">({{ $row['periods_per_class'] }})</span>
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-slate-800">{{ $row['lessons_label'] ?? $row['lessons_needed'] }}</td>
                            <td class="px-4 py-2.5 tabular-nums {{ $row['ft_enough'] ? 'text-emerald-700' : 'font-semibold text-amber-900' }}">
                                {{ $row['ft_enough'] ? 'Yes' : 'No −'.$row['short_by'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-slate-500">No classes yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
