<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timetable — {{ $schoolName }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-slate-50 p-6 text-slate-900">
    <div class="no-print mb-4 flex justify-end gap-2">
        <button onclick="window.print()" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Print / Save PDF</button>
        <button onclick="window.close()" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">Close</button>
    </div>

    <div class="mx-auto max-w-5xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 border-b border-slate-200 pb-4 text-center">
            <h1 class="text-xl font-bold text-slate-900">{{ $schoolName }} — Class Timetable</h1>
            <p class="mt-1 text-sm text-slate-600">
                @if ($mode === 'class')
                    {{ $schoolClass->displayName() }} · Academic Year {{ $academicYear }}
                @else
                    {{ $staff->full_name }} · Academic Year {{ $academicYear }}
                @endif
            </p>
            <p class="mt-0.5 text-xs text-slate-400">School Week: Saturday – Wednesday</p>
        </div>

        <table class="w-full border-collapse border border-slate-300 text-xs">
            <thead>
                <tr class="bg-dugsi-sidebar text-white">
                    <th class="border border-slate-600 px-2 py-2 text-left">Period / Time</th>
                    @foreach ($days as $day)
                        <th class="border border-slate-600 px-2 py-2 text-center">{{ \App\Support\SchoolWeek::dayLabel($day) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    <tr class="{{ $loop->even ? 'bg-slate-50' : 'bg-white' }}">
                        <td class="border border-slate-200 px-2 py-2 font-bold text-slate-700">
                            <div>P{{ $period['period'] }}</div>
                            <div class="font-mono text-[10px] font-normal text-slate-400">{{ $period['label'] }}</div>
                        </td>
                        @foreach ($days as $day)
                            @php $slot = $grid[$period['period']][$day] ?? null; @endphp
                            <td class="border border-slate-200 px-2 py-2 align-top">
                                @if ($slot)
                                    @php
                                        $isMine = isset($highlightTeacherId) && (int) $slot->teacher_id === (int) $highlightTeacherId;
                                    @endphp
                                    <div class="{{ $isMine ? 'rounded bg-blue-50 p-1 ring-1 ring-dugsi-primary' : '' }}">
                                        <div class="font-semibold">{{ $slot->subject?->name }}</div>
                                        @if ($mode === 'teacher')
                                            <div class="text-slate-500">{{ $slot->schoolClass?->displayName() }}</div>
                                        @else
                                            <div class="text-slate-500">{{ $slot->teacher?->full_name }}</div>
                                            @if ($isMine)
                                                <div class="text-[9px] font-semibold uppercase text-dugsi-primary">Your period</div>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
