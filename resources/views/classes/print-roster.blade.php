@extends('layouts.print', ['printOrientation' => 'A4 portrait'])

@section('title', $schoolClass->displayName().' Roster')
@section('back_url', route('classes.roster', $schoolClass))
@section('doc_pill', 'Class Roster')
@section('meta')
    {{ $schoolClass->displayName() }} · {{ $schoolClass->academic_year }} · Room {{ $schoolClass->classroom() }}
@endsection
@section('counts')
    {{ $enrollments->count() }} enrolled · Capacity {{ $schoolClass->capacity }}
    @if ($schoolClass->homeroomTeacher)
        · Form Master: {{ $schoolClass->homeroomTeacher->full_name }}
    @endif
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:8%" class="ctr">Roll</th>
            <th style="width:34%">Name</th>
            <th style="width:14%">Student ID</th>
            <th style="width:10%" class="ctr">Gender</th>
            <th style="width:22%">Guardian</th>
            <th style="width:12%">Phone</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($enrollments as $enrollment)
            @php $s = $enrollment->student; $g = $s->primaryGuardian; @endphp
            <tr>
                <td class="ctr">{{ str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $s->full_name }}</td>
                <td style="font-family:ui-monospace,monospace;font-size:10px;">{{ $s->student_code }}</td>
                <td class="ctr">{{ $s->gender?->label() ?? '—' }}</td>
                <td>{{ $g?->full_name ?? '—' }}</td>
                <td style="font-size:10px;">{{ $g?->phone ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No students enrolled.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
