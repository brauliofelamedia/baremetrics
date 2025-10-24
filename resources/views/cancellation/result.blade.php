@extends('layouts.auth')

@section('title', 'Resultado de cancelación')

@push('styles')
    <style>
        .auth-container {
            background-image: none;
            background-color: #33334F;
        }
        .auth-card {
            padding: 0;
            max-width: 600px;
        }

        .text {
            padding: 30px 30px 0 30px;
        }

        h3 {
            font-size: 22px;
            font-weight: 600;
        }

        p {
            font-size: 16px;
            line-height: 1.4em;
        }

        .result-header {
            padding: 15px;
            color: white;
            font-size: 17px;
            text-align: center;
        }

        .result-content {
            padding: 30px;
        }

        .success-header {
            background-color: #28a745;
        }

        .error-header {
            background-color: #dc3545;
        }

        .warning-header {
            background-color: #ffc107;
            color: #212529;
        }

        .result-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .subscription-result {
            background-color: #fff;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .status-success {
            color: #28a745;
            font-weight: bold;
        }

        .status-error {
            color: #dc3545;
            font-weight: bold;
        }

        .status-warning {
            color: #ffc107;
            font-weight: bold;
        }

        .btn-home {
            background-color: #007bff;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-home:hover {
            background-color: #0056b3;
            color: white;
            text-decoration: none;
        }

        .result-summary {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
    </style>
@endpush

@section('content')
<div class="auth-card">
    <div class="text">
        @if($success)
            <img src="{{ asset('assets/img/success.png') }}" alt="Success" style="width: 80px; height: 80px;">
            <h3>¡Cancelación procesada!</h3>
        @else
            <img src="{{ asset('assets/img/warning.png') }}" alt="Warning" style="width: 80px; height: 80px;">
            <h3>Cancelación procesada con problemas</h3>
        @endif
    </div>

    <div class="result-header {{ $success ? 'success-header' : ($hasErrors ? 'warning-header' : 'error-header') }}">
        {{ $message }}
    </div>

    <div class="result-content">
        <!-- Resumen de la cancelación -->
        <div class="result-summary">
            <h4><i class="bi bi-info-circle"></i> Resumen del proceso</h4>
            <p><strong>Cliente:</strong> {{ $data['email'] }}</p>
            <p><strong>ID de cliente:</strong> {{ $data['customer_id'] }}</p>
            <p><strong>Motivo:</strong> {{ $data['reason'] }}</p>
            @if($data['additional_comments'])
                <p><strong>Comentarios:</strong> {{ $data['additional_comments'] }}</p>
            @endif
            <p><strong>Suscripciones procesadas:</strong> {{ $data['subscriptions_cancelled'] }}</p>
        </div>

        <!-- Detalles de cada suscripción -->
        @if(!empty($data['cancellation_details']))
        <div class="result-card">
            <h4><i class="bi bi-list-check"></i> Detalles por suscripción</h4>
            @foreach($data['cancellation_details'] as $detail)
            <div class="subscription-result">
                <p><strong>ID de suscripción:</strong> {{ $detail['subscription_id'] }}</p>

                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <div>
                        <strong>Stripe:</strong>
                        @if($detail['stripe'] === 'success')
                            <span class="status-success">✅ Cancelada</span>
                        @else
                            <span class="status-error">❌ Error: {{ $detail['stripe_error'] ?? 'Desconocido' }}</span>
                        @endif
                    </div>

                    <div>
                        <strong>Baremetrics:</strong>
                        @if(isset($detail['baremetrics']))
                            @if($detail['baremetrics'] === 'success')
                                <span class="status-success">✅ Eliminada</span>
                            @else
                                <span class="status-error">❌ Error: {{ $detail['baremetrics_error'] ?? 'Desconocido' }}</span>
                            @endif
                        @else
                            <span class="status-warning">⚠️ No procesada</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Información adicional -->
        @if($success)
        <div class="result-card" style="background-color: #d4edda; border-color: #c3e6cb;">
            <h4><i class="bi bi-check-circle"></i> ¿Qué sucede ahora?</h4>
            <ul>
                <li>Tu suscripción ha sido cancelada y no se te cobrará en el próximo período</li>
                <li>Podrás seguir usando el servicio hasta la fecha de finalización del período actual</li>
                <li>Recibirás un email de confirmación con los detalles</li>
                <li>Tus datos han sido guardados para análisis y mejora del servicio</li>
            </ul>
        </div>
        @else
        <div class="result-card" style="background-color: #f8d7da; border-color: #f5c6cb;">
            <h4><i class="bi bi-exclamation-triangle"></i> ¿Qué hacer si hay problemas?</h4>
            <ul>
                <li>Contacta a nuestro soporte técnico con el ID de tu cliente: <strong>{{ $data['customer_id'] }}</strong></li>
                <li>Proporciona esta información para que podamos revisar tu caso</li>
                <li>Algunas cancelaciones pueden requerir procesamiento manual</li>
            </ul>
        </div>
        @endif

        <!-- Botón de regreso -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ url('/') }}" class="btn-home">
                🏠 Ir al inicio
            </a>
        </div>
    </div>
</div>
@endsection