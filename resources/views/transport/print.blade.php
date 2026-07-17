<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $route->name }} roster — {{ $schoolName }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #0f172a; margin: 24px; font-size: 12px; }
        h1 { font-size: 18px; margin: 0; color: #1e3a6e; }
        .sub { color: #64748b; font-size: 11px; margin-top: 2px; }
        .meta { margin: 16px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #1e3a6e; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        @media print { body { margin: 12px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 12px;">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('transport.buses.show', $route) }}">Back</a>
    </div>

    <h1>{{ $schoolName }}</h1>
    <div class="sub">{{ $schoolLetterheadSub }}</div>
    <h1 style="margin-top: 12px;">Bus roster — {{ $route->name }}</h1>
    <div class="sub">{{ $route->academic_year }} · Printed {{ now()->format('j M Y') }}</div>

    <div class="meta">
        <div><strong>Plate:</strong> {{ $route->vehicle?->plate_number ?? '—' }}</div>
        <div><strong>Capacity:</strong> {{ $riders->count() }} / {{ $route->capacity() }}</div>
        <div><strong>Driver:</strong> {{ $route->vehicle?->driver?->full_name ?? '—' }}</div>
        <div><strong>Seats free:</strong> {{ max(0, $route->capacity() - $riders->count()) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>ID</th>
                <th>Class</th>
                <th>Guardian phone</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($riders as $i => $a)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $a->student?->full_name }}</td>
                    <td>{{ $a->student?->student_code }}</td>
                    <td>{{ $a->student?->currentEnrollment?->schoolClass?->displayName() ?? '—' }}</td>
                    <td>{{ $a->student?->primaryGuardian?->phone ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#94a3b8;">No riders</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
