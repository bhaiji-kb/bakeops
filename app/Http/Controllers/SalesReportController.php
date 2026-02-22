<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function daily(Request $request)
    {
        $date = $request->get('date', now()->toDateString());

        $sales = Sale::with('items.product')
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalSales = $sales->sum('total_amount');
        $totalCogs = $sales->sum(function (Sale $sale) {
            return $sale->items->sum('cost_total');
        });
        $grossProfit = $totalSales - $totalCogs;
        $grossMarginPercent = $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 2) : 0;

        $byPayment = $sales->groupBy('payment_mode')->map(function ($group) {
            return $group->sum('total_amount');
        });

        return view('reports.sales_daily', compact(
            'date',
            'sales',
            'totalSales',
            'totalCogs',
            'grossProfit',
            'grossMarginPercent',
            'byPayment'
        ));
    }
}
