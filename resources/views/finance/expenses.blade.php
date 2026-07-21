@extends('layouts.app')

@section('title', 'Expenses — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Expenses" sub="Record school spending by category">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('finance.expenses.print', ['from' => $from, 'to' => $to, 'category' => $categoryId ?: null]) }}">Print</x-btn>
            <x-btn type="button" data-dugsi-open="#add-expense-modal">+ Add expense</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="GET" action="{{ route('finance.expenses') }}" class="flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white p-3">
        <div>
            <label class="mb-1 block text-[11px] font-medium text-slate-500">From</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-medium text-slate-500">To</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-medium text-slate-500">Category</label>
            <select name="category" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($allCategories as $cat)
                    <option value="{{ $cat->id }}" @selected($categoryId === $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white">Filter</button>
        <div class="ml-auto text-sm font-semibold text-slate-800">
            Total: {{ \App\Support\Money::format($total) }}
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-2.5">Date</th>
                    <th class="px-4 py-2.5">Category</th>
                    <th class="px-4 py-2.5">Description</th>
                    <th class="px-4 py-2.5">Method</th>
                    <th class="px-4 py-2.5 text-right">Amount</th>
                    <th class="px-4 py-2.5 text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr class="border-b border-slate-50">
                        <td class="px-4 py-2.5 text-slate-700">{{ $expense->expense_date->format('j M Y') }}</td>
                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ $expense->category?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $expense->description ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $expense->payment_method->label() }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-slate-900">{{ \App\Support\Money::format($expense->amount) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button"
                                    class="edit-expense-btn text-xs font-medium text-blue-700 hover:underline"
                                    data-dugsi-open="#edit-expense-modal"
                                    data-id="{{ $expense->id }}"
                                    data-action="{{ route('finance.expenses.update', $expense) }}"
                                    data-category="{{ $expense->expense_category_id }}"
                                    data-date="{{ $expense->expense_date->format('Y-m-d') }}"
                                    data-amount="{{ $expense->amount }}"
                                    data-method="{{ $expense->payment_method->value }}"
                                    data-description="{{ $expense->description }}">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('finance.expenses.destroy', $expense) }}"
                                    data-dugsi-confirm="Remove this expense?"
                                    data-dugsi-confirm-title="Delete expense"
                                    data-dugsi-confirm-ok="Delete"
                                    data-dugsi-danger>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No expenses in this range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($expenses->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $expenses->links() }}</div>
        @endif
    </div>
</div>

<div id="add-expense-modal" class="hidden" data-dugsi-width="28rem">
    <div class="border-b border-slate-200 px-5 py-4">
        <h3 class="text-sm font-semibold text-slate-900">Add expense</h3>
    </div>
    <form method="POST" action="{{ route('finance.expenses.store') }}" class="space-y-3 p-5">
        @csrf
        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label class="block text-xs font-medium text-slate-700">Category <span class="text-red-500">*</span></label>
                <button type="button" id="toggle-add-category" class="text-[11px] font-medium text-blue-700 hover:underline">+ Add category</button>
            </div>
            <select name="expense_category_id" id="expense-category-select" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                <option value="">Select category</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((int) old('expense_category_id', session('selected_category_id', $categoryId ?: 0)) === $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('expense_category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

            <div id="add-category-panel" class="mt-2 hidden rounded-md border border-dashed border-slate-300 bg-slate-50 p-3">
                <label class="mb-1 block text-[11px] font-medium text-slate-600">New category name</label>
                <div class="flex gap-2">
                    <input type="text" id="new-category-name" maxlength="64" placeholder="e.g. Stationery"
                        class="min-w-0 flex-1 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    <button type="submit" form="add-category-form" class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                        Save
                    </button>
                </div>
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Date <span class="text-red-500">*</span></label>
                <input type="date" name="expense_date" required value="{{ old('expense_date', now()->toDateString()) }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @error('expense_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Amount (USD) <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-1">
                    <span class="text-slate-400">$</span>
                    <input type="number" name="amount" step="0.01" min="0.01" max="999999.99" required value="{{ old('amount') }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                </div>
                @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Payment method <span class="text-red-500">*</span></label>
            <select name="payment_method" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->value }}" @selected(old('payment_method', 'cash') === $method->value)>{{ $method->label() }}</option>
                @endforeach
            </select>
            @error('payment_method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Description</label>
            <input type="text" name="description" maxlength="255" value="{{ old('description') }}" placeholder="Optional note"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
            <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Save expense</button>
        </div>
    </form>
</div>

<form id="add-category-form" method="POST" action="{{ route('finance.expense-categories.store') }}" class="hidden">
    @csrf
    <input type="hidden" name="name" id="add-category-name-field" value="">
</form>

<div id="edit-expense-modal" class="hidden" data-dugsi-width="28rem">
    <div class="border-b border-slate-200 px-5 py-4">
        <h3 class="text-sm font-semibold text-slate-900">Edit expense</h3>
    </div>
    <form id="edit-expense-form" method="POST" action="" class="space-y-3 p-5">
        @csrf
        @method('PUT')
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Category <span class="text-red-500">*</span></label>
            <select name="expense_category_id" id="edit-expense-category" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Date <span class="text-red-500">*</span></label>
                <input type="date" name="expense_date" id="edit-expense-date" required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Amount (USD) <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-1">
                    <span class="text-slate-400">$</span>
                    <input type="number" name="amount" id="edit-expense-amount" step="0.01" min="0.01" max="999999.99" required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                </div>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Payment method <span class="text-red-500">*</span></label>
            <select name="payment_method" id="edit-expense-method" required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Description</label>
            <input type="text" name="description" id="edit-expense-description" maxlength="255" placeholder="Optional note"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
            <button type="button" data-dugsi-close class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Save changes</button>
        </div>
    </form>
</div>

@if (($errors->any() && ! old('_method')) || $openAdd || session('selected_category_id'))
<script>document.addEventListener('DOMContentLoaded', () => window.DugsiUI?.openModal('#add-expense-modal'));</script>
@endif

<script>
(() => {
    const toggle = document.getElementById('toggle-add-category');
    const panel = document.getElementById('add-category-panel');
    const nameInput = document.getElementById('new-category-name');
    const hiddenName = document.getElementById('add-category-name-field');
    const categoryForm = document.getElementById('add-category-form');
    const editForm = document.getElementById('edit-expense-form');

    toggle?.addEventListener('click', () => {
        panel?.classList.toggle('hidden');
        if (!panel?.classList.contains('hidden')) nameInput?.focus();
    });

    categoryForm?.addEventListener('submit', () => {
        if (hiddenName && nameInput) hiddenName.value = nameInput.value.trim();
    });

    document.querySelectorAll('.edit-expense-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editForm) return;
            editForm.action = btn.dataset.action || '';
            const category = document.getElementById('edit-expense-category');
            const date = document.getElementById('edit-expense-date');
            const amount = document.getElementById('edit-expense-amount');
            const method = document.getElementById('edit-expense-method');
            const description = document.getElementById('edit-expense-description');
            if (category) category.value = btn.dataset.category || '';
            if (date) date.value = btn.dataset.date || '';
            if (amount) amount.value = btn.dataset.amount || '';
            if (method) method.value = btn.dataset.method || '';
            if (description) description.value = btn.dataset.description || '';
        });
    });

    @if ($errors->has('name'))
    document.addEventListener('DOMContentLoaded', () => panel?.classList.remove('hidden'));
    @endif
})();
</script>
@endsection
