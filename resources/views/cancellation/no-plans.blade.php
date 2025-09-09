@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Estado de suscripción') }}</div>

                <div class="card-body text-center">
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (session('message'))
                        <div class="alert alert-info" role="alert">
                            {{ session('message') }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>No hay planes activos para cancelar</h4>
                        <p>
                            La cuenta asociada al correo electrónico <strong>{{ $email }}</strong> no tiene planes de Stripe activos que requieran cancelación.
                        </p>
                        <p>
                            Si cree que esto es un error o necesita asistencia adicional, por favor contacte con nuestro equipo de soporte.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
