@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-logo">
        <img src="{{ asset('assets/img/logo.png') }}" alt="" class="img-fluid logo">
    </div>
    @if (session('error'))
        <div class="alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div style="text-align: center; margin: 20px 0;">
        <div style="background-color: #f0f4ff; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
            <i class="bi bi-envelope-check" style="font-size: 36px; color: #292272;"></i>
        </div>
        <h4 style="margin-bottom: 15px; color: #292272;">Correo de verificación enviado</h4>
        <p style="color: #4b5563; font-size: 15px;">
            Hemos enviado un enlace de verificación a: <br>
            <strong style="color: #292272;">{{ $email }}</strong>
        </p>
        <p style="color: #4b5563; font-size: 15px;">
            Por favor, revise su bandeja de entrada (y la carpeta de spam si es necesario) y haga clic en el enlace 
            para continuar con el proceso de cancelación.
        </p>
        <div style="background-color: #f0f4ff; border-left: 4px solid #292272; padding: 12px; margin: 20px 0; text-align: left; border-radius: 0 8px 8px 0;">
            <p style="margin: 0; color: #4b5563; font-size: 14px;">
                <strong>Importante:</strong> El enlace expirará en <strong>15 minutos</strong> por motivos de seguridad.
            </p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <p style="color: #6b7280; font-size: 14px;">¿No ha recibido el correo?</p>
        <a href="{{ route('cancellation.send.verification', ['email' => $email]) }}" class="btn-auth">
            <i class="bi bi-arrow-repeat"></i>
            Reenviar verificación
        </a>
        
        <div style="margin-top: 20px;">
            <a href="{{ route('cancellation.form') }}" style="color: #667eea; text-decoration: none; font-size: 14px;">
                <i class="bi bi-arrow-left"></i> Volver al formulario
            </a>
        </div>
    </div>
</div>
@endsection
