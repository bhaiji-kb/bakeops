@extends('layouts.app')

@section('content')
<h2>Purchases</h2>

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

<form method="GET" action="{{ route('purchases.index') }}" style="margin-bottom: 16px;">
    <label>Month</label>
    <input type="month" name="month" value="{{ $month }}">
    <button type="submit">Filter</button>
</form>

<h3>Monthly Summary ({{ $month }})</h3>
<p><strong>Total Purchase:</strong> Rs.{{ number_format($totals['total_purchase'], 2) }}</p>
<p><strong>Total Paid:</strong> Rs.{{ number_format($totals['total_paid'], 2) }}</p>
<p><strong>Total Due:</strong> Rs.{{ number_format($totals['total_due'], 2) }}</p>

<hr>

<h3>Create Supplier Bill + Ingredient Inward</h3>
<p class="text-muted small mb-2">
    Enter purchase quantity in the ingredient's base unit shown below.
    Example: if unit is <strong>G</strong>, then 1 Kg = 1000.
</p>

@if($rawMaterials->isEmpty())
    <p>No active raw materials found. <a href="{{ route('products.index') }}">Add ingredients in Product Master</a>.</p>
@elseif($suppliers->isEmpty())
    <p>No active suppliers found. <a href="{{ route('suppliers.index') }}">Add suppliers first</a>.</p>
@else
    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf

        <label>Supplier</label>
        <select name="supplier_id" required>
            <option value="">Select supplier</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->name }}
                </option>
            @endforeach
        </select>

        <label>Bill Number</label>
        <input type="text" name="bill_number" value="{{ old('bill_number') }}">

        <label>Purchase Date</label>
        <input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" required>

        <label>Initial Paid Amount</label>
        <input type="number" name="initial_paid_amount" min="0" step="0.01" value="{{ old('initial_paid_amount', '0') }}">

        <label>Initial Payment Mode</label>
        <select name="initial_payment_mode">
            <option value="">Select mode (if paid)</option>
            @foreach($paymentModes as $mode)
                <option value="{{ $mode }}" {{ old('initial_payment_mode') === $mode ? 'selected' : '' }}>
                    {{ strtoupper($mode) }}
                </option>
            @endforeach
        </select>

        <label>Notes</label>
        <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Optional notes">

        <br><br>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Unit</th>
                    <th>Current Stock</th>
                    <th>Purchase Qty</th>
                    <th>Unit Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rawMaterials as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ strtoupper($product->unit) }}</td>
                        <td>{{ number_format((float) $product->currentStock(), 2) }} {{ $product->unit }}</td>
                        <td>
                            <input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $product->id }}">
                            <input type="number" name="items[{{ $loop->index }}][quantity]" min="0" step="0.01" value="{{ old("items.$loop->index.quantity") }}" placeholder="Qty in {{ strtoupper($product->unit) }}">
                        </td>
                        <td>
                            <input type="number" name="items[{{ $loop->index }}][unit_price]" min="0" step="0.01" value="{{ old("items.$loop->index.unit_price") }}" placeholder="Price per {{ strtoupper($product->unit) }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <br>

        <button type="submit">Create Purchase Bill</button>
    </form>
@endif

<hr>

<h3>Purchase Bills</h3>

@if($purchases->isEmpty())
    <p>No purchase bills for this month.</p>
@else
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Supplier</th>
                <th>Bill No.</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Due</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchases as $purchase)
                <tr>
                    <td>{{ $purchase->purchase_date->format('Y-m-d') }}</td>
                    <td>{{ $purchase->supplier->name ?? $purchase->supplier_name }}</td>
                    <td>{{ $purchase->bill_number ?: '-' }}</td>
                    <td>Rs.{{ number_format($purchase->total_amount, 2) }}</td>
                    <td>Rs.{{ number_format($purchase->paid_amount, 2) }}</td>
                    <td>Rs.{{ number_format($purchase->due_amount, 2) }}</td>
                    <td>{{ strtoupper($purchase->status) }}</td>
                    <td>
                        <a href="{{ route('purchases.show', ['purchase' => $purchase->id]) }}">View</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection
