@extends('layouts.app')

@section('content')
<h2>Supplier Ledger Detail</h2>

<p>
    <a href="{{ route('reports.suppliers.ledger', ['as_of' => $asOf]) }}">Back to Supplier Ledger</a>
</p>

<h3>{{ $supplier->name }}</h3>
<p><strong>As of Date:</strong> {{ $asOf }}</p>

<form method="GET" action="{{ route('reports.suppliers.ledger.show', ['supplier' => $supplier->id]) }}">
    <label>As of Date</label>
    <input type="date" name="as_of" value="{{ $asOf }}">
    <button type="submit">View</button>
</form>

<hr>

<h3>Summary</h3>
<p><strong>Total Purchase:</strong> Rs.{{ number_format($totalPurchase, 2) }}</p>
<p><strong>Total Paid:</strong> Rs.{{ number_format($totalPaid, 2) }}</p>
<p><strong>Total Due:</strong> Rs.{{ number_format($totalDue, 2) }}</p>
<p><strong>Open Bills:</strong> {{ $aging['open_bills'] }}</p>

<h4>Due Aging</h4>
<p><strong>0-30 Days:</strong> Rs.{{ number_format($aging['bucket_0_30'], 2) }}</p>
<p><strong>31-60 Days:</strong> Rs.{{ number_format($aging['bucket_31_60'], 2) }}</p>
<p><strong>60+ Days:</strong> Rs.{{ number_format($aging['bucket_60_plus'], 2) }}</p>

<hr>

<h3>Open Bills</h3>
@if($openPurchases->isEmpty())
    <p>No open bills for this supplier up to this date.</p>
@else
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Bill No.</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Due</th>
                <th>Age (Days)</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($openPurchases as $purchase)
                <tr>
                    <td>{{ $purchase->purchase_date->format('Y-m-d') }}</td>
                    <td>{{ $purchase->bill_number ?: '-' }}</td>
                    <td>Rs.{{ number_format($purchase->total_amount, 2) }}</td>
                    <td>Rs.{{ number_format($purchase->paid_amount, 2) }}</td>
                    <td>Rs.{{ number_format($purchase->due_amount, 2) }}</td>
                    <td>{{ $purchase->purchase_date->diffInDays(\Carbon\Carbon::parse($asOf)) }}</td>
                    <td>{{ strtoupper($purchase->status) }}</td>
                    <td><a href="{{ route('purchases.show', ['purchase' => $purchase->id]) }}">View Bill</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection
