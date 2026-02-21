@extends('layouts.app')

@section('content')
<h2>POS</h2>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

<form method="POST" action="{{ route('pos.checkout') }}">
    @csrf

    @foreach($products as $product)
        <div>
            <strong>{{ $product->name }}</strong>
            (₹{{ $product->price }})
            <input type="number" name="items[{{ $loop->index }}][quantity]" min="0" step="1">
            <input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $product->id }}">
        </div>
    @endforeach

    <br>

    <select name="payment_mode" required>
        <option value="">Payment Mode</option>
        <option value="cash">Cash</option>
        <option value="upi">UPI</option>
        <option value="card">Card</option>
    </select>

    <br><br>

    <button type="submit">Checkout</button>
</form>
@endsection

