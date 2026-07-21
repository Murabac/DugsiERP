<?php

namespace App\Support;

use App\Enums\PayrollRunStatus;
use App\Enums\StaffStatus;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PayrollGenerator
{
    /**
     * Normalize a billing month to the first day of the month.
     *
     * Accepts Y-m, Y-m-d, or a CarbonInterface.
     *
     * @throws ValidationException
     */
    public static function parseMonth(CarbonInterface|string $month): Carbon
    {
        if ($month instanceof CarbonInterface) {
            return Carbon::instance($month)->copy()->startOfMonth()->startOfDay();
        }

        $raw = trim($month);

        if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
            $parsed = Carbon::createFromFormat('!Y-m', $raw);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $parsed = Carbon::createFromFormat('!Y-m-d', $raw);
        } else {
            throw ValidationException::withMessages([
                'month' => 'Invalid month. Use YYYY-MM.',
            ]);
        }

        if ($parsed === false) {
            throw ValidationException::withMessages([
                'month' => 'Invalid month. Use YYYY-MM.',
            ]);
        }

        return $parsed->startOfMonth()->startOfDay();
    }

    /**
     * Active staff with a salary who had joined by the end of the billing month.
     *
     * @return Collection<int, Staff>
     */
    public static function eligibleStaff(CarbonInterface|string $month): Collection
    {
        $billingMonth = self::parseMonth($month);
        $monthEnd = $billingMonth->copy()->endOfMonth();

        return Staff::query()
            ->where('status', StaffStatus::Active)
            ->whereNotNull('fixed_salary_usd')
            ->where('fixed_salary_usd', '>', 0)
            ->where(function ($q) use ($monthEnd) {
                $q->whereNull('date_joined')
                    ->orWhereDate('date_joined', '<=', $monthEnd->toDateString());
            })
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @return array{
     *     billing_month: Carbon,
     *     staff: Collection<int, Staff>,
     *     total: float,
     *     count: int
     * }
     */
    public static function preview(CarbonInterface|string $month): array
    {
        $billingMonth = self::parseMonth($month);

        if ($billingMonth->format('Y-m') > now()->format('Y-m')) {
            throw ValidationException::withMessages([
                'month' => 'Cannot generate payroll for a future month.',
            ]);
        }

        if (PayrollRun::query()->whereDate('billing_month', $billingMonth->toDateString())->exists()) {
            throw ValidationException::withMessages([
                'month' => 'A payroll run already exists for '.$billingMonth->format('F Y').'.',
            ]);
        }

        $staff = self::eligibleStaff($billingMonth);
        $total = Money::round($staff->sum(fn (Staff $s) => (float) $s->fixed_salary_usd));

        return [
            'billing_month' => $billingMonth,
            'staff' => $staff,
            'total' => $total,
            'count' => $staff->count(),
        ];
    }

    /**
     * Create and confirm a payroll run for the month (idempotent guard via unique month).
     *
     * When $expectedCount / $expectedTotal are provided (from the preview form), confirm
     * fails if the live roster no longer matches what the user reviewed.
     */
    public static function confirm(
        CarbonInterface|string $month,
        User $actor,
        ?string $notes = null,
        ?int $expectedCount = null,
        ?float $expectedTotal = null,
    ): PayrollRun {
        $preview = self::preview($month);

        if ($preview['count'] === 0) {
            throw ValidationException::withMessages([
                'month' => 'No active staff with a salary to include for '.$preview['billing_month']->format('F Y').'.',
            ]);
        }

        if ($expectedCount !== null && $expectedCount !== $preview['count']) {
            throw ValidationException::withMessages([
                'month' => 'Staff list changed since preview. Review the updated payroll and confirm again.',
            ]);
        }

        if ($expectedTotal !== null && Money::round($expectedTotal) !== $preview['total']) {
            throw ValidationException::withMessages([
                'month' => 'Salary totals changed since preview. Review the updated payroll and confirm again.',
            ]);
        }

        try {
            return DB::transaction(function () use ($preview, $actor, $notes) {
                $billingMonth = $preview['billing_month'];

                $run = PayrollRun::query()->create([
                    'billing_month' => $billingMonth->toDateString(),
                    'status' => PayrollRunStatus::Confirmed,
                    'staff_count' => 0,
                    'total_amount' => 0,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                    'notes' => $notes,
                ]);

                $total = 0.0;
                $count = 0;

                foreach ($preview['staff'] as $staff) {
                    $salary = Money::round($staff->fixed_salary_usd);
                    PayrollItem::query()->create([
                        'payroll_run_id' => $run->id,
                        'staff_id' => $staff->id,
                        'employee_code' => $staff->employee_code,
                        'full_name' => $staff->full_name,
                        'role_label' => $staff->roleDisplayName(),
                        'salary_usd' => $salary,
                        'payslip_number' => DocumentNumbers::nextPayslipNumber($billingMonth->copy()),
                    ]);
                    $total = Money::round($total + $salary);
                    $count++;
                }

                if ($count === 0) {
                    throw new RuntimeException('Payroll run produced no line items.');
                }

                $run->update([
                    'staff_count' => $count,
                    'total_amount' => $total,
                ]);

                return $run->fresh(['items', 'generatedBy', 'confirmedBy']);
            });
        } catch (UniqueConstraintViolationException $e) {
            $billingMonth = $preview['billing_month'];
            throw ValidationException::withMessages([
                'month' => 'A payroll run already exists for '.$billingMonth->format('F Y').'.',
            ]);
        }
    }
}
