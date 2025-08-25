@extends('layouts.admin')

@section('title', 'Clientes de Stripe')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Stripe</h3>
        </div>
        <div class="card-body">
            <!-- Verificar si hay errores -->
            @if(!$customers['success'])
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error al conectar con Stripe</h5>
                    <p class="mb-2">{{ $customers['error'] ?? 'Error desconocido' }}</p>
                    <small class="text-muted">
                        Por favor, verifica que las claves de Stripe estén configuradas correctamente en el archivo .env
                    </small>
                    <hr>
                    <button class="btn btn-outline-danger btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-redo me-1"></i>Intentar de nuevo
                    </button>
                </div>
            @elseif(empty($customers['data']))
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>No hay clientes</h5>
                    <p class="mb-0">No se encontraron clientes en tu cuenta de Stripe.</p>
                </div>
            @else
                <!-- Información de resultados -->
                <div class="alert alert-success" id="results-info">
                    <i class="fas fa-check-circle me-2"></i>
                    Se encontraron <strong id="customer-count">{{ count($customers['data']) }}</strong> cliente(s) mostrados
                    @if($pagination['has_more'])
                        <span class="text-muted">(hay más disponibles)</span>
                    @endif
                </div>

                <!-- Loading indicator -->
                <div id="loading-indicator" class="text-center" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando más clientes...</p>
                </div>

                <!-- Tabla de customers -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 180px;">ID</th>
                                <th>Correo electrónico</th>
                                <th>Nombre</th>
                                <th>Balance</th>
                                <th>Fecha de creación</th>
                                <th style="width: 120px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="customers-table-body">
                        <tbody id="customers-table-body">
                            @foreach ($customers['data'] as $customer)
                                <tr>
                                    <td><code class="text-muted">{{ $customer['id'] }}</code></td>
                                    <td>{{ $customer['email'] ?? 'Sin email' }}</td>
                                    <td>{{ $customer['name'] ?? 'Sin nombre' }}</td>
                                    <td>
                                        @if(isset($customer['balance']) && $customer['balance'] != 0)
                                            <span class="badge bg-{{ $customer['balance'] > 0 ? 'success' : 'danger' }}">
                                                ${{ number_format($customer['balance'] / 100, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted">$0.00</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($customer['created']))
                                            {{ date('d/m/Y H:i', $customer['created']) }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.stripe.customers.show', $customer['id']) }}" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye me-1"></i>Ver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Botón cargar más -->
                @if($pagination['has_more'])
                    <div class="text-center mt-3">
                        <button id="load-more-btn" class="btn btn-primary" 
                                data-starting-after="{{ $pagination['starting_after'] }}">
                            <i class="fas fa-chevron-down me-1"></i>Cargar más clientes
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let hasMore = {{ $pagination['has_more'] ? 'true' : 'false' }};
let startingAfter = '{{ $pagination['starting_after'] ?? '' }}';
let currentCount = {{ $pagination['current_count'] ?? 0 }};

document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadingIndicator = document.getElementById('loading-indicator');
    const tableBody = document.getElementById('customers-table-body');
    const customerCount = document.getElementById('customer-count');

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            loadMoreCustomers();
        });
    }

    function loadMoreCustomers() {
        if (!hasMore) return;

        // Mostrar loading
        loadingIndicator.style.display = 'block';
        loadMoreBtn.style.display = 'none';

        fetch(`{{ route('admin.stripe.customers.load-more') }}?starting_after=${startingAfter}&limit=50`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    // Agregar nuevas filas a la tabla
                    data.data.forEach(customer => {
                        const row = createCustomerRow(customer);
                        tableBody.appendChild(row);
                    });

                    // Actualizar contador
                    currentCount += data.data.length;
                    customerCount.textContent = currentCount;

                    // Actualizar paginación
                    hasMore = data.pagination.has_more;
                    startingAfter = data.pagination.starting_after;

                    if (hasMore) {
                        loadMoreBtn.setAttribute('data-starting-after', startingAfter);
                        loadMoreBtn.style.display = 'block';
                    } else {
                        loadMoreBtn.style.display = 'none';
                        // Actualizar mensaje de información
                        const resultsInfo = document.getElementById('results-info');
                        resultsInfo.innerHTML = '<i class="fas fa-check-circle me-2"></i>Se encontraron <strong>' + currentCount + '</strong> cliente(s) - Todos los clientes cargados';
                    }
                } else {
                    console.error('Error cargando más clientes:', data.error);
                    alert('Error al cargar más clientes: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar más clientes');
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
            });
    }

    function createCustomerRow(customer) {
        const row = document.createElement('tr');
        
        const balance = customer.balance && customer.balance != 0 
            ? `<span class="badge bg-${customer.balance > 0 ? 'success' : 'danger'}">$${(customer.balance / 100).toFixed(2)}</span>`
            : '<span class="text-muted">$0.00</span>';

        const created = customer.created 
            ? new Date(customer.created * 1000).toLocaleDateString('es-ES') + ' ' + new Date(customer.created * 1000).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})
            : '<span class="text-muted">N/A</span>';

        row.innerHTML = `
            <td><code class="text-muted">${customer.id}</code></td>
            <td>${customer.email || 'Sin email'}</td>
            <td>${customer.name || 'Sin nombre'}</td>
            <td>${balance}</td>
            <td>${created}</td>
            <td>
                <a href="/admin/stripe/customers/${customer.id}" class="btn btn-sm btn-info">
                    <i class="fas fa-eye me-1"></i>Ver
                </a>
            </td>
        `;
        
        return row;
    }
});

// Auto-refresh cada 5 minutos si hay errores
@if(!$customers['success'])
    setTimeout(function() {
        console.log('Auto-refresh debido a error de conexión');
        window.location.reload();
    }, 300000); // 5 minutos
@endif
</script>
@endpush

