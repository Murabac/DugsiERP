@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Mark Sheet '.$schoolClass->displayName())
@section('back_url', route('grades.index', ['class' => $schoolClass->id, 'subject' => $subject->id, 'term' => $term->value]))
@section('doc_pill', 'Class Mark Sheet')
@section('meta')
    {{ $schoolClass->displayName() }} · {{ $subject->name }} · {{ $term->label() }} · {{ $academicYear }}
@endsection
@section('counts')
    {{ $rows->count() }} student{{ $rows->count() === 1 ? '' : 's' }}
    @if ($classAverage !== null)
        · Class average {{ number_format($classAverage, 1) }}%
        @if ($classAverageLetter)
            ({{ $classAverageLetter->value }})
        @endif
    @endif
    · Max {{ $termMax }}
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:8%" class="ctr">Roll</th>
            <th style="width:36%">Name</th>
            <th style="width:16%">Student ID</th>
            <th style="width:12%" class="num">Marks</th>
            <th style="width:12%" class="num">%</th>
            <th style="width:8%" class="ctr">Grade</th>
            <th style="width:8%" class="ctr">Remark</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="ctr">{{ $row['roll'] }}</td>
                <td>{{ $row['student']->full_name }}</td>
                <td style="font-family:ui-monospace,monospace;font-size:10px;">{{ $row['student']->student_code }}</td>
                <td class="num">{{ $row['score'] !== '' ? $row['score'] : '—' }}</td>
                <td class="num">{{ $row['percent'] !== null ? number_format($row['percent'], 1) : '—' }}</td>
                <td class="ctr">{{ $row['letter']?->value ?? '—' }}</td>
                <td class="ctr" style="font-size:10px;">{{ $row['remarks'] ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No students in this class.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
