@extends('layouts.app')

@section('title', 'Settings — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold text-slate-900">Settings</h2>
        <p class="mt-0.5 text-xs text-slate-500">Manage users, school profile, and academic configuration</p>
    </div>

    <div class="mb-4 flex w-full max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 sm:w-fit">
        @foreach ([
            'users' => 'Users',
            'school' => 'School Profile',
            'academic' => 'Academic Setup',
        ] as $key => $label)
            <a href="{{ route('settings.index', ['tab' => $key]) }}"
                class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ $tab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

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
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                    Save School Profile
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
                        <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
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
                <p class="text-sm text-slate-500">Class sections are managed under <a href="{{ route('classes.manage') }}" class="font-medium text-dugsi-primary hover:underline">Manage Classes</a>.</p>
            </div>
        </div>
    @endif
</div>
@endsection
