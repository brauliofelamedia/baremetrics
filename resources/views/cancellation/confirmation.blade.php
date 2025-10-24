@extends('layouts.auth')

@section('title', 'Confirmación de cancelación')

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

        .blue-line {
            padding: 15px;
            background-color: #6078FF;
            color: white;
            font-size: 17px;
        }

        .confirmation-content {
            padding: 30px;
        }

        .customer-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .subscription-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .survey-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .btn-cancel {
            background-color: #E84C85;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 20px;
            border: none;
        }

        .btn-cancel:hover {
            background-color: #db165e;
            color: white;
        }

        .btn-back {
            background-color: #6c757d;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            margin-top: 10px;
        }

        .btn-back:hover {
            background-color: #545b62;
            color: white;
        }
    </style>
@endpush

@section('content')
<div class="auth-card">
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

    <div class="text">
        <img src="{{ asset('assets/img/sad.png') }}" alt="Sad">
        <h3>Confirmación de cancelación</h3>
        <p>Antes de proceder con la cancelación, revisa la información de tu cuenta y suscripciones.</p>
    </div>

    <div class="blue-line">
        Información recopilada 📋
    </div>

    <div class="confirmation-content">
        <!-- Información del cliente -->
        @if($customer)
        <div class="customer-info">
            <h4><i class="bi bi-person-circle"></i> Información del cliente</h4>
            <p><strong>Nombre:</strong> {{ $customer['name'] ?? 'N/A' }}</p>
            <p><strong>Email:</strong> {{ $customer['email'] ?? $email }}</p>
            <p><strong>ID de cliente:</strong> {{ $customer['oid'] ?? 'N/A' }}</p>
            @if(isset($customer['properties']))
                <p><strong>Información adicional:</strong></p>
                <ul>
                    @foreach($customer['properties'] as $property)
                        <li>{{ $property['field_id'] }}: {{ $property['value'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif

        <!-- Información de suscripciones -->
        @if(!empty($activeSubscriptions))
        <div class="subscription-info">
            <h4><i class="bi bi-credit-card"></i> Suscripciones activas ({{ count($activeSubscriptions) }})</h4>
            @foreach($activeSubscriptions as $sub)
            <div style="border-bottom: 1px solid #dee2e6; padding: 10px 0;">
                <p><strong>Plan:</strong> {{ $sub['plan']['name'] }}</p>
                <p><strong>Monto:</strong> ${{ number_format($sub['plan']['amount'], 2) }} {{ $sub['plan']['currency'] }} / {{ $sub['plan']['interval'] }}</p>
                <p><strong>Estado:</strong> {{ ucfirst($sub['status']) }}</p>
                <p><strong>ID de suscripción:</strong> {{ $sub['subscription_id'] }}</p>
                <p><strong>Período actual:</strong> {{ date('d/m/Y', $sub['current_period_start']) }} - {{ date('d/m/Y', $sub['current_period_end']) }}</p>
            </div>
            @endforeach
        </div>
        @else
        <div class="subscription-info">
            <h4><i class="bi bi-exclamation-triangle"></i> No se encontraron suscripciones activas</h4>
            <p>No hay suscripciones activas para cancelar en este momento.</p>
        </div>
        @endif

        <!-- Información del survey -->
        <div class="survey-info">
            <h4><i class="bi bi-chat-quote"></i> Razón de cancelación</h4>
            <p><strong>Motivo:</strong> {{ $reason }}</p>
            @if($additional_comments)
            <p><strong>Comentarios adicionales:</strong> {{ $additional_comments }}</p>
            @endif
        </div>

        <!-- Botones de acción -->
        <form action="{{ route('cancellation.cancel') }}" method="POST" style="margin-top: 30px;">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer['oid'] ?? '' }}">
            @if(!empty($activeSubscriptions))
                @foreach($activeSubscriptions as $sub)
                <input type="hidden" name="subscription_ids[]" value="{{ $sub['subscription_id'] }}">
                @endforeach
            @endif
            <input type="hidden" name="reason" value="{{ $reason }}">
            <input type="hidden" name="additional_comments" value="{{ $additional_comments }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <p style="text-align: center; color: #666; font-size: 14px; margin-bottom: 20px;">
                ⚠️ Esta acción cancelará todas las suscripciones activas y no se puede deshacer.
            </p>

            <button type="submit" class="btn-cancel" disabled>
                🚫 Cancelación deshabilitada (solo mostrando datos)
            </button>
        </form>

        <a href="{{ $customer && isset($customer['oid']) ? route('cancellation.survey', ['customer_id' => $customer['oid']]) : '#' }}" class="btn-back">
            ← Volver al survey
        </a>
    </div>
</div>
@endsection