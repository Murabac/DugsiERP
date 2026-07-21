@extends('layouts.app')

@section('title', 'Settings — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Settings" sub="Manage users, school profile, and academic configuration" />

    <x-tabs :active="$tab" :tabs="array_values(array_filter([
        ['key' => 'users', 'label' => 'Users', 'href' => route('settings.index', ['tab' => 'users'])],
        ['key' => 'roles', 'label' => 'Roles', 'href' => route('settings.index', ['tab' => 'roles'])],
        ['key' => 'school', 'label' => 'School Profile', 'href' => route('settings.index', ['tab' => 'school'])],
        ['key' => 'fees', 'label' => 'Monthly Fee', 'href' => route('settings.index', ['tab' => 'fees'])],
        ['key' => 'checkin', 'label' => 'Staff Check-in', 'href' => route('settings.index', ['tab' => 'checkin'])],
        ['key' => 'grades', 'label' => 'Grades', 'href' => route('settings.index', ['tab' => 'grades'])],
        ['key' => 'academic', 'label' => 'Academic Setup', 'href' => route('settings.index', ['tab' => 'academic'])],
    ], fn ($t) => ($t['key'] ?? '') !== 'roles' || ($canManageRoles ?? false)))" class="mb-4" />

    @if ($tab === 'users')
        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">System Users</h3>
                    <p class="mt-1 text-xs text-slate-500">Logins are created from Staff (Create login account). Use this list to activate or remove accounts.</p>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            @foreach (['User', 'Email', 'Role', 'Staff link', 'Last Login', 'Status', ''] as $h)
                                <th class="px-4 py-2 text-left text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-slate-50 hover:bg-slate-50">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">{{ $user->initials() }}</div>
                                        <span class="font-medium text-slate-900">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-500">{{ $user->email }}</td>
                                <td class="px-4 py-2.5"><x-status-badge :status="$user->roleKey()" :label="$user->roleLabel()" /></td>
                                <td class="px-4 py-2.5 text-xs text-slate-500">
                                    @if ($user->staff)
                                        <a href="{{ route('staff.show', $user->staff) }}" class="text-blue-700 hover:underline">{{ $user->staff->employee_code }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-400">{{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    @if (auth()->user()->canManageUser($user))
                                        <form method="POST" action="{{ route('settings.users.toggle', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium {{ $user->is_active ? 'text-green-700' : 'text-slate-400' }}">
                                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs {{ $user->is_active ? 'text-green-700' : 'text-slate-400' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if (auth()->user()->canManageUser($user))
                                        <form method="POST" action="{{ route('settings.users.destroy', $user) }}"
                                            data-dugsi-confirm="Deactivate this user account?"
                                            data-dugsi-confirm-title="Remove user"
                                            data-dugsi-confirm-ok="Deactivate"
                                            data-dugsi-danger>
                                            @csrf
                                            <button type="submit" class="text-xs text-red-500 hover:underline">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            @unless ($isSuperAdmin)
                <div class="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                    Only Super Admins can remove Admin accounts.
                </div>
            @endunless
        </div>

    @elseif ($tab === 'roles' && ($canManageRoles ?? false))
        <div class="space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">Create custom roles and tick the features each role can use. System roles can have their permissions adjusted (except Super Admin).</p>
                <button type="button" data-dugsi-open="#add-role-modal"
                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56] sm:w-auto">
                    + New Role
                </button>
            </div>

            @foreach ($roles as $role)
                @php
                    $checked = $role->permissions->pluck('key')->all();
                    $isSuper = $role->key === 'super_admin';
                @endphp
                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-200 px-4 py-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $role->name }}</h3>
                            <p class="text-xs text-slate-500">
                                Key: <code class="rounded bg-slate-100 px-1">{{ $role->key }}</code>
                                · {{ $role->users_count }} user{{ $role->users_count === 1 ? '' : 's' }}
                                @if ($role->is_system)
                                    · System role
                                @endif
                            </p>
                            @if ($role->description)
                                <p class="mt-1 text-xs text-slate-500">{{ $role->description }}</p>
                            @endif
                        </div>
                        @unless ($isSuper || $role->is_system)
                            <form method="POST" action="{{ route('settings.roles.destroy', $role) }}"
                                data-dugsi-confirm="Delete this role? Users must be reassigned first."
                                data-dugsi-confirm-title="Delete role"
                                data-dugsi-confirm-ok="Delete"
                                data-dugsi-danger>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        @endunless
                    </div>

                    @if ($isSuper)
                        <div class="px-4 py-3 text-sm text-slate-600">
                            Super Admin always has every permission, including managing roles. This role cannot be edited.
                        </div>
                    @else
                        <form method="POST" action="{{ route('settings.roles.update', $role) }}" class="space-y-4 p-4">
                            @csrf
                            @method('PUT')
                            @unless ($role->is_system)
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Name</label>
                                        <input name="name" value="{{ old('name', $role->name) }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Description</label>
                                        <input name="description" value="{{ old('description', $role->description) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                </div>
                            @else
                                <input type="hidden" name="name" value="{{ $role->name }}">
                            @endunless

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($permissionGroups as $group => $perms)
                                    @if ($group === 'Roles')
                                        @continue
                                    @endif
                                    <fieldset class="rounded-md border border-slate-100 bg-slate-50/80 p-3">
                                        <legend class="px-1 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $group }}</legend>
                                        <div class="mt-1 space-y-1.5">
                                            @foreach ($perms as $key => $label)
                                                <label class="flex items-start gap-2 text-xs text-slate-700">
                                                    <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                                        class="mt-0.5 rounded border-slate-300"
                                                        @checked(in_array($key, old('permissions', $checked), true))>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                                    Save {{ $role->name }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div id="add-role-modal" class="hidden" data-dugsi-width="42rem">
            <form method="POST" action="{{ route('settings.roles.store') }}" class="max-h-[80vh] overflow-y-auto p-5">
                @csrf
                <h3 class="mb-4 text-sm font-semibold text-slate-900">New Custom Role</h3>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Name <span class="text-red-500">*</span></label>
                        <input name="name" value="{{ old('name') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Registrar">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Description</label>
                        <input name="description" value="{{ old('description') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($permissionGroups as $group => $perms)
                            @if ($group === 'Roles')
                                @continue
                            @endif
                            <fieldset class="rounded-md border border-slate-100 bg-slate-50/80 p-3">
                                <legend class="px-1 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $group }}</legend>
                                <div class="mt-1 space-y-1.5">
                                    @foreach ($perms as $key => $label)
                                        <label class="flex items-start gap-2 text-xs text-slate-700">
                                            <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                                class="mt-0.5 rounded border-slate-300"
                                                @checked(in_array($key, old('permissions', []), true))>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    @error('permissions')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Create Role</button>
                </div>
            </form>
        </div>

        @if ($errors->any() && $tab === 'roles' && ! old('_method'))
            <script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#add-role-modal'));</script>
        @endif

    @elseif ($tab === 'school')
        <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">School Profile</h3>
            <p class="mb-4 text-sm text-slate-500">
                This name appears on printable documents (grade reports, attendance registers, timetables).
                The app product name remains Dugsi ERP in the sidebar and login only.
            </p>
            <form method="POST" action="{{ route('settings.school-profile') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">School name</label>
                    <input type="text" name="school_name" required maxlength="120"
                        value="{{ old('school_name', $schoolProfile['name']) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    @error('school_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Tagline</label>
                    <input type="text" name="school_tagline" maxlength="120"
                        value="{{ old('school_tagline', $schoolProfile['tagline']) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                        placeholder="Secondary School">
                    @error('school_tagline')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Location</label>
                    <input type="text" name="school_location" required maxlength="120"
                        value="{{ old('school_location', $schoolProfile['location']) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                        placeholder="Somaliland">
                    @error('school_location')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <x-btn type="submit">Save School Profile</x-btn>
            </form>

            <div class="mt-5 rounded-md border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Letterhead preview</div>
                <div class="mt-2 border-b-2 border-dugsi-primary pb-2">
                    <div class="text-lg font-bold text-dugsi-primary">{{ $schoolProfile['name'] }}</div>
                    <div class="text-xs text-slate-500">{{ trim(($schoolProfile['tagline'] ?? '').(($schoolProfile['tagline'] ?? '') && ($schoolProfile['location'] ?? '') ? ' · ' : '').($schoolProfile['location'] ?? '')) }}</div>
                </div>
                <p class="mt-2 text-[11px] text-slate-400">Appears on printable documents (not the Dugsi ERP product name).</p>
            </div>
        </div>

    @elseif ($tab === 'fees')
        <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Monthly Fee</h3>
            <p class="mb-4 text-sm text-slate-500">
                One school-wide monthly amount for every class. Sibling discount is school-wide; need-based discount is a fixed USD amount set per student.
            </p>
            <form method="POST" action="{{ route('settings.fee-settings') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Monthly fee (USD)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-slate-400">$</span>
                        <input type="number" name="monthly_fee_usd" step="0.01" min="0" max="99999.99" required
                            value="{{ old('monthly_fee_usd', $feeSettings['monthly_fee_usd']) }}"
                            class="w-32 rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    </div>
                    @error('monthly_fee_usd')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Transport fee (USD / month)</label>
                    <div class="flex items-center gap-1">
                        <span class="text-slate-400">$</span>
                        <input type="number" name="transport_fee_usd" step="0.01" min="0" max="99999.99" required
                            value="{{ old('transport_fee_usd', $feeSettings['transport_fee_usd'] ?? 15) }}"
                            class="w-32 rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Added to the tuition invoice for students with an active bus assignment. Discounts do not apply to transport.</p>
                    @error('transport_fee_usd')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Sibling discount %</label>
                    <input type="number" name="sibling_discount_percent" min="0" max="100" required
                        value="{{ old('sibling_discount_percent', $feeSettings['sibling_discount_percent']) }}"
                        class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    <p class="mt-1 text-[11px] text-slate-400">2nd+ child with the same primary guardian phone. If a student also has a need-based USD discount, the larger dollar reduction is used (not stacked).</p>
                    @error('sibling_discount_percent')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    Save Fee Settings
                </button>
            </form>
        </div>

    @elseif ($tab === 'checkin')
        <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Staff phone check-in</h3>
            <p class="mb-4 text-sm text-slate-500">
                Staff open a personal link on their phone and use fingerprint / Face ID.
                Check-in only works when the phone is on school Wi‑Fi (matched by IP / CIDR below — browsers cannot read the Wi‑Fi name).
            </p>
            <form method="POST" action="{{ route('settings.staff-attendance') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Allowed school IPs / CIDRs</label>
                    <textarea name="staff_attendance_allowed_cidrs" rows="3"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                        placeholder="197.x.x.x&#10;192.168.1.0/24">{{ old('staff_attendance_allowed_cidrs', $staffAttendanceSettings['allowed_cidrs'] ?? '') }}</textarea>
                    <p class="mt-1 text-[11px] text-slate-400">Comma or new-line separated. Leave empty only for local testing — production should list the school public IP and/or LAN range.</p>
                    @error('staff_attendance_allowed_cidrs')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Check-in start</label>
                        <input type="time" name="staff_attendance_checkin_start" required
                            value="{{ old('staff_attendance_checkin_start', $staffAttendanceSettings['checkin_start'] ?? '07:00') }}"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-400">Earliest time staff can check in.</p>
                        @error('staff_attendance_checkin_start')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Late after</label>
                        <input type="time" name="staff_attendance_late_after" required
                            value="{{ old('staff_attendance_late_after', $staffAttendanceSettings['late_after'] ?? '08:00') }}"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-400">Check-ins after this time are Late.</p>
                        @error('staff_attendance_late_after')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Check-out time</label>
                        <input type="time" name="staff_attendance_checkout_time" required
                            value="{{ old('staff_attendance_checkout_time', $staffAttendanceSettings['checkout_time'] ?? '16:00') }}"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-400">Earliest time staff can check out.</p>
                        @error('staff_attendance_checkout_time')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    Save check-in settings
                </button>
            </form>
        </div>

    @elseif ($tab === 'grades')
        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-1 text-xs font-semibold tracking-wider text-slate-700 uppercase">Term Mark Splits</h3>
                <p class="mb-4 text-sm text-slate-500">
                    The year total is <strong>100</strong>. Split that across terms (e.g. Term 1 = 20). Teachers enter the actual mark for each term, not a percentage.
                </p>

                <form method="POST" action="{{ route('settings.term-marks') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (\App\Enums\AcademicTerm::options() as $term)
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">{{ $term->label() }} (max marks)</label>
                                <input type="number" name="term_marks[{{ $term->value }}]"
                                    value="{{ old('term_marks.'.$term->value, $termMarkMaxima[$term->value] ?? '') }}"
                                    min="0.01" max="100" step="0.01" required
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary {{ $errors->has('term_marks.'.$term->value) ? 'border-red-400' : '' }}">
                                @error('term_marks.'.$term->value)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                    @error('term_marks')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-500">
                            Current total:
                            <span class="font-semibold text-slate-800">{{ number_format(array_sum($termMarkMaxima), 2) }}</span>
                            / 100
                        </p>
                        <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                            Save Term Marks
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-1 text-xs font-semibold tracking-wider text-slate-700 uppercase">Grade Boundaries</h3>
                <p class="mb-4 text-sm text-slate-500">
                    Letter grades are based on the <strong>percentage</strong> of the term’s max marks (score ÷ term max × 100).
                </p>

                <form method="POST" action="{{ route('settings.grade-boundaries') }}">
                    @csrf
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
                                @foreach ($gradeBoundaries as $i => $b)
                                    <tr class="border-b border-slate-50">
                                        <td class="px-4 py-2.5">
                                            <input type="hidden" name="boundaries[{{ $i }}][letter]" value="{{ $b->letter->value }}">
                                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-bold {{ $b->letter->badgeClass() }}">{{ $b->letter->value }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <input type="number" name="boundaries[{{ $i }}][min_percent]" value="{{ old("boundaries.$i.min_percent", $b->min_percent) }}"
                                                min="0" max="100"
                                                class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <input type="number" name="boundaries[{{ $i }}][max_percent]" value="{{ old("boundaries.$i.max_percent", $b->max_percent) }}"
                                                min="0" max="100"
                                                class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <input type="text" name="boundaries[{{ $i }}][remark]" value="{{ old("boundaries.$i.remark", $b->remark) }}"
                                                maxlength="64"
                                                class="w-full min-w-[120px] rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-slate-500">
                                            {{ $b->min_percent }}–{{ $b->max_percent }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('boundaries')
                        <p class="mt-2 px-4 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end border-t border-slate-200 px-4 py-3">
                        <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                            Save Boundaries
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @else
        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Current Academic Year</h3>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">{{ $academicYear }}</div>
                <p class="mt-2 text-xs text-slate-500">Year switcher (view past years without changing live data) is planned — see CONTEXT.md. The app currently operates on this computed year.</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h3 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Grade Edit Window</h3>
                @if ($isSuperAdmin)
                    <form method="POST" action="{{ route('settings.grade-edit-window') }}" class="max-w-md space-y-3">
                        @csrf
                        <p class="text-sm text-slate-600">
                            Teachers (including class headmasters) may change a score for this many days after their <strong>first save</strong>.
                            After day 1 they must add an edit note. Admins can always correct grades.
                        </p>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Days (1–14)</label>
                            <input type="number" name="grade_edit_window_days" min="1" max="14" required
                                value="{{ old('grade_edit_window_days', $gradeEditWindowDays) }}"
                                class="w-28 rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            @error('grade_edit_window_days')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">
                            Save Window
                        </button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">
                        Current teacher edit window: <strong>{{ $gradeEditWindowDays }} day{{ $gradeEditWindowDays === 1 ? '' : 's' }}</strong>
                        after first save. Only Super Admin can change this.
                    </p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">School day structure</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            How many periods students take each school day (e.g. 7 most days and 6 on Wednesday = 34/week).
                            Also set start/end times for each period number.
                        </p>
                    </div>
                    <div id="day-structure-banner" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                        Weekly capacity: <strong id="day-structure-total">{{ $weeklyCapacity }}</strong>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.day-structure') }}" id="day-structure-form" class="space-y-4">
                    @csrf
                    <div>
                        <div class="mb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Periods per day</div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                            @foreach ($schoolDays as $day)
                                <label class="rounded-md border border-slate-200 bg-slate-50 px-2 py-2">
                                    <span class="block text-[11px] font-medium text-slate-600">{{ \App\Support\SchoolWeek::dayLabel($day) }}</span>
                                    <input type="number"
                                        name="per_day[{{ $day }}]"
                                        value="{{ old('per_day.'.$day, $periodsPerDay[$day] ?? 6) }}"
                                        min="{{ \App\Support\SchoolWeek::MIN_PERIODS_PER_DAY }}"
                                        max="{{ $maxPeriodsPerDay }}"
                                        required
                                        data-per-day-input
                                        class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-center text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                </label>
                            @endforeach
                        </div>
                        @error('per_day')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div class="mb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Period times</div>
                        <div class="overflow-hidden rounded-md border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Period</th>
                                        <th class="px-3 py-2 text-left">Start</th>
                                        <th class="px-3 py-2 text-left">End</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" id="period-times-body">
                                    @php
                                        $defs = collect($dayStructure['definitions'])->keyBy('period');
                                        $defaultDefs = collect(\App\Support\SchoolWeek::defaultPeriodDefinitions())->keyBy('period');
                                        $showThrough = max(array_values($periodsPerDay));
                                    @endphp
                                    @for ($n = 1; $n <= $maxPeriodsPerDay; $n++)
                                        @php
                                            $def = $defs->get($n) ?? $defaultDefs->get($n) ?? ['start' => '08:00', 'end' => '08:45'];
                                        @endphp
                                        <tr data-period-time-row data-period="{{ $n }}" class="{{ $n > $showThrough ? 'hidden' : '' }}">
                                            <td class="px-3 py-2 font-medium text-slate-800">
                                                P{{ $n }}
                                                <input type="hidden" name="definitions[{{ $n }}][period]" value="{{ $n }}">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="time" name="definitions[{{ $n }}][start]"
                                                    value="{{ old('definitions.'.$n.'.start', $def['start']) }}"
                                                    required
                                                    class="rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="time" name="definitions[{{ $n }}][end]"
                                                    value="{{ old('definitions.'.$n.'.end', $def['end']) }}"
                                                    required
                                                    class="rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                        @error('definitions')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button type="submit" name="reset" value="1"
                            class="text-xs text-slate-500 underline hover:text-slate-800"
                            formnovalidate
                            onclick="return confirm('Reset to factory day structure (7+7+7+7+6 = 34/week)?')">
                            Reset day structure
                        </button>
                        <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                            Save day structure
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">Weekly Timetable Periods</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Periods per subject per class each week. Must fit within weekly capacity ({{ $weeklyCapacity }}).
                            Used by Timetable <strong>Generate</strong> defaults and
                            <a href="{{ route('timetable.requirements') }}" class="font-medium text-dugsi-primary hover:underline">Requirements</a>.
                        </p>
                    </div>
                    <div id="weekly-periods-banner" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                        Total: <strong id="weekly-periods-total">{{ array_sum($weeklyPeriods) }}</strong> / {{ $weeklyCapacity }}
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.weekly-periods') }}" id="weekly-periods-form" class="space-y-3">
                    @csrf
                    <div class="divide-y divide-slate-100 rounded-md border border-slate-200">
                        @foreach ($weeklyPeriods as $subjectName => $count)
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <span class="inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-medium {{ $subjectColors[$subjectName] ?? 'bg-slate-50 border-slate-200 text-slate-800' }}">
                                    {{ $subjectName }}
                                </span>
                                <input type="number"
                                    name="periods[{{ $subjectName }}]"
                                    value="{{ old('periods.'.$subjectName, $count) }}"
                                    min="0"
                                    max="{{ $weeklyCapacity }}"
                                    required
                                    data-weekly-period-input
                                    class="w-16 rounded-md border border-slate-300 px-2 py-1.5 text-center text-sm font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            </div>
                        @endforeach
                    </div>
                    @error('periods')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <button type="submit" name="reset" value="1"
                            class="text-xs text-slate-500 underline hover:text-slate-800"
                            formnovalidate
                            onclick="return confirm('Reset all subjects to factory defaults?')">
                            Reset to factory defaults
                        </button>
                        <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                            Save weekly periods
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Class sections are managed under <a href="{{ route('classes.manage') }}" class="font-medium text-dugsi-primary hover:underline">Manage Classes</a>. Monthly fee is under the <a href="{{ route('settings.index', ['tab' => 'fees']) }}" class="font-medium text-dugsi-primary hover:underline">Monthly Fee</a> tab.</p>
            </div>
        </div>
    @endif
</div>

@if ($tab === 'academic')
@push('scripts')
<script>
(function () {
    const perDayInputs = document.querySelectorAll('[data-per-day-input]');
    const dayTotalEl = document.getElementById('day-structure-total');
    const periodRows = document.querySelectorAll('[data-period-time-row]');

    function dayCapacity() {
        let total = 0;
        let max = 1;
        perDayInputs.forEach((el) => {
            const n = Number(el.value || 0);
            total += n;
            max = Math.max(max, n);
        });
        periodRows.forEach((row) => {
            const p = Number(row.dataset.period || 0);
            const active = p <= max;
            row.classList.toggle('hidden', !active);
            row.querySelectorAll('input').forEach((input) => {
                input.disabled = !active;
            });
        });
        if (dayTotalEl) dayTotalEl.textContent = String(total);
        return total;
    }

    perDayInputs.forEach((el) => el.addEventListener('input', dayCapacity));
    const capacity = dayCapacity();

    const inputs = document.querySelectorAll('[data-weekly-period-input]');
    const totalEl = document.getElementById('weekly-periods-total');
    const banner = document.getElementById('weekly-periods-banner');
    if (!inputs.length || !totalEl) return;

    function refresh() {
        let total = 0;
        inputs.forEach((el) => { total += Number(el.value || 0); });
        totalEl.textContent = String(total);
        const cap = dayCapacity();
        if (!banner) return;
        banner.classList.toggle('border-amber-200', total > cap || total < 1);
        banner.classList.toggle('bg-amber-50', total > cap || total < 1);
        banner.classList.toggle('text-amber-800', total > cap || total < 1);
        banner.classList.toggle('border-slate-200', total <= cap && total >= 1);
        banner.classList.toggle('bg-slate-50', total <= cap && total >= 1);
        banner.classList.toggle('text-slate-600', total <= cap && total >= 1);
    }

    inputs.forEach((el) => el.addEventListener('input', refresh));
    refresh();
})();
</script>
@endpush
@endif
@endsection
