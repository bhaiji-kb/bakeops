<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BakeOps Accounts Dashboard</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        h2 { margin: 18px 0 8px; font-size: 14px; }
        .muted { color: #666; margin-bottom: 12px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary th, .summary td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .summary th { background: #f6f6f6; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>BakeOps Accounts Dashboard Snapshot</h1>
    <div class="muted">
        Range Type: {{ strtoupper($rangeType) }}<br>
        Selected Period: {{ $periodLabel }}<br>
        Comparison Period: {{ $previousPeriodLabel }}
    </div>

    <h2>Summary</h2>
    <table class="summary">
        <tr><th>Metric</th><th class="right">Amount (Rs.)</th></tr>
        <tr><td>Cash In</td><td class="right">{{ number_format($cashIn, 2) }}</td></tr>
        <tr><td>Cash Out</td><td class="right">{{ number_format($cashOut, 2) }}</td></tr>
        <tr><td>Net Cash</td><td class="right">{{ number_format($netCash, 2) }}</td></tr>
        <tr><td>Supplier Due</td><td class="right">{{ number_format($payableDue, 2) }}</td></tr>
        <tr><td>Open Bills</td><td class="right">{{ $openBillsCount }}</td></tr>
        <tr><td>Sales</td><td class="right">{{ number_format($sales, 2) }}</td></tr>
        <tr><td>COGS</td><td class="right">{{ number_format($cogs, 2) }}</td></tr>
        <tr><td>Expenses</td><td class="right">{{ number_format($expenses, 2) }}</td></tr>
        <tr><td>Net Profit</td><td class="right">{{ number_format($netProfit, 2) }}</td></tr>
    </table>

    <h2>Comparison</h2>
    <table class="summary">
        <tr>
            <th>Metric</th>
            <th class="right">Current</th>
            <th class="right">Previous</th>
            <th class="right">Delta</th>
            <th class="right">Delta %</th>
        </tr>
        @foreach($comparisonRows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="right">{{ number_format($row['current'], 2) }}</td>
                <td class="right">{{ number_format($row['previous'], 2) }}</td>
                <td class="right">{{ number_format($row['delta'], 2) }}</td>
                <td class="right">
                    {{ $row['deltaPercent'] === null ? 'N/A' : number_format($row['deltaPercent'], 2).'%' }}
                </td>
            </tr>
        @endforeach
    </table>

    <h2>Top Supplier Dues</h2>
    <table class="summary">
        <tr><th>Supplier</th><th class="right">Open Bills</th><th class="right">Total Due (Rs.)</th></tr>
        @forelse($payablesBySupplier as $row)
            <tr>
                <td>{{ $row->supplier_name ?: 'Unknown' }}</td>
                <td class="right">{{ $row->open_bills }}</td>
                <td class="right">{{ number_format((float) $row->due_total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No outstanding supplier dues.</td></tr>
        @endforelse
    </table>
</body>
</html>
