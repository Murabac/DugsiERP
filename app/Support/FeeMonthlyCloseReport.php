<?php

namespace App\Support;

/**
 * Full monthly fee accounting close — all fee report sections on one sheet.
 */
class FeeMonthlyCloseReport
{
    /**
     * @return array{
     *     students: array{
     *         rows: list<array<string, mixed>>,
     *         totals: array<string, int|float>,
     *         summary: list<array<string, mixed>>
     *     },
     *     income: array{lines: list<array{key: string, label: string, amount: float}>, total: float},
     *     expenses: array{lines: list<array{key: string, label: string, amount: float}>, total: float},
     *     net: float,
     *     unpaid_rows: list<array{label: string, unpaid: int, unpaid_amount: float}>,
     *     unpaid_totals: array{unpaid: int, unpaid_amount: float},
     *     overview: list<array{label: string, students: int}>
     * }
     */
    public static function build(string $academicYear, string $billingMonthYm, string $lang = 'so'): array
    {
        $lang = $lang === 'en' ? 'en' : 'so';
        $students = FeeStudentsByFormReport::rows($academicYear, $billingMonthYm, $lang);
        $income = FeeIncomeReport::rows($academicYear, $billingMonthYm, $lang);
        $expenses = FeeExpensesReport::rows($billingMonthYm, $lang);
        $net = Money::round($income['total'] - $expenses['total']);

        $unpaidRows = [];
        $unpaidStudents = 0;
        $unpaidAmount = 0.0;
        foreach ($students['rows'] as $row) {
            $unpaidRows[] = [
                'label' => $row['label'],
                'unpaid' => (int) $row['unpaid'],
                'unpaid_amount' => (float) $row['unpaid_amount'],
            ];
            $unpaidStudents += (int) $row['unpaid'];
            $unpaidAmount = Money::round($unpaidAmount + (float) $row['unpaid_amount']);
        }

        $studentLabels = FeeStudentsByFormReport::labels()[$lang];
        $overview = [
            [
                'label' => $studentLabels['info_students'],
                'students' => (int) $students['totals']['total'],
            ],
            [
                'label' => $studentLabels['info_paid'],
                'students' => (int) $students['totals']['paid'],
            ],
            [
                'label' => $studentLabels['info_partial'],
                'students' => (int) $students['totals']['partial'],
            ],
            [
                'label' => $studentLabels['info_unpaid'],
                'students' => (int) $students['totals']['unpaid'],
            ],
        ];

        return [
            'students' => $students,
            'income' => $income,
            'expenses' => $expenses,
            'net' => $net,
            'unpaid_rows' => $unpaidRows,
            'unpaid_totals' => [
                'unpaid' => $unpaidStudents,
                'unpaid_amount' => $unpaidAmount,
            ],
            'overview' => $overview,
        ];
    }

    /**
     * @return array{so: array<string, string>, en: array<string, string>}
     */
    public static function labels(): array
    {
        $students = FeeStudentsByFormReport::labels();
        $income = FeeIncomeReport::labels();
        $expenses = FeeExpensesReport::labels();

        return [
            'so' => [
                'page_title' => 'Xisaab-xidhka bil\'le',
                'sub' => 'Warbixin dhamaystiran · Forms 1–4 · secondary',
                'month' => $students['so']['month'],
                'apply' => $students['so']['apply'],
                'print' => $students['so']['print'],
                'back' => $students['so']['back'],
                'description' => $income['so']['description'],
                'amount' => $income['so']['amount'],
                'class' => $students['so']['class'],
                'total' => $students['so']['total'],
                'paid' => $students['so']['paid'],
                'partial' => $students['so']['partial'],
                'unpaid' => $students['so']['unpaid'],
                'grand_total' => $students['so']['grand_total'],
                'section_1' => $students['so']['section'],
                'section_2' => $income['so']['section'],
                'section_3' => $expenses['so']['section'],
                'section_4' => 'Qaybta 4: Natiijada xisaab-xidhka',
                'section_5' => 'Qaybta 5: Lacagta maqan (ardayda aan bixin)',
                'section_6' => 'Qaybta 6: Koobidda guud ee ardayda',
                'income_total' => 'Wadarta dakhliga',
                'expense_total' => 'Wadarta kharashka',
                'profit_loss' => 'Faa\'iido / Khasaaro',
                'unpaid_students' => 'Tirada ardayda aan bixin',
                'missing_amount' => 'Wadarta lacagta maqan',
                'overview_students' => 'Ardayda',
                'sign_accountant' => 'Xisaabiyaha',
                'sign_manager' => 'Maamulaha dugsiga',
                'sign_approval' => 'Saxiixa iyo ansixinta',
                'sign_stamp' => 'Shaabadda dugsiga',
                'date' => 'Taariikh',
                'note' => 'Warbixintan waxay isku daraysaa dhammaan qaybaha lacagaha bishan. Ma jirto Grade 8 / Khamiis.',
            ],
            'en' => [
                'page_title' => 'Monthly accounting close',
                'sub' => 'Complete fee report · Forms 1–4 · secondary',
                'month' => $students['en']['month'],
                'apply' => $students['en']['apply'],
                'print' => $students['en']['print'],
                'back' => $students['en']['back'],
                'description' => $income['en']['description'],
                'amount' => $income['en']['amount'],
                'class' => $students['en']['class'],
                'total' => $students['en']['total'],
                'paid' => $students['en']['paid'],
                'partial' => $students['en']['partial'],
                'unpaid' => $students['en']['unpaid'],
                'grand_total' => $students['en']['grand_total'],
                'section_1' => $students['en']['section'],
                'section_2' => $income['en']['section'],
                'section_3' => $expenses['en']['section'],
                'section_4' => 'Section 4: Accounting result',
                'section_5' => 'Section 5: Missing fees (unpaid students)',
                'section_6' => 'Section 6: Student overview',
                'income_total' => 'Total income',
                'expense_total' => 'Total expenses',
                'profit_loss' => 'Profit / Loss',
                'unpaid_students' => 'Unpaid students',
                'missing_amount' => 'Total missing fees',
                'overview_students' => 'Students',
                'sign_accountant' => 'Accountant',
                'sign_manager' => 'School manager',
                'sign_approval' => 'Approval signature',
                'sign_stamp' => 'School stamp',
                'date' => 'Date',
                'note' => 'This report combines all fee sections for the month. No Grade 8 / Thursday special fees.',
            ],
        ];
    }
}
