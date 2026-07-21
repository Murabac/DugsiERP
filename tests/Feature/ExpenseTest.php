<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_finance_can_record_expense_with_category(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $category = ExpenseCategory::query()->where('name', 'Utilities')->first()
            ?? ExpenseCategory::query()->create(['name' => 'Utilities', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('finance.expenses'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('finance.expenses'))
            ->assertOk()
            ->assertSee('Expenses')
            ->assertSee('+ Add expense');

        $this->actingAs($finance)
            ->post(route('finance.expenses.store'), [
                'expense_category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'amount' => 25.5,
                'payment_method' => PaymentMethod::Cash->value,
                'description' => 'Electricity bill',
            ])
            ->assertRedirect(route('finance.expenses'));

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $category->id,
            'amount' => 25.50,
            'description' => 'Electricity bill',
            'recorded_by' => $finance->id,
        ]);
    }

    public function test_admin_can_add_expense_category_inline(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('finance.expense-categories.store'), [
                'name' => 'Stationery',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Stationery',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('finance.expenses', ['add' => 1]))
            ->post(route('finance.expense-categories.store'), [
                'name' => 'Stationery',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_expense(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $category = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Supplies'],
            ['is_active' => true]
        );
        $other = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Maintenance'],
            ['is_active' => true]
        );
        $expense = Expense::query()->create([
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => 10,
            'payment_method' => PaymentMethod::Cash,
            'description' => 'Old note',
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('finance.expenses.update', $expense), [
                'expense_category_id' => $other->id,
                'expense_date' => now()->subDay()->toDateString(),
                'amount' => 42.25,
                'payment_method' => PaymentMethod::MobileMoney->value,
                'description' => 'Updated note',
            ])
            ->assertRedirect(route('finance.expenses'));

        $expense->refresh();
        $this->assertSame($other->id, $expense->expense_category_id);
        $this->assertSame(42.25, (float) $expense->amount);
        $this->assertSame('Updated note', $expense->description);
        $this->assertSame(PaymentMethod::MobileMoney, $expense->payment_method);
    }

    public function test_admin_can_delete_expense(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $category = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Other'],
            ['is_active' => true]
        );
        $expense = Expense::query()->create([
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => 10,
            'payment_method' => PaymentMethod::MobileMoney,
            'description' => 'Test',
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('finance.expenses.destroy', $expense))
            ->assertRedirect(route('finance.expenses'));

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
