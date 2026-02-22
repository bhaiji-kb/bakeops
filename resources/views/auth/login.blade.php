@extends('layouts.app')

@section('content')
<h2>Login</h2>

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('login.post') }}">
    @csrf

    <label>Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required autofocus>

    <label>Password</label>
    <input type="password" name="password" required>

    <button type="submit">Login</button>
</form>

<p style="margin-top: 12px;">
    Demo users (password: <strong>password</strong>):<br>
    <strong>test@example.com</strong> (OWNER)<br>
    <strong>manager@example.com</strong> (MANAGER)<br>
    <strong>purchase@example.com</strong> (PURCHASE)<br>
    <strong>cashier@example.com</strong> (CASHIER)
</p>
@endsection
