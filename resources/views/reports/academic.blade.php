@extends('layouts.app')

@section('title', 'Academic Performance — Dugsi ERP')

@section('content')
@php
    $selectedValues = collect($selectedTerms)->map(fn ($t) => $t->value)->all();
@endphp
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Academic Performance'],
    ]" />

    <x-section-header title="Academic Performance" :sub="'By class, subject, and one or more terms · AY '.$academicYear" />

    <form method="GET" action="{{ route('reports.academic') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="apply" value="1">
        <input type="hidden" name="terms_submitted" value="1">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-select label="Class" name="class" required>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" @selected($schoolClass?->id === $c->id)>{{ $c->displayName() }}</option>
                @endforeach
            </x-select>
            <x-select label="Subject" name="subject">
                <option value="">All subjects (averages)</option>
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}" @selected($subject?->id === $s->id)>{{ $s->name }}</option>
                @endforeach
            </x-select>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-xs font-medium text-slate-700">Terms</label>
                <div class="flex flex-wrap gap-3 rounded-md border border-slate-300 px-3 py-2.5 {{ ! empty($termsError) ? 'border-red-400' : '' }}">
                    @foreach ($terms as $t)
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                            <input type="checkbox" name="terms[]" value="{{ $t->value }}"
                                @checked(in_array($t->value, $selectedValues, true))
                                class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary">
                            {{ $t->label() }}
                        </label>
                    @endforeach
                </div>
                @if (! empty($termsError))
                    <p class="mt-1 text-xs text-red-600">{{ $termsError }}</p>
                @else
                    <p class="mt-1 text-[11px] text-slate-400">Select more than one term to combine (e.g. Term 1 + Term 2 average).</p>
                @endif
            </div>
            <div class="flex items-end lg:col-span-4">
                <x-btn type="submit" class="w-full sm:w-auto">Generate Report</x-btn>
            </div>
        </div>
    </form>

    @unless ($applied && $schoolClass)
        <div class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-16 text-center">
            <p class="text-sm text-slate-500">Select class and one or more terms, then generate. Combine Term 1 and Term 2 to see overall averages.</p>
            <a href="{{ route('reports.index') }}" class="mt-3 inline-block text-xs font-medium text-blue-700 hover:underline">Back to Reports</a>
        </div>
    @else
        @if ($stats)
            <div class="mb-1 text-xs text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $termLabel }}</span>
                @if ($combined)
                    <span class="text-slate-400">· combined average across selected terms</span>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-stat-card
                    :label="$combined ? 'Combined Average' : 'Class Average'"
                    :value="$stats['average'] !== null ? number_format($stats['average'], 1).'%'.($stats['average_letter'] ? ' ('.$stats['average_letter']->value.')' : '') : '—'"
                    icon="graduation-cap"
                    :accent="true"
                />
                <x-stat-card label="Students" :value="(string) $stats['students']" icon="users" />
                <x-stat-card label="Pass Rate (≥40%)" :value="$stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—'" icon="bar-chart" />
            </div>
        @endif

        @if ($chart)
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">
                    Letter grade distribution
                    @if ($combined)
                        <span class="font-medium normal-case text-slate-400">· from combined scores</span>
                    @endif
                </h3>
                <div class="relative h-56 w-full">
                    <canvas data-dugsi-chart='@json($chart)'></canvas>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">#</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Scope</th>
                            @if ($combined)
                                @foreach ($selectedTerms as $t)
                                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $t->label() }}</th>
                                @endforeach
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Combined</th>
                            @else
                                <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Score</th>
                            @endif
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $i => $row)
                            <tr class="border-b border-slate-50">
                                <td class="px-4 py-2.5 text-slate-400">{{ $i + 1 }}</td>
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['student']?->full_name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $row['subject'] }}</td>
                                @if ($combined)
                                    @foreach ($selectedTerms as $t)
                                        @php $termScore = $row['term_scores'][$t->value] ?? null; @endphp
                                        <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">
                                            {{ $termScore !== null ? number_format($termScore, 1).'%' : '—' }}
                                        </td>
                                    @endforeach
                                @endif
                                <td class="px-4 py-2.5 text-right tabular-nums font-medium">{{ $row['score'] !== null ? number_format($row['score'], 1).'%' : '—' }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($row['letter'])
                                        <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-bold {{ $row['letter']->badgeClass() }}">{{ $row['letter']->value }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $combined ? 4 + count($selectedTerms) : 5 }}" class="px-4 py-10 text-center text-sm text-slate-400">
                                    No grades entered for this class and selected term(s).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endunless
</div>
@endsection
