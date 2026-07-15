@extends('layouts.app')

@section('title', 'Staff — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Staff</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ $staff->count() }} staff members</p>
        </div>
        <button type="button" data-dugsi-open="#add-staff-modal" data-dugsi-width="32rem"
            class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56] sm:w-auto">
            + Add Staff
        </button>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <form method="GET" class="flex flex-col gap-2 border-b border-slate-200 px-3 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:px-4">
            <div class="relative w-full min-w-0 flex-1 sm:min-w-48">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400">⌕</span>
                <input type="search" name="q" value="{{ $search }}"
                    placeholder="Search name, ID, or phone…"
                    class="w-full rounded-md border border-slate-300 py-1.5 pr-3 pl-8 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
            </div>
            <select name="role" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm sm:w-auto">
                <option value="">All roles</option>
                @foreach ($roleLabels as $rl)
                    <option value="{{ $rl->value }}" @selected($roleFilter === $rl->value)>{{ $rl->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm sm:w-auto">
                <option value="">All status</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected($statusFilter === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 sm:w-auto">Filter</button>
        </form>

        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    @foreach (['ID', 'Name', 'Role', 'Subject / Dept', 'Joined', 'Status', ''] as $h)
                        <th class="px-4 py-2 text-left text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $member->employee_code }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-800">{{ $member->initials() }}</div>
                                <span class="font-medium text-slate-900">{{ $member->full_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <x-status-badge :status="$member->role_label->value" :label="$member->role_label->label()" />
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $member->subject_specialty ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $member->date_joined?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-2.5"><x-status-badge :status="$member->status" /></td>
                        <td class="px-4 py-2.5">
                            <a href="{{ route('staff.show', $member) }}" class="text-xs text-blue-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">No staff members found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

<div id="add-staff-modal" class="hidden" data-dugsi-width="32rem">
    <form method="POST" action="{{ route('staff.store') }}" class="p-5">
        @csrf
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Add Staff Member</h3>
        <div class="space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
                    <input name="full_name" value="{{ old('full_name') }}" required placeholder="e.g. Axmed Maxamed Farah"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                    <select name="role_label" id="staff-role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @foreach ($roleLabels as $rl)
                            <option value="{{ $rl->value }}" @selected(old('role_label', 'teacher') === $rl->value)>{{ $rl->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Subject</label>
                    <select name="subject_specialty" id="staff-subject" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($subjects as $sub)
                            <option value="{{ $sub }}" @selected(old('subject_specialty') === $sub)>{{ $sub }}</option>
                        @endforeach
                    </select>
                    @error('subject_specialty')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" placeholder="+252 63 xxx xxxx"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <x-date-select
                    name="date_joined"
                    label="Date Joined"
                    :value="old('date_joined')"
                    :min-year="1995"
                    :max-year="now()->year"
                    :allow-empty="true"
                    hint="Day · Month · Year — leave blank if unknown"
                />
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Monthly Salary ($)</label>
                    <input type="number" step="0.01" min="0" name="fixed_salary_usd" value="{{ old('fixed_salary_usd') }}" placeholder="e.g. 600"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Qualification</label>
                    <select name="qualification" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($qualifications as $q)
                            <option value="{{ $q }}" @selected(old('qualification') === $q)>{{ $q }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Gender</label>
                    <select name="gender" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($genders as $g)
                            <option value="{{ $g->value }}" @selected(old('gender') === $g->value)>{{ $g->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                    <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" @selected(old('status', 'active') === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="create_login" value="1" id="create-login" @checked(old('create_login')) class="rounded border-slate-300">
                    Create login account for this staff member
                </label>
                <div id="login-fields" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 {{ old('create_login') ? '' : 'hidden' }}">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Login email</label>
                        <input type="email" name="login_email" value="{{ old('login_email') }}" placeholder="user@dugsi.edu.sl"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('login_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('create_login')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Login phone (optional)</label>
                        <input name="login_phone" value="{{ old('login_phone') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <p class="col-span-2 text-[11px] text-slate-500">Temporary password will be <strong>password</strong> (change after first login later).</p>
                </div>
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Staff</button>
        </div>
    </form>
</div>

@if ($errors->any())
    <script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#add-staff-modal'));</script>
@endif

<script>
(() => {
    const checkbox = document.getElementById('create-login');
    const fields = document.getElementById('login-fields');
    checkbox?.addEventListener('change', () => fields?.classList.toggle('hidden', !checkbox.checked));
})();
</script>
@endsection
