@php
    $classQuery = array_filter(['class' => $schoolClass?->id]);
@endphp
<div class="flex gap-1 border-b border-slate-200 text-sm">
    <a href="{{ route('attendance.index', $classQuery) }}"
        class="{{ ($active ?? '') === 'mark' ? 'border-b-2 border-dugsi-primary px-3 py-2 font-semibold text-dugsi-primary' : 'px-3 py-2 text-slate-500 hover:text-slate-800' }}">
        Mark Attendance
    </a>
    <a href="{{ route('attendance.week-sheet', $classQuery) }}"
        class="{{ ($active ?? '') === 'week-sheet' ? 'border-b-2 border-dugsi-primary px-3 py-2 font-semibold text-dugsi-primary' : 'px-3 py-2 text-slate-500 hover:text-slate-800' }}">
        Week Sheet
    </a>
    <a href="{{ route('attendance.history', $classQuery) }}"
        class="{{ ($active ?? '') === 'history' ? 'border-b-2 border-dugsi-primary px-3 py-2 font-semibold text-dugsi-primary' : 'px-3 py-2 text-slate-500 hover:text-slate-800' }}">
        History
    </a>
</div>
