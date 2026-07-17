@extends('layouts.app')

@section('title', $staff->full_name.' — Staff — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Staff', 'url' => route('staff.index')],
        ['label' => $staff->full_name],
    ]" />

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-800">
                {{ $staff->initials() }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ $staff->full_name }}</h3>
                        <div class="mt-0.5 font-mono text-xs text-slate-500">
                            {{ $staff->employee_code }} · {{ $staff->role_label->label() }}
                            @if ($staff->subject_specialty)
                                · {{ $staff->subject_specialty }}
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-2">
                        <x-status-badge :status="$staff->status" />
                        @if ($canEdit)
                            <button type="button" data-dugsi-open="#edit-staff-modal" data-dugsi-width="32rem"
                                class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Edit
                            </button>
                        @endif
                    </div>
                </div>
                <div class="mt-2.5 flex flex-wrap gap-4">
                    <span class="text-xs text-slate-500">{{ $staff->phone ?? 'No phone' }}</span>
                    <span class="text-xs text-slate-500">Joined {{ $staff->date_joined?->format('d M Y') ?? '—' }}</span>
                    @if ($staff->user)
                        <span class="text-xs text-green-700">Login: {{ $staff->user->email }}</span>
                    @else
                        <span class="text-xs text-slate-400">No login account</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="flex w-full max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 sm:w-fit">
        <a href="{{ route('staff.show', ['staff' => $staff, 'tab' => 'overview']) }}"
            class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ ($tab ?? 'overview') === 'overview' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
            Overview
        </a>
        <span class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium text-slate-400">Assignments</span>
        @if ($canSeeSalary)
            <a href="{{ route('staff.show', ['staff' => $staff, 'tab' => 'payroll']) }}"
                class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ ($tab ?? 'overview') === 'payroll' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                Payroll
            </a>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-5">
        @if (($tab ?? 'overview') === 'payroll' && $canSeeSalary)
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h4 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">Payroll History</h4>
                <a href="{{ route('payroll.index') }}" class="text-xs font-medium text-dugsi-primary hover:underline">Open all payroll runs →</a>
            </div>
            @if (($payrollHistory ?? collect())->isEmpty())
                <p class="py-8 text-center text-sm text-slate-400">No payslips yet for this staff member.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50">
                                @foreach (['Month', 'Payslip #', 'Amount', 'Confirmed', ''] as $h)
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payrollHistory as $item)
                                <tr class="border-b border-slate-50 hover:bg-slate-50">
                                    <td class="px-3 py-2.5 font-medium text-slate-800">
                                        {{ $item->payrollRun?->billing_month?->format('F Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-xs text-slate-500">{{ $item->payslip_number }}</td>
                                    <td class="px-3 py-2.5 font-medium text-slate-800">{{ \App\Support\Money::format($item->salary_usd) }}</td>
                                    <td class="px-3 py-2.5 text-xs text-slate-500">
                                        {{ $item->payrollRun?->confirmed_at?->format('j M Y') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($item->payrollRun)
                                            <a href="{{ route('payroll.payslip', [$item->payrollRun, $item]) }}" target="_blank" rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline">
                                                <x-icon name="eye" :size="11" /> Payslip
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <div class="grid grid-cols-1 gap-6 text-sm md:grid-cols-2">
                <div>
                    <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Personal</h4>
                    <div class="space-y-2">
                        @foreach ([
                            ['Full Name', $staff->full_name],
                            ['Employee ID', $staff->employee_code],
                            ['Gender', $staff->gender?->label() ?? '—'],
                            ['DOB', $staff->dob?->format('Y-m-d') ?? '—'],
                            ['Phone', $staff->phone ?? '—'],
                        ] as [$k, $v])
                            <div class="flex gap-2">
                                <span class="w-28 flex-shrink-0 text-xs text-slate-400">{{ $k }}</span>
                                <span class="text-xs font-medium text-slate-800">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Employment</h4>
                    <div class="space-y-2">
                        @foreach ([
                            ['Role', $staff->role_label->label()],
                            ['Subject', $staff->subject_specialty ?? '—'],
                            ['Qualification', $staff->qualification ?? '—'],
                            ['Date Joined', $staff->date_joined?->format('d M Y') ?? '—'],
                            ['Status', $staff->status->label()],
                            ['Salary', $canSeeSalary ? ($staff->fixed_salary_usd !== null ? '$'.number_format((float) $staff->fixed_salary_usd, 2) : '—') : '••••'],
                        ] as [$k, $v])
                            <div class="flex gap-2">
                                <span class="w-28 flex-shrink-0 text-xs text-slate-400">{{ $k }}</span>
                                <span class="text-xs font-medium text-slate-800">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($canEdit && $staff->checkin_token)
                <div class="mt-6 rounded-md border border-slate-200 bg-slate-50 p-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-700">Phone check-in</h4>
                    <p class="mb-2 text-xs text-slate-500">Send this link to their phone (WhatsApp). They must be on school Wi‑Fi and enroll fingerprint / Face ID once.</p>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="text" readonly id="checkin-url" value="{{ $staff->checkinUrl() }}"
                            class="w-full flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-xs">
                        <button type="button" id="copy-checkin"
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Copy link
                        </button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('staff.checkin-link', $staff) }}"
                            data-dugsi-confirm="Old check-in links will stop working. Continue?"
                            data-dugsi-confirm-title="Regenerate link"
                            data-dugsi-confirm-ok="Regenerate">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-blue-700 hover:underline">Regenerate link</button>
                        </form>
                        <form method="POST" action="{{ route('staff.reset-biometric', $staff) }}"
                            data-dugsi-confirm="They will need to enroll biometric again on their phone."
                            data-dugsi-confirm-title="Reset biometric"
                            data-dugsi-confirm-ok="Reset"
                            data-dugsi-danger>
                            @csrf
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Reset biometric</button>
                        </form>
                    </div>
                </div>
                @push('scripts')
                <script>
                document.getElementById('copy-checkin')?.addEventListener('click', async () => {
                    const input = document.getElementById('checkin-url');
                    try {
                        await navigator.clipboard.writeText(input.value);
                        window.DugsiUI?.success('Link copied');
                    } catch {
                        input.select();
                        document.execCommand('copy');
                        window.DugsiUI?.success('Link copied');
                    }
                });
                </script>
                @endpush
            @endif
        @endif
    </div>
</div>

@if ($canEdit)
<div id="edit-staff-modal" class="hidden" data-dugsi-width="32rem">
    <form method="POST" action="{{ route('staff.update', $staff) }}" class="p-5">
        @csrf
        @method('PUT')
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Edit Staff Member</h3>
        <div class="space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
                    <input name="full_name" value="{{ old('full_name', $staff->full_name) }}" required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                    <select name="role_label" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @foreach ($roleLabels as $rl)
                            <option value="{{ $rl->value }}" @selected(old('role_label', $staff->role_label->value) === $rl->value)>{{ $rl->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Subject</label>
                    <select name="subject_specialty" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($subjects as $sub)
                            <option value="{{ $sub }}" @selected(old('subject_specialty', $staff->subject_specialty) === $sub)>{{ $sub }}</option>
                        @endforeach
                    </select>
                    @error('subject_specialty')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                    <input name="phone" value="{{ old('phone', $staff->phone) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Gender</label>
                    <select name="gender" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($genders as $g)
                            <option value="{{ $g->value }}" @selected(old('gender', $staff->gender?->value) === $g->value)>{{ $g->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-date-select
                    name="dob"
                    label="Date of Birth"
                    :value="old('dob', $staff->dob?->toDateString())"
                    :min-year="1950"
                    :max-year="now()->year - 18"
                    :allow-empty="true"
                    hint="Day · Month · Year — optional"
                />
                <x-date-select
                    name="date_joined"
                    label="Date Joined"
                    :value="old('date_joined', $staff->date_joined?->toDateString())"
                    :min-year="1995"
                    :max-year="now()->year"
                    :allow-empty="true"
                    hint="Day · Month · Year — optional"
                />
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Monthly Salary ($)</label>
                    <input type="number" step="0.01" min="0" name="fixed_salary_usd"
                        value="{{ old('fixed_salary_usd', $staff->fixed_salary_usd) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Qualification</label>
                    <select name="qualification" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($qualifications as $q)
                            <option value="{{ $q }}" @selected(old('qualification', $staff->qualification) === $q)>{{ $q }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                    <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $staff->status->value) === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="text-[11px] text-slate-400">
                Employee ID ({{ $staff->employee_code }}) cannot be changed.
                Changing role or status also updates the linked login (if any): role sync, deactivate on leave/resigned, and Librarian removes login access.
            </p>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Changes</button>
        </div>
    </form>
</div>

@if ($errors->any() || request()->boolean('edit'))
    <script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#edit-staff-modal'));</script>
@endif
@endif
@endsection
