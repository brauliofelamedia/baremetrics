@extends('layouts.admin')

@section('title', 'Detalles del Rol')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Rol: {{ $role->name }}</h1>
    <div>
        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary me-2">
            <i class="bi bi-pencil me-2"></i>Editar
        </a>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-shield-check text-primary me-2"></i>
                    Información del Rol
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nombre:</strong></td>
                                <td>{{ $role->name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Fecha de creación:</strong></td>
                                <td>{{ $role->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Última actualización:</strong></td>
                                <td>{{ $role->updated_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Usuarios asignados:</strong></td>
                                <td>
                                    <span class="badge bg-primary">{{ $role->users->count() }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Permisos Asignados</h6>
                        @forelse($role->permissions as $permission)
                            <div class="mb-2">
                                <span class="badge bg-success me-2">{{ $permission->name }}</span>
                                <br>
                                <small class="text-muted">
                                    @switch($permission->name)
                                        @case('manage-users')
                                            Crear, editar y eliminar usuarios
                                            @break
                                        @case('manage-roles')
                                            Gestionar roles y permisos
                                            @break
                                        @case('manage-system-settings')
                                            Configurar ajustes del sistema
                                            @break
                                        @case('manage-baremetrics')
                                            Acceso completo a Baremetrics
                                            @break
                                        @case('manage-stripe')
                                            Gestionar integraciones de Stripe
                                            @break
                                        @case('manage-cancellations')
                                            Gestionar cancelaciones de usuarios
                                            @break
                                        @case('view-dashboard')
                                            Acceso al panel de control
                                            @break
                                        @default
                                            {{ $permission->name }}
                                    @endswitch
                                </small>
                            </div>
                        @empty
                            <p class="text-muted">Este rol no tiene permisos asignados.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">Usuarios con este Rol</h6>
            </div>
            <div class="card-body">
                @forelse($role->users as $user)
                    <div class="d-flex align-items-center mb-3">
                        <div class="user-avatar me-3" style="width: 35px; height: 35px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <div class="small text-muted">{{ $user->email }}</div>
                        </div>
                        <div>
                            @if($user->email_verified_at)
                                <span class="badge bg-success">Activo</span>
                            @else
                                <span class="badge bg-warning">Inactivo</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-3">
                        <i class="bi bi-people text-muted fs-2"></i>
                        <p class="text-muted mt-2">No hay usuarios asignados a este rol</p>
                    </div>
                @endforelse
            </div>
        </div>
        
        @if($role->name !== 'Admin')
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header">
                <h6 class="mb-0">Acciones</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" 
                      onsubmit="return confirm('¿Estás seguro de eliminar este rol? Esta acción no se puede deshacer.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-trash me-2"></i>Eliminar Rol
                    </button>
                </form>
                <div class="small text-muted mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Al eliminar el rol, los usuarios perderán estos permisos.
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
