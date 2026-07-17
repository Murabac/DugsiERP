@extends('layouts.app')

@section('title', 'Preview Payroll — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Preview payroll</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ $billingMonth->format('F Y') }} · Academic Year {{ $academicYear }}</p>
        </div>
        <a href="{{ route('payroll.index') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back</a>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Staff included</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ $count }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 sm:col-span-2">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total payroll</div>
            <div class="mt-1 text-lg font-bold text-dugsi-primary">{{ \App\Support\Money::format($total) }}</div>
        </div>
    </div>

    @if ($count === 0)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            No active staff with a salary for this month. Set salaries under Staff, then try again.
        </div>
    @else
        <div class="rounded-lg border border-slate-200 bg-white overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Employee</th>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Name</th>
                        <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Role</th>
                        <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Salary</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $member)
                        <tr class="border-b border-slate-50">
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $member->employee_code }}</td>
                            <td class="px-4 py-2.5 font-medium text-slate-900">{{ $member->full_name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $member->role_label?->label() ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::format($member->fixed_salary_usd) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('payroll.generate.store') }}" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            data-dugsi-confirm="Confirm payroll for {{ $billingMonth->format('F Y') }}? This cannot be undone for this month."
            data-dugsi-confirm-title="Confirm payroll run"
            data-dugsi-confirm-ok="Confirm">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="expected_count" value="{{ $count }}">
            <input type="hidden" name="expected_total" value="{{ $total }}">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Notes (optional)</label>
                <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}"
                    class="w-full max-w-lg rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. March salaries">
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    Confirm payroll
                </button>
                <a href="{{ route('payroll.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    @endif
</div>
@endsection
