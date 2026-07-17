@extends('layouts.app')

@section('title', 'Enrollment Report — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Enrollment Report'],
    ]" />

    <x-section-header title="Enrollment Report" :sub="'Student counts by class and status · AY '.$academicYear" />

    <form method="GET" action="{{ route('reports.enrollment') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-select label="Form" name="form">
                <option value="">All forms</option>
                @foreach ([1, 2, 3, 4] as $form)
                    <option value="{{ $form }}" @selected($formFilter === $form)>Form {{ $form }}</option>
                @endforeach
            </x-select>
            <x-select label="Status" name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-select>
            <div class="flex items-end">
                <x-btn type="submit" class="w-full sm:w-auto">Generate Report</x-btn>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($statuses as $status)
            <x-stat-card
                :label="$status->label()"
                :value="(string) ($totals[$status->value] ?? 0)"
                :icon="$status === \App\Enums\StudentStatus::Active ? 'users' : 'file-text'"
                :accent="$status === \App\Enums\StudentStatus::Active"
            />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">Status mix</h3>
            @if (array_sum($totals) === 0)
                <p class="py-10 text-center text-sm text-slate-400">No enrollments for this year.</p>
            @else
                <div class="relative mx-auto h-56 w-full max-w-[16rem]">
                    <canvas data-dugsi-chart='@json($chart)'></canvas>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Class</th>
                            @foreach ($statuses as $status)
                                <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $status->label() }}</th>
                            @endforeach
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-slate-50">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row['class']->displayName() }}</td>
                                @foreach ($statuses as $status)
                                    <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $row['counts'][$status->value] }}</td>
                                @endforeach
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + count($statuses) }}" class="px-4 py-10 text-center text-sm text-slate-400">No classes match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                                <td class="px-4 py-2.5">Total</td>
                                @foreach ($statuses as $status)
                                    <td class="px-3 py-2.5 text-right">{{ $totals[$status->value] }}</td>
                                @endforeach
                                <td class="px-4 py-2.5 text-right">{{ array_sum($totals) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
