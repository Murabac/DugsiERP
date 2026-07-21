<?php

namespace App\Http\Controllers;

use App\Enums\StaffAttendanceSource;
use App\Enums\StaffAttendanceStatus;
use App\Enums\StaffStatus;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\StaffWebauthnCredential;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));
        $roleKey = $this->resolveRoleFilter($request->query('role'));
        $roleOptions = $this->roleFilterOptions();

        $staffQuery = Staff::query()
            ->where('status', StaffStatus::Active)
            ->withCount('webauthnCredentials')
            ->orderBy('full_name');

        if ($roleKey !== null) {
            $staffQuery->where('role_label', $roleKey);
        }

        $staff = $staffQuery->get();

        $existing = StaffAttendanceRecord::query()
            ->whereDate('date', $date->toDateString())
            ->when(
                $staff->isNotEmpty(),
                fn ($q) => $q->whereIn('staff_id', $staff->pluck('id')),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->get()
            ->keyBy('staff_id');

        $rows = $staff->map(function (Staff $member) use ($existing) {
            $record = $existing->get($member->id);
            $oldStatus = old('statuses.'.$member->id);

            return [
                'staff' => $member,
                'status' => $oldStatus
                    ?? $record?->status?->value,
                'check_in_at' => $record?->check_in_at,
                'check_out_at' => $record?->check_out_at,
                'source' => $record?->source,
                'has_biometric' => $member->webauthn_credentials_count > 0,
            ];
        });

        return view('staff-attendance.index', [
            'date' => $date->toDateString(),
            'dateLabel' => $date->format('j F Y'),
            'role' => $roleKey,
            'roleOptions' => $roleOptions,
            'rows' => $rows,
            'isFuture' => $date->gt(now()->startOfDay()),
            'today' => now()->toDateString(),
            'alreadyMarked' => $existing->isNotEmpty(),
            'statuses' => StaffAttendanceStatus::cases(),
            'counts' => [
                'present' => $existing->where('status', StaffAttendanceStatus::Present)->count(),
                'late' => $existing->where('status', StaffAttendanceStatus::Late)->count(),
                'absent' => $existing->where('status', StaffAttendanceStatus::Absent)->count(),
                'on_leave' => $existing->where('status', StaffAttendanceStatus::OnLeave)->count(),
            ],
        ]);
    }

    public function print(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));
        $roleKey = $this->resolveRoleFilter($request->query('role'));

        $staffQuery = Staff::query()
            ->where('status', StaffStatus::Active)
            ->orderBy('full_name');

        if ($roleKey !== null) {
            $staffQuery->where('role_label', $roleKey);
        }

        $staff = $staffQuery->get();

        $existing = StaffAttendanceRecord::query()
            ->whereDate('date', $date->toDateString())
            ->when(
                $staff->isNotEmpty(),
                fn ($q) => $q->whereIn('staff_id', $staff->pluck('id')),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->get()
            ->keyBy('staff_id');

        $rows = $staff->map(function (Staff $member) use ($existing) {
            $record = $existing->get($member->id);

            return [
                'staff' => $member,
                'status' => $record?->status?->value,
                'check_in_at' => $record?->check_in_at,
                'check_out_at' => $record?->check_out_at,
            ];
        });

        return view('staff-attendance.print', [
            'date' => $date->toDateString(),
            'dateLabel' => $date->format('j F Y'),
            'role' => $roleKey,
            'rows' => $rows,
            'counts' => [
                'present' => $existing->where('status', StaffAttendanceStatus::Present)->count(),
                'late' => $existing->where('status', StaffAttendanceStatus::Late)->count(),
                'absent' => $existing->where('status', StaffAttendanceStatus::Absent)->count(),
                'on_leave' => $existing->where('status', StaffAttendanceStatus::OnLeave)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'role' => ['nullable', 'string', 'max:64'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::enum(StaffAttendanceStatus::class)],
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();
        $roleKey = $this->resolveRoleFilter($data['role'] ?? null);

        $activeQuery = Staff::query()->where('status', StaffStatus::Active);
        if ($roleKey !== null) {
            $activeQuery->where('role_label', $roleKey);
        }
        $activeIds = $activeQuery->pluck('id')->all();

        DB::transaction(function () use ($data, $date, $request, $activeIds) {
            foreach ($data['statuses'] as $staffId => $statusValue) {
                $staffId = (int) $staffId;
                if (! in_array($staffId, $activeIds, true)) {
                    continue;
                }

                $status = StaffAttendanceStatus::from($statusValue);
                $existing = StaffAttendanceRecord::query()
                    ->where('staff_id', $staffId)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                $clearsPunches = in_array($status, [
                    StaffAttendanceStatus::Absent,
                    StaffAttendanceStatus::OnLeave,
                ], true);

                $keepPunchAudit = ! $clearsPunches
                    && $existing?->check_in_at
                    && $existing->source
                    && $existing->source !== StaffAttendanceSource::Manual;

                $payload = [
                    'status' => $status,
                    'marked_by' => $request->user()->id,
                ];

                if ($clearsPunches) {
                    $payload['check_in_at'] = null;
                    $payload['check_out_at'] = null;
                    $payload['source'] = StaffAttendanceSource::Manual;
                } elseif ($keepPunchAudit) {
                    // Keep biometric/mobile punch times and source; only update status.
                    $payload['source'] = $existing->source;
                } else {
                    $payload['source'] = StaffAttendanceSource::Manual;
                    if (! $existing?->check_in_at) {
                        $payload['check_in_at'] = $date->copy()->setTimeFromTimeString(
                            $status === StaffAttendanceStatus::Late ? '08:15:00' : '07:45:00'
                        );
                    }
                }

                if ($existing) {
                    $existing->update($payload);
                } else {
                    StaffAttendanceRecord::query()->create([
                        'staff_id' => $staffId,
                        'date' => $date->toDateString(),
                        ...$payload,
                    ]);
                }
            }
        });

        return redirect()
            ->route('staff-attendance.index', array_filter([
                'date' => $date->toDateString(),
                'role' => $roleKey,
            ]))
            ->with('status', 'Staff attendance saved.');
    }

    public function history(Request $request): View
    {
        $from = $this->resolveDate($request->query('from'), now()->subDays(13)->toDateString());
        $to = $this->resolveDate($request->query('to'), now()->toDateString());
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $days = StaffAttendanceRecord::query()
            ->selectRaw('date, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_count")
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        return view('staff-attendance.history', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
        ]);
    }

    public function printHistory(Request $request): View
    {
        $from = $this->resolveDate($request->query('from'), now()->subDays(13)->toDateString());
        $to = $this->resolveDate($request->query('to'), now()->toDateString());
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $days = StaffAttendanceRecord::query()
            ->selectRaw('date, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_count")
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        return view('staff-attendance.print-history', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
        ]);
    }

    public function regenerateLink(Staff $staff): RedirectResponse
    {
        $staff->regenerateCheckinToken();

        return back()->with('status', 'Check-in link regenerated. Old links no longer work.');
    }

    public function resetBiometric(Staff $staff): RedirectResponse
    {
        StaffWebauthnCredential::query()->where('staff_id', $staff->id)->delete();

        return back()->with('status', 'Biometric reset. Staff must enroll again on their next check-in.');
    }

    private function resolveDate(mixed $value, ?string $fallback = null): Carbon
    {
        $fallback = $fallback ?? now()->toDateString();
        try {
            return Carbon::parse(is_string($value) && $value !== '' ? $value : $fallback)->startOfDay();
        } catch (\Throwable) {
            return Carbon::parse($fallback)->startOfDay();
        }
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function roleFilterOptions(): array
    {
        $usedKeys = Staff::query()
            ->where('status', StaffStatus::Active)
            ->whereNotNull('role_label')
            ->where('role_label', '!=', '')
            ->distinct()
            ->pluck('role_label')
            ->map(fn ($key) => (string) $key)
            ->all();

        if ($usedKeys === []) {
            return [];
        }

        $roles = Role::query()
            ->whereIn('key', $usedKeys)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('key');

        $options = [];
        foreach ($usedKeys as $key) {
            $options[] = [
                'key' => $key,
                'label' => $roles->get($key)?->name ?? Str::headline($key),
            ];
        }

        usort($options, fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    private function resolveRoleFilter(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value === 'all') {
            return null;
        }

        return preg_match('/^[a-z][a-z0-9_-]*$/', $value) === 1 ? $value : null;
    }
}
