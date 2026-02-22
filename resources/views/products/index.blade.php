@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Product Master</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-product-modal">
        Add Product
    </button>
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

<div class="row g-2 mb-3">
    <div class="col-md-2 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Total</div>
                <div class="h5 mb-0">{{ $stats['total'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Finished Goods</div>
                <div class="h5 mb-0">{{ $stats['finished_goods'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Raw Materials</div>
                <div class="h5 mb-0">{{ $stats['raw_materials'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Active</div>
                <div class="h5 mb-0 text-success">{{ $stats['active'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Low Stock Alerts</div>
                <div class="h5 mb-0 {{ ($stats['low_stock'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $stats['low_stock'] ?? 0 }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Products</span>
                <input type="text" class="form-control form-control-sm" id="product-search" placeholder="Search by code / name" style="max-width: 220px;">
            </div>
            <div class="card-body p-0">
                @if($products->isEmpty())
                    <div class="p-3 text-muted">No products found.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Stock</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="products-table-body">
                                @foreach($products as $product)
                                    @php
                                        $stock = (float) $product->currentStock();
                                        $isLow = $stock <= (float) $product->reorder_level;
                                    @endphp
                                    <tr data-key="{{ strtolower($product->code . ' ' . $product->name . ' ' . $product->type) }}">
                                        <td>
                                            @php
                                                $codeDigits = preg_replace('/\D+/', '', (string) $product->code);
                                                $shortCode = $codeDigits !== '' ? str_pad((string) ((int) $codeDigits), 3, '0', STR_PAD_LEFT) : null;
                                            @endphp
                                            <div class="fw-semibold">{{ $product->code }}</div>
                                            <div class="small text-muted">Short: {{ $shortCode ?: '-' }}</div>
                                        </td>
                                        <td>{{ $product->name }}</td>
                                        <td>
                                            <span class="badge {{ $product->type === 'finished_good' ? 'text-bg-primary' : 'text-bg-dark' }}">
                                                {{ $product->type === 'finished_good' ? 'FG' : 'RM' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold {{ $isLow ? 'text-danger' : '' }}">{{ number_format($stock, 2) }}</div>
                                            @if($isLow)
                                                <div class="small text-danger">Low</div>
                                            @endif
                                        </td>
                                        <td>{{ strtoupper($product->unit) }}</td>
                                        <td>
                                            <div>Rs.{{ number_format($product->price, 2) }}</div>
                                            <div class="small text-muted">Cost: Rs.{{ number_format($product->unit_cost, 2) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('products.edit', ['product' => $product->id]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="add-product-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('products.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label mb-1">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                    </div>
                    <div class="form-text mb-2">Code will be auto-generated by type (FG / RM) without duplicates.</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label mb-1">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="">Select</option>
                                @foreach($types as $type)
                                    <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>
                                        {{ strtoupper(str_replace('_', ' ', $type)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1">Unit</label>
                            <select name="unit" class="form-select" required>
                                @foreach($units as $unit)
                                    <option value="{{ $unit }}" {{ old('unit', 'pcs') === $unit ? 'selected' : '' }}>{{ strtoupper($unit) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1">Unit Price / Cost</label>
                        <input type="number" name="price" min="0" step="0.01" value="{{ old('price', '0') }}" class="form-control" required>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label mb-1">Reorder Level</label>
                            <input type="number" name="reorder_level" min="0" step="0.01" value="{{ old('reorder_level', '0') }}" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1">Opening Stock</label>
                            <input type="number" name="opening_stock" min="0" step="0.01" value="{{ old('opening_stock', '0') }}" class="form-control">
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const searchInput = document.getElementById('product-search');
    const rows = Array.from(document.querySelectorAll('#products-table-body tr[data-key]'));
    if (!searchInput || rows.length === 0) return;

    searchInput.addEventListener('input', () => {
        const key = (searchInput.value || '').trim().toLowerCase();
        rows.forEach((row) => {
            const haystack = row.getAttribute('data-key') || '';
            row.style.display = key === '' || haystack.includes(key) ? '' : 'none';
        });
    });

    const shouldOpenModal = @json($errors->any() && (old('name') !== null || old('type') !== null || old('unit') !== null || old('price') !== null));
    if (shouldOpenModal && window.bootstrap) {
        const modalEl = document.getElementById('add-product-modal');
        if (modalEl) {
            const modal = new window.bootstrap.Modal(modalEl);
            modal.show();
        }
    }
})();
</script>
@endsection
