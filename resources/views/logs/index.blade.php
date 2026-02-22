@extends('layouts.app')

@section('content')
<h2>Activity Logs</h2>

<form method="GET" action="{{ route('logs.index') }}" style="margin-bottom: 16px;">
    <label>Module</label>
    <select name="module">
        <option value="">All</option>
        @foreach($modules as $module)
            <option value="{{ $module }}" {{ request('module') === $module ? 'selected' : '' }}>
                {{ strtoupper($module) }}
            </option>
        @endforeach
    </select>

    <label>Action</label>
    <select name="action">
        <option value="">All</option>
        @foreach($actions as $action)
            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                {{ strtoupper($action) }}
            </option>
        @endforeach
    </select>

    <label>Date</label>
    <input type="date" name="date" value="{{ request('date') }}">

    <button type="submit">Filter</button>
</form>

@if($logs->isEmpty())
    <p>No logs found.</p>
@else
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>When</th>
                <th>User</th>
                <th>Module</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Description</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user->email ?? 'System' }}</td>
                    <td>{{ strtoupper($log->module) }}</td>
                    <td>{{ strtoupper($log->action) }}</td>
                    <td>
                        {{ $log->entity_type ?: '-' }}
                        @if($log->entity_id)
                            #{{ $log->entity_id }}
                        @endif
                    </td>
                    <td>{{ $log->description ?: '-' }}</td>
                    <td>{{ $log->ip_address ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection
