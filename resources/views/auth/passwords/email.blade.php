@extends('layouts.auth')

@section('content')
<div class="auth-card">
     <div class="auth-logo">
        <img src="{{ asset('assets/img/logo.png') }}" alt="" class="img-fluid logo">
    </div>
    <p class="auth-subtitle">Recupera tu contraseña</p>

    @if (session('status'))
        <div class="alert-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
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

        <button type="submit" class="btn-auth">Enviar enlace de recuperación</button>

        <div style="text-align: center;">
            <a class="auth-link" href="{{ route('login') }}">
                Volver al inicio de sesión
            </a>
        </div>
    </form>
</div>
@endsection
