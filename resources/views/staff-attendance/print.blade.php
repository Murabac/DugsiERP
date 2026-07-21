@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Staff Attendance '.$dateLabel)
@section('back_url', route('staff-attendance.index', ['date' => $date]))
@section('doc_pill', 'Staff Attendance Register')
@section('meta')
    {{ $dateLabel }}
@endsection
@section('counts')
    Present {{ $counts['present'] }}
    · Late {{ $counts['late'] }}
    · Absent {{ $counts['absent'] }}
    · On leave {{ $counts['on_leave'] }}
    · {{ $rows->count() }} staff
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:8%" class="ctr">#</th>
            <th style="width:28%">Name</th>
            <th style="width:12%">Code</th>
            <th style="width:14%">Role</th>
            <th style="width:12%" class="ctr">Status</th>
            <th style="width:13%" class="ctr">Check in</th>
            <th style="width:13%" class="ctr">Check out</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $row)
            <tr>
                <td class="ctr">{{ $i + 1 }}</td>
                <td>{{ $row['staff']->full_name }}</td>
                <td style="font-family:ui-monospace,monospace;font-size:10px;">{{ $row['staff']->employee_code }}</td>
                <td>{{ $row['staff']->roleDisplayName() }}</td>
                <td class="ctr">{{ $row['status'] ? \App\Enums\StaffAttendanceStatus::from($row['status'])->label() : '—' }}</td>
                <td class="ctr">{{ $row['check_in_at']?->format('H:i') ?? '—' }}</td>
                <td class="ctr">{{ $row['check_out_at']?->format('H:i') ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
