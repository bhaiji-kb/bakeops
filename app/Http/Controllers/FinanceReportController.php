<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function monthly(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        [$month, $start, $end] = $this->resolveMonthRange($month);

        $sales = Sale::with('items')
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->get();
        $expenses = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])->get();

        $totalSales = $sales->sum('total_amount');
        $totalCogs = $sales->sum(function (Sale $sale) {
            return $sale->items->sum('cost_total');
        });
        $grossProfit = $totalSales - $totalCogs;
        $totalExpense = $expenses->sum('amount');
        $netProfit = $grossProfit - $totalExpense;

        $salesByPayment = $sales->groupBy('payment_mode')->map(function ($group) {
            return $group->sum('total_amount');
        });

        $expensesByCategory = $expenses->groupBy('category')->map(function ($group) {
            return $group->sum('amount');
        });

        return view('reports.profit_loss_monthly', compact(
            'month',
            'totalSales',
            'totalCogs',
            'grossProfit',
            'totalExpense',
            'netProfit',
            'salesByPayment',
            'expensesByCategory'
        ));
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
