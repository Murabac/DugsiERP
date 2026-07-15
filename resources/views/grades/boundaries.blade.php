@extends('layouts.app')

@section('title', 'Grade Boundaries — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Grade Boundaries</h2>
            <p class="mt-0.5 text-xs text-slate-500">School-wide letter grade thresholds used for live score conversion</p>
        </div>
        <a href="{{ route('grades.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Back to Grade Entry
        </a>
    </div>

    <div class="flex gap-1 border-b border-slate-200 text-sm">
        <a href="{{ route('grades.index') }}" class="px-3 py-2 text-slate-500 hover:text-slate-800">Grade Entry</a>
        <span class="border-b-2 border-dugsi-primary px-3 py-2 font-semibold text-dugsi-primary">Grade Boundaries</span>
        @if ($canGenerateReports)
            <a href="{{ route('grades.report') }}" class="px-3 py-2 text-slate-500 hover:text-slate-800">Student Report</a>
        @endif
    </div>

    @unless ($canEdit)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Teachers can view grade boundaries. Only Admin / Super Admin can edit them.
        </div>
    @endunless

    <div class="rounded-lg border border-slate-200 bg-white">
        @if ($canEdit)
            <form method="POST" action="{{ route('grades.boundaries.update') }}">
                @csrf
        @endif
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        @foreach (['Letter', 'Min %', 'Max %', 'Remark', 'Range'] as $h)
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($boundaries as $i => $b)
                        <tr class="border-b border-slate-50">
                            <td class="px-4 py-2.5">
                                @if ($canEdit)
                                    <input type="hidden" name="boundaries[{{ $i }}][letter]" value="{{ $b->letter->value }}">
                                @endif
                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-bold {{ $b->letter->badgeClass() }}">{{ $b->letter->value }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($canEdit)
                                    <input type="number" name="boundaries[{{ $i }}][min_percent]" value="{{ old("boundaries.$i.min_percent", $b->min_percent) }}"
                                        min="0" max="100"
                                        class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                @else
                                    <span class="text-slate-800">{{ $b->min_percent }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($canEdit)
                                    <input type="number" name="boundaries[{{ $i }}][max_percent]" value="{{ old("boundaries.$i.max_percent", $b->max_percent) }}"
                                        min="0" max="100"
                                        class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                @else
                                    <span class="text-slate-800">{{ $b->max_percent }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($canEdit)
                                    <input type="text" name="boundaries[{{ $i }}][remark]" value="{{ old("boundaries.$i.remark", $b->remark) }}"
                                        maxlength="64"
                                        class="w-full min-w-[120px] rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                @else
                                    <span class="text-slate-700">{{ $b->remark ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">
                                {{ $b->min_percent }}–{{ $b->max_percent }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($canEdit)
                <div class="flex justify-end border-t border-slate-200 px-4 py-3">
                    <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                        Save Boundaries
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
