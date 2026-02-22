@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Integration Connectors</h2>
    <div class="text-muted small">Configure API keys, webhook secrets, and connector drivers.</div>
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

<div class="card mb-4">
    <div class="card-header">Add Connector</div>
    <div class="card-body">
        <form method="POST" action="{{ route('integrations.connectors.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Code</label>
                <input type="text" name="code" value="{{ old('code') }}" class="form-control" placeholder="swiggy" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="Swiggy" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Driver</label>
                <input list="driver-options" name="driver" value="{{ old('driver', 'generic') }}" class="form-control" required>
                <datalist id="driver-options">
                    @foreach($drivers as $driver)
                        <option value="{{ $driver }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="col-md-5">
                <label class="form-label">API Base URL</label>
                <input type="url" name="api_base_url" value="{{ old('api_base_url') }}" class="form-control" placeholder="https://api.example.com">
            </div>

            <div class="col-md-4">
                <label class="form-label">API Key</label>
                <input type="text" name="api_key" value="{{ old('api_key') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">API Secret</label>
                <input type="text" name="api_secret" value="{{ old('api_secret') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Webhook Secret</label>
                <input type="text" name="webhook_secret" value="{{ old('webhook_secret') }}" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label">Settings JSON</label>
                <textarea name="settings_json" rows="4" class="form-control" placeholder='{"outlet_id":"123","auto_accept":false}'>{{ old('settings_json') }}</textarea>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active_new" {{ old('is_active', 1) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active_new">Active connector</label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save Connector</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Configured Connectors</div>
    <div class="card-body p-0">
        @if($connectors->isEmpty())
            <div class="p-3 text-muted">No connectors configured yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Driver</th>
                            <th>Base URL</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($connectors as $connector)
                            <tr>
                                <td>{{ $connector->id }}</td>
                                <td>{{ $connector->code }}</td>
                                <td>{{ $connector->name }}</td>
                                <td>{{ $connector->driver }}</td>
                                <td>{{ $connector->api_base_url ?: '-' }}</td>
                                <td>
                                    @if($connector->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('integrations.connectors.edit', ['connector' => $connector->id]) }}" class="btn btn-sm btn-outline-dark">Edit</a>
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
