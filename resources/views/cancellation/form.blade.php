@extends('layouts.auth')

@section('title', 'Cancelación de suscripción')

@section('content')
<div class="auth-card">
    <div class="auth-logo">
        <img src="{{ asset('assets/img/logo.png') }}" alt="" class="img-fluid logo">
    </div>
    <p class="auth-subtitle">Cancelación de suscripción</p>

    <!-- Mensajes de estado -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <p style="text-align: left; margin-bottom: 20px; color: #6b7280; font-size: 14px;">
        Para iniciar el proceso de cancelación de su suscripción, por favor ingrese su correo electrónico 
        asociado a la cuenta. Le enviaremos un enlace de verificación para confirmar su solicitud.
    </p>

    <form action="{{ route('cancellation.send.verification') }}" method="GET" id="cancellationForm">
        <div class="form-group">
            <label for="email" class="form-label">Correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input 
                    type="email" 
                    class="form-control @error('email') is-invalid @enderror" 
                    id="email" 
                    name="email" 
                    placeholder="ejemplo@correo.com"
                    value="{{ old('email') }}" 
                    required autocomplete="email"
                >
            </div>
            @error('email')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            <small style="display: block; color: #6b7280; font-size: 12px; margin-top: 5px;">
                Ingrese el correo electrónico asociado a su cuenta para verificar su identidad.
            </small>
        </div>

        <button type="submit" class="btn-auth">
            <i class="bi bi-envelope-check"></i>
            Enviar verificación
        </button>
        
        <div style="text-align: center; margin-top: 15px;">
            <small style="color: #6b7280;">
                Si tiene problemas con este proceso, por favor contacte a nuestro soporte técnico.
            </small>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('cancellationForm');
        
        form.addEventListener('submit', function(e) {
            const emailInput = document.getElementById('email');
            const email = emailInput.value.trim();
            
            if (!email) {
                e.preventDefault();
                emailInput.classList.add('is-invalid');
                
                // Create error message if it doesn't exist
                if (!document.querySelector('.invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'El campo de correo electrónico es obligatorio.';
                    emailInput.parentNode.appendChild(errorDiv);
                }
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                emailInput.classList.add('is-invalid');
                
                // Create or update error message
                let errorDiv = document.querySelector('.invalid-feedback');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    emailInput.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = 'Por favor, ingrese una dirección de correo electrónico válida.';
            }
        });
        
        // Clear validation state on input
        document.getElementById('email').addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
</script>
@endsection
