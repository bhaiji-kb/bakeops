@extends('layouts.app')

@section('content')
<h2>User Management</h2>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<h3>Create User</h3>
<form method="POST" action="{{ route('users.store') }}">
    @csrf

    <label>Name</label>
    <input type="text" name="name" value="{{ old('name') }}" required>

    <label>Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required>

    <label>Role</label>
    <select name="role" required>
        <option value="">Select role</option>
        @foreach($roles as $role)
            <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                {{ strtoupper($role) }}
            </option>
        @endforeach
    </select>

    <label>Password</label>
    <input type="password" name="password" required>

    <label>
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
        Active
    </label>

    <button type="submit">Create User</button>
</form>

<hr>

<h3>Users</h3>
<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ strtoupper($user->role) }}</td>
                <td>{{ $user->is_active ? 'ACTIVE' : 'INACTIVE' }}</td>
                <td>
                    <a href="{{ route('users.edit', ['user' => $user->id]) }}">Edit</a>

                    <details style="margin-top: 6px;">
                        <summary>Reset Password</summary>
                        <form method="POST" action="{{ route('users.reset_password', ['user' => $user->id]) }}">
                            @csrf

                            <input type="password" name="password" placeholder="New password" minlength="6" required>
                            <input type="password" name="password_confirmation" placeholder="Confirm password" minlength="6" required>
                            <button type="submit" onclick="return confirm('Reset password for {{ $user->email }}?')">Reset</button>
                        </form>
                    </details>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
