@extends('layouts.app')

@section('content')
<h2>Edit Supplier</h2>

<p>
    <a href="{{ route('suppliers.index') }}">Back to Suppliers</a>
</p>

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('suppliers.update', ['supplier' => $supplier->id]) }}">
    @csrf
    @method('PUT')

    <label>Name</label>
    <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required>

    <label>Phone</label>
    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}">

    <label>Address</label>
    <input type="text" name="address" value="{{ old('address', $supplier->address) }}">

    <label>
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }}>
        Active
    </label>

    <button type="submit">Update Supplier</button>
</form>
@endsection
