@extends('layouts.app')

@section('title', $staff->full_name.' — Staff — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Staff', 'url' => route('staff.index')],
        ['label' => $staff->full_name],
    ]" />

    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            <div class="font-medium">Could not save changes:</div>
            <ul class="mt-1 list-disc pl-4 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                            <button type="button" onclick="document.getElementById('edit-staff-modal').showModal()"
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

    <div class="flex w-fit gap-1 rounded-lg bg-slate-100 p-1">
        <span class="rounded-md bg-white px-3 py-1.5 text-xs font-medium text-slate-900 shadow-sm">Overview</span>
        <span class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-400">Assignments (Week 4)</span>
        <span class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-400">Payroll (Week 8)</span>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="grid grid-cols-2 gap-6 text-sm">
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
    </div>
</div>

@if ($canEdit)
<dialog id="edit-staff-modal" class="m-auto w-full max-w-lg rounded-xl border border-slate-200 p-0 shadow-xl">
    <form method="POST" action="{{ route('staff.update', $staff) }}" class="p-5">
        @csrf
        @method('PUT')
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Edit Staff Member</h3>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
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
            <button type="button" onclick="this.closest('dialog').close()" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Changes</button>
        </div>
    </form>
</dialog>

@if ($errors->any())
    <script>document.getElementById('edit-staff-modal')?.showModal();</script>
@endif
@endif
@endsection
