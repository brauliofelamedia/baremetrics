@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-logo">
        <img src="{{ asset('assets/img/logo.png') }}" alt="" class="img-fluid logo">
    </div>
    <p class="auth-subtitle">Ingresa a tu cuenta</p>
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">Correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" 
                       name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                       placeholder="Ingresa tu email">
            </div>
            @error('email')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Contraseña</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" 
                       name="password" required autocomplete="current-password"
                       placeholder="Ingresa tu contraseña">
            </div>
            @error('password')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" 
                   {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">
                Recordarme
            </label>
        </div>

        <button type="submit" class="btn-auth">
            <i class="bi bi-box-arrow-in-right"></i>
            Iniciar Sesión
        </button>

        @if (Route::has('password.request'))
            <div style="text-align: center;">
                <a class="auth-link" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
        @endif
    </form>
</div>
@endsection
