<?php

namespace App\Support;

/**
 * Combined income + expense sheet with net income for one calendar month.
 */
class FeeNetIncomeReport
{
    /**
     * @return array{
     *     income: array{lines: list<array{key: string, label: string, amount: float}>, total: float},
     *     expenses: array{lines: list<array{key: string, label: string, amount: float}>, total: float},
     *     net: float
     * }
     */
    public static function build(string $academicYear, string $billingMonthYm, string $lang = 'so'): array
    {
        $lang = $lang === 'en' ? 'en' : 'so';
        $income = FeeIncomeReport::rows($academicYear, $billingMonthYm, $lang);
        $expenses = FeeExpensesReport::rows($billingMonthYm, $lang);
        $net = Money::round($income['total'] - $expenses['total']);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => $net,
        ];
    }

    /**
     * @return array{so: array<string, string>, en: array<string, string>}
     */
    public static function labels(): array
    {
        $income = FeeIncomeReport::labels();
        $expenses = FeeExpensesReport::labels();

        return [
            'so' => [
                'page_title' => 'Dakhliga ah',
                'sub' => 'Dakhliga iyo kharashaadka bishan · Forms 1–4',
                'month' => $income['so']['month'],
                'apply' => $income['so']['apply'],
                'print' => $income['so']['print'],
                'back' => $income['so']['back'],
                'description' => $income['so']['description'],
                'amount' => $income['so']['amount'],
                'section_income' => $income['so']['section'],
                'section_expenses' => $expenses['so']['section'],
                'section_net' => 'Soo koobida dakhliga ah',
                'income_total' => $income['so']['grand_total'],
                'expense_total' => $expenses['so']['grand_total'],
                'net_total' => 'WADARTA DAKHLIGA AH',
                'note' => 'Dakhliga = lacagta la soo ururiyey. Kharashaadka = mushahar + kharashyada kale. Dakhliga ah = dakhliga − kharashaadka.',
            ],
            'en' => [
                'page_title' => 'Net income',
                'sub' => 'Income and expenses for the month · Forms 1–4',
                'month' => $income['en']['month'],
                'apply' => $income['en']['apply'],
                'print' => $income['en']['print'],
                'back' => $income['en']['back'],
                'description' => $income['en']['description'],
                'amount' => $income['en']['amount'],
                'section_income' => $income['en']['section'],
                'section_expenses' => $expenses['en']['section'],
                'section_net' => 'Net income summary',
                'income_total' => $income['en']['grand_total'],
                'expense_total' => $expenses['en']['grand_total'],
                'net_total' => 'NET INCOME',
                'note' => 'Income = cash collected. Expenses = payroll + operating costs. Net income = income − expenses.',
            ],
        ];
    }
}
