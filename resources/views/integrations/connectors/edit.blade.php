@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Edit Connector #{{ $connector->id }}</h2>
    <a href="{{ route('integrations.connectors.index') }}">Back to Connectors</a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('integrations.connectors.update', ['connector' => $connector->id]) }}" class="row g-2">
            @csrf
            @method('PUT')

            <div class="col-md-2">
                <label class="form-label">Code</label>
                <input type="text" name="code" value="{{ old('code', $connector->code) }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" value="{{ old('name', $connector->name) }}" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Driver</label>
                <input list="driver-options" name="driver" value="{{ old('driver', $connector->driver) }}" class="form-control" required>
                <datalist id="driver-options">
                    @foreach($drivers as $driver)
                        <option value="{{ $driver }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="col-md-5">
                <label class="form-label">API Base URL</label>
                <input type="url" name="api_base_url" value="{{ old('api_base_url', $connector->api_base_url) }}" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">API Key (leave blank to keep)</label>
                <input type="text" name="api_key" value="{{ old('api_key') }}" class="form-control">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="clear_api_key" value="1" id="clear_api_key">
                    <label class="form-check-label small" for="clear_api_key">Clear API key</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">API Secret (leave blank to keep)</label>
                <input type="text" name="api_secret" value="{{ old('api_secret') }}" class="form-control">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="clear_api_secret" value="1" id="clear_api_secret">
                    <label class="form-check-label small" for="clear_api_secret">Clear API secret</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Webhook Secret (leave blank to keep)</label>
                <input type="text" name="webhook_secret" value="{{ old('webhook_secret') }}" class="form-control">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="clear_webhook_secret" value="1" id="clear_webhook_secret">
                    <label class="form-check-label small" for="clear_webhook_secret">Clear webhook secret</label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Settings JSON</label>
                <textarea name="settings_json" rows="5" class="form-control">{{ old('settings_json', json_encode($connector->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active_edit" {{ old('is_active', $connector->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active_edit">Active connector</label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Update Connector</button>
            </div>
        </form>
    </div>
</div>
@endsection
