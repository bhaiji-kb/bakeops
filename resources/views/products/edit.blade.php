@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Edit Product</h2>
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Back to Product Master</a>
</div>

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Product Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('products.update', ['product' => $product->id]) }}" class="row g-2">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label class="form-label mb-1">Code</label>
                        <input type="text" name="code" value="{{ old('code', $product->code) }}" class="form-control" required maxlength="5" placeholder="FG001 / RM001">
                        <div class="form-text">`FG` for finished goods, `RM` for raw materials.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Legacy Code</label>
                        <input type="text" class="form-control" value="{{ $product->legacy_code ?: '-' }}" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Name</label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Type</label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $type)
                                <option value="{{ $type }}" {{ old('type', $product->type) === $type ? 'selected' : '' }}>
                                    {{ strtoupper(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Unit</label>
                        <select name="unit" class="form-select" required>
                            @foreach($units as $unit)
                                <option value="{{ $unit }}" {{ old('unit', $product->unit) === $unit ? 'selected' : '' }}>
                                    {{ strtoupper($unit) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Reorder Level</label>
                        <input type="number" name="reorder_level" min="0" step="0.01" value="{{ old('reorder_level', $product->reorder_level) }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Unit Price / Cost</label>
                        <input type="number" name="price" min="0" step="0.01" value="{{ old('price', $product->price) }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Current Unit Cost</label>
                        <input type="text" class="form-control" value="Rs.{{ number_format($product->unit_cost, 2) }}" readonly>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-12 d-grid d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Quick Info</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Current Stock</span>
                    <strong>{{ number_format($product->currentStock(), 2) }} {{ $product->unit }}</strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Created</span>
                    <strong>{{ $product->created_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Last Updated</span>
                    <strong>{{ $product->updated_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
