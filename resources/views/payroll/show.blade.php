@extends('layouts.app')

@section('title', 'Payroll '.$run->billing_month->format('F Y').' — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">{{ $run->billing_month->format('F Y') }} payroll</h2>
            <p class="mt-0.5 text-xs text-slate-500">
                <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $run->status->badgeClass() }}">{{ $run->status->label() }}</span>
                · {{ $run->staff_count }} staff · {{ \App\Support\Money::format($run->total_amount) }}
            </p>
        </div>
        <a href="{{ route('payroll.index') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">All runs</a>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 text-sm">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Confirmed</div>
            <div class="mt-1 text-slate-900">{{ $run->confirmed_at?->format('j M Y, H:i') ?? '—' }}</div>
            <div class="text-xs text-slate-400">{{ $run->confirmedBy?->name ?? '—' }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 sm:col-span-2">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Notes</div>
            <div class="mt-1 text-slate-700">{{ $run->notes ?: '—' }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Payslip</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Employee</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Role</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Salary</th>
                    <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($run->items as $item)
                    <tr class="border-b border-slate-50">
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $item->payslip_number }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $item->employee_code }}</td>
                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ $item->full_name }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $item->role_label ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium">{{ \App\Support\Money::format($item->salary_usd) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('payroll.payslip', [$run, $item]) }}" target="_blank" rel="noopener"
                                class="text-xs font-medium text-blue-700 hover:underline">Print</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
