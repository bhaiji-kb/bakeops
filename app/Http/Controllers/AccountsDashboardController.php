<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountsDashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('accounts.dashboard', $this->buildDashboardData($request));
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $filename = sprintf(
            'accounts-dashboard-%s-%s.pdf',
            $data['periodStart']->format('Ymd'),
            $data['periodEnd']->format('Ymd')
        );

        $pdf = Pdf::loadView('accounts.dashboard_pdf', $data)->setPaper('a4');

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $data = $this->buildDashboardData($request);
        $filename = sprintf(
            'accounts-dashboard-%s-%s.csv',
            $data['periodStart']->format('Ymd'),
            $data['periodEnd']->format('Ymd')
        );

        $rows = $this->buildCsvRows($data);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildDashboardData(Request $request): array
    {
        [$rangeType, $dateInput, $startDateInput, $endDateInput, $periodStart, $periodEnd] = $this->resolveRangeInputs($request);
        $periodSummary = $this->buildSummary($periodStart, $periodEnd);

        $daysInPeriod = $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1;
        $previousPeriodEnd = $periodStart->copy()->subDay()->endOfDay();
        $previousPeriodStart = $previousPeriodEnd->copy()->subDays($daysInPeriod - 1)->startOfDay();
        $previousSummary = $this->buildSummary($previousPeriodStart, $previousPeriodEnd);

        $comparisonRows = $this->buildComparisonRows($periodSummary, $previousSummary);
        $trendData = $this->buildTrendData($periodStart, $periodEnd);

        return array_merge($periodSummary, [
            'rangeType' => $rangeType,
            'dateInput' => $dateInput,
            'startDateInput' => $startDateInput,
            'endDateInput' => $endDateInput,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodLabel' => $periodStart->toDateString() . ' to ' . $periodEnd->toDateString(),
            'previousPeriodLabel' => $previousPeriodStart->toDateString() . ' to ' . $previousPeriodEnd->toDateString(),
            'comparisonRows' => $comparisonRows,
            'cashTrend' => [
                'labels' => $trendData['labels'],
                'cashIn' => $trendData['cashIn'],
                'cashOut' => $trendData['cashOut'],
                'netCash' => $trendData['netCash'],
            ],
            'payableTrend' => [
                'labels' => $trendData['labels'],
                'due' => $trendData['due'],
            ],

            // Legacy aliases used by existing tests and template sections.
            'todayIn' => $periodSummary['cashIn'],
            'todayExpenseOut' => $periodSummary['expenseOut'],
            'todayPurchaseOut' => $periodSummary['purchaseOut'],
            'todayOut' => $periodSummary['cashOut'],
            'todayNet' => $periodSummary['netCash'],
            'totalPayableDue' => $periodSummary['payableDue'],
            'mtdSales' => $periodSummary['sales'],
            'mtdCogs' => $periodSummary['cogs'],
            'mtdExpenses' => $periodSummary['expenses'],
            'mtdNetProfit' => $periodSummary['netProfit'],
            'todaySalesByPayment' => $periodSummary['salesByPayment'],
        ]);
    }

    private function resolveRangeInputs(Request $request): array
    {
        $rangeType = (string) $request->get('range_type', 'day');
        if (!in_array($rangeType, ['day', 'week', 'month', 'custom'], true)) {
            $rangeType = 'day';
        }

        $now = now();
        $dateInput = (string) $request->get('date', $now->toDateString());
        $startDateInput = (string) $request->get('start_date', $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString());
        $endDateInput = (string) $request->get('end_date', $now->toDateString());

        $anchorDate = $this->safeParseDate($dateInput, $now->copy())->startOfDay();

        if ($rangeType === 'week') {
            $start = $anchorDate->copy()->startOfWeek(Carbon::MONDAY);
            $end = $anchorDate->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($rangeType === 'month') {
            $start = $anchorDate->copy()->startOfMonth();
            $end = $anchorDate->copy()->endOfMonth();
        } elseif ($rangeType === 'custom') {
            $start = $this->safeParseDate($startDateInput, $anchorDate->copy())->startOfDay();
            $end = $this->safeParseDate($endDateInput, $anchorDate->copy())->endOfDay();
            if ($start->gt($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            $startDateInput = $start->toDateString();
            $endDateInput = $end->toDateString();
        } else {
            $start = $anchorDate->copy()->startOfDay();
            $end = $anchorDate->copy()->endOfDay();
        }

        return [$rangeType, $anchorDate->toDateString(), $startDateInput, $endDateInput, $start, $end];
    }

    private function safeParseDate(string $value, Carbon $fallback): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return $fallback;
        }
    }

    private function buildSummary(Carbon $start, Carbon $end): array
    {
        $cashIn = (float) Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->sum('paid_amount');

        $expenseOut = (float) Expense::query()
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->sum('amount');

        $purchaseOut = (float) PurchasePayment::query()
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->sum('amount');

        $cashOut = round($expenseOut + $purchaseOut, 2);
        $netCash = round($cashIn - $cashOut, 2);

        $payableDue = (float) Purchase::query()
            ->where('due_amount', '>', 0)
            ->sum('due_amount');

        $openBillsCount = Purchase::query()
            ->where('due_amount', '>', 0)
            ->count();

        $payablesBySupplier = DB::table('purchases')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->selectRaw('
                purchases.supplier_id,
                COALESCE(suppliers.name, purchases.supplier_name) as supplier_name,
                SUM(purchases.due_amount) as due_total,
                COUNT(*) as open_bills
            ')
            ->where('purchases.due_amount', '>', 0)
            ->groupBy('purchases.supplier_id', 'supplier_name')
            ->orderByDesc('due_total')
            ->limit(10)
            ->get();

        $sales = (float) Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $cogs = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->sum('sale_items.cost_total');

        $expenses = (float) Expense::query()
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->sum('amount');

        $netProfit = round($sales - $cogs - $expenses, 2);

        $salesByPayment = Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('payment_mode, SUM(paid_amount) as paid_total')
            ->groupBy('payment_mode')
            ->orderBy('payment_mode')
            ->pluck('paid_total', 'payment_mode');

        return [
            'cashIn' => $cashIn,
            'expenseOut' => $expenseOut,
            'purchaseOut' => $purchaseOut,
            'cashOut' => $cashOut,
            'netCash' => $netCash,
            'payableDue' => $payableDue,
            'openBillsCount' => $openBillsCount,
            'payablesBySupplier' => $payablesBySupplier,
            'sales' => $sales,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'netProfit' => $netProfit,
            'salesByPayment' => $salesByPayment,
        ];
    }

    private function buildComparisonRows(array $current, array $previous): array
    {
        $metrics = [
            'Cash In' => ['key' => 'cashIn', 'decimals' => 2],
            'Cash Out' => ['key' => 'cashOut', 'decimals' => 2],
            'Net Cash' => ['key' => 'netCash', 'decimals' => 2],
            'Sales' => ['key' => 'sales', 'decimals' => 2],
            'Net Profit' => ['key' => 'netProfit', 'decimals' => 2],
        ];

        $rows = [];
        foreach ($metrics as $label => $config) {
            $key = $config['key'];
            $currentValue = round((float) ($current[$key] ?? 0), $config['decimals']);
            $previousValue = round((float) ($previous[$key] ?? 0), $config['decimals']);
            $delta = round($currentValue - $previousValue, $config['decimals']);
            $deltaPercent = $previousValue == 0.0
                ? null
                : round(($delta / $previousValue) * 100, 2);

            $rows[] = [
                'label' => $label,
                'current' => $currentValue,
                'previous' => $previousValue,
                'delta' => $delta,
                'deltaPercent' => $deltaPercent,
            ];
        }

        return $rows;
    }

    private function buildTrendData(Carbon $start, Carbon $end): array
    {
        $salesByDate = Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(paid_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $expenseByDate = Expense::query()
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->selectRaw('expense_date as day, SUM(amount) as total')
            ->groupBy('expense_date')
            ->pluck('total', 'day');

        $purchasePaymentByDate = PurchasePayment::query()
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->selectRaw('payment_date as day, SUM(amount) as total')
            ->groupBy('payment_date')
            ->pluck('total', 'day');

        $purchaseTotalsInRange = Purchase::query()
            ->whereDate('purchase_date', '>=', $start->toDateString())
            ->whereDate('purchase_date', '<=', $end->toDateString())
            ->selectRaw('purchase_date as day, SUM(total_amount) as total')
            ->groupBy('purchase_date')
            ->pluck('total', 'day');

        $paymentTotalsInRange = PurchasePayment::query()
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->selectRaw('payment_date as day, SUM(amount) as total')
            ->groupBy('payment_date')
            ->pluck('total', 'day');

        $openingPurchases = (float) Purchase::query()
            ->whereDate('purchase_date', '<', $start->toDateString())
            ->sum('total_amount');

        $openingPayments = (float) PurchasePayment::query()
            ->whereDate('payment_date', '<', $start->toDateString())
            ->sum('amount');

        $runningDue = max(0, round($openingPurchases - $openingPayments, 2));

        $labels = [];
        $cashInSeries = [];
        $cashOutSeries = [];
        $netCashSeries = [];
        $payableSeries = [];

        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $day) {
            $dayKey = $day->toDateString();
            $labels[] = $day->format('d M');

            $in = round((float) ($salesByDate[$dayKey] ?? 0), 2);
            $expense = round((float) ($expenseByDate[$dayKey] ?? 0), 2);
            $purchasePay = round((float) ($purchasePaymentByDate[$dayKey] ?? 0), 2);
            $out = round($expense + $purchasePay, 2);
            $net = round($in - $out, 2);

            $runningDue += round((float) ($purchaseTotalsInRange[$dayKey] ?? 0), 2);
            $runningDue -= round((float) ($paymentTotalsInRange[$dayKey] ?? 0), 2);
            $runningDue = max(0, round($runningDue, 2));

            $cashInSeries[] = $in;
            $cashOutSeries[] = $out;
            $netCashSeries[] = $net;
            $payableSeries[] = $runningDue;
        }

        return [
            'labels' => $labels,
            'cashIn' => $cashInSeries,
            'cashOut' => $cashOutSeries,
            'netCash' => $netCashSeries,
            'due' => $payableSeries,
        ];
    }

    private function buildCsvRows(array $data): array
    {
        $rows = [
            ['BakeOps Accounts Dashboard Snapshot'],
            ['Range Type', ucfirst($data['rangeType'])],
            ['Selected Period', $data['periodLabel']],
            ['Comparison Period', $data['previousPeriodLabel']],
            [],
            ['Metric', 'Amount (Rs.)'],
            ['Cash In', number_format($data['cashIn'], 2, '.', '')],
            ['Cash Out', number_format($data['cashOut'], 2, '.', '')],
            ['Net Cash', number_format($data['netCash'], 2, '.', '')],
            ['Payable Due', number_format($data['payableDue'], 2, '.', '')],
            ['Open Bills', (string) $data['openBillsCount']],
            ['Sales', number_format($data['sales'], 2, '.', '')],
            ['COGS', number_format($data['cogs'], 2, '.', '')],
            ['Expenses', number_format($data['expenses'], 2, '.', '')],
            ['Net Profit', number_format($data['netProfit'], 2, '.', '')],
            [],
            ['Comparison', 'Current', 'Previous', 'Delta', 'Delta %'],
        ];

        foreach ($data['comparisonRows'] as $row) {
            $rows[] = [
                $row['label'],
                number_format((float) $row['current'], 2, '.', ''),
                number_format((float) $row['previous'], 2, '.', ''),
                number_format((float) $row['delta'], 2, '.', ''),
                $row['deltaPercent'] === null ? 'NA' : number_format((float) $row['deltaPercent'], 2, '.', '') . '%',
            ];
        }

        $rows[] = [];
        $rows[] = ['Supplier', 'Open Bills', 'Total Due (Rs.)'];
        foreach ($data['payablesBySupplier'] as $supplierRow) {
            $rows[] = [
                (string) ($supplierRow->supplier_name ?: 'Unknown'),
                (string) $supplierRow->open_bills,
                number_format((float) $supplierRow->due_total, 2, '.', ''),
            ];
        }

        $rows[] = [];
        $rows[] = ['Date', 'Cash In', 'Cash Out', 'Net Cash', 'Payable Due'];
        $labels = $data['cashTrend']['labels'];
        foreach ($labels as $index => $label) {
            $rows[] = [
                $label,
                number_format((float) ($data['cashTrend']['cashIn'][$index] ?? 0), 2, '.', ''),
                number_format((float) ($data['cashTrend']['cashOut'][$index] ?? 0), 2, '.', ''),
                number_format((float) ($data['cashTrend']['netCash'][$index] ?? 0), 2, '.', ''),
                number_format((float) ($data['payableTrend']['due'][$index] ?? 0), 2, '.', ''),
            ];
        }

        return $rows;
    }
}
