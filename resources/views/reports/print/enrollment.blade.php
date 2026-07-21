@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Enrollment Report')
@section('back_url', route('reports.enrollment', request()->query()))
@section('doc_pill', 'Enrollment Report')
@section('meta')
    AY {{ $academicYear }}
    @if ($formFilter > 0) · Form {{ $formFilter }} @endif
    @if ($statusFilter !== '') · {{ \App\Enums\StudentStatus::tryFrom($statusFilter)?->label() ?? $statusFilter }} @endif
@endsection
@section('counts')
    Total {{ array_sum($totals) }} enrollments across {{ $rows->count() }} classes
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:22%">Class</th>
            @foreach ($statuses as $status)
                <th class="num">{{ $status->label() }}</th>
            @endforeach
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['class']->displayName() }}</td>
                @foreach ($statuses as $status)
                    <td class="num">{{ $row['counts'][$status->value] }}</td>
                @endforeach
                <td class="num">{{ $row['total'] }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 2 + count($statuses) }}" class="empty">No enrollment data.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td>Totals</td>
            @foreach ($statuses as $status)
                <td class="num">{{ $totals[$status->value] }}</td>
            @endforeach
            <td class="num">{{ array_sum($totals) }}</td>
        </tr>
    </tfoot>
</table>
@endsection
