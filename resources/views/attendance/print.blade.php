<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Register — {{ $schoolClass->displayName() }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            color: #0f172a;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f8fafc;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 12px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        .toolbar a { color: #64748b; font-size: 13px; text-decoration: none; }
        .toolbar a:hover { color: #0f172a; }
        .toolbar button {
            padding: 8px 14px;
            background: #1e3a6e;
            color: #fff;
            border: 0;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .sheet {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 10px 12px 8px;
            background: #fff;
        }
        .header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1.5px solid #1e3a6e;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .header-left { min-width: 0; }
        .brand {
            font-size: 15px;
            font-weight: 700;
            color: #1e3a6e;
            line-height: 1.2;
        }
        .sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 1px;
        }
        .header-right { text-align: right; flex-shrink: 0; }
        .doc-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            line-height: 1.2;
        }
        .meta {
            font-size: 11px;
            color: #475569;
            margin-top: 2px;
        }
        .counts {
            font-size: 10px;
            color: #64748b;
            margin-top: 1px;
        }
        table.register {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }
        table.register th {
            background: #f1f5f9;
            border: 1px solid #94a3b8;
            padding: 3px 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #1e293b;
        }
        table.register td {
            border: 1px solid #cbd5e1;
            padding: 2px 6px;
            vertical-align: middle;
            line-height: 1.25;
        }
        table.register tbody tr:nth-child(even) { background: #f8fafc; }
        .col-roll { width: 5%; text-align: center; color: #64748b; font-variant-numeric: tabular-nums; }
        .col-name { width: 48%; font-weight: 500; }
        .col-status { width: 12%; text-align: center; font-weight: 600; }
        .col-sig { width: 35%; }
        .reason {
            font-size: 9px;
            font-weight: 400;
            color: #94a3b8;
        }
        .sigs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
            margin-top: 14px;
            padding-top: 4px;
        }
        .sig {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
        }
        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 9px;
            color: #cbd5e1;
        }

        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        @media print {
            html, body { background: #fff !important; }
            .no-print { display: none !important; }
            .sheet { padding: 0; }
            table.register { page-break-inside: auto; }
            table.register tr { page-break-inside: avoid; page-break-after: auto; }
            table.register thead { display: table-header-group; }
            .sigs { page-break-inside: avoid; }
        }

        @media screen {
            .sheet {
                margin: 0 auto;
                min-height: calc(100vh - 52px);
                border: 1px solid #e2e8f0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('attendance.index', ['class' => $schoolClass->id, 'date' => $date->toDateString()]) }}">← Back to Attendance</a>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="sheet">
        <div class="header">
            <div class="header-left">
                <div class="brand">{{ $schoolName }}</div>
                <div class="sub">{{ $schoolLetterheadSub }}</div>
            </div>
            <div class="header-right">
                <div class="doc-title">Attendance Register</div>
                <div class="meta">
                    {{ $schoolClass->displayName() }}
                    · {{ $dateLabel }}
                    · {{ $academicYear }}
                </div>
                <div class="counts">
                    Present {{ $counts['present'] }}
                    · Late {{ $counts['late'] }}
                    · Absent {{ $counts['absent'] }}
                    · Suspended {{ $counts['suspended'] }}
                    @if ($counts['unmarked'] > 0)
                        · Unmarked {{ $counts['unmarked'] }}
                    @endif
                </div>
            </div>
        </div>

        <table class="register">
            <thead>
                <tr>
                    <th class="col-roll">Roll</th>
                    <th class="col-name" style="text-align:left;">Student Name</th>
                    <th class="col-status">Status</th>
                    <th class="col-sig">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="col-roll">{{ $row['roll'] }}</td>
                        <td class="col-name">
                            {{ $row['name'] }}
                            @if ($row['reason'])
                                <div class="reason">{{ $row['reason'] }}</div>
                            @endif
                        </td>
                        <td class="col-status">
                            @if ($row['status'])
                                {{ $row['status']->label() }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="col-sig">&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="sigs">
            @foreach (['Class Teacher', 'Form Master / Mistress', 'Principal'] as $sig)
                <div class="sig">{{ $sig }}</div>
            @endforeach
        </div>
        <div class="footer">{{ $schoolName }} · {{ $schoolLocation }}</div>
    </div>
</body>
</html>
