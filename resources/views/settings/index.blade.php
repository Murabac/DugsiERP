@extends('layouts.app')

@section('title', 'Settings — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Settings" sub="Manage users, school profile, and academic configuration" />

    <x-tabs :active="$tab" :tabs="[
        ['key' => 'users', 'label' => 'Users', 'href' => route('settings.index', ['tab' => 'users'])],
        ['key' => 'school', 'label' => 'School Profile', 'href' => route('settings.index', ['tab' => 'school'])],
        ['key' => 'academic', 'label' => 'Academic Setup', 'href' => route('settings.index', ['tab' => 'academic'])],
    ]" class="mb-4" />

    @if ($tab === 'users')
        <div class="space-y-4">
            <div class="flex justify-stretch sm:justify-end">
                <button type="button" data-dugsi-open="#add-user-modal"
                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56] sm:w-auto">
                    + Add User
                </button>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">System Users</h3>
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
                                <td class="px-4 py-2.5"><x-status-badge :status="$user->role->value" :label="$user->role->label()" /></td>
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
                    Only Super Admins can create or remove Admin accounts.
                </div>
            @endunless
        </div>

        <div id="add-user-modal" class="hidden" data-dugsi-width="28rem">
            <form method="POST" action="{{ route('settings.users.store') }}" class="p-5">
                @csrf
                <h3 class="mb-4 text-sm font-semibold text-slate-900">Add System User</h3>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
                        <input name="name" value="{{ old('name') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                        <input name="phone" value="{{ old('phone') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                        <select name="role" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                            @foreach ($assignableRoles as $role)
                                <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Link to staff (optional)</label>
                        <select name="staff_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">None</option>
                            @foreach ($unlinkedStaff as $member)
                                <option value="{{ $member->id }}" @selected((string) old('staff_id') === (string) $member->id)>
                                    {{ $member->employee_code }} — {{ $member->full_name }} ({{ $member->role_label->label() }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @unless ($isSuperAdmin)
                        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Admin accounts can only be created by a Super Admin.</div>
                    @endunless
                    <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">Temporary password: <strong>password</strong></div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Create User</button>
                </div>
            </form>
        </div>

        @if ($errors->any() && $tab === 'users')
            <script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#add-user-modal'));</script>
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

        <div class="mt-4 max-w-xl rounded-lg border border-slate-200 bg-white p-5">
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Monthly Fee</h3>
            <p class="mb-4 text-sm text-slate-500">
                One school-wide monthly amount for every class. Sibling and need-based discounts apply to individual students only (not different fees per form).
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
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Sibling discount %</label>
                        <input type="number" name="sibling_discount_percent" min="0" max="100" required
                            value="{{ old('sibling_discount_percent', $feeSettings['sibling_discount_percent']) }}"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        <p class="mt-1 text-[11px] text-slate-400">2nd+ child with the same primary guardian phone</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Need-based discount %</label>
                        <input type="number" name="need_based_discount_percent" min="0" max="100" required
                            value="{{ old('need_based_discount_percent', $feeSettings['need_based_discount_percent']) }}"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        <p class="mt-1 text-[11px] text-slate-400">When the student has need-based assistance enabled</p>
                    </div>
                </div>
                <p class="text-xs text-slate-400">If both discounts apply to a student, the larger percentage is used (not stacked).</p>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    Save Fee Settings
                </button>
            </form>
        </div>

        <div class="mt-4 max-w-xl rounded-lg border border-slate-200 bg-white p-5">
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
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Late after</label>
                    <input type="time" name="staff_attendance_late_after" required
                        value="{{ old('staff_attendance_late_after', $staffAttendanceSettings['late_after'] ?? '08:00') }}"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-[11px] text-slate-400">Phone check-ins after this time are marked Late.</p>
                    @error('staff_attendance_late_after')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    Save check-in settings
                </button>
            </form>
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
                <p class="text-sm text-slate-500">Class sections are managed under <a href="{{ route('classes.manage') }}" class="font-medium text-dugsi-primary hover:underline">Manage Classes</a>. Monthly fee is under the <a href="{{ route('settings.index', ['tab' => 'school']) }}" class="font-medium text-dugsi-primary hover:underline">School</a> tab.</p>
            </div>
        </div>
    @endif
</div>
@endsection
