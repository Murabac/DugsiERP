<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>School Timetable — {{ $schoolName }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            @page { size: landscape; margin: 10mm; }
        }
        .master-grid { font-size: 10px; }
        .master-grid th, .master-grid td { vertical-align: top; }
        .period-line { line-height: 1.35; }
        .period-line + .period-line { margin-top: 2px; border-top: 1px dotted #e2e8f0; padding-top: 2px; }
    </style>
</head>
<body class="bg-slate-50 p-4 text-slate-900">
    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('timetable.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Timetable</a>
        <div class="flex gap-2">
            <button onclick="window.print()" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Print / Save PDF</button>
            <button onclick="window.close()" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">Close</button>
        </div>
    </div>

    <div class="mx-auto max-w-[1400px] rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 border-b border-slate-200 pb-3 text-center">
            <h1 class="text-xl font-bold text-slate-900">{{ $schoolName }} — Full School Timetable</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $classGrids->count() }} class(es) · Academic Year {{ $academicYear }}</p>
            <p class="mt-0.5 text-xs text-slate-400">Classes ↓ · Days → · Each cell lists periods P1–P{{ count($periods) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="master-grid w-full border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-dugsi-sidebar text-white">
                        <th class="sticky left-0 border border-slate-600 bg-dugsi-sidebar px-2 py-2 text-left text-[11px] font-semibold">Class</th>
                        @foreach ($days as $day)
                            <th class="border border-slate-600 px-2 py-2 text-center text-[11px] font-semibold">
                                {{ \App\Support\SchoolWeek::dayLabel($day) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classGrids as $item)
                        @php
                            $schoolClass = $item['schoolClass'];
                            $grid = $item['grid'];
                        @endphp
                        <tr class="{{ $loop->even ? 'bg-slate-50' : 'bg-white' }}">
                            <td class="sticky left-0 border border-slate-200 bg-inherit px-2 py-2 font-semibold text-slate-800">
                                <div>{{ $schoolClass->displayName() }}</div>
                                <div class="text-[9px] font-normal text-slate-400">{{ $schoolClass->classroom() }}</div>
                            </td>
                            @foreach ($days as $day)
                                <td class="border border-slate-200 px-1.5 py-1.5">
                                    @foreach ($periods as $period)
                                        @php $slot = $grid[$period['period']][$day] ?? null; @endphp
                                        <div class="period-line">
                                            <span class="font-mono text-[9px] text-slate-400">P{{ $period['period'] }}</span>
                                            @if ($slot)
                                                <span class="font-semibold text-slate-800">{{ $slot->subject?->name }}</span>
                                                <span class="text-slate-500">· {{ $slot->teacher?->full_name }}</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
