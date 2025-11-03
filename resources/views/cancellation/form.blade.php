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

    <div style="background-color: #f0f4ff; border-radius: 10px; padding: 15px; margin-bottom: 25px;">
        <div style="display: flex; align-items: flex-start;">
            <i class="bi bi-info-circle" style="color: #292272; font-size: 20px; margin-right: 10px; margin-top: 2px;"></i>
            <p style="text-align: left; color: #4b5563; font-size: 14px; margin: 0;">
                Para iniciar el proceso de cancelación, por favor ingrese su correo electrónico 
                asociado a la cuenta. Le enviaremos un enlace de verificación para confirmar su solicitud.
            </p>
        </div>
    </div>

    @if(request()->has('embed') && request()->get('embed') == '1')
    <div style="background-color: #d1ecf1; border-left: 4px solid #0c5460; padding: 12px; margin-bottom: 20px; border-radius: 0 8px 8px 0;">
        <p style="margin: 0; color: #0c5460; font-size: 13px;">
            <strong>Modo Embed:</strong> Estás usando el flujo con embed de Baremetrics.
        </p>
    </div>
    @endif

    <form action="{{ route('cancellation.send.verification') }}" method="GET" id="cancellationForm">
        @if(request()->has('embed') && request()->get('embed') == '1')
        <input type="hidden" name="embed" value="1">
        @endif
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

        <button type="submit" class="btn-auth" id="submitBtn">
            <i class="bi bi-envelope-check" id="buttonIcon"></i>
            <span id="buttonText">Continuar con la verificación</span>
            <span id="loadingSpinner" style="display: none;">
                <div class="spinner"></div><span class="processing-text">Procesando...</span>
            </span>
        </button>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
            <small style="color: #6b7280; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-shield-check" style="margin-right: 5px; color: #292272;"></i>
                Si tiene problemas con este proceso, por favor contacte a nuestro soporte técnico.
            </small>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    @keyframes spinner {
        to {transform: rotate(360deg);}
    }
    
    .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spinner 0.8s linear infinite;
        margin-right: 5px;
        vertical-align: middle;
    }
    
    .btn-auth:disabled {
        background-color: #a0a0b2;
        cursor: not-allowed;
        opacity: 0.8;
    }
    
    .processing-text {
        display: inline-block;
        vertical-align: middle;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('cancellationForm');
        const submitBtn = document.getElementById('submitBtn');
        const buttonText = document.getElementById('buttonText');
        const loadingSpinner = document.getElementById('loadingSpinner');
        
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
            } else {
                // Formulario válido, mostrar loader y deshabilitar botón
                e.preventDefault(); // Prevenir el envío inmediato del formulario para mostrar el spinner
                submitBtn.disabled = true;
                buttonText.style.display = 'none';
                document.getElementById('buttonIcon').style.display = 'none';
                loadingSpinner.style.display = 'inline-block';
                
                // Enviar el formulario después de un pequeño retraso para mostrar el spinner
                setTimeout(function() {
                    form.submit();
                }, 300);
            }
        });
        
        // Clear validation state on input
        document.getElementById('email').addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
</script>
@endpush
