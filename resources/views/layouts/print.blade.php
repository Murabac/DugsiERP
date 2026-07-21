<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ $schoolName ?? 'School' }}</title>
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
            margin: 0;
            padding: 14px 16px 12px;
            background: #fff;
        }
        .header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 4px;
            border-bottom: 1.5px solid #1e3a6e;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand { font-size: 17px; font-weight: 700; color: #1e3a6e; line-height: 1.2; }
        .sub { font-size: 10px; color: #64748b; margin-top: 1px; }
        .doc-pill {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1e3a6e;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .meta { font-size: 11px; color: #475569; margin-top: 2px; }
        .counts { font-size: 10px; color: #64748b; margin-top: 1px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }
        .stat {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            background: #f8fafc;
        }
        .stat .lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
        .stat .val { font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 2px; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }
        table.data th {
            background: #f1f5f9;
            border: 1px solid #94a3b8;
            padding: 4px 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #1e293b;
            text-align: left;
        }
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 3px 6px;
            vertical-align: middle;
            line-height: 1.3;
        }
        table.data tbody tr:nth-child(even) { background: #f8fafc; }
        table.data .num { text-align: right; font-variant-numeric: tabular-nums; }
        table.data .ctr { text-align: center; }
        table.data tfoot td {
            background: #f1f5f9;
            font-weight: 700;
        }
        .empty {
            text-align: center;
            padding: 28px 12px;
            color: #94a3b8;
            font-size: 12px;
        }
        .sigs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 28px;
            margin-top: 28px;
            padding-top: 4px;
        }
        .sig {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
        }
        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 9px;
            color: #cbd5e1;
        }
        @page {
            size: {{ $printOrientation ?? 'A4 portrait' }};
            margin: 10mm;
        }
        @media print {
            html, body { background: #fff !important; }
            .no-print { display: none !important; }
            .sheet { padding: 0; }
            table.data { page-break-inside: auto; }
            table.data tr { page-break-inside: avoid; page-break-after: auto; }
            table.data thead { display: table-header-group; }
            .sigs { page-break-inside: avoid; }
        }
        @media screen {
            .sheet {
                margin: 0 auto;
                min-height: calc(100vh - 52px);
                border: 1px solid #e2e8f0;
                max-width: {{ ($printOrientation ?? '') === 'A4 landscape' ? '1100px' : '900px' }};
            }
        }
        @yield('styles')
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('modules.home') }}">← Apps</a>
            <a href="@yield('back_url', url()->previous())">← Back</a>
        </div>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="sheet">
        <div class="header">
            <div class="brand">{{ $schoolName ?? 'School' }}</div>
            <div class="sub">{{ $schoolLetterheadSub ?? '' }}</div>
            <div class="doc-pill">@yield('doc_pill', 'Document')</div>
            @hasSection('meta')
                <div class="meta">@yield('meta')</div>
            @endif
            @hasSection('counts')
                <div class="counts">@yield('counts')</div>
            @endif
        </div>

        @yield('content')

        @unless(isset($hideSignatures) && $hideSignatures)
            <div class="sigs">
                @foreach (($signatureLabels ?? ['Prepared by', 'Checked by', 'Principal']) as $label)
                    <div class="sig">{{ $label }}</div>
                @endforeach
            </div>
        @endunless

        <div class="footer">Printed {{ now()->format('j M Y, H:i') }} · Dugsi ERP</div>
    </div>

    @if (!empty($autoPrint))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
