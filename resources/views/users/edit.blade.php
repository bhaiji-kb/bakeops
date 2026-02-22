@extends('layouts.app')

@section('content')
<h2>Edit User</h2>

<p>
    <a href="{{ route('users.index') }}">Back to Users</a>
</p>

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('users.update', ['user' => $user->id]) }}">
    @csrf
    @method('PUT')

    <label>Name</label>
    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>

    <label>Email</label>
    <input type="email" name="email" value="{{ old('email', $user->email) }}" required>

    <label>Role</label>
    <select name="role" required>
        @foreach($roles as $role)
            <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>
                {{ strtoupper($role) }}
            </option>
        @endforeach
    </select>

    <label>
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
        Active
    </label>

    <button type="submit">Update User</button>
</form>

<hr>

<h3>Reset Password</h3>
<form method="POST" action="{{ route('users.reset_password', ['user' => $user->id]) }}">
    @csrf

    <label>New Password</label>
    <input type="password" name="password" minlength="6" required>

    <label>Confirm Password</label>
    <input type="password" name="password_confirmation" minlength="6" required>

    <button type="submit" onclick="return confirm('Reset password for this user?')">Reset Password</button>
</form>
@endsection
