<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MonthlyInvoiceGenerator
{
    /**
     * Create missing monthly invoices for all active enrollments in the academic year.
     * Existing invoices for the billing month are skipped (idempotent).
     *
     * @return array{created: int, skipped: int, billing_month: string}
     */
    public static function generate(CarbonInterface|string $month, ?string $academicYear = null): array
    {
        $billingMonth = FeeCalculator::billingMonthStart($month);
        $year = $academicYear ?? AcademicYear::current();

        if ($billingMonth->format('Y-m') > now()->format('Y-m')) {
            throw new RuntimeException('Cannot generate invoices for a future month.');
        }

        if (SchoolSetting::monthlyFeeUsd() <= 0) {
            throw new RuntimeException('Monthly fee is not configured. Set it under Settings → School.');
        }

        $created = 0;
        $skipped = 0;

        FeeCalculator::primeSiblingCache($year);

        try {
            $enrollments = Enrollment::query()
                ->with(['student.primaryGuardian', 'schoolClass'])
                ->where('academic_year', $year)
                ->where('status', StudentStatus::Active)
                ->get();

            DB::transaction(function () use ($enrollments, $billingMonth, $year, &$created, &$skipped) {
                foreach ($enrollments as $enrollment) {
                    $class = $enrollment->schoolClass;
                    $student = $enrollment->student;
                    if (! $class || ! $student) {
                        $skipped++;

                        continue;
                    }

                    $result = self::createForStudent($student, $class, $billingMonth, $year);
                    if ($result === 'created') {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });
        } finally {
            FeeCalculator::clearSiblingCache();
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'billing_month' => $billingMonth->format('Y-m'),
        ];
    }

    /**
     * Ensure a single active student has an invoice for the given (or current) month.
     */
    public static function ensureForStudent(
        Student $student,
        SchoolClass $class,
        CarbonInterface|string|null $month = null,
        ?string $academicYear = null,
    ): bool {
        if (SchoolSetting::monthlyFeeUsd() <= 0) {
            return false;
        }

        $billingMonth = FeeCalculator::billingMonthStart($month ?? now()->startOfMonth());
        if ($billingMonth->format('Y-m') > now()->format('Y-m')) {
            return false;
        }

        $year = $academicYear ?? $class->academic_year ?? AcademicYear::current();

        return DB::transaction(function () use ($student, $class, $billingMonth, $year) {
            return self::createForStudent($student, $class, $billingMonth, $year) === 'created';
        });
    }

    /**
     * Recalculate unpaid invoices (no payments yet) from current fee settings / student flags.
     *
     * @return int Number of invoices updated
     */
    public static function recalculateUnpaid(?Student $student = null, ?string $academicYear = null): int
    {
        if (SchoolSetting::monthlyFeeUsd() <= 0) {
            return 0;
        }

        $year = $academicYear ?? AcademicYear::current();

        $query = Invoice::query()
            ->with(['student.primaryGuardian', 'schoolClass'])
            ->where('academic_year', $year)
            ->where('amount_paid', 0)
            ->where('status', InvoiceStatus::Unpaid);

        if ($student) {
            $query->where('student_id', $student->id);
        }

        $updated = 0;
        FeeCalculator::primeSiblingCache($year);

        try {
            DB::transaction(function () use ($query, $year, &$updated) {
                foreach ($query->lockForUpdate()->get() as $invoice) {
                    $class = $invoice->schoolClass;
                    $stu = $invoice->student;
                    if (! $class || ! $stu) {
                        continue;
                    }

                    $quote = FeeCalculator::quote($stu, $class, $year);
                    $status = self::statusForAmounts($quote['due'], 0);

                    $invoice->update([
                        'base_amount' => $quote['base'],
                        'discount_applied' => $quote['discount'],
                        'discount_reason' => $quote['reason'],
                        'transport_fee' => $quote['transport'],
                        'amount_due' => $quote['due'],
                        'status' => $status,
                    ]);
                    $updated++;
                }
            });
        } finally {
            FeeCalculator::clearSiblingCache();
        }

        return $updated;
    }

    /**
     * @return array{created: int, skipped: int, billing_month: string}|null
     */
    public static function ensureCurrentMonth(): ?array
    {
        if (SchoolSetting::monthlyFeeUsd() <= 0) {
            return null;
        }

        return self::generate(now()->startOfMonth());
    }

    /**
     * @return 'created'|'skipped'
     */
    private static function createForStudent(
        Student $student,
        SchoolClass $class,
        CarbonInterface $billingMonth,
        string $year,
    ): string {
        $exists = Invoice::query()
            ->where('student_id', $student->id)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        $quote = FeeCalculator::quote($student, $class, $year);
        $billingCarbon = \Illuminate\Support\Carbon::parse($billingMonth->toDateString())->startOfMonth();
        $status = self::statusForAmounts($quote['due'], 0);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                Invoice::query()->create([
                    'invoice_number' => DocumentNumbers::nextInvoiceNumber($billingCarbon),
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'academic_year' => $year,
                    'billing_month' => $billingMonth->toDateString(),
                    'base_amount' => $quote['base'],
                    'discount_applied' => $quote['discount'],
                    'discount_reason' => $quote['reason'],
                    'transport_fee' => $quote['transport'],
                    'amount_due' => $quote['due'],
                    'amount_paid' => 0,
                    'status' => $status,
                ]);

                return 'created';
            } catch (UniqueConstraintViolationException $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'invoices_student_billing_month_unique')
                    || (str_contains($message, 'student_id') && str_contains($message, 'billing_month'))) {
                    return 'skipped';
                }
                // invoice_number collision — retry
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        return 'skipped';
    }

    public static function statusForAmounts(float $due, float $paid): InvoiceStatus
    {
        $due = Money::round($due);
        $paid = Money::round($paid);

        return match (true) {
            $due <= 0.001 => InvoiceStatus::Paid,
            $paid <= 0 => InvoiceStatus::Unpaid,
            $paid + 0.001 >= $due => InvoiceStatus::Paid,
            default => InvoiceStatus::Partial,
        };
    }
}
