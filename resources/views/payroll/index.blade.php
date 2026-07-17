@extends('layouts.app')

@section('title', 'Payroll — Dugsi ERP')

@section('content')
@php
    $pendingMonth = now()->format('F Y');
    $lastPayslipByStaff = $lastPayslipByStaff ?? collect();
@endphp
<div class="space-y-4">
    <x-section-header title="Payroll" :sub="'Monthly payroll runs and individual payslips · Academic Year '.$academicYear">
        <x-slot:action>
            <x-btn href="{{ route('payroll.index', ['generate' => 1]) }}">
                <x-icon name="plus" :size="14" /> Generate Payroll Run
            </x-btn>
        </x-slot:action>
    </x-section-header>

    @if (session('status'))
        <div class="flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
            <x-icon name="check-circle" :size="14" /> {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Monthly Total" :value="\App\Support\Money::format($monthlyTotal)" :sub="$staff->count().' staff members'" icon="credit-card" accent />
        <x-stat-card
            label="Last Run"
            :value="$lastRun ? $lastRun->billing_month->format('F Y') : '—'"
            :sub="$lastRun ? 'Generated '.$lastRun->confirmed_at?->format('j M Y') : 'No runs yet'"
            icon="check-circle"
        />
        <x-stat-card
            label="Pending Run"
            :value="$pendingMonth"
            :sub="$previewError ?: 'Ready to generate'"
            icon="calendar"
        />
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Staff Salary List</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        @foreach (['ID', 'Name', 'Role', 'Monthly Salary', 'Last Payslip', ''] as $h)
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        @php $lastItem = $lastPayslipByStaff->get($member->id); @endphp
                        <tr class="border-b border-slate-50 hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $member->employee_code }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-800">
                                        {{ strtoupper(mb_substr($member->full_name, 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-slate-900">{{ $member->full_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <x-status-badge :status="$member->role_label?->value ?? 'neutral'" :label="$member->role_label?->label() ?? 'Staff'" />
                            </td>
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ \App\Support\Money::format($member->fixed_salary_usd) }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">
                                {{ $lastItem?->payrollRun?->billing_month?->format('j M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($lastItem && $lastItem->payrollRun)
                                    <a href="{{ route('payroll.payslip', [$lastItem->payrollRun, $lastItem]) }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-1 text-xs text-blue-700 hover:underline">
                                        <x-icon name="eye" :size="11" /> Payslip
                                    </a>
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No active staff with salaries.</td></tr>
                    @endforelse
                </tbody>
                @if ($staff->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-slate-200 bg-slate-50">
                            <td colspan="3" class="px-4 py-2.5 text-sm font-semibold text-slate-700">Total Monthly Payroll</td>
                            <td class="px-4 py-2.5 font-bold text-dugsi-primary">{{ \App\Support\Money::format($monthlyTotal) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Previous Payroll Runs</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        @foreach (['Month', 'Generated', 'Staff', 'Total Payout', 'Status', ''] as $h)
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr class="border-b border-slate-50 hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $run->billing_month->format('F Y') }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $run->confirmed_at?->format('j M Y') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $run->staff_count }} staff</td>
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ \App\Support\Money::format($run->total_amount) }}</td>
                            <td class="px-4 py-2.5"><x-status-badge :status="$run->status" /></td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('payroll.show', $run) }}" class="text-xs font-medium text-blue-700 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No payroll runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showGenerate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" id="payroll-generate-modal">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                    <h3 class="text-sm font-semibold text-slate-900">Generate Payroll Run</h3>
                    <a href="{{ route('payroll.index') }}" class="text-slate-400 hover:text-slate-700" aria-label="Close">✕</a>
                </div>
                @if ($preview)
                    <form method="POST" action="{{ route('payroll.generate.store') }}"
                        data-dugsi-confirm="Confirm payroll for {{ $preview['billing_month']->format('F Y') }}? This cannot be undone for this month."
                        data-dugsi-confirm-title="Confirm payroll run"
                        data-dugsi-confirm-ok="Confirm">
                        @csrf
                        <div class="max-h-[60vh] space-y-4 overflow-y-auto p-5">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-field label="Payroll Month" name="month" type="month" :value="$preview['billing_month']->format('Y-m')" required />
                                <x-field label="Payment Date" type="date" :value="now()->toDateString()" readonly />
                            </div>
                            <input type="hidden" name="expected_count" value="{{ $preview['count'] }}">
                            <input type="hidden" name="expected_total" value="{{ $preview['total'] }}">
                            <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2.5 text-xs text-blue-700">
                                Generating payslips for all <strong>{{ $preview['count'] }} active staff members</strong>.
                                Total: <strong>{{ \App\Support\Money::format($preview['total']) }}</strong>.
                            </div>
                            @error('month')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <div class="overflow-x-auto rounded-md border border-slate-200">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50">
                                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Name</th>
                                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Role</th>
                                            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($preview['staff'] as $member)
                                            <tr class="border-b border-slate-50">
                                                <td class="px-3 py-2 font-medium text-slate-800">{{ $member->full_name }}</td>
                                                <td class="px-3 py-2"><x-status-badge :status="$member->role_label?->value ?? 'neutral'" :label="$member->role_label?->label() ?? 'Staff'" /></td>
                                                <td class="px-3 py-2 text-right text-slate-700">{{ \App\Support\Money::format($member->fixed_salary_usd) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t-2 border-slate-200 bg-slate-50">
                                            <td colspan="2" class="px-3 py-2 font-bold text-slate-800">Total</td>
                                            <td class="px-3 py-2 text-right font-bold text-dugsi-primary">{{ \App\Support\Money::format($preview['total']) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <x-field label="Notes (optional)" name="notes" :value="old('notes')" />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5">
                            <x-btn variant="secondary" href="{{ route('payroll.index') }}">Cancel</x-btn>
                            <x-btn type="submit">Confirm &amp; Generate</x-btn>
                        </div>
                    </form>
                @else
                    <div class="space-y-3 p-5">
                        <p class="text-sm text-amber-800">{{ $previewError ?? 'Unable to preview payroll for this month.' }}</p>
                        <form method="GET" action="{{ route('payroll.index') }}" class="flex flex-wrap items-end gap-2">
                            <input type="hidden" name="generate" value="1">
                            <x-field label="Try another month" name="month" type="month" :value="request('month', $defaultMonth)" />
                            <x-btn type="submit" variant="secondary">Reload preview</x-btn>
                        </form>
                    </div>
                    <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3.5">
                        <x-btn variant="secondary" href="{{ route('payroll.index') }}">Close</x-btn>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
