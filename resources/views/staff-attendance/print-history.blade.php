@extends('layouts.print', ['printOrientation' => 'A4 portrait'])

@section('title', 'Staff Attendance History')
@section('back_url', route('staff-attendance.history', ['from' => $from, 'to' => $to]))
@section('doc_pill', 'Staff Attendance History')
@section('meta')
    {{ \Illuminate\Support\Carbon::parse($from)->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('j M Y') }}
@endsection
@section('counts')
    {{ $days->count() }} day{{ $days->count() === 1 ? '' : 's' }} with records
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:28%">Date</th>
            <th style="width:14%" class="num">Present</th>
            <th style="width:14%" class="num">Late</th>
            <th style="width:14%" class="num">Absent</th>
            <th style="width:14%" class="num">On leave</th>
            <th style="width:16%" class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($days as $day)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($day->date)->format('D, j M Y') }}</td>
                <td class="num">{{ $day->present_count }}</td>
                <td class="num">{{ $day->late_count }}</td>
                <td class="num">{{ $day->absent_count }}</td>
                <td class="num">{{ $day->leave_count }}</td>
                <td class="num">{{ $day->total }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No attendance records in this range.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
