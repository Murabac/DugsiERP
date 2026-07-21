@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Attendance Report')
@section('back_url', route('reports.attendance', request()->query()))
@section('doc_pill', 'Attendance Report')
@section('meta')
    {{ \Illuminate\Support\Carbon::parse($from)->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('j M Y') }}
    · AY {{ $academicYear }}
    @if ($schoolClass) · {{ $schoolClass->displayName() }} @endif
@endsection
@section('counts')
    @if ($stats)
        Rate {{ $stats['rate'] !== null ? $stats['rate'].'%' : '—' }}
        · Present {{ $stats['present'] }} · Late {{ $stats['late'] }} · Absent {{ $stats['absent'] }}
        · {{ $stats['students'] }} students
    @endif
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:28%">Student</th>
            <th style="width:14%">Class</th>
            <th style="width:10%" class="num">Present</th>
            <th style="width:10%" class="num">Late</th>
            <th style="width:10%" class="num">Absent</th>
            <th style="width:12%" class="num">Suspended</th>
            <th style="width:8%" class="num">Total</th>
            <th style="width:8%" class="num">Rate</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['student']?->full_name ?? '—' }}</td>
                <td>{{ $row['class'] ?? '—' }}</td>
                <td class="num">{{ $row['present'] }}</td>
                <td class="num">{{ $row['late'] }}</td>
                <td class="num">{{ $row['absent'] }}</td>
                <td class="num">{{ $row['suspended'] }}</td>
                <td class="num">{{ $row['total'] }}</td>
                <td class="num">{{ $row['rate'] !== null ? $row['rate'].'%' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No attendance data for this filter.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
