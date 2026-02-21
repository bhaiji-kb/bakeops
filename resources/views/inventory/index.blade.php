@extends('layouts.app')

@section('content')
<h2>Inventory</h2>

<p>
    <a href="/pos">Go to POS</a>
</p>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

@if(session('error'))
    <p style="color:red">{{ session('error') }}</p>
@endif

<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>Product</th>
            <th>Type</th>
            <th>Price</th>
            <th>Current Stock</th>
            <th>Add Stock</th>
            <th>Record Wastage</th>
        </tr>
    </thead>
    <tbody>
        @foreach($products as $p)
            <tr>
                <td>{{ $p->name }}</td>
                <td>{{ $p->type }}</td>
                <td>₹{{ $p->price }}</td>
                <td><strong>{{ $p->currentStock() }}</strong></td>

                <td>
                    <form method="POST" action="{{ route('inventory.add') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $p->id }}">
                        <input type="number" name="quantity" step="0.01" min="0.01" placeholder="Qty" required>
                        <input type="text" name="notes" placeholder="Notes (optional)">
                        <button type="submit">Add</button>
                    </form>
                </td>

                <td>
                    <form method="POST" action="{{ route('inventory.waste') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $p->id }}">
                        <input type="number" name="quantity" step="0.01" min="0.01" placeholder="Qty" required>
                        <input type="text" name="notes" placeholder="Reason (optional)">
                        <button type="submit">Waste</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection