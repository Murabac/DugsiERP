{{-- Shared timetable grid. Expects: $days, $periods, $grid, $dayLabels, $subjectColors, $modeCell ('teacher'|'class'|'admin'), optional $schoolClass, $highlightTeacherId, $canEdit --}}
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="tt-grid w-full border-collapse text-sm">
        <thead>
            <tr class="bg-dugsi-sidebar text-white">
                <th class="w-28 px-3 py-3 text-left text-xs font-semibold">Period</th>
                @foreach ($days as $day)
                    <th class="px-2 py-3 text-center text-xs font-semibold">{{ $dayLabels[$day] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($periods as $period)
                <tr class="border-b border-slate-100">
                    <td class="border-r border-slate-200 bg-slate-50 px-3 py-2">
                        <div class="text-xs font-bold text-slate-700">P{{ $period['period'] }}</div>
                        <div class="font-mono text-[11px] text-slate-400">{{ $period['label'] }}</div>
                    </td>
                    @foreach ($days as $day)
                        @php
                            $slot = $grid[$period['period']][$day] ?? null;
                            $dayActive = \App\Support\SchoolWeek::dayHasPeriod($day, $period['period']);
                            $isMine = $slot && isset($highlightTeacherId) && (int) $slot->teacher_id === (int) $highlightTeacherId;
                        @endphp
                        <td class="px-2 py-2 align-top {{ $dayActive ? '' : 'bg-slate-50/80' }}">
                            @if (! $dayActive)
                                <div class="flex min-h-[56px] items-center justify-center rounded-md text-[10px] font-medium text-slate-300">—</div>
                            @elseif ($slot)
                                @php
                                    $color = $subjectColors[$slot->subject?->name] ?? 'bg-slate-50 border-slate-200 text-slate-900';
                                @endphp
                                <div class="tt-slot min-h-[56px] rounded-md border px-2 py-1.5 {{ $color }} {{ $isMine ? 'ring-2 ring-dugsi-primary ring-offset-1' : '' }} {{ ! $isMine && isset($highlightTeacherId) ? 'opacity-60' : '' }}">
                                    <div class="text-xs font-semibold leading-tight">{{ $slot->subject?->name ?? '—' }}</div>
                                    @if ($modeCell === 'teacher')
                                        <div class="mt-0.5 text-[10px] font-medium opacity-80">{{ $slot->schoolClass?->displayName() }}</div>
                                    @elseif ($modeCell === 'class')
                                        <div class="mt-0.5 text-[10px] opacity-70">{{ $slot->teacher?->full_name }}</div>
                                        @if ($isMine)
                                            <div class="mt-0.5 text-[9px] font-semibold uppercase tracking-wide text-dugsi-primary">Your period</div>
                                        @endif
                                    @else
                                        <div class="mt-0.5 text-[10px] opacity-70">{{ $slot->teacher?->full_name }}</div>
                                        @if (isset($schoolClass))
                                            <div class="text-[10px] opacity-60">{{ $schoolClass->displayName() }}</div>
                                        @endif
                                    @endif
                                </div>
                            @else
                                <div class="flex min-h-[56px] items-center justify-center rounded-md border-2 border-dashed border-slate-100 text-xs text-slate-200">—</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
