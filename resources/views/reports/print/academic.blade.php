@extends('layouts.print', ['printOrientation' => 'A4 landscape'])

@section('title', 'Academic Report')
@section('back_url', route('reports.academic', request()->query()))
@section('doc_pill', 'Academic Performance')
@section('meta')
    {{ $schoolClass?->displayName() ?? 'Class' }}
    · {{ $termLabel ?: 'Terms' }}
    @if ($subject) · {{ $subject->name }} @endif
    · AY {{ $academicYear }}
@endsection
@section('counts')
    @if ($stats)
        Students {{ $stats['students'] ?? $rows->count() }}
        @if (($stats['average'] ?? null) !== null)
            · Average {{ number_format($stats['average'], 1) }}%
        @endif
        @if (($stats['pass_rate'] ?? null) !== null)
            · Pass rate {{ $stats['pass_rate'] }}%
        @endif
    @endif
@endsection

@section('content')
<table class="data">
    <thead>
        <tr>
            <th style="width:6%" class="ctr">#</th>
            <th style="width:24%">Student</th>
            <th style="width:18%">Scope</th>
            @if ($combined)
                @foreach ($selectedTerms as $t)
                    <th class="num">{{ $t->label() }}</th>
                @endforeach
                <th class="num">Combined</th>
            @else
                <th class="num">Score</th>
            @endif
            <th style="width:10%" class="ctr">Grade</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $i => $row)
            <tr>
                <td class="ctr">{{ $i + 1 }}</td>
                <td>{{ $row['student']?->full_name ?? '—' }}</td>
                <td style="font-size:10px;">{{ $row['subject'] ?? '—' }}</td>
                @if ($combined)
                    @foreach ($selectedTerms as $t)
                        @php $termScore = $row['term_scores'][$t->value] ?? null; @endphp
                        <td class="num">{{ $termScore !== null ? number_format($termScore, 1) : '—' }}</td>
                    @endforeach
                @endif
                <td class="num">{{ $row['score'] !== null ? number_format($row['score'], 1) : '—' }}</td>
                <td class="ctr">{{ $row['letter']?->value ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No grade data for this filter. Generate the report first.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
