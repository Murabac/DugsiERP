<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SchoolWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'users');
        $allowedTabs = ['users', 'school', 'fees', 'checkin', 'grades', 'academic'];
        if ($request->user()?->hasPermission('roles.manage')) {
            $allowedTabs[] = 'roles';
        }
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'users';
        }

        $actor = $request->user();

        $roleSort = Role::query()->pluck('sort_order', 'key');

        $users = User::query()
            ->with('staff')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (User $u) => (int) ($roleSort[$u->roleKey()] ?? 99))
            ->values();

        if ($tab === 'grades' && \App\Models\GradeBoundary::query()->doesntExist()) {
            \App\Models\GradeBoundary::seedDefaults();
        }

        $roles = null;
        $permissionGroups = null;
        if ($tab === 'roles' && $actor->hasPermission('roles.manage')) {
            $roles = Role::query()
                ->with('permissions')
                ->withCount('users')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
            $permissionGroups = PermissionCatalog::grouped();
        }

        return view('settings.index', [
            'tab' => $tab,
            'users' => $users,
            'isSuperAdmin' => $actor->isSuperAdmin(),
            'canManageRoles' => $actor->hasPermission('roles.manage'),
            'roles' => $roles,
            'permissionGroups' => $permissionGroups,
            'academicYear' => \App\Support\AcademicYear::current(),
            'gradeEditWindowDays' => \App\Models\SchoolSetting::gradeEditWindowDays(),
            'gradeBoundaries' => \App\Models\GradeBoundary::ordered(),
            'termMarkMaxima' => \App\Support\TermMarks::maxima(),
            'schoolProfile' => [
                'name' => \App\Models\SchoolSetting::schoolName(),
                'location' => \App\Models\SchoolSetting::schoolLocation(),
                'tagline' => \App\Models\SchoolSetting::schoolTagline(),
            ],
            'feeSettings' => \App\Models\SchoolSetting::feeSettings(),
            'staffAttendanceSettings' => \App\Models\SchoolSetting::staffAttendanceSettings(),
            'weeklyPeriods' => SchoolWeek::weeklyPeriods(),
            'defaultWeeklyPeriods' => SchoolWeek::defaultWeeklyPeriods(),
            'weeklyCapacity' => SchoolWeek::weeklyCapacity(),
            'subjectColors' => SchoolWeek::subjectColors(),
            'dayStructure' => SchoolWeek::dayStructure(),
            'periodsPerDay' => SchoolWeek::periodsPerDay(),
            'maxPeriodsPerDay' => SchoolWeek::MAX_PERIODS_PER_DAY,
            'schoolDays' => SchoolWeek::days(),
        ]);
    }

    public function updateDayStructure(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($request->boolean('reset')) {
            SchoolWeek::setDayStructure(null);

            return redirect()
                ->route('settings.index', ['tab' => 'academic'])
                ->with('status', 'Day structure reset to factory defaults (34 periods/week).');
        }

        $dayRules = [];
        foreach (SchoolWeek::days() as $day) {
            $dayRules['per_day.'.$day] = [
                'required',
                'integer',
                'min:'.SchoolWeek::MIN_PERIODS_PER_DAY,
                'max:'.SchoolWeek::MAX_PERIODS_PER_DAY,
            ];
        }

        $data = $request->validate(array_merge([
            'per_day' => ['required', 'array'],
            'definitions' => ['required', 'array', 'min:1'],
            'definitions.*.period' => ['required', 'integer', 'min:1', 'max:'.SchoolWeek::MAX_PERIODS_PER_DAY],
            'definitions.*.start' => ['required', 'date_format:H:i'],
            'definitions.*.end' => ['required', 'date_format:H:i'],
        ], $dayRules));

        $maxDay = max(array_map('intval', $data['per_day']));
        foreach ($data['definitions'] as $row) {
            if ((int) $row['period'] > $maxDay) {
                continue;
            }
            if ($row['start'] >= $row['end']) {
                throw ValidationException::withMessages([
                    'definitions' => 'Period '.(int) $row['period'].' end time must be after start time.',
                ]);
            }
        }

        $normalized = SchoolWeek::setDayStructure([
            'per_day' => $data['per_day'],
            'definitions' => $data['definitions'],
        ]);

        $capacity = (int) array_sum($normalized['per_day']);
        $subjectTotal = array_sum(SchoolWeek::weeklyPeriods());
        $status = 'Day structure saved: '.$capacity.' periods/week ('
            .collect($normalized['per_day'])->map(fn ($n, $d) => SchoolWeek::dayLabel($d).' '.$n)->implode(', ')
            .').';

        if ($subjectTotal > $capacity) {
            $status .= ' Warning: subject plan totals '.$subjectTotal.' — reduce Weekly Timetable Periods below to fit.';
        }

        return redirect()
            ->route('settings.index', ['tab' => 'academic'])
            ->with('status', $status);
    }

    public function updateWeeklyPeriods(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($request->boolean('reset')) {
            SchoolWeek::setWeeklyPeriods(null);

            return redirect()
                ->route('settings.index', ['tab' => 'academic'])
                ->with('status', 'Weekly subject periods reset to factory defaults.');
        }

        $subjectNames = array_keys(SchoolWeek::defaultWeeklyPeriods());
        $rules = ['periods' => ['required', 'array']];
        foreach ($subjectNames as $name) {
            $rules['periods.'.$name] = ['required', 'integer', 'min:0', 'max:'.SchoolWeek::weeklyCapacity()];
        }

        $data = $request->validate($rules);
        $normalized = SchoolWeek::normalizeWeeklyPeriods($data['periods']);
        $total = array_sum($normalized);

        if ($total > SchoolWeek::weeklyCapacity()) {
            throw ValidationException::withMessages([
                'periods' => 'Total periods ('.$total.') exceed weekly capacity ('.SchoolWeek::weeklyCapacity().').',
            ]);
        }

        if ($total < 1) {
            throw ValidationException::withMessages([
                'periods' => 'Set at least one subject period.',
            ]);
        }

        SchoolWeek::setWeeklyPeriods($normalized);

        return redirect()
            ->route('settings.index', ['tab' => 'academic'])
            ->with('status', 'Weekly subject periods saved. Timetable generate and requirements now use this plan.');
    }

    public function updateSchoolProfile(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:120'],
            'school_location' => ['required', 'string', 'max:120'],
            'school_tagline' => ['nullable', 'string', 'max:120'],
        ]);

        \App\Models\SchoolSetting::set('school_name', trim($data['school_name']));
        \App\Models\SchoolSetting::set('school_location', trim($data['school_location']));
        \App\Models\SchoolSetting::set(
            'school_tagline',
            trim((string) ($data['school_tagline'] ?? '')) ?: 'Secondary School'
        );

        return redirect()
            ->route('settings.index', ['tab' => 'school'])
            ->with('status', 'School profile updated. Printable documents will use the new name.');
    }

    public function updateFeeSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'monthly_fee_usd' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'transport_fee_usd' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'sibling_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        \App\Models\SchoolSetting::set('monthly_fee_usd', (string) round((float) $data['monthly_fee_usd'], 2));
        \App\Models\SchoolSetting::set('transport_fee_usd', (string) round((float) $data['transport_fee_usd'], 2));
        \App\Models\SchoolSetting::set('sibling_discount_percent', (string) (int) $data['sibling_discount_percent']);

        $revised = \App\Support\MonthlyInvoiceGenerator::recalculateUnpaid();

        $message = 'Monthly fee settings updated. Transport fee applies only to students with an active bus assignment.';
        if ($revised > 0) {
            $message .= ' Revised '.$revised.' unpaid invoice'.($revised === 1 ? '' : 's').'.';
        }

        return redirect()
            ->route('settings.index', ['tab' => 'fees'])
            ->with('status', $message);
    }

    public function updateStaffAttendance(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'staff_attendance_allowed_cidrs' => ['nullable', 'string', 'max:2000'],
            'staff_attendance_checkin_start' => ['required', 'date_format:H:i'],
            'staff_attendance_late_after' => ['required', 'date_format:H:i'],
            'staff_attendance_checkout_time' => ['required', 'date_format:H:i'],
        ]);

        $start = $data['staff_attendance_checkin_start'];
        $late = $data['staff_attendance_late_after'];
        $checkout = $data['staff_attendance_checkout_time'];

        if ($start > $late) {
            return back()->withInput()->withErrors([
                'staff_attendance_checkin_start' => 'Check-in start must be before or equal to Late after.',
            ]);
        }
        if ($late > $checkout) {
            return back()->withInput()->withErrors([
                'staff_attendance_late_after' => 'Late after must be before or equal to Check-out time.',
            ]);
        }

        $cidrs = trim((string) ($data['staff_attendance_allowed_cidrs'] ?? ''));
        foreach (preg_split('/[\s,;]+/', $cidrs) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_contains($part, '/')) {
                [$ip, $mask] = explode('/', $part, 2);
                if (! filter_var($ip, FILTER_VALIDATE_IP) || ! is_numeric($mask)) {
                    return back()->withInput()->withErrors([
                        'staff_attendance_allowed_cidrs' => "Invalid CIDR: {$part}",
                    ]);
                }
            } elseif (! filter_var($part, FILTER_VALIDATE_IP)) {
                return back()->withInput()->withErrors([
                    'staff_attendance_allowed_cidrs' => "Invalid IP: {$part}",
                ]);
            }
        }

        \App\Models\SchoolSetting::set('staff_attendance_allowed_cidrs', $cidrs);
        \App\Models\SchoolSetting::set('staff_attendance_checkin_start', $start);
        \App\Models\SchoolSetting::set('staff_attendance_late_after', $late);
        \App\Models\SchoolSetting::set('staff_attendance_checkout_time', $checkout);

        return redirect()
            ->route('settings.index', ['tab' => 'checkin'])
            ->with('status', 'Staff attendance check-in settings saved.');
    }

    public function updateGradeEditWindow(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'grade_edit_window_days' => ['required', 'integer', 'min:1', 'max:14'],
        ]);

        \App\Models\SchoolSetting::set('grade_edit_window_days', (string) $data['grade_edit_window_days']);

        return redirect()
            ->route('settings.index', ['tab' => 'academic'])
            ->with('status', 'Teacher grade edit window updated to '.$data['grade_edit_window_days'].' day(s).');
    }

    public function updateGradeBoundaries(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'boundaries' => ['required', 'array', 'min:1'],
            'boundaries.*.letter' => ['required', 'string', Rule::in(array_column(\App\Enums\LetterGrade::cases(), 'value'))],
            'boundaries.*.min_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'boundaries.*.max_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'boundaries.*.remark' => ['nullable', 'string', 'max:64'],
        ]);

        \App\Support\GradeScale::assertContiguous($data['boundaries']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $keep = [];
            foreach ($data['boundaries'] as $row) {
                $boundary = \App\Models\GradeBoundary::query()->updateOrCreate(
                    ['letter' => $row['letter']],
                    [
                        'min_percent' => (int) $row['min_percent'],
                        'max_percent' => (int) $row['max_percent'],
                        'remark' => trim((string) ($row['remark'] ?? '')) ?: null,
                    ]
                );
                $keep[] = $boundary->id;
            }

            \App\Models\GradeBoundary::query()->whereNotIn('id', $keep)->delete();
        });

        return redirect()
            ->route('settings.index', ['tab' => 'grades'])
            ->with('status', 'Grade boundaries updated.');
    }

    public function updateTermMarks(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'term_marks' => ['required', 'array'],
            'term_marks.*' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $maxima = [];
        foreach (\App\Enums\AcademicTerm::options() as $term) {
            if (! array_key_exists($term->value, $data['term_marks'])) {
                return back()->withInput()->withErrors([
                    'term_marks' => 'Set marks for every term.',
                ]);
            }
            $maxima[$term->value] = round((float) $data['term_marks'][$term->value], 2);
        }

        \App\Support\TermMarks::assertValidMaxima($maxima);

        foreach (\App\Enums\AcademicTerm::options() as $term) {
            \App\Models\SchoolSetting::set(
                \App\Support\TermMarks::settingKey($term),
                (string) $maxima[$term->value]
            );
        }

        return redirect()
            ->route('settings.index', ['tab' => 'grades'])
            ->with('status', 'Term mark splits updated. Year total remains 100.');
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);

        $user->update(['is_active' => ! $user->is_active]);

        $state = $user->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('settings.index', ['tab' => 'users'])
            ->with('status', "{$user->name} {$state}.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);
        abort_if($user->isSuperAdmin(), 403);

        $name = $user->name;
        $user->update([
            'is_active' => false,
            'staff_id' => null,
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'users'])
            ->with('status', "{$name} removed (account deactivated).");
    }
}
