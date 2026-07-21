<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample data for the fee expense report (Qaybta 3), July 2026.
 * Amounts match the user's reference sheet; Thursday-teacher line is omitted (secondary).
 */
class JulyExpenseReportSampleSeeder extends Seeder
{
    public const SAMPLE_MONTH = '2026-07';

    public const MARKER = 'Sample July 2026 expense report';

    public function run(): void
    {
        $monthStart = Carbon::createFromFormat('!Y-m', self::SAMPLE_MONTH)->startOfMonth();
        $expenseDate = $monthStart->copy()->addDays(14)->toDateString();

        if (PayrollRun::query()->whereDate('billing_month', $monthStart)->where('notes', self::MARKER)->exists()) {
            $existing = PayrollRun::query()
                ->whereDate('billing_month', $monthStart)
                ->where('notes', self::MARKER)
                ->first();

            if ($existing && $existing->items()->exists()) {
                return;
            }

            $existing?->delete();
        }

        $actor = User::query()->where('email', 'finance@dugsi.edu.sl')->first()
            ?? User::query()->where('email', 'admin@dugsi.edu.sl')->first();

        if (! $actor) {
            return;
        }

        $this->seedPayroll($monthStart, $actor);
        $this->seedExpenses($expenseDate, $actor->id);
    }

    private function seedPayroll(Carbon $monthStart, User $actor): void
    {
        /** @var list<array{code: string, salary: float}> $teacherLines */
        $teacherLines = [
            ['code' => 'EMP-001', 'salary' => 620.00],
            ['code' => 'EMP-003', 'salary' => 700.00],
            ['code' => 'EMP-002', 'salary' => 700.00],
        ];

        /** @var list<array{code: string, salary: float}> $adminLines */
        $adminLines = [
            ['code' => 'EMP-005', 'salary' => 365.00],
            ['code' => 'EMP-006', 'salary' => 280.00],
        ];

        $teacherTotal = Money::round(array_sum(array_column($teacherLines, 'salary')));
        $adminTotal = Money::round(array_sum(array_column($adminLines, 'salary')));
        $total = Money::round($teacherTotal + $adminTotal);

        $run = PayrollRun::query()->create([
            'billing_month' => $monthStart->toDateString(),
            'status' => PayrollRunStatus::Confirmed,
            'staff_count' => count($teacherLines) + count($adminLines),
            'total_amount' => $total,
            'generated_by' => $actor->id,
            'generated_at' => now(),
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'notes' => self::MARKER,
        ]);

        $seq = 1;
        foreach ([...$teacherLines, ...$adminLines] as $line) {
            $staff = Staff::query()->where('employee_code', $line['code'])->first();
            if (! $staff) {
                continue;
            }

            PayrollItem::query()->create([
                'payroll_run_id' => $run->id,
                'staff_id' => $staff->id,
                'employee_code' => $staff->employee_code,
                'full_name' => $staff->full_name,
                'role_label' => $staff->roleDisplayName(),
                'salary_usd' => $line['salary'],
                'payslip_number' => sprintf('PSL-%s-SMP-%03d', $monthStart->format('Y-m'), $seq++),
            ]);
        }
    }

    private function seedExpenses(string $expenseDate, int $recordedBy): void
    {
        /** @var array<string, array{amount: float, description: string}> $lines */
        $lines = [
            'Condolence / contingency' => ['amount' => 30.00, 'description' => 'Qaadhan / tacsi (maamulka)'],
            'Utilities' => ['amount' => 80.00, 'description' => 'Laydh, xashish, biyo, internet'],
            'Cleaning supplies' => ['amount' => 26.90, 'description' => 'Qalab-nadaafadeed'],
            'Support staff wages' => ['amount' => 260.00, 'description' => 'Shaqaalaha hoosaadka'],
            'Fee collection costs' => ['amount' => 104.80, 'description' => 'Kharashka ururinta'],
            'Tea / refreshments' => ['amount' => 103.00, 'description' => 'Shaah / cunto fudud'],
            'Exam marking' => ['amount' => 28.70, 'description' => 'Nususaace — sixidda imtixaanka'],
            'Stationery' => ['amount' => 173.00, 'description' => 'Qalimaan iyo waraaqo'],
        ];

        foreach ($lines as $categoryName => $row) {
            $category = ExpenseCategory::query()->firstOrCreate(
                ['name' => $categoryName],
                ['is_active' => true]
            );

            $exists = Expense::query()
                ->where('expense_category_id', $category->id)
                ->whereDate('expense_date', $expenseDate)
                ->where('description', self::MARKER.' — '.$row['description'])
                ->exists();

            if ($exists) {
                continue;
            }

            Expense::query()->create([
                'expense_category_id' => $category->id,
                'expense_date' => $expenseDate,
                'amount' => $row['amount'],
                'payment_method' => PaymentMethod::Cash,
                'description' => self::MARKER.' — '.$row['description'],
                'recorded_by' => $recordedBy,
            ]);
        }
    }
}
