<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Report Card — {{ $student->full_name }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #0f172a; margin: 0; padding: 24px; }
        .sheet { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e3a6e; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: 700; color: #1e3a6e; }
        .sub { font-size: 12px; color: #64748b; margin-top: 2px; }
        .title { font-size: 16px; font-weight: 700; text-align: right; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; font-size: 13px; margin-bottom: 16px; }
        .meta .label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #1e3a6e; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        th.num, td.num { text-align: center; white-space: nowrap; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px 10px; }
        .summary { display: flex; justify-content: space-between; margin-top: 16px; font-size: 14px; }
        .sigs { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 48px; font-size: 12px; text-align: center; }
        .sig-line { border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 6px; color: #64748b; }
        .footer { margin-top: 28px; text-align: center; font-size: 11px; color: #94a3b8; }
        @media print { body { padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="window.print()" style="padding:8px 14px;background:#1e3a6e;color:#fff;border:0;border-radius:6px;cursor:pointer;">Print / Save PDF</button>
        <a href="{{ route('documents.index', ['tab' => 'history']) }}" style="padding:8px 14px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#1e3a6e;">Back</a>
        <a href="{{ route('modules.home') }}" style="padding:8px 14px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#1e3a6e;">Apps</a>
    </div>
    <div class="header">
        <div>
            <div class="brand">{{ $schoolName }}</div>
            <div class="sub">{{ $schoolLetterheadSub }}</div>
        </div>
        <div>
            <div class="title">Grade Report Card</div>
            <div class="sub" style="text-align:right;">{{ $termLabel }} · {{ $academicYear }}</div>
            <div class="sub" style="text-align:right;">{{ $document->document_number }}</div>
        </div>
    </div>
    <div class="meta">
        <div><div class="label">Student</div><div>{{ $student->full_name }}</div></div>
        <div><div class="label">Student ID</div><div>{{ $student->student_code }}</div></div>
        <div><div class="label">Class / Roll</div><div>{{ $schoolClass->displayName() }} · {{ str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT) }}</div></div>
        <div><div class="label">Guardian</div><div>{{ $student->primaryGuardian?->full_name ?? '—' }}</div></div>
        <div><div class="label">Attendance</div><div>{{ $report['attendance_rate'] !== null ? $report['attendance_rate'].'%' : '—' }}</div></div>
        <div><div class="label">Date Issued</div><div>{{ $issuedAt->format('j F Y') }}</div></div>
    </div>
    <table>
        @if ($allTerms)
            <thead>
                <tr>
                    <th>Subject</th>
                    @foreach ($terms as $t)
                        <th class="num">{{ $t->label() }}</th>
                    @endforeach
                    <th class="num">Total</th>
                    <th class="num">%</th>
                    <th class="num">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['subject']->name }}</td>
                        @foreach ($terms as $t)
                            <td class="num">{{ $row['term_scores'][$t->value] !== null ? number_format($row['term_scores'][$t->value], 1) : '—' }}</td>
                        @endforeach
                        <td class="num">{{ $row['average_marks'] !== null ? number_format($row['average_marks'], 1) : '—' }}</td>
                        <td class="num">{{ $row['average'] !== null ? number_format($row['average'], 1).'%' : '—' }}</td>
                        <td class="num">{{ $row['letter']?->value ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        @else
            <thead><tr><th>Subject</th><th>Score</th><th>%</th><th>Grade</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['subject']->name }}</td>
                        <td>{{ $row['marks'] !== null ? number_format($row['marks'], 1) : '—' }}</td>
                        <td>{{ $row['percent'] !== null ? number_format($row['percent'], 1).'%' : '—' }}</td>
                        <td>{{ $row['letter']?->value ?? '—' }}</td>
                        <td>{{ $row['remarks'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        @endif
    </table>
    <div class="summary">
        <div>
            <strong>{{ $allTerms ? 'Overall Average:' : 'Term Average:' }}</strong>
            @if ($report['average'] !== null)
                @if ($allTerms)
                    {{ number_format($report['average_marks'], 1) }}/100
                    ({{ number_format($report['average'], 1) }}%)
                @else
                    {{ number_format($report['average_marks'], 1) }}/{{ number_format($report['term_max'], $report['term_max'] == (int) $report['term_max'] ? 0 : 1) }}
                    ({{ number_format($report['average'], 1) }}%)
                @endif
                @if ($report['average_letter']) ({{ $report['average_letter']->value }}) @endif
            @else — @endif
        </div>
        <div>
            <strong>Class Rank:</strong>
            @if ($report['rank'] !== null) {{ $report['rank'] }} of {{ $report['class_size'] }} @else — @endif
        </div>
    </div>
    <div class="sigs">
        <div><div class="sig-line">Class Teacher</div></div>
        <div><div class="sig-line">Principal</div></div>
        <div><div class="sig-line">Parent / Guardian</div></div>
    </div>
    <div class="footer">Generated via Dugsi ERP · {{ $document->document_number }}</div>
</div>
@include('documents.print.partials.autoprint')
</body>
</html>
