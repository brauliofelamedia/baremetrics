@extends('layouts.admin')

@section('title', 'Gestión de cancelaciones')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <!-- Mensajes de estado -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(isset($error) && $error)
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ $error }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <!-- Formulario de búsqueda principal -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <form action="{{ route('admin.cancellations.index') }}" method="GET" id="searchForm">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="email" class="form-label">Palabra clave (Nombre o correo)</label>
                                        <input 
                                            type="text"
                                            class="form-control @error('email') is-invalid @enderror" 
                                            id="email" 
                                            name="email" 
                                            placeholder="Ejemplo: jorge@felamedia.com"
                                            value="{{ old('email', $searchedEmail ?? '') }}"
                                            required
                                        >
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2" id="searchBtn">
                                            <i class="fas fa-search me-1"></i>Buscar cliente
                                        </button>
                                        <a href="{{ route('admin.cancellations.index') }}" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if(isset($searchedEmail) && !$showSearchForm)
                        <div class="alert alert-success">
                            <strong><i class="fas fa-check-circle me-1"></i>Resultados para: {{ $searchedEmail }}</strong>
                            <a href="{{ route('admin.cancellations.index') }}" class="btn btn-sm btn-success ms-3">
                                <i class="fas fa-plus me-1"></i>Nueva búsqueda
                            </a>
                        </div>
                    @endif

                    @if(!empty($customers) && count($customers) > 0)
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                                Se encontraron <strong>{{ count($customers) }}</strong> cliente(s) 
                            @if(isset($searchedEmail))
                                con el texto: <strong>{{ $searchedEmail }}</strong>
                            @endif
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID cliente</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Fecha de Creación</th>
                                        <th>Subscripciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customers as $index => $customer)
                                        <tr>
                                            <td><code class="text-muted">{{ $customer['oid'] }}</code></td>
                                            <td>{{ $customer['name'] ?? 'Sin nombre' }}</td>
                                            <td>{{ $customer['email'] ?? 'Sin email' }}</td>
                                            <td>{{ date('d/m/Y H:i', $customer['created']) }}</td>
                                            <td>
                                                @if(!empty($customer['current_plans']))
                                                    @if(count($customer['current_plans']) >= 2)
                                                        <!-- Botón que abre modal con la lista de planes -->
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#plansModal-{{ $index }}">
                                                            Ver subscripciones
                                                        </button>

                                                        <!-- Modal por cliente: muestra tabla con planes -->
                                                        <div class="modal fade" id="plansModal-{{ $index }}" tabindex="-1" aria-labelledby="plansModalLabel-{{ $index }}" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="plansModalLabel-{{ $index }}">Planes de {{ $customer['name'] ?? 'Usuario sin nombre' }}</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm table-striped">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Nombre del plan</th>
                                                                                        <th>Activo</th>
                                                                                        <th class="text-end">Acciones</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($customer['current_plans'] as $plan)
                                                                                        <tr>
                                                                                            <td class="text-truncate" style="max-width:300px;">{{ $plan['name'] ?? 'Plan sin nombre' }}</td>
                                                                                            <td>{{ !empty($plan['active']) && $plan['active'] ? 'Sí' : 'No' }}</td>
                                                                                            <td class="text-end">
                                                                                                <a href="{{route('admin.cancellations.manual', ['customer_id' => $customer['oid'], 'subscription_id' => $plan['oid']])}}" class="btn btn-danger btn-xs" title="{{ $plan['name'] }}">Cancelar</a>
                                                                                            </td>
                                                                                        </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        @inject('stripeService', 'App\Services\StripeService')
                                                        @if(!is_null($customer['current_plans']))
                                                            @foreach($customer['current_plans'] as $plan)
                                                                @php
                                                                    $subscription = $stripeService->getSubscriptionCustomer($customer['oid'],$plan['oid']);
                                                                    $isCanceled = false;
                                                                    if ($subscription && isset($subscription['id'])) {
                                                                        $isCanceled = $stripeService->checkSubscriptionCancellationStatus($subscription['id']);
                                                                    }
                                                                @endphp
                                                                @if($customer['is_canceled'] == false && !$isCanceled)
                                                                    <a href="{{route('admin.cancellations.manual', ['customer_id' => $customer['oid'], 'subscription_id' => $plan['oid']])}}" class="btn btn-danger btn-xs" title="{{ $plan['name'] }}">Cancelar</a>
                                                                @else
                                                                    <p>No hay subscripciones activas</p>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <p>No hay subscripciones activas</p>
                                                        @endif
                                                @endif
                                            @else
                                                <p>No hay subscripciones activas</p>
                                            @endif
                                        </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @elseif(!isset($showSearchForm) || !$showSearchForm)
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <p class="mb-0">No se encontraron customers. Utiliza el formulario de búsqueda para encontrar clientes.</p>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-xs {
        padding: 2px 10px;
        border-radius: 0;
        font-size: 12px; 
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('searchForm');
        const searchBtn = document.getElementById('searchBtn');
        const emailInput = document.getElementById('email');
        
        if (searchForm && searchBtn && emailInput) {
            searchForm.addEventListener('submit', function(e) {
                // Deshabilitar el botón para evitar múltiples envíos
                searchBtn.disabled = true;
                searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Buscando...';
                
                // Si hay algún error, re-habilitar después de un tiempo
                setTimeout(function() {
                    if (searchBtn.disabled) {
                        searchBtn.disabled = false;
                        searchBtn.innerHTML = '<i class="fas fa-search me-1"></i>Buscar Customer';
                    }
                }, 10000); // 10 segundos máximo
            });
            
            // Limpiar mensajes de error al empezar a escribir
            emailInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const errorDiv = this.nextElementSibling;
                if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                    errorDiv.style.display = 'none';
                }
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar clic en botón cancelar plan dentro del modal
        document.querySelectorAll('.cancel-plan-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                const customerId = this.getAttribute('data-customer');
                const planId = this.getAttribute('data-plan');
                const planNameCell = this.closest('tr').querySelector('td');
                const planName = planNameCell ? planNameCell.textContent.trim() : '';

                const confirmMsg = `¿Confirmas cancelar el plan "${planName}" para el cliente ${customerId}?`;
                if (!confirm(confirmMsg)) return;

                // Crear y enviar formulario POST oculto hacia la ruta de cancelación.
                // Asume que existe la ruta named 'admin.cancellations.cancel' que acepta customer y plan
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.action = `{{ route('admin.cancellations.cancel') }}`;

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                const custInput = document.createElement('input');
                custInput.type = 'hidden';
                custInput.name = 'customer_oid';
                custInput.value = customerId;
                form.appendChild(custInput);

                const planInput = document.createElement('input');
                planInput.type = 'hidden';
                planInput.name = 'plan_id';
                planInput.value = planId;
                form.appendChild(planInput);

                document.body.appendChild(form);
                form.submit();
            });
        });
    });
</script>
@endpush