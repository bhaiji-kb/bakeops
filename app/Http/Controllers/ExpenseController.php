<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['rent', 'salary', 'ingredients', 'emi'];

    public function index(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        [$month, $start, $end] = $this->resolveMonthRange($month);

        $expenses = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalExpense = $expenses->sum('amount');
        $byCategory = $expenses->groupBy('category')->map(function ($group) {
            return $group->sum('amount');
        });

        $categories = self::CATEGORIES;

        return view('expenses.index', compact(
            'month',
            'expenses',
            'totalExpense',
            'byCategory',
            'categories'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $expense = Expense::create($validated);

        app(ActivityLogService::class)->log(
            module: 'expenses',
            action: 'create',
            entityType: Expense::class,
            entityId: (int) $expense->id,
            description: 'Expense created.',
            newValues: [
                'expense_date' => $expense->expense_date?->toDateString(),
                'category' => $expense->category,
                'amount' => (float) $expense->amount,
                'notes' => $expense->notes,
            ]
        );

        $month = Carbon::parse($validated['expense_date'])->format('Y-m');

        return redirect()
            ->route('expenses.index', ['month' => $month])
            ->with('success', 'Expense added successfully');
    }

    public function edit(Request $request, Expense $expense)
    {
        $month = $request->get('month', $expense->expense_date->format('Y-m'));
        $categories = self::CATEGORIES;

        return view('expenses.edit', compact('expense', 'month', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $oldValues = [
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'notes' => $expense->notes,
        ];

        $expense->update([
            'expense_date' => $validated['expense_date'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        app(ActivityLogService::class)->log(
            module: 'expenses',
            action: 'update',
            entityType: Expense::class,
            entityId: (int) $expense->id,
            description: 'Expense updated.',
            oldValues: $oldValues,
            newValues: [
                'expense_date' => $expense->expense_date?->toDateString(),
                'category' => $expense->category,
                'amount' => (float) $expense->amount,
                'notes' => $expense->notes,
            ]
        );

        $month = $validated['month'] ?? Carbon::parse($validated['expense_date'])->format('Y-m');

        return redirect()
            ->route('expenses.index', ['month' => $month])
            ->with('success', 'Expense updated successfully');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'month' => 'nullable|date_format:Y-m',
        ]);

        $month = $validated['month'] ?? $expense->expense_date->format('Y-m');
        $oldValues = [
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'notes' => $expense->notes,
        ];
        $expense->delete();

        app(ActivityLogService::class)->log(
            module: 'expenses',
            action: 'delete',
            entityType: Expense::class,
            entityId: (int) $expense->id,
            description: 'Expense deleted.',
            oldValues: $oldValues
        );

        return redirect()
            ->route('expenses.index', ['month' => $month])
            ->with('success', 'Expense deleted successfully');
    }

    private function resolveMonthRange(string $month): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            return [$month, $start, $end];
        } catch (\Exception $e) {
            $currentMonth = now()->format('Y-m');
            return [$currentMonth, now()->startOfMonth(), now()->endOfMonth()];
        }
    }
}
