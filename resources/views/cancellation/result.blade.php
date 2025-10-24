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
            text-align: left;
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

        p {
            margin-bottom: 4px;
        }

        li {
            line-height: 1.2em;
            margin-bottom: 10px;
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

        h4, h3 {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 700;
        }

        h3 {
            font-size: 30px;
            margin-bottom: 30px;
        }
    </style>
@endpush

@section('content')
<div class="auth-card">
    <div class="text">
        <h3>¡Cancelación procesada!</h3>
    </div>

    <div class="result-header {{ $success ? 'success-header' : ($hasErrors ? 'warning-header' : 'error-header') }}">
        {{ $message }}
    </div>

    <div class="result-content">
        <!-- Resumen de la cancelación -->
        <div class="result-summary">
            <h4>Resumen del proceso</h4>
            <p><strong>Cliente:</strong> {{ $data['email'] }}</p>
            <p><strong>Motivo:</strong> {{ $data['reason'] }}</p>
            @if($data['additional_comments'])
                <p><strong>Comentarios:</strong> {{ $data['additional_comments'] }}</p>
            @endif
            <p><strong>Suscripciones procesadas:</strong> {{ $data['subscriptions_cancelled'] }}</p>
        </div>

        <!-- Información adicional -->
        @if($success)
        <div class="result-card" style="background-color: #d4edda; border-color: #c3e6cb;">
            <h4>¿Qué sucede ahora?</h4>
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
            <button class="btn-home" onclick="window.close();">Cerrar pestaña</button>
        </div>
    </div>
</div>
@endsection