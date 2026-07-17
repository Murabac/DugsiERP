<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PayrollRunStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\UserRole;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: User, finance: User, staff: Staff}
     */
    private function seedBasics(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();

        $staff = Staff::query()->create([
            'employee_code' => 'EMP-PAY1',
            'full_name' => 'Payroll Teacher',
            'dob' => '1990-01-01',
            'gender' => Gender::Female,
            'phone' => '+252634999001',
            'date_joined' => now()->subYear()->toDateString(),
            'fixed_salary_usd' => 500,
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);

        Staff::query()->create([
            'employee_code' => 'EMP-PAY2',
            'full_name' => 'Payroll Admin',
            'dob' => '1985-01-01',
            'gender' => Gender::Male,
            'phone' => '+252634999002',
            'date_joined' => now()->subYears(2)->toDateString(),
            'fixed_salary_usd' => 700,
            'role_label' => StaffRoleLabel::Admin,
            'status' => StaffStatus::Active,
        ]);

        return compact('admin', 'finance', 'staff');
    }

    /**
     * @return array{month: string, expected_count: int, expected_total: float}
     */
    private function confirmPayload(?string $month = null, ?int $count = null, ?float $total = null): array
    {
        $month ??= now()->format('Y-m');
        $preview = PayrollGenerator::preview($month);

        return [
            'month' => $month,
            'expected_count' => $count ?? $preview['count'],
            'expected_total' => $total ?? $preview['total'],
        ];
    }

    public function test_finance_can_preview_and_confirm_payroll(): void
    {
        ['finance' => $finance] = $this->seedBasics();
        $month = now()->format('Y-m');

        $this->actingAs($finance)
            ->get(route('payroll.generate', ['month' => $month]))
            ->assertRedirect(route('payroll.index', ['generate' => 1, 'month' => $month]));

        $this->actingAs($finance)
            ->get(route('payroll.index', ['generate' => 1, 'month' => $month]))
            ->assertOk()
            ->assertSee('Generate Payroll Run')
            ->assertSee('Payroll Teacher')
            ->assertSee(Money::format(1200));

        $this->actingAs($finance)
            ->post(route('payroll.generate.store'), array_merge(
                $this->confirmPayload($month),
                ['notes' => 'Test run'],
            ))
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->assertSame(PayrollRunStatus::Confirmed, $run->status);
        $this->assertSame(2, $run->staff_count);
        $this->assertSame(1200.0, (float) $run->total_amount);
        $this->assertSame(2, PayrollItem::query()->count());
        $this->assertStringStartsWith('PSL-'.$month.'-', PayrollItem::query()->value('payslip_number'));
    }

    public function test_cannot_generate_duplicate_month_or_future(): void
    {
        ['finance' => $finance] = $this->seedBasics();
        $month = now()->format('Y-m');

        $this->actingAs($finance)
            ->post(route('payroll.generate.store'), $this->confirmPayload($month))
            ->assertRedirect();

        $this->actingAs($finance)
            ->from(route('payroll.index'))
            ->post(route('payroll.generate.store'), [
                'month' => $month,
                'expected_count' => 2,
                'expected_total' => 1200,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('month');

        $future = now()->addMonth()->format('Y-m');
        $this->actingAs($finance)
            ->get(route('payroll.index', ['generate' => 1, 'month' => $future]))
            ->assertOk()
            ->assertSee('Cannot generate payroll for a future month');
    }

    public function test_invalid_month_query_redirects_with_error(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        $this->actingAs($finance)
            ->from(route('payroll.index'))
            ->get(route('payroll.generate', ['month' => 'not-a-month']))
            ->assertRedirect(route('payroll.index'))
            ->assertSessionHasErrors('month');
    }

    public function test_payslip_printable_and_teacher_forbidden(): void
    {
        ['finance' => $finance] = $this->seedBasics();
        $month = now()->format('Y-m');

        $this->actingAs($finance)
            ->post(route('payroll.generate.store'), $this->confirmPayload($month))
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $item = $run->items()->firstOrFail();

        $this->actingAs($finance)
            ->get(route('payroll.payslip', [$run, $item]))
            ->assertOk()
            ->assertSee('Payslip')
            ->assertSee($item->payslip_number)
            ->assertSee($item->full_name);

        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $this->actingAs($teacher)
            ->get(route('payroll.index'))
            ->assertForbidden();
    }

    public function test_payslip_from_other_run_returns_404(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        $this->actingAs($finance)
            ->post(route('payroll.generate.store'), $this->confirmPayload(now()->format('Y-m')))
            ->assertRedirect();

        $runA = PayrollRun::query()->firstOrFail();
        $itemA = $runA->items()->firstOrFail();

        $past = now()->startOfMonth()->subMonth();
        $runB = PayrollGenerator::confirm($past, $finance, 'Other month');

        $this->actingAs($finance)
            ->get(route('payroll.payslip', [$runB, $itemA]))
            ->assertNotFound();
    }

    public function test_excludes_staff_without_salary_inactive_and_late_joiners(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        Staff::query()->create([
            'employee_code' => 'EMP-NOSAL',
            'full_name' => 'No Salary',
            'gender' => Gender::Male,
            'date_joined' => now()->subYear()->toDateString(),
            'fixed_salary_usd' => null,
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);

        Staff::query()->create([
            'employee_code' => 'EMP-INACT',
            'full_name' => 'Inactive Staff',
            'gender' => Gender::Female,
            'date_joined' => now()->subYear()->toDateString(),
            'fixed_salary_usd' => 400,
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Resigned,
        ]);

        Staff::query()->create([
            'employee_code' => 'EMP-LATE',
            'full_name' => 'Late Joiner',
            'gender' => Gender::Male,
            'date_joined' => now()->startOfMonth()->addMonth()->toDateString(),
            'fixed_salary_usd' => 450,
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.generate.store'), $this->confirmPayload(now()->format('Y-m')))
            ->assertRedirect();

        $this->assertSame(2, PayrollRun::query()->firstOrFail()->staff_count);
        $this->assertDatabaseMissing('payroll_items', ['employee_code' => 'EMP-NOSAL']);
        $this->assertDatabaseMissing('payroll_items', ['employee_code' => 'EMP-INACT']);
        $this->assertDatabaseMissing('payroll_items', ['employee_code' => 'EMP-LATE']);
    }

    public function test_empty_staff_set_rejected(): void
    {
        $finance = User::factory()->role(UserRole::Finance)->create();
        $month = now()->format('Y-m');

        $this->actingAs($finance)
            ->from(route('payroll.index'))
            ->post(route('payroll.generate.store'), [
                'month' => $month,
                'expected_count' => 1,
                'expected_total' => 100,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('month');

        $this->assertSame(0, PayrollRun::query()->count());
    }

    public function test_confirm_rejects_stale_preview_totals(): void
    {
        ['finance' => $finance, 'staff' => $staff] = $this->seedBasics();
        $month = now()->format('Y-m');
        $payload = $this->confirmPayload($month);

        $staff->update(['fixed_salary_usd' => 999]);

        $this->actingAs($finance)
            ->from(route('payroll.generate', ['month' => $month]))
            ->post(route('payroll.generate.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('month');

        $this->assertSame(0, PayrollRun::query()->count());
    }

    public function test_duplicate_confirm_race_returns_validation_error(): void
    {
        ['finance' => $finance] = $this->seedBasics();
        $month = now()->format('Y-m');

        PayrollGenerator::confirm($month.'-01', $finance);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        PayrollGenerator::confirm($month.'-01', $finance);
    }
}
