<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FeeCollectionReport
{
    /**
     * Monthly due/collected bars for an academic year, optionally filtered by class and month range.
     *
     * @return list<array{key: string, label: string, full_label: string, due: float, paid: float, outstanding: float, pct: float}>
     */
    public static function monthlyRows(
        string $year,
        ?string $minYm = null,
        ?string $maxYm = null,
        ?int $classId = null,
    ): array {
        $query = Invoice::query()->where('academic_year', $year);
        if ($classId !== null && $classId > 0) {
            $query->where('class_id', $classId);
        }

        /** @var Collection<string, Collection<int, Invoice>> $grouped */
        $grouped = $query->get()->groupBy(fn (Invoice $i) => $i->billing_month->format('Y-m'));

        $end = $maxYm
            ? Carbon::createFromFormat('!Y-m', $maxYm)->startOfMonth()
            : now()->startOfMonth();
        $start = $minYm
            ? Carbon::createFromFormat('!Y-m', $minYm)->startOfMonth()
            : $end->copy()->subMonths(5);

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        $cursor = $start->copy()->startOfMonth();
        $end = $end->copy()->startOfMonth();
        $rows = [];
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $group = $grouped->get($key, collect());
            $due = Money::round($group->sum(fn (Invoice $inv) => (float) $inv->amount_due));
            $paid = Money::round($group->sum(fn (Invoice $inv) => (float) $inv->amount_paid));
            $outstanding = Money::round(max(0, $due - $paid));
            $rows[] = [
                'key' => $key,
                'label' => $cursor->format('M'),
                'full_label' => $cursor->format('F Y'),
                'due' => $due,
                'paid' => $paid,
                'outstanding' => $outstanding,
                'pct' => Money::percentOf($paid, $due),
            ];
            $cursor->addMonth();
        }

        return $rows;
    }
}
