@extends('layouts.app')

@section('content')
<h2>Daily Sales Report</h2>

<p>
    <a href="/pos">POS</a> | <a href="/inventory">Inventory</a>
</p>

<form method="GET" action="{{ route('reports.sales.daily') }}">
    <label>Select Date: </label>
    <input type="date" name="date" value="{{ $date }}">
    <button type="submit">View</button>
</form>

<hr>

<h3>Summary ({{ $date }})</h3>
<p><strong>Total Sales:</strong> ₹{{ number_format($totalSales, 2) }}</p>

<h4>By Payment Mode</h4>
<ul>
    @forelse($byPayment as $mode => $amount)
        <li><strong>{{ strtoupper($mode) }}:</strong> ₹{{ number_format($amount, 2) }}</li>
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
                <strong>Bill:</strong> {{ $sale->bill_number }}
                | <strong>Time:</strong> {{ $sale->created_at->format('H:i') }}
                | <strong>Payment:</strong> {{ strtoupper($sale->payment_mode) }}
                | <strong>Total:</strong> ₹{{ number_format($sale->total_amount, 2) }}
            </p>

            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $item)
                        <tr>
                            <td>{{ $item->product->name ?? 'Unknown' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>₹{{ number_format($item->price, 2) }}</td>
                            <td>₹{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
@endsection
