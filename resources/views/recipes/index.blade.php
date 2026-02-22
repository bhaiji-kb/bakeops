@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Recipe Master</h2>
    <a href="{{ route('production.index') }}" class="btn btn-outline-primary">Go to Production</a>
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

@php
    $warningPreview = session('import_warning_preview', []);
    $warningCount = (int) session('import_warning_count', 0);
@endphp
@if($warningCount > 0)
    <div class="alert alert-warning py-2">
        <div class="fw-semibold mb-1">Import completed with {{ $warningCount }} warning(s).</div>
        @foreach($warningPreview as $warning)
            <div class="small">{{ $warning }}</div>
        @endforeach
        @if($warningCount > count($warningPreview))
            <div class="small text-muted mt-1">Showing first {{ count($warningPreview) }} warnings.</div>
        @endif
    </div>
@endif

<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Finished Goods</div>
                <div class="h5 mb-0">{{ $finishedGoods->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Configured Recipes</div>
                <div class="h5 mb-0 text-success">{{ $configuredCount ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Pending Configuration</div>
                <div class="h5 mb-0 {{ ($unconfiguredCount ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $unconfiguredCount ?? 0 }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Import Recipes from Excel</div>
    <div class="card-body">
        <form method="POST" action="{{ route('recipes.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-lg-5">
                <label class="form-label mb-1">File Path (local machine)</label>
                <input type="text" name="source_path" class="form-control" value="{{ old('source_path', 'C:\\Users\\Kapil\\OneDrive\\Desktop\\Recipes.xlsx') }}" placeholder="C:\path\Recipes.xlsx">
            </div>
            <div class="col-lg-4">
                <label class="form-label mb-1">Or Upload Workbook</label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls">
            </div>
            <div class="col-lg-3 d-grid">
                <button type="submit" class="btn btn-dark">Import / Refresh Recipes</button>
            </div>
        </form>
        <div class="form-text mt-2">Import will upsert finished goods, raw materials, and recipe lines from worksheet tabs.</div>
    </div>
</div>

<div class="card">
    <div class="card-header">Finished Product Recipes</div>
    <div class="card-body p-0">
        @if($finishedGoods->isEmpty())
            <div class="p-3 text-muted">
                No finished goods found.
                <a href="{{ route('products.index') }}">Create finished products first</a>.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Finished Product</th>
                            <th>Unit</th>
                            <th>Ingredients</th>
                            <th>Estimated Cost</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($finishedGoods as $product)
                            @php
                                $count = (int) ($recipeCounts[$product->id] ?? 0);
                                $cost = (float) ($recipeCosts[$product->id] ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ strtoupper($product->unit) }}</td>
                                <td>{{ $count }}</td>
                                <td>Rs.{{ number_format($cost, 2) }}</td>
                                <td>
                                    <span class="badge {{ $count > 0 ? 'text-bg-success' : 'text-bg-warning' }}">
                                        {{ $count > 0 ? 'Configured' : 'Pending' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('recipes.edit', ['product' => $product->id]) }}" class="btn btn-sm {{ $count > 0 ? 'btn-outline-primary' : 'btn-primary' }}">
                                        {{ $count > 0 ? 'Update' : 'Configure' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
