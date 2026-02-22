<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SupplierReportController extends Controller
{
    public function ledger(Request $request)
    {
        [$asOf, $asOfDate] = $this->resolveAsOfDate($request->get('as_of', now()->toDateString()));

        $purchases = Purchase::with('supplier')
            ->whereDate('purchase_date', '<=', $asOf)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($purchases as $purchase) {
            $supplierName = $purchase->supplier->name ?? $purchase->supplier_name ?? 'Unknown Supplier';
            $key = $purchase->supplier_id
                ? 'supplier:' . $purchase->supplier_id
                : 'name:' . strtolower(trim((string) $supplierName));

            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'supplier_id' => $purchase->supplier_id ? (int) $purchase->supplier_id : null,
                    'supplier_name' => $supplierName,
                    'total_purchase' => 0.0,
                    'total_paid' => 0.0,
                    'total_due' => 0.0,
                    'bucket_0_30' => 0.0,
                    'bucket_31_60' => 0.0,
                    'bucket_60_plus' => 0.0,
                    'open_bills' => 0,
                ];
            }

            $rows[$key]['total_purchase'] += (float) $purchase->total_amount;
            $rows[$key]['total_paid'] += (float) $purchase->paid_amount;
            $rows[$key]['total_due'] += (float) $purchase->due_amount;

            $dueAmount = (float) $purchase->due_amount;
            if ($dueAmount > 0) {
                $ageDays = $purchase->purchase_date->diffInDays($asOfDate);
                $rows[$key]['open_bills']++;

                if ($ageDays <= 30) {
                    $rows[$key]['bucket_0_30'] += $dueAmount;
                } elseif ($ageDays <= 60) {
                    $rows[$key]['bucket_31_60'] += $dueAmount;
                } else {
                    $rows[$key]['bucket_60_plus'] += $dueAmount;
                }
            }
        }

        $rows = collect($rows)
            ->map(function ($row) {
                $row['total_purchase'] = round($row['total_purchase'], 2);
                $row['total_paid'] = round($row['total_paid'], 2);
                $row['total_due'] = round($row['total_due'], 2);
                $row['bucket_0_30'] = round($row['bucket_0_30'], 2);
                $row['bucket_31_60'] = round($row['bucket_31_60'], 2);
                $row['bucket_60_plus'] = round($row['bucket_60_plus'], 2);
                return $row;
            })
            ->sortByDesc('total_due')
            ->values();

        $grand = [
            'total_purchase' => $rows->sum('total_purchase'),
            'total_paid' => $rows->sum('total_paid'),
            'total_due' => $rows->sum('total_due'),
            'bucket_0_30' => $rows->sum('bucket_0_30'),
            'bucket_31_60' => $rows->sum('bucket_31_60'),
            'bucket_60_plus' => $rows->sum('bucket_60_plus'),
            'open_bills' => $rows->sum('open_bills'),
        ];

        return view('reports.supplier_ledger', compact('asOf', 'rows', 'grand'));
    }

    public function ledgerSupplier(Request $request, Supplier $supplier)
    {
        [$asOf, $asOfDate] = $this->resolveAsOfDate($request->get('as_of', now()->toDateString()));

        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->whereDate('purchase_date', '<=', $asOf)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $totalPurchase = (float) $purchases->sum('total_amount');
        $totalPaid = (float) $purchases->sum('paid_amount');
        $totalDue = (float) $purchases->sum('due_amount');

        $aging = [
            'bucket_0_30' => 0.0,
            'bucket_31_60' => 0.0,
            'bucket_60_plus' => 0.0,
            'open_bills' => 0,
        ];

        $openPurchases = $purchases->filter(function ($purchase) use (&$aging, $asOfDate) {
            $dueAmount = (float) $purchase->due_amount;
            if ($dueAmount <= 0) {
                return false;
            }

            $aging['open_bills']++;
            $ageDays = $purchase->purchase_date->diffInDays($asOfDate);

            if ($ageDays <= 30) {
                $aging['bucket_0_30'] += $dueAmount;
            } elseif ($ageDays <= 60) {
                $aging['bucket_31_60'] += $dueAmount;
            } else {
                $aging['bucket_60_plus'] += $dueAmount;
            }

            return true;
        })->values();

        $aging['bucket_0_30'] = round($aging['bucket_0_30'], 2);
        $aging['bucket_31_60'] = round($aging['bucket_31_60'], 2);
        $aging['bucket_60_plus'] = round($aging['bucket_60_plus'], 2);

        return view('reports.supplier_ledger_detail', compact(
            'supplier',
            'asOf',
            'totalPurchase',
            'totalPaid',
            'totalDue',
            'aging',
            'openPurchases'
        ));
    }

    private function resolveAsOfDate(string $asOf): array
    {
        try {
            $asOfDate = Carbon::parse($asOf)->startOfDay();
            return [$asOfDate->toDateString(), $asOfDate];
        } catch (\Exception $e) {
            $asOfDate = now()->startOfDay();
            return [$asOfDate->toDateString(), $asOfDate];
        }
    }
}
