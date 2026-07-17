<?php

namespace App\Support;

use App\Enums\StudentStatus;
use App\Enums\TransportAssignmentStatus;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\TransportAssignment;
use Carbon\CarbonInterface;

class FeeCalculator
{
    /** @var array<string, list<int>>|null phone digits => student ids */
    private static ?array $siblingPhoneIndex = null;

    /** @var array<string, list<int>>|null academic year => ordered student ids by enrollment */
    private static ?array $siblingEnrollmentOrder = null;

    /**
     * @return array{
     *     base: float,
     *     discount: float,
     *     tuition_due: float,
     *     transport: float,
     *     due: float,
     *     reason: ?string
     * }
     */
    public static function quote(Student $student, SchoolClass $schoolClass, ?string $academicYear = null): array
    {
        $academicYear ??= $schoolClass->academic_year;
        $base = SchoolSetting::monthlyFeeUsd();

        if ($base <= 0) {
            throw new \RuntimeException('Monthly fee is not configured. Set it under Settings → School.');
        }

        $siblingPct = SchoolSetting::siblingDiscountPercent();
        $needPct = SchoolSetting::needBasedDiscountPercent();

        $siblingApplies = $siblingPct > 0 && self::isLaterSibling($student, $academicYear);
        $needApplies = $needPct > 0 && (bool) $student->need_based_discount;

        $chosenPct = 0;
        $reasons = [];

        if ($siblingApplies && $needApplies) {
            if ($siblingPct >= $needPct) {
                $chosenPct = $siblingPct;
                $reasons[] = 'Sibling discount '.$siblingPct.'%';
            } else {
                $chosenPct = $needPct;
                $reasons[] = 'Need-based discount '.$needPct.'%';
            }
        } elseif ($siblingApplies) {
            $chosenPct = $siblingPct;
            $reasons[] = 'Sibling discount '.$siblingPct.'%';
        } elseif ($needApplies) {
            $chosenPct = $needPct;
            $reasons[] = 'Need-based discount '.$needPct.'%';
        }

        $discount = Money::round($base * ($chosenPct / 100));
        $tuitionDue = Money::round(max(0, $base - $discount));
        $transport = self::transportFeeFor($student, $academicYear);
        $due = Money::round($tuitionDue + $transport);

        return [
            'base' => $base,
            'discount' => $discount,
            'tuition_due' => $tuitionDue,
            'transport' => $transport,
            'due' => $due,
            'reason' => $reasons === [] ? null : implode('; ', $reasons),
        ];
    }

    public static function transportFeeFor(Student $student, string $academicYear): float
    {
        $fee = SchoolSetting::transportFeeUsd();
        if ($fee <= 0) {
            return 0.0;
        }

        $hasRide = TransportAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $academicYear)
            ->where('status', TransportAssignmentStatus::Active)
            ->exists();

        return $hasRide ? $fee : 0.0;
    }

    /**
     * Prefetch guardian phones + enrollment order once per billing run.
     */
    public static function primeSiblingCache(string $academicYear): void
    {
        if (self::$siblingPhoneIndex !== null && isset(self::$siblingEnrollmentOrder[$academicYear])) {
            return;
        }

        $index = [];
        Guardian::query()
            ->where('is_primary', true)
            ->whereNotNull('phone')
            ->select(['id', 'student_id', 'phone'])
            ->orderBy('id')
            ->chunkById(500, function ($guardians) use (&$index) {
                foreach ($guardians as $g) {
                    $digits = self::normalizePhone((string) $g->phone);
                    if ($digits === '') {
                        continue;
                    }
                    $index[$digits][] = (int) $g->student_id;
                }
            });

        foreach ($index as $digits => $ids) {
            $index[$digits] = array_values(array_unique($ids));
        }

        self::$siblingPhoneIndex = $index;

        $order = Enrollment::query()
            ->where('academic_year', $academicYear)
            ->where('status', StudentStatus::Active)
            ->orderBy('enrollment_date')
            ->orderBy('student_id')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        self::$siblingEnrollmentOrder[$academicYear] = $order;
    }

    public static function clearSiblingCache(): void
    {
        self::$siblingPhoneIndex = null;
        self::$siblingEnrollmentOrder = null;
    }

    /**
     * Earliest enrollment among siblings sharing the same primary guardian phone pays full fee.
     */
    public static function isLaterSibling(Student $student, string $academicYear): bool
    {
        $phone = self::normalizedPrimaryPhone($student);
        if ($phone === null) {
            return false;
        }

        if (self::$siblingPhoneIndex === null) {
            self::primeSiblingCache($academicYear);
        }

        $siblingStudentIds = self::$siblingPhoneIndex[$phone] ?? [];
        if (count($siblingStudentIds) < 2) {
            return false;
        }

        $order = self::$siblingEnrollmentOrder[$academicYear] ?? [];
        $enrolledSiblings = array_values(array_filter(
            $order,
            fn (int $id) => in_array($id, $siblingStudentIds, true)
        ));

        if (count($enrolledSiblings) < 2) {
            return false;
        }

        $firstStudentId = $enrolledSiblings[0];

        return (int) $student->id !== $firstStudentId
            && in_array((int) $student->id, $enrolledSiblings, true);
    }

    public static function billingMonthStart(CarbonInterface|string $month): CarbonInterface
    {
        return \Illuminate\Support\Carbon::parse($month)->startOfMonth();
    }

    private static function normalizedPrimaryPhone(Student $student): ?string
    {
        $phone = $student->primaryGuardian?->phone
            ?? $student->guardians()->where('is_primary', true)->value('phone');

        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = self::normalizePhone($phone);

        return $digits === '' ? null : $digits;
    }

    private static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
