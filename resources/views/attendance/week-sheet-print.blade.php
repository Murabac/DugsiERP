<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Week Sheet — {{ $schoolClass->displayName() }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #0f172a; margin: 0; padding: 20px; }
        .sheet { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e3a6e; padding-bottom: 10px; margin-bottom: 14px; }
        .brand { font-size: 18px; font-weight: 700; color: #1e3a6e; }
        .sub { font-size: 12px; color: #64748b; margin-top: 2px; }
        .title { font-size: 15px; font-weight: 700; text-align: right; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; border: 1.5px solid #0f172a; }
        th, td { border: 1px solid #334155; padding: 8px 6px; vertical-align: middle; }
        th { background: #1e3a6e; color: #fff; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        th.day, td.day { text-align: center; width: 11%; min-width: 52px; }
        td.day { height: 30px; font-weight: 700; font-size: 13px; }
        .code-present { color: #15803d; }
        .code-late { color: #b45309; }
        .code-absent { color: #b91c1c; }
        .code-suspended { color: #475569; }
        .legend { margin-top: 14px; font-size: 11px; color: #64748b; }
        .no-print { margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .no-print a, .no-print button {
            padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1;
        }
        .no-print button { background: #1e3a6e; color: #fff; border-color: #1e3a6e; }
        .no-print a { background: #fff; color: #1e3a6e; }
        @media print { body { padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button type="button" onclick="window.print()">Print / Save PDF</button>
        <a href="{{ route('modules.home') }}">Apps</a>
        <a href="{{ route('attendance.week-sheet', ['class' => $schoolClass->id, 'week' => $weekStart, 'fill' => $fill]) }}">Back</a>
        @if ($fill === 'empty')
            <a href="{{ route('attendance.week-sheet', ['class' => $schoolClass->id, 'week' => $weekStart, 'fill' => 'marked', 'print' => 1]) }}">Switch to filled marks</a>
        @else
            <a href="{{ route('attendance.week-sheet', ['class' => $schoolClass->id, 'week' => $weekStart, 'fill' => 'empty', 'print' => 1]) }}">Switch to empty sheet</a>
        @endif
    </div>
    <div class="header">
        <div>
            <div class="brand">{{ $schoolName }}</div>
            <div class="sub">{{ $schoolLetterheadSub }}</div>
        </div>
        <div>
            <div class="title">Weekly Attendance Sheet</div>
            <div class="sub" style="text-align:right;">{{ $schoolClass->displayName() }} · {{ $weekLabel }}</div>
            <div class="sub" style="text-align:right;">AY {{ $academicYear }} · {{ $fill === 'marked' ? 'With marked days' : 'Blank sheet' }}</div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Student</th>
                @foreach ($days as $day)
                    <th class="day">{{ $day['label'] }}<br><span style="font-weight:400;text-transform:none;">{{ $day['date']->format('j M') }}</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
                <tr>
                    <td>{{ $student['roll'] }}</td>
                    <td>{{ $student['name'] }}</td>
                    @foreach ($days as $day)
                        @php
                            $mark = $student['days'][$day['key']] ?? null;
                            $code = $mark['code'] ?? null;
                            $codeClass = match ($mark['status']?->value ?? null) {
                                'present' => 'code-present',
                                'late' => 'code-late',
                                'absent' => 'code-absent',
                                'suspended' => 'code-suspended',
                                default => '',
                            };
                        @endphp
                        <td class="day {{ $codeClass }}">{{ $code ?: '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="legend">Mark: ✓ = Present · ◐ = Late · ✗ = Absent · ⊘ = Suspended</div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
</body>
</html>
