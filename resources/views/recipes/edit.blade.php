@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Configure Recipe</h2>
    <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary">Back to Recipe Master</a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-md-6"><span class="small text-muted">Finished Product</span><div class="fw-semibold">{{ $product->name }}</div></div>
            <div class="col-md-3"><span class="small text-muted">Type</span><div>{{ strtoupper(str_replace('_', ' ', $product->type)) }}</div></div>
            <div class="col-md-3"><span class="small text-muted">Unit</span><div>{{ strtoupper($product->unit) }}</div></div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if($rawMaterials->isEmpty())
    <div class="alert alert-warning mb-0">
        No active raw materials found.
        <a href="{{ route('products.index') }}">Add raw materials in Product Master</a>.
    </div>
@else
    <form method="POST" action="{{ route('recipes.update', ['product' => $product->id]) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Ingredient BOM</span>
                <span class="small text-muted">Quantity required for 1 {{ strtoupper($product->unit) }} of {{ $product->name }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Unit</th>
                                <th>Current Price</th>
                                <th style="max-width: 200px;">Required Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rawMaterials as $material)
                                <tr>
                                    <td>{{ $material->name }}</td>
                                    <td>{{ strtoupper($material->unit) }}</td>
                                    <td>Rs.{{ number_format($material->price, 2) }}</td>
                                    <td>
                                        <input type="hidden" name="rows[{{ $loop->index }}][ingredient_product_id]" value="{{ $material->id }}">
                                        <input
                                            type="number"
                                            name="rows[{{ $loop->index }}][quantity]"
                                            min="0"
                                            step="0.01"
                                            value="{{ old("rows.$loop->index.quantity", $existing[$material->id] ?? '') }}"
                                            class="form-control form-control-sm"
                                            placeholder="0.00"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted">Leave quantity blank/0 to exclude ingredient.</span>
                <button type="submit" class="btn btn-primary">Save Recipe</button>
            </div>
        </div>
    </form>
@endif
@endsection
