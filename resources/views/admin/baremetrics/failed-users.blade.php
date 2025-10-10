@extends('layouts.admin')

@section('title', 'Usuarios Fallidos - Baremetrics')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.baremetrics.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Selector de Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter me-2"></i>Filtrar por Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="btn-group" role="group" aria-label="Status filter">
                                <a href="{{ route('admin.baremetrics.failed-users', ['status' => 'pending']) }}" 
                                   class="btn btn-outline-primary {{ $status === 'pending' ? 'active' : '' }}">
                                    <i class="fas fa-clock me-1"></i>Pending
                                    <span class="badge bg-secondary ms-1">{{ $statusCounts['pending'] }}</span>
                                </a>
                                <a href="{{ route('admin.baremetrics.failed-users', ['status' => 'importing']) }}" 
                                   class="btn btn-outline-info {{ $status === 'importing' ? 'active' : '' }}">
                                    <i class="fas fa-spinner me-1"></i>Importing
                                    <span class="badge bg-secondary ms-1">{{ $statusCounts['importing'] }}</span>
                                </a>
                                <a href="{{ route('admin.baremetrics.failed-users', ['status' => 'imported']) }}" 
                                   class="btn btn-outline-success {{ $status === 'imported' ? 'active' : '' }}">
                                    <i class="fas fa-check me-1"></i>Imported
                                    <span class="badge bg-secondary ms-1">{{ $statusCounts['imported'] }}</span>
                                </a>
                                <a href="{{ route('admin.baremetrics.failed-users', ['status' => 'failed']) }}" 
                                   class="btn btn-outline-danger {{ $status === 'failed' ? 'active' : '' }}">
                                    <i class="fas fa-times me-1"></i>Failed
                                    <span class="badge bg-secondary ms-1">{{ $statusCounts['failed'] }}</span>
                                </a>
                                <a href="{{ route('admin.baremetrics.failed-users', ['status' => 'found_in_other_source']) }}" 
                                   class="btn btn-outline-warning {{ $status === 'found_in_other_source' ? 'active' : '' }}">
                                    <i class="fas fa-search me-1"></i>Other Source
                                    <span class="badge bg-secondary ms-1">{{ $statusCounts['found_in_other_source'] }}</span>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <strong>Status actual:</strong> 
                            <span class="badge bg-primary fs-6">{{ strtoupper($status) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información y Acción de Borrado -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Información
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        <strong>Total de usuarios con status "{{ $status }}":</strong> 
                        <span class="badge bg-danger fs-6">{{ $failedUsers->total() }}</span>
                    </p>
                    <p class="mb-3">
                        Esta herramienta permite eliminar usuarios y sus suscripciones de Baremetrics que tienen el status seleccionado.
                    </p>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Advertencia:</strong> Esta acción eliminará los usuarios y suscripciones de Baremetrics y cambiará su estado a "pending" en la base de datos.
                    </div>
                    
                    @if($failedUsers->total() > 0)
                        <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">
                            <i class="fas fa-trash-alt me-2"></i>Eliminar Todos los Usuarios con Status "{{ strtoupper($status) }}" ({{ $failedUsers->total() }})
                        </button>
                    @else
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            No hay usuarios con status "{{ $status }}" para eliminar.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Usuarios Fallidos -->
    @if($failedUsers->count() > 0)
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>Lista de Usuarios con Status: {{ strtoupper($status) }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Nombre</th>
                                    <th>Customer ID</th>
                                    <th>Status</th>
                                    <th>Error</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($failedUsers as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>
                                        <i class="fas fa-envelope me-1 text-muted"></i>
                                        {{ $user->email }}
                                    </td>
                                    <td>{{ $user->name ?? 'N/A' }}</td>
                                    <td>
                                        @if($user->baremetrics_customer_id)
                                            <code>{{ $user->baremetrics_customer_id }}</code>
                                        @else
                                            <span class="text-muted">Sin ID</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match($user->import_status) {
                                                'pending' => 'bg-secondary',
                                                'importing' => 'bg-info',
                                                'imported' => 'bg-success',
                                                'failed' => 'bg-danger',
                                                'found_in_other_source' => 'bg-warning',
                                                default => 'bg-secondary'
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $user->import_status }}</span>
                                    </td>
                                    <td>
                                        @if($user->import_error)
                                            <small class="text-danger" title="{{ $user->import_error }}">
                                                {{ Str::limit($user->import_error, 50) }}
                                            </small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $user->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $failedUsers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Modal de Confirmación -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    <strong>¿Estás seguro de que deseas eliminar todos los usuarios con status "{{ strtoupper($status) }}"?</strong>
                </p>
                <p class="mb-3">
                    Se eliminarán <strong>{{ $failedUsers->total() }} usuarios</strong> y sus suscripciones de Baremetrics.
                </p>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Los usuarios se cambiarán a estado "pending" y se limpiarán sus datos de Baremetrics.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="deleteFailedUsers('{{ $status }}')">
                    <i class="fas fa-trash-alt me-2"></i>Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Progreso -->
<div class="modal fade" id="progressModal" tabindex="-1" aria-labelledby="progressModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="progressModalLabel">
                    <i class="fas fa-spinner fa-spin me-2"></i>Eliminando Usuarios Fallidos
                </h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Procesando...</span>
                    </div>
                </div>
                <p class="text-center mb-3">
                    <strong>Por favor espera mientras se procesan los usuarios...</strong>
                </p>
                <div id="progressContent" class="mt-3">
                    <p class="text-muted">Iniciando proceso...</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function deleteFailedUsers(status) {
    // Cerrar modal de confirmación
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
    confirmModal.hide();
    
    // Mostrar modal de progreso
    const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
    progressModal.show();
    
    // Obtener el token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Hacer la petición
    fetch('{{ route('admin.baremetrics.delete-failed-users') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        progressModal.hide();
        
        if (data.success) {
            // Mostrar mensaje de éxito
            let message = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>¡Éxito!</strong> ${data.message}
                    <br><br>
                    <strong>Detalles:</strong>
                    <ul class="mb-0">
                        <li>Total procesados: ${data.data.total_processed}</li>
                        <li>Eliminados/Limpiados: ${data.data.deleted_count}</li>
                        ${data.data.failed_count > 0 ? `<li class="text-danger">Fallidos: ${data.data.failed_count}</li>` : ''}
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Insertar mensaje al inicio del contenido
            document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', message);
            
            // Recargar la página después de 3 segundos
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            // Mostrar mensaje de error
            let message = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Error:</strong> ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', message);
        }
    })
    .catch(error => {
        progressModal.hide();
        
        let message = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error:</strong> Ocurrió un error al procesar la solicitud.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', message);
        
        console.error('Error:', error);
    });
}
</script>
@endpush

@push('styles')
<style>
    .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .badge {
        font-weight: 500;
    }
    
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
</style>
@endpush
@endsection
