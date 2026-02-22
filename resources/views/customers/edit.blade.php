@extends('layouts.app')

@section('content')
<h2>Edit Customer</h2>

<p>
    <a href="{{ route('customers.index') }}">Back to Customers</a>
</p>

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('customers.update', ['customer' => $customer->id]) }}" class="row g-2">
            @csrf
            @method('PUT')

            <div class="col-md-4">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mobile</label>
                <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $customer->mobile) }}" maxlength="10" pattern="\d{10}" placeholder="10 digits" inputmode="numeric">
            </div>
            <div class="col-md-2">
                <label class="form-label">Identifier</label>
                <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $customer->identifier) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-select">
                    <option value="1" {{ (string) old('is_active', $customer->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ (string) old('is_active', $customer->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Apartment / House</label>
                <input type="text" name="address_line1" class="form-control" value="{{ old('address_line1', $customer->address_line1) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Road</label>
                <input type="text" name="road" class="form-control" value="{{ old('road', $customer->road) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sector</label>
                <input type="text" name="sector" class="form-control" value="{{ old('sector', $customer->sector) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="{{ old('city', $customer->city) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Pincode</label>
                <input type="text" name="pincode" class="form-control" value="{{ old('pincode', $customer->pincode) }}" maxlength="6" pattern="\d{6}" inputmode="numeric">
            </div>

            <div class="col-md-4">
                <label class="form-label">Preference / Notes</label>
                <input type="text" name="preference" class="form-control" value="{{ old('preference', $customer->preference ?: $customer->notes) }}">
            </div>
            <div class="col-12 d-grid">
                <button class="btn btn-primary" type="submit">Update Customer</button>
            </div>
        </form>
    </div>
</div>
@endsection
