@extends('layouts.admin')

@section('title', 'Detalles del Permiso')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Permiso: {{ $permission->name }}</h1>
    <div>
        <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-primary me-2">
            <i class="bi bi-pencil me-2"></i>Editar
        </a>
        <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-key text-primary me-2"></i>
                    Información del Permiso
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nombre:</strong></td>
                                <td><code>{{ $permission->name }}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Descripción:</strong></td>
                                <td>
                                    @switch($permission->name)
                                        @case('manage-users')
                                            Crear, editar y eliminar usuarios del sistema
                                            @break
                                        @case('manage-roles')
                                            Gestionar roles y permisos del sistema
                                            @break
                                        @case('manage-system-settings')
                                            Configurar ajustes generales del sistema
                                            @break
                                        @case('manage-baremetrics')
                                            Acceso completo a las funcionalidades de Baremetrics
                                            @break
                                        @case('manage-stripe')
                                            Gestionar integraciones y configuraciones de Stripe
                                            @break
                                        @case('manage-cancellations')
                                            Gestionar cancelaciones de suscripciones de usuarios
                                            @break
                                        @case('view-dashboard')
                                            Acceso al panel de control y métricas principales
                                            @break
                                        @default
                                            Permiso personalizado del sistema
                                    @endswitch
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Fecha de creación:</strong></td>
                                <td>{{ $permission->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Última actualización:</strong></td>
                                <td>{{ $permission->updated_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Roles asignados:</strong></td>
                                <td>
                                    <span class="badge bg-primary">{{ $permission->roles->count() }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Roles que tienen este Permiso</h6>
                        @forelse($permission->roles as $role)
                            <div class="mb-2 d-flex align-items-center">
                                <span class="badge bg-success me-2">{{ $role->name }}</span>
                                <small class="text-muted">
                                    ({{ $role->users->count() }} usuarios)
                                </small>
                            </div>
                        @empty
                            <div class="text-center py-3">
                                <i class="bi bi-shield-x text-muted fs-2"></i>
                                <p class="text-muted mt-2">Este permiso no está asignado a ningún rol</p>
                                <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-primary">
                                    Ver Roles Disponibles
                                </a>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        @if($permission->roles->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">Usuarios Afectados</h6>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Total de usuarios que tienen este permiso a través de sus roles:
                </div>
                
                @php
                    $allUsers = collect();
                    foreach($permission->roles as $role) {
                        $allUsers = $allUsers->merge($role->users);
                    }
                    $uniqueUsers = $allUsers->unique('id');
                @endphp
                
                <div class="text-center mb-3">
                    <span class="badge bg-info fs-6">{{ $uniqueUsers->count() }} usuarios</span>
                </div>
                
                @foreach($uniqueUsers->take(5) as $user)
                    <div class="d-flex align-items-center mb-2">
                        <div class="user-avatar me-2" style="width: 25px; height: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 10px;">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-semibold">{{ $user->name }}</div>
                            <div class="small text-muted">{{ $user->email }}</div>
                        </div>
                    </div>
                @endforeach
                
                @if($uniqueUsers->count() > 5)
                    <div class="small text-muted text-center">
                        ... y {{ $uniqueUsers->count() - 5 }} usuarios más
                    </div>
                @endif
            </div>
        </div>
        @endif
        
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header">
                <h6 class="mb-0">Acciones</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" 
                      onsubmit="return confirm('¿Estás seguro de eliminar este permiso? Se removerá de todos los roles que lo tengan asignado.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-trash me-2"></i>Eliminar Permiso
                    </button>
                </form>
                <div class="small text-muted mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Esta acción no se puede deshacer.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
