<?php

namespace App\Support;

use App\Enums\StaffRoleLabel;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollItem;
use Illuminate\Support\Carbon;

/**
 * Monthly outgoings sheet: payroll salaries + expenses by category (secondary).
 * No Grade 8 / Thursday-teacher special lines.
 */
class FeeExpensesReport
{
    /**
     * @return array{
     *     lines: list<array{key: string, label: string, amount: float}>,
     *     total: float
     * }
     */
    public static function rows(string $billingMonthYm, string $lang = 'so'): array
    {
        $lang = $lang === 'en' ? 'en' : 'so';
        $labels = self::labels()[$lang];
        $monthStart = Carbon::createFromFormat('!Y-m', $billingMonthYm)->startOfMonth();
        $rangeStart = $monthStart->copy()->startOfDay();
        $rangeEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $teacherSalary = (float) PayrollItem::query()
            ->whereHas('payrollRun', fn ($q) => $q->whereDate('billing_month', $monthStart->toDateString()))
            ->where(function ($q) {
                $q->where('role_label', StaffRoleLabel::Teacher->label())
                    ->orWhere('role_label', StaffRoleLabel::Teacher->value);
            })
            ->sum('salary_usd');

        $adminSalary = (float) PayrollItem::query()
            ->whereHas('payrollRun', fn ($q) => $q->whereDate('billing_month', $monthStart->toDateString()))
            ->where(function ($q) {
                $q->where('role_label', '!=', StaffRoleLabel::Teacher->label())
                    ->where('role_label', '!=', StaffRoleLabel::Teacher->value);
            })
            ->sum('salary_usd');

        $spentByCategory = Expense::query()
            ->whereBetween('expense_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        $categories = ExpenseCategory::query()
            ->where(function ($q) use ($spentByCategory) {
                $q->where('is_active', true);
                if ($spentByCategory->isNotEmpty()) {
                    $q->orWhereIn('id', $spentByCategory->keys());
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $lines = [
            [
                'key' => 'payroll_teachers',
                'label' => $labels['line_teachers'],
                'amount' => Money::round($teacherSalary),
            ],
            [
                'key' => 'payroll_admin',
                'label' => $labels['line_admin'],
                'amount' => Money::round($adminSalary),
            ],
        ];

        foreach ($categories as $category) {
            $amount = Money::round((float) ($spentByCategory[$category->id] ?? 0));
            if ($amount <= 0 && ! $category->is_active) {
                continue;
            }
            $lines[] = [
                'key' => 'cat_'.$category->id,
                'label' => self::categoryLabel((string) $category->name, $lang),
                'amount' => $amount,
            ];
        }

        // Keep the sheet readable: hide zero expense categories (payroll lines always shown).
        $lines = array_values(array_filter(
            $lines,
            fn (array $line) => str_starts_with($line['key'], 'payroll_') || $line['amount'] > 0
        ));

        $total = Money::round(array_sum(array_column($lines, 'amount')));

        return [
            'lines' => $lines,
            'total' => $total,
        ];
    }

    public static function categoryLabel(string $name, string $lang): string
    {
        $map = [
            'Utilities' => ['so' => 'Laydh / xashish / biyo / internet', 'en' => 'Utilities (power, waste, water, internet)'],
            'Supplies' => ['so' => 'Agabka / saadka', 'en' => 'Supplies'],
            'Maintenance' => ['so' => 'Dayactirka', 'en' => 'Maintenance'],
            'Transport' => ['so' => 'Gaadiidka', 'en' => 'Transport'],
            'Other' => ['so' => 'Kale', 'en' => 'Other'],
            'Condolence / contingency' => ['so' => 'Qaadhan / tacsi (maamulka)', 'en' => 'Condolence / contingency (admin)'],
            'Cleaning supplies' => ['so' => 'Qalab-nadaafadeed', 'en' => 'Cleaning supplies'],
            'Support staff wages' => ['so' => 'Shaqaalaha hoosaadka', 'en' => 'Support staff wages'],
            'Fee collection costs' => ['so' => 'Kharashka ururinta', 'en' => 'Fee collection costs'],
            'Tea / refreshments' => ['so' => 'Shaah / cunto fudud', 'en' => 'Tea / refreshments'],
            'Exam marking' => ['so' => 'Nususaace (sixidda imtixaanka)', 'en' => 'Exam marking'],
            'Stationery' => ['so' => 'Qalimaan iyo waraaqo', 'en' => 'Stationery (pens & paper)'],
        ];

        return $map[$name][$lang] ?? $name;
    }

    /**
     * @return array{so: array<string, string>, en: array<string, string>}
     */
    public static function labels(): array
    {
        return [
            'so' => [
                'page_title' => 'Warbixinta kharashaadka',
                'section' => 'Qaybta 3: Fahfaahinta kharashaadka (secondary)',
                'description' => 'Sharaxaad',
                'amount' => 'Qiimaha',
                'line_teachers' => 'Mushaharka macallimiinta',
                'line_admin' => 'Mushaharka maamulka',
                'grand_total' => 'WADARTA KHARASHKA',
                'month' => 'Bisha',
                'apply' => 'Sifee',
                'print' => 'Daabac',
                'back' => 'Warbixinnada lacagaha',
                'sub' => 'Mushahar + kharashaadka bishan · secondary',
                'note' => 'Mushahar wuxuu ka yimaadaa payroll. Kharashaadka kale waa categories-ka expenses. Ma jirto Macallimiinta Khamiis / Grade 8.',
                'empty' => 'Ma jiro kharash / mushahar bishan.',
            ],
            'en' => [
                'page_title' => 'Expense report',
                'section' => 'Section 3: Expense details (secondary)',
                'description' => 'Description',
                'amount' => 'Amount',
                'line_teachers' => 'Teacher salaries',
                'line_admin' => 'Admin / non-teacher salaries',
                'grand_total' => 'TOTAL EXPENSES',
                'month' => 'Month',
                'apply' => 'Apply',
                'print' => 'Print',
                'back' => 'Fee reports',
                'sub' => 'Payroll + operating expenses for the month · secondary',
                'note' => 'Salaries come from payroll. Other lines are expense categories. No Thursday-teacher / Grade 8 special fees.',
                'empty' => 'No payroll or expenses recorded for this month.',
            ],
        ];
    }
}
