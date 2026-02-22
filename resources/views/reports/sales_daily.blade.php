@extends('layouts.app')

@section('content')
<h2>Daily Sales Report</h2>

<form method="GET" action="{{ route('reports.sales.daily') }}">
    <label>Select Date: </label>
    <input type="date" name="date" value="{{ $date }}">
    <button type="submit">View</button>
</form>

<hr>

<h3>Summary ({{ $date }})</h3>
<p><strong>Total Sales:</strong> Rs.{{ number_format($totalSales, 2) }}</p>
<p><strong>Total COGS:</strong> Rs.{{ number_format($totalCogs, 2) }}</p>
<p><strong>Gross Profit:</strong> Rs.{{ number_format($grossProfit, 2) }}</p>
<p><strong>Gross Margin %:</strong> {{ number_format($grossMarginPercent, 2) }}%</p>

<h4>By Payment Mode</h4>
<ul>
    @forelse($byPayment as $mode => $amount)
        <li><strong>{{ strtoupper($mode) }}:</strong> Rs.{{ number_format($amount, 2) }}</li>
    @empty
        <li>No sales</li>
    @endforelse
</ul>

<hr>

<h3>Sales</h3>

@if($sales->isEmpty())
    <p>No sales for this date.</p>
@else
    @foreach($sales as $sale)
        <div style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">
            <p>
                @php($saleCost = (float) $sale->items->sum('cost_total'))
                @php($saleMargin = (float) $sale->total_amount - $saleCost)
                <strong>Bill:</strong> {{ $sale->bill_number }}
                | <strong>Time:</strong> {{ $sale->created_at->format('H:i') }}
                | <strong>Payment:</strong> {{ strtoupper($sale->payment_mode) }}
                | <strong>Total:</strong> Rs.{{ number_format($sale->total_amount, 2) }}
                | <strong>COGS:</strong> Rs.{{ number_format($saleCost, 2) }}
                | <strong>Margin:</strong> Rs.{{ number_format($saleMargin, 2) }}
            </p>

            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Sell Price</th>
                        <th>Unit Cost</th>
                        <th>Revenue</th>
                        <th>COGS</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'Unknown' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>Rs.{{ number_format($item->price, 2) }}</td>
                            <td>Rs.{{ number_format($item->unit_cost, 2) }}</td>
                            <td>Rs.{{ number_format($item->total, 2) }}</td>
                            <td>Rs.{{ number_format($item->cost_total, 2) }}</td>
                            <td>Rs.{{ number_format((float) $item->total - (float) $item->cost_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
@endsection
