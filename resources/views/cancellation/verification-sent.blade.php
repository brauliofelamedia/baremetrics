@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Verificación de Cancelación') }}</div>

                <div class="card-body text-center">
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <i class="fas fa-envelope fa-4x text-primary mb-3"></i>
                        <h4>Correo de verificación enviado</h4>
                        <p>
                            Hemos enviado un enlace de verificación a su correo electrónico: <strong>{{ $email }}</strong>.
                        </p>
                        <p>
                            Por favor, revise su bandeja de entrada (y la carpeta de spam si es necesario) y haga clic en el enlace 
                            para continuar con el proceso de cancelación.
                        </p>
                        <p class="text-muted">
                            El enlace expirará en <strong>15 minutos</strong> por motivos de seguridad.
                        </p>
                    </div>

                    <div class="mt-4">
                        <p>¿No ha recibido el correo?</p>
                        <a href="{{ route('cancellation.send.verification', ['email' => $email]) }}" class="btn btn-outline-primary">
                            Reenviar correo de verificación
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
