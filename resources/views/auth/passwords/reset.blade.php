@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-logo">
        <img src="{{ asset('assets/img/logo.png') }}" alt="" class="img-fluid logo">
    </div>
    <p class="auth-subtitle">Establece nueva contraseña</p>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label for="email" class="form-label">Correo Electrónico</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" 
                       name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus
                       placeholder="Ingresa tu email">
            </div>
            @error('email')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Nueva Contraseña</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" 
                       name="password" required autocomplete="new-password"
                       placeholder="Ingresa tu nueva contraseña">
            </div>
            @error('password')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password-confirm" class="form-label">Confirmar Contraseña</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password-confirm" type="password" class="form-control" 
                       name="password_confirmation" required autocomplete="new-password"
                       placeholder="Confirma tu nueva contraseña">
            </div>
        </div>

        <button type="submit" class="btn-auth">
            <i class="bi bi-shield-check"></i>
            Restablecer Contraseña
        </button>

        <div style="text-align: center;">
            <a class="auth-link" href="{{ route('login') }}">
                Volver al inicio de sesión
            </a>
        </div>
    </form>
</div>
@endsection
