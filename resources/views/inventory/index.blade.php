@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Stock</h2>
    <a href="{{ route('products.index') }}" class="btn btn-outline-primary">Manage Products</a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="row g-2 mb-3">
    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Active Products</div>
                <div class="h5 mb-0">{{ $summary['active_products'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Low Stock</div>
                <div class="h5 mb-0 {{ ($summary['low_stock'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $summary['low_stock'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Stock Value (book)</div>
                <div class="h5 mb-0">Rs.{{ number_format((float) ($summary['total_stock_value'] ?? 0), 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Recent Movements</div>
                <div class="h5 mb-0">{{ $summary['recent_movements'] ?? 0 }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Current Stock & Adjustments</div>
    <div class="card-body p-0">
        @if($products->isEmpty())
            <div class="p-3 text-muted">No active products found.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Unit</th>
                            <th>Reorder</th>
                            <th>Price</th>
                            <th>Current Stock</th>
                            <th style="min-width: 260px;">Add Stock</th>
                            <th style="min-width: 260px;">Record Wastage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                            @php($stock = (float) $p->currentStock())
                            @php($isLow = $stock <= (float) $p->reorder_level)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $p->name }}</div>
                                    <div class="small text-muted">{{ $p->code }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $p->type === 'finished_good' ? 'text-bg-primary' : 'text-bg-dark' }}">
                                        {{ $p->type === 'finished_good' ? 'FG' : 'RM' }}
                                    </span>
                                </td>
                                <td>{{ strtoupper($p->unit) }}</td>
                                <td>{{ number_format($p->reorder_level, 2) }} {{ $p->unit }}</td>
                                <td>Rs.{{ number_format($p->price, 2) }}</td>
                                <td>
                                    <div class="fw-semibold {{ $isLow ? 'text-danger' : '' }}">{{ number_format($stock, 2) }} {{ $p->unit }}</div>
                                    @if($isLow)
                                        <div class="small text-danger">Low</div>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('inventory.add') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $p->id }}">
                                        <input type="number" name="quantity" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="Qty" required>
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes">
                                        <button type="submit" class="btn btn-sm btn-success">Add</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('inventory.waste') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $p->id }}">
                                        <input type="number" name="quantity" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="Qty" required>
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Reason">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Waste</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">Recent Inventory Movements</div>
    <div class="card-body p-0">
        @if(($recentTransactions ?? collect())->isEmpty())
            <div class="p-3 text-muted">No inventory transactions yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Ref</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTransactions as $tx)
                            @php
                                $txType = strtoupper((string) $tx->transaction_type);
                                $badgeClass = $txType === 'IN' ? 'text-bg-success' : ($txType === 'OUT' ? 'text-bg-primary' : 'text-bg-danger');
                            @endphp
                            <tr>
                                <td>{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $tx->product?->name ?: '-' }}</td>
                                <td><span class="badge {{ $badgeClass }}">{{ $txType }}</span></td>
                                <td>{{ number_format((float) $tx->quantity, 2) }} {{ $tx->product?->unit ?? '' }}</td>
                                <td>
                                    @if($tx->reference_type && $tx->reference_id)
                                        {{ strtoupper((string) $tx->reference_type) }} #{{ $tx->reference_id }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $tx->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
