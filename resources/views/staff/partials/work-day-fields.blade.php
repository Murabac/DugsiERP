@php
    $defaultSchedule = \App\Models\Staff::defaultWorkSchedule();
    $selectedSchedule = old('work_schedule', $selectedSchedule ?? $defaultSchedule);
    if (! is_array($selectedSchedule)) {
        $selectedSchedule = $defaultSchedule;
    }
    // Legacy: flat day list from old() or callers
    if ($selectedSchedule !== [] && array_is_list($selectedSchedule)) {
        $selectedSchedule = \App\Models\Staff::normalizeWorkSchedule($selectedSchedule);
    }
    $weekDays = $weekDays ?? \App\Support\SchoolWeek::days();
    $shifts = \App\Support\SchoolWeek::shifts();
@endphp
<div id="{{ $wrapId ?? 'staff-workdays-wrap' }}" @class([
    ($colSpan ?? 'col-span-2'),
    'hidden' => ($hideForFinance ?? false) && ($currentRole ?? 'teacher') === 'finance',
]) data-staff-workdays>
    <label class="mb-1.5 block text-xs font-medium text-slate-700">
        Attend schedule
        <span class="font-normal text-slate-400">
            — First shift {{ \App\Support\SchoolWeek::shiftHint('first') }},
            Second shift {{ \App\Support\SchoolWeek::shiftHint('second') }}
        </span>
    </label>
    <div class="overflow-hidden rounded-md border border-slate-200">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2">Day</th>
                    @foreach ($shifts as $shift)
                        <th class="px-3 py-2">
                            {{ \App\Support\SchoolWeek::shiftLabel($shift) }}
                            <span class="font-normal normal-case text-slate-400">({{ \App\Support\SchoolWeek::shiftHint($shift) }})</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($weekDays as $day)
                    @php $dayShifts = $selectedSchedule[$day] ?? []; @endphp
                    <tr class="border-b border-slate-100 last:border-0" data-schedule-day="{{ $day }}">
                        <td class="px-3 py-2 font-medium text-slate-800">{{ \App\Support\SchoolWeek::dayLabel($day) }}</td>
                        @foreach ($shifts as $shift)
                            <td class="px-3 py-2">
                                <label class="inline-flex items-center gap-1.5 text-slate-700">
                                    <input type="checkbox"
                                        name="work_schedule[{{ $day }}][]"
                                        value="{{ $shift }}"
                                        @checked(in_array($shift, $dayShifts, true))
                                        class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary"
                                        data-schedule-shift>
                                    <span>{{ $shift === 'first' ? '1st' : '2nd' }}</span>
                                </label>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-1 text-[11px] text-slate-500">Tick at least one shift on each day the teacher attends. Leave a day unchecked to skip it.</p>
    @error('work_schedule')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('work_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
