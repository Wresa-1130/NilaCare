@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<div class="login-wrap">
    <form method="POST" action="{{ route('login') }}" class="card login-card">
        @csrf
        <div class="brand brand-lg">
            <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12c3-5 9-6 13-3l4-3v12l-4-3c-4 3-10 2-13-3z" fill="currentColor"/></svg>
            NilaCare
        </div>
        <p class="muted">Monitoring & kendali kualitas air kolam nila</p>
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <label class="check"><input type="checkbox" name="remember"> Ingat saya</label>
        @error('email')<div class="flash err">{{ $message }}</div>@enderror
        <button class="btn btn-primary btn-block">Masuk</button>
    </form>
</div>
@endsection
