@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Production</h2>
    <a href="{{ route('inventory.index') }}" class="btn btn-outline-primary">Go to Stock</a>
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

<div class="card mb-3">
    <div class="card-header">Batch Preview</div>
    <div class="card-body">
        @if($finishedGoods->isEmpty())
            <div class="text-muted">
                No active finished goods found.
                <a href="{{ route('products.index') }}">Create finished products first</a>.
            </div>
        @else
            <form method="GET" action="{{ route('production.index') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label mb-1">Finished Product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Select</option>
                        @foreach($finishedGoods as $product)
                            <option value="{{ $product->id }}" {{ (string) $selectedProductId === (string) $product->id ? 'selected' : '' }}>
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Quantity to Produce</label>
                    <input type="number" name="quantity" min="0.01" step="0.01" value="{{ $selectedQuantity > 0 ? $selectedQuantity : '' }}" class="form-control" required>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-dark">Preview Requirement</button>
                </div>
            </form>
        @endif
    </div>
</div>

@if($selectedProduct && $selectedQuantity > 0)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Ingredient Requirement</span>
            <span class="small text-muted">{{ $selectedProduct->name }} | Batch {{ number_format($selectedQuantity, 2) }} {{ $selectedProduct->unit }}</span>
        </div>
        <div class="card-body p-0">
            @if($previewError)
                <div class="p-3 text-danger">{{ $previewError }}</div>
            @elseif($previewRows->isEmpty())
                <div class="p-3 text-muted">No recipe ingredients found.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Per Unit</th>
                                <th>Required</th>
                                <th>Available</th>
                                <th>Unit Cost</th>
                                <th>Required Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($previewRows as $row)
                                <tr>
                                    <td>{{ $row['ingredient_name'] }}</td>
                                    <td>{{ number_format($row['quantity_per_unit'], 2) }} {{ $row['unit'] }}</td>
                                    <td>{{ number_format($row['required'], 2) }} {{ $row['unit'] }}</td>
                                    <td>{{ number_format($row['available'], 2) }} {{ $row['unit'] }}</td>
                                    <td>Rs.{{ number_format($row['ingredient_unit_cost'], 2) }}</td>
                                    <td>Rs.{{ number_format($row['required_cost'], 2) }}</td>
                                    <td>
                                        <span class="badge {{ $row['is_sufficient'] ? 'text-bg-success' : 'text-bg-danger' }}">
                                            {{ $row['is_sufficient'] ? 'OK' : 'Insufficient' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if(!$previewError && !$previewRows->isEmpty())
            <div class="card-footer">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <div class="small text-muted">Total Ingredient Cost</div>
                        <div class="h6 mb-0">Rs.{{ number_format($previewTotalCost, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted">Estimated Unit Production Cost</div>
                        <div class="h6 mb-0">Rs.{{ number_format($previewUnitCost, 2) }}</div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <form method="POST" action="{{ route('production.store') }}" class="row g-2">
                            @csrf
                            <input type="hidden" name="finished_product_id" value="{{ $selectedProduct->id }}">
                            <input type="hidden" name="quantity_produced" value="{{ $selectedQuantity }}">
                            <div class="col-md-6">
                                <input type="datetime-local" name="produced_at" value="{{ old('produced_at', now()->format('Y-m-d\TH:i')) }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" class="form-control form-control-sm" placeholder="Batch notes">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success w-100">Create Production Batch</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif

<div class="card">
    <div class="card-header">Recent Batches</div>
    <div class="card-body p-0">
        @if($recentBatches->isEmpty())
            <div class="p-3 text-muted">No production batches yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Ingredient Cost</th>
                            <th>Unit Cost</th>
                            <th>Ingredients</th>
                            <th>By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentBatches as $batch)
                            <tr>
                                <td>{{ $batch->produced_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $batch->finishedProduct->name ?? '-' }}</td>
                                <td>{{ number_format($batch->quantity_produced, 2) }} {{ $batch->finishedProduct->unit ?? '' }}</td>
                                <td>Rs.{{ number_format($batch->total_ingredient_cost, 2) }}</td>
                                <td>Rs.{{ number_format($batch->unit_production_cost, 2) }}</td>
                                <td>{{ $batch->items->count() }}</td>
                                <td>{{ $batch->producer->name ?? 'System' }}</td>
                                <td>{{ $batch->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
