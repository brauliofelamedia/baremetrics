@extends('layouts.admin')

@section('title', 'Gestión de Roles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Gestión de Roles</h1>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Nuevo Rol
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    @forelse($roles as $role)
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-check text-primary me-2"></i>
                        {{ $role->name }}
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.roles.show', $role) }}"><i class="bi bi-eye me-2"></i>Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.roles.edit', $role) }}"><i class="bi bi-pencil me-2"></i>Editar</a></li>
                            @if($role->name !== 'Admin')
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Estás seguro de eliminar este rol?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i>Eliminar
                                    </button>
                                </form>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Permisos asignados:</small>
                    <div class="mt-2">
                        @forelse($role->permissions as $permission)
                            <span class="badge bg-light text-dark me-1 mb-1">{{ $permission->name }}</span>
                        @empty
                            <span class="text-muted">Sin permisos asignados</span>
                        @endforelse
                    </div>
                </div>
                
                <div class="text-muted small">
                    <i class="bi bi-calendar me-1"></i>
                    Creado: {{ $role->created_at->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield-x text-muted fs-1"></i>
                <p class="text-muted mt-2">No hay roles creados</p>
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Crear primer rol
                </a>
            </div>
        </div>
    </div>
    @endforelse
</div>

<!-- Modal para gestión de permisos rápida -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestionar Permisos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Permisos Disponibles</h6>
                        <div class="list-group">
                            @foreach($permissions as $permission)
                                <div class="list-group-item">
                                    <span class="badge bg-secondary">{{ $permission->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Descripción de Permisos</h6>
                        <div class="small text-muted">
                            <p><strong>manage-users:</strong> Crear, editar y eliminar usuarios</p>
                            <p><strong>manage-roles:</strong> Gestionar roles y permisos</p>
                            <p><strong>manage-system-settings:</strong> Configurar el sistema</p>
                            <p><strong>manage-baremetrics:</strong> Acceso a Baremetrics</p>
                            <p><strong>manage-stripe:</strong> Gestionar Stripe</p>
                            <p><strong>manage-cancellations:</strong> Gestionar cancelaciones</p>
                            <p><strong>view-dashboard:</strong> Ver el dashboard</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Script para mostrar información de permisos
document.addEventListener('DOMContentLoaded', function() {
    // Cualquier script adicional para la gestión de roles
});
</script>
@endpush
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield text-muted fs-1"></i>
                <p class="text-muted mt-2 mb-0">No hay roles configurados</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
