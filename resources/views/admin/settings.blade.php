@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Business Settings</h2>
    <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-secondary">Order Management</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!($settingsReady ?? true))
    <div class="alert alert-warning">
        Settings storage is not ready yet. Run <code>php artisan migrate</code> and refresh this page.
    </div>
@endif

<div class="card">
    <div class="card-header">GST and Invoice Profile</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="row g-3" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="col-md-6">
                <label class="form-label">Business Name</label>
                <input
                    type="text"
                    name="business_name"
                    value="{{ old('business_name', $settings['business_name'] ?? '') }}"
                    class="form-control"
                    placeholder="BakeOps Bakery"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label">Business Phone</label>
                <input
                    type="text"
                    name="business_phone"
                    value="{{ old('business_phone', $settings['business_phone'] ?? '') }}"
                    class="form-control"
                    placeholder="+91-XXXXXXXXXX"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label">Business Logo</label>
                <input
                    type="file"
                    name="business_logo"
                    class="form-control"
                    accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                >
                <div class="small text-muted mt-1">Recommended: square logo, up to 2 MB.</div>
                @if(!empty($settings['business_logo_url']))
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <img src="{{ $settings['business_logo_url'] }}" alt="Business Logo" style="width:52px;height:52px;object-fit:cover;border:1px solid #d6deee;border-radius:8px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_business_logo" value="1" id="remove_business_logo">
                            <label class="form-check-label" for="remove_business_logo">Remove logo</label>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-12">
                <label class="form-label">Business Address</label>
                <textarea
                    name="business_address"
                    rows="2"
                    class="form-control"
                    placeholder="Address printed on invoice"
                >{{ old('business_address', $settings['business_address'] ?? '') }}</textarea>
            </div>

            <div class="col-md-4">
                <div class="form-check mt-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="gst_enabled"
                        value="1"
                        id="gst_enabled"
                        {{ old('gst_enabled', (int) ($settings['gst_enabled'] ?? false)) ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="gst_enabled">Enable GST in POS and Invoice</label>
                </div>
                <div class="small text-muted mt-1">
                    Keep this OFF until GST registration is active.
                </div>
            </div>

            <div class="col-md-8">
                <label class="form-label">GSTIN</label>
                <input
                    type="text"
                    name="gstin"
                    value="{{ old('gstin', $settings['gstin'] ?? '') }}"
                    class="form-control"
                    placeholder="22AAAAA0000A1Z5"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label">KOT Mode</label>
                <select name="kot_mode" class="form-select">
                    @php($selectedKotMode = old('kot_mode', $settings['kot_mode'] ?? 'always'))
                    <option value="off" {{ $selectedKotMode === 'off' ? 'selected' : '' }}>Off (No KOT)</option>
                    <option value="conditional" {{ $selectedKotMode === 'conditional' ? 'selected' : '' }}>Conditional (Only when moved to Kitchen)</option>
                    <option value="always" {{ $selectedKotMode === 'always' ? 'selected' : '' }}>Always</option>
                </select>
                <div class="small text-muted mt-1">
                    Controls if kitchen tickets are generated for orders.
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
