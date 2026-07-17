<?php

namespace App\Http\Controllers;

use App\Enums\StaffAttendanceSource;
use App\Enums\StaffAttendanceStatus;
use App\Enums\StaffStatus;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\StaffWebauthnCredential;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));

        $staff = Staff::query()
            ->where('status', StaffStatus::Active)
            ->withCount('webauthnCredentials')
            ->orderBy('full_name')
            ->get();

        $existing = StaffAttendanceRecord::query()
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('staff_id');

        $rows = $staff->map(function (Staff $member) use ($existing) {
            $record = $existing->get($member->id);
            $oldStatus = old('statuses.'.$member->id);

            return [
                'staff' => $member,
                'status' => $oldStatus
                    ?? $record?->status?->value
                    ?? StaffAttendanceStatus::Present->value,
                'check_in_at' => $record?->check_in_at,
                'check_out_at' => $record?->check_out_at,
                'source' => $record?->source,
                'has_biometric' => $member->webauthn_credentials_count > 0,
            ];
        });

        return view('staff-attendance.index', [
            'date' => $date->toDateString(),
            'dateLabel' => $date->format('j F Y'),
            'rows' => $rows,
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::enum(StaffAttendanceStatus::class)],
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();
        $activeIds = Staff::query()
            ->where('status', StaffStatus::Active)
            ->pluck('id')
            ->all();

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

                $payload = [
                    'status' => $status,
                    'source' => StaffAttendanceSource::Manual,
                    'marked_by' => $request->user()->id,
                ];

                // Manual absent/on_leave clears punches; present/late keeps existing punches if any
                if (in_array($status, [StaffAttendanceStatus::Absent, StaffAttendanceStatus::OnLeave], true)) {
                    $payload['check_in_at'] = null;
                    $payload['check_out_at'] = null;
                } elseif (! $existing?->check_in_at) {
                    $payload['check_in_at'] = $date->copy()->setTimeFromTimeString(
                        $status === StaffAttendanceStatus::Late ? '08:15:00' : '07:45:00'
                    );
                }

                StaffAttendanceRecord::query()->updateOrCreate(
                    [
                        'staff_id' => $staffId,
                        'date' => $date->toDateString(),
                    ],
                    $payload
                );
            }
        });

        return redirect()
            ->route('staff-attendance.index', ['date' => $date->toDateString()])
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
}
