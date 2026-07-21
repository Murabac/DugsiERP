@extends('layouts.app')

@section('title', 'Payroll Report — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Payroll Report'],
    ]" />

    <x-section-header title="Payroll Report" :sub="'Staff salary summary by month · AY '.$academicYear">
        <x-slot:action>
            <x-btn variant="secondary" :href="route('reports.payroll.print', request()->query())" target="_blank">Print</x-btn>
            <x-btn variant="secondary" href="{{ route('payroll.index') }}">Open Payroll</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('reports.payroll') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-56">
                <x-field label="Month" name="month" type="month" :value="$month" />
            </div>
            <x-btn type="submit">Generate Report</x-btn>
        </div>
    </form>

    @if (! $stats)
        <div class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-16 text-center">
            <p class="text-sm text-slate-500">No payroll data for {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }}.</p>
            <a href="{{ route('reports.index') }}" class="mt-3 inline-block text-xs font-medium text-blue-700 hover:underline">Back to Reports</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-stat-card label="Staff" :value="(string) $stats['staff']" icon="users" :accent="true" />
            <x-stat-card label="Total Payroll" :value="\App\Support\Money::format($stats['total'])" icon="dollar-sign" />
            <x-stat-card
                label="Status"
                :value="$run ? $run->status->label() : 'Preview (not confirmed)'"
                :sub="$stats['confirmed_at'] ? 'Confirmed '.$stats['confirmed_at']->format('j M Y') : null"
                icon="file-text"
            />
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-700">
                {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }}
                @if (! empty($stats['preview']))
                    <span class="ml-2 font-medium normal-case text-amber-700">Preview — generate a run in Payroll to confirm</span>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Staff</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Role</th>
                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Salary</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Payslip</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr class="border-b border-slate-50">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $item->full_name }}</td>
                                <td class="px-4 py-2.5 text-slate-600">
                                    {{ is_object($item->role_label) && method_exists($item->role_label, 'label') ? $item->role_label->label() : $item->role_label }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-medium">{{ \App\Support\Money::format($item->salary_usd) }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                                    @if ($run && $item->payslip_number)
                                        <a href="{{ route('payroll.payslip', [$run, $item]) }}" class="text-blue-700 hover:underline" target="_blank" rel="noopener">{{ $item->payslip_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                            <td class="px-4 py-2.5" colspan="2">Total</td>
                            <td class="px-4 py-2.5 text-right">{{ \App\Support\Money::format($stats['total']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
