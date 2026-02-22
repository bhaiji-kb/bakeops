@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Customers</h2>
    <div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#add-customer-modal">Add Customer</button>
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

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Customer Insights!</span>
        <small class="text-muted">High-Value: Rs.{{ number_format((float) ($highValueThreshold ?? 5000), 2) }}+</small>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <a href="{{ route('customers.index', ['segment' => 'repeat', 'q' => $q]) }}" class="text-decoration-none">
                    <div class="border rounded p-3 {{ $segment === 'repeat' ? 'border-primary bg-light' : '' }}">
                        <div class="small text-muted">Repeat Customers</div>
                        <div class="h5 mb-0">{{ (int) ($segmentCounts['repeat'] ?? 0) }}</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('customers.index', ['segment' => 'high_value', 'q' => $q]) }}" class="text-decoration-none">
                    <div class="border rounded p-3 {{ $segment === 'high_value' ? 'border-primary bg-light' : '' }}">
                        <div class="small text-muted">High-Value Customers</div>
                        <div class="h5 mb-0">{{ (int) ($segmentCounts['high_value'] ?? 0) }}</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('customers.index', ['segment' => 'new', 'q' => $q]) }}" class="text-decoration-none">
                    <div class="border rounded p-3 {{ $segment === 'new' ? 'border-primary bg-light' : '' }}">
                        <div class="small text-muted">New Customers</div>
                        <div class="h5 mb-0">{{ (int) ($segmentCounts['new'] ?? 0) }}</div>
                    </div>
                </a>
            </div>
        </div>
        <div class="mt-2">
            <a href="{{ route('customers.index', ['segment' => 'all', 'q' => $q]) }}" class="btn btn-sm {{ $segment === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
            <a href="{{ route('customers.index', ['segment' => 'repeat', 'q' => $q]) }}" class="btn btn-sm {{ $segment === 'repeat' ? 'btn-primary' : 'btn-outline-primary' }}">Repeat</a>
            <a href="{{ route('customers.index', ['segment' => 'high_value', 'q' => $q]) }}" class="btn btn-sm {{ $segment === 'high_value' ? 'btn-primary' : 'btn-outline-primary' }}">High-Value</a>
            <a href="{{ route('customers.index', ['segment' => 'new', 'q' => $q]) }}" class="btn btn-sm {{ $segment === 'new' ? 'btn-primary' : 'btn-outline-primary' }}">New</a>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Customer List</div>
    <div class="card-body">
        <form method="GET" action="{{ route('customers.index') }}" class="row g-2 mb-3">
            <input type="hidden" name="segment" value="{{ $segment }}">
            <div class="col-md-10">
                <input
                    type="text"
                    name="q"
                    class="form-control"
                    value="{{ $q }}"
                    placeholder="Search by name / mobile / city / preference"
                >
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </div>
        </form>

        @if($customers->isEmpty())
            <div class="alert alert-info mb-0">No customers found.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Identifier</th>
                            <th>Location</th>
                            <th>Preference</th>
                            <th>Status</th>
                            <th>Visits</th>
                            <th>Lifetime Spend</th>
                            <th>Last Sale</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                            <tr>
                                <td>{{ $customer->name }}</td>
                                <td>{{ $customer->mobile ?: '-' }}</td>
                                <td>{{ $customer->identifier ?: '-' }}</td>
                                <td>
                                    <div>{{ $customer->city ?: '-' }}</div>
                                    <div class="small text-muted">{{ $customer->pincode ?: '-' }}</div>
                                </td>
                                <td>{{ $customer->preference ?: ($customer->notes ?: '-') }}</td>
                                <td>{{ $customer->is_active ? 'ACTIVE' : 'INACTIVE' }}</td>
                                <td>{{ $customer->sales_count }}</td>
                                <td>Rs.{{ number_format((float) ($customer->sales_sum_total_amount ?? 0), 2) }}</td>
                                <td>{{ $customer->sales_max_created_at ? \Carbon\Carbon::parse($customer->sales_max_created_at)->format('Y-m-d H:i') : '-' }}</td>
                                <td><a href="{{ route('customers.edit', ['customer' => $customer->id]) }}" class="btn btn-sm btn-outline-dark">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        @endif
    </div>
</div>

<div class="modal fade" id="add-customer-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Customer Manually</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('customers.store') }}" class="modal-body row g-2">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mobile</label>
                    <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}" maxlength="10" pattern="\d{10}" placeholder="10 digits" inputmode="numeric">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Identifier</label>
                    <input type="text" name="identifier" class="form-control" value="{{ old('identifier') }}" placeholder="ALT ID">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3"></div>

                <div class="col-md-4">
                    <label class="form-label">Apartment / House</label>
                    <input type="text" name="address_line1" class="form-control" value="{{ old('address_line1') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Road</label>
                    <input type="text" name="road" class="form-control" value="{{ old('road') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sector</label>
                    <input type="text" name="sector" class="form-control" value="{{ old('sector') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="{{ old('pincode') }}" maxlength="6" pattern="\d{6}" inputmode="numeric">
                </div>

                <div class="col-12">
                    <label class="form-label">Preference / Notes</label>
                    <input type="text" name="preference" class="form-control" value="{{ old('preference', old('notes')) }}" placeholder="Eggless, less sugar, favorite flavors, birthdays, etc.">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    if (!window.bootstrap) return;
    const shouldOpenModal = @json($errors->any());
    if (!shouldOpenModal) return;

    const modalEl = document.getElementById('add-customer-modal');
    if (!modalEl) return;

    const modal = new window.bootstrap.Modal(modalEl);
    modal.show();
})();
</script>
@endsection
