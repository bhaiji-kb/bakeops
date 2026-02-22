@extends('layouts.app')

@section('content')
<h2>Purchase Bill Details</h2>

<p>
    <a href="{{ route('purchases.index', ['month' => $purchase->purchase_date->format('Y-m')]) }}">Back to Purchases</a>
    @if($purchase->supplier_id)
        | <a href="{{ route('reports.suppliers.ledger.show', ['supplier' => $purchase->supplier_id, 'as_of' => $purchase->purchase_date->format('Y-m-d')]) }}">Supplier Detail</a>
    @endif
    | <a href="{{ route('reports.suppliers.ledger', ['as_of' => $purchase->purchase_date->format('Y-m-d')]) }}">Supplier Ledger</a>
</p>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<p><strong>Supplier:</strong> {{ $purchase->supplier->name ?? $purchase->supplier_name }}</p>
<p><strong>Bill Number:</strong> {{ $purchase->bill_number ?: '-' }}</p>
<p><strong>Purchase Date:</strong> {{ $purchase->purchase_date->format('Y-m-d') }}</p>
<p><strong>Status:</strong> {{ strtoupper($purchase->status) }}</p>
<p><strong>Total:</strong> Rs.{{ number_format($purchase->total_amount, 2) }}</p>
<p><strong>Paid:</strong> Rs.{{ number_format($purchase->paid_amount, 2) }}</p>
<p><strong>Due:</strong> Rs.{{ number_format($purchase->due_amount, 2) }}</p>
<p><strong>Notes:</strong> {{ $purchase->notes ?: '-' }}</p>

<hr>

<h3>Items (Ingredient Inward)</h3>
<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>Ingredient</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($purchase->items as $item)
            <tr>
                <td>{{ $item->product->name ?? 'Unknown' }}</td>
                <td>{{ number_format($item->quantity, 2) }}</td>
                <td>Rs.{{ number_format($item->unit_price, 2) }}</td>
                <td>Rs.{{ number_format($item->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<hr>

<h3>Due Payments</h3>
@if((float) $purchase->due_amount > 0)
    <form method="POST" action="{{ route('purchases.payments.store', ['purchase' => $purchase->id]) }}">
        @csrf

        <label>Payment Date</label>
        <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required>

        <label>Amount</label>
        <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>

        <label>Payment Mode</label>
        <select name="payment_mode" required>
            <option value="">Select mode</option>
            @foreach($paymentModes as $mode)
                <option value="{{ $mode }}" {{ old('payment_mode') === $mode ? 'selected' : '' }}>
                    {{ strtoupper($mode) }}
                </option>
            @endforeach
        </select>

        <label>Notes</label>
        <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Optional notes">

        <button type="submit">Add Payment</button>
    </form>
@else
    <p>No due amount pending for this purchase.</p>
@endif

<br>

<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>Date</th>
            <th>Amount</th>
            <th>Mode</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($purchase->payments->sortByDesc('payment_date') as $payment)
            <tr>
                <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
                <td>Rs.{{ number_format($payment->amount, 2) }}</td>
                <td>{{ strtoupper($payment->payment_mode) }}</td>
                <td>{{ $payment->notes ?: '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No payments recorded.</td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection
