@extends('layouts.app')

@section('content')
<h2>Suppliers</h2>

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

<h3>Add Supplier</h3>
<form method="POST" action="{{ route('suppliers.store') }}">
    @csrf

    <label>Name</label>
    <input type="text" name="name" value="{{ old('name') }}" required>

    <label>Phone</label>
    <input type="text" name="phone" value="{{ old('phone') }}">

    <label>Address</label>
    <input type="text" name="address" value="{{ old('address') }}">

    <label>
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
        Active
    </label>

    <button type="submit">Add Supplier</button>
</form>

<hr>

<h3>Supplier List</h3>

@if($suppliers->isEmpty())
    <p>No suppliers found.</p>
@else
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($suppliers as $supplier)
                <tr>
                    <td>{{ $supplier->name }}</td>
                    <td>{{ $supplier->phone ?: '-' }}</td>
                    <td>{{ $supplier->address ?: '-' }}</td>
                    <td>{{ $supplier->is_active ? 'ACTIVE' : 'INACTIVE' }}</td>
                    <td>
                        <a href="{{ route('suppliers.edit', ['supplier' => $supplier->id]) }}">Edit</a> |
                        <a href="{{ route('reports.suppliers.ledger.show', ['supplier' => $supplier->id]) }}">Ledger</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection
