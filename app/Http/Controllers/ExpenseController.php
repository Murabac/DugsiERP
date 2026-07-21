<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        try {
            $fromDate = \Illuminate\Support\Carbon::parse($from)->toDateString();
            $toDate = \Illuminate\Support\Carbon::parse($to)->toDateString();
        } catch (\Throwable) {
            $fromDate = now()->startOfMonth()->toDateString();
            $toDate = now()->toDateString();
        }

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $categoryId = (int) $request->query('category', 0);

        $expenses = Expense::query()
            ->with(['category', 'recorder'])
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->when($categoryId > 0, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $total = Money::round(
            (float) Expense::query()
                ->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate)
                ->when($categoryId > 0, fn ($q) => $q->where('expense_category_id', $categoryId))
                ->sum('amount')
        );

        return view('finance.expenses', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->active()->orderBy('name')->get(),
            'allCategories' => ExpenseCategory::query()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::cases(),
            'from' => $fromDate,
            'to' => $toDate,
            'categoryId' => $categoryId,
            'total' => $total,
            'openAdd' => (bool) $request->query('add'),
        ]);
    }

    public function print(Request $request): View
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        try {
            $fromDate = \Illuminate\Support\Carbon::parse($from)->toDateString();
            $toDate = \Illuminate\Support\Carbon::parse($to)->toDateString();
        } catch (\Throwable) {
            $fromDate = now()->startOfMonth()->toDateString();
            $toDate = now()->toDateString();
        }

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $categoryId = (int) $request->query('category', 0);

        $expenses = Expense::query()
            ->with(['category', 'recorder'])
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->when($categoryId > 0, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $total = Money::round((float) $expenses->sum('amount'));

        return view('finance.print-expenses', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->orderBy('name')->get(),
            'from' => $fromDate,
            'to' => $toDate,
            'categoryId' => $categoryId,
            'total' => $total,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Expense::query()->create([
            'expense_category_id' => (int) $data['expense_category_id'],
            'expense_date' => $data['expense_date'],
            'amount' => Money::round($data['amount']),
            'payment_method' => $data['payment_method'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('finance.expenses')
            ->with('status', 'Expense recorded.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:expense_categories,name'],
        ]);

        $category = ExpenseCategory::query()->create([
            'name' => trim($data['name']),
            'is_active' => true,
        ]);

        return redirect()
            ->route('finance.expenses', ['add' => 1, 'category' => $category->id])
            ->with('status', 'Category “'.$category->name.'” added.')
            ->with('selected_category_id', $category->id);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $expense->update([
            'expense_category_id' => (int) $data['expense_category_id'],
            'expense_date' => $data['expense_date'],
            'amount' => Money::round($data['amount']),
            'payment_method' => $data['payment_method'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
        ]);

        return redirect()
            ->route('finance.expenses')
            ->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()
            ->route('finance.expenses')
            ->with('status', 'Expense removed.');
    }
}
