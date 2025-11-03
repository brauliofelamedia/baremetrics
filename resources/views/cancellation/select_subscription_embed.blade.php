@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-credit-card me-2"></i>Seleccionar Suscripción a Cancelar
                    </h5>
                </div>

                <div class="card-body p-4">
                    <div class="customer-info mb-4 p-3 bg-light rounded border-start border-4 border-primary">
                        <h4>
                            <i class="bi bi-person me-2"></i>{{ $customer['name'] ?? $email }}
                        </h4>
                        <p class="text-muted mb-0">
                            <i class="bi bi-envelope me-2"></i>{{ $email }}
                        </p>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="d-flex align-items-center mb-3">
                        <i class="bi bi-list-check me-2 text-primary"></i>
                        <span>Suscripciones Activas</span>
                    </h5>
                    <p class="text-muted">Por favor, selecciona la suscripción que deseas cancelar:</p>
                    
                    <div class="list-group mt-4 subscription-list">
                        @foreach($activeSubscriptions as $index => $subscription)
                            <a href="{{ route('cancellation.embed', [
                                'customer_id' => $subscription['customer_id'],
                                'subscription_id' => $subscription['subscription_id']
                            ]) }}" class="list-group-item list-group-item-action border-0 mb-3 shadow-sm">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h5 class="mb-1 fw-bold text-primary">{{ $subscription['plan']['name'] }}</h5>
                                    <span class="badge bg-primary rounded-pill">
                                        {{ strtoupper($subscription['plan']['currency']) }} {{ $subscription['plan']['amount'] }}
                                    </span>
                                </div>
                                <p class="mb-1 text-muted">
                                    @if(isset($subscription['plan']['interval']))
                                        <i class="bi bi-calendar me-1"></i> Facturación: 
                                        <strong>{{ $subscription['plan']['interval_count'] }} {{ $subscription['plan']['interval'] }}(s)</strong>
                                    @endif
                                </p>
                            </a>
                        @endforeach
                    </div>
                    
                    <div class="mt-4 text-end">
                        <a href="{{ route('cancellation.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Regresar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background-color: #f5f7fa;
        font-family: 'Nunito', sans-serif;
    }
    
    .subscription-list .list-group-item {
        transition: all 0.3s ease;
        border-radius: 8px;
    }
    
    .subscription-list .list-group-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1) !important;
        border-left: 5px solid #0d6efd;
    }
    
    .card {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .card-header {
        padding: 1rem 1.5rem;
    }
    
    @media (max-width: 768px) {
        .card-body {
            padding: 1.5rem;
        }
    }
</style>
@endsection

