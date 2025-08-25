@extends('layouts.admin')

@section('title', 'Editar Rol')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Editar Rol: {{ $role->name }}</h1>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('admin.roles.update', $role) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4">
                        <label for="name" class="form-label">Nombre del rol</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name', $role->name) }}" required
                               {{ $role->name === 'Admin' ? 'readonly' : '' }}>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($role->name === 'Admin')
                            <div class="form-text text-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                El nombre del rol de Administrador no puede ser modificado.
                            </div>
                        @endif
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Permisos</label>
                        @error('permissions')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        
                        <div class="row">
                            @foreach($permissions as $permission)
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" 
                                               value="{{ $permission->name }}" id="permission_{{ $permission->id }}"
                                               {{ in_array($permission->name, old('permissions', $role->permissions->pluck('name')->toArray())) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="permission_{{ $permission->id }}">
                                            <strong>{{ $permission->name }}</strong>
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
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Actualizar Rol
                        </button>
                        <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">Información del Rol</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Nombre:</strong></td>
                        <td>{{ $role->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Creado:</strong></td>
                        <td>{{ $role->created_at->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Actualizado:</strong></td>
                        <td>{{ $role->updated_at->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Usuarios:</strong></td>
                        <td>{{ $role->users->count() }} usuarios asignados</td>
                    </tr>
                </table>
                
                @if($role->users->count() > 0)
                    <hr>
                    <div class="small">
                        <strong>Usuarios con este rol:</strong>
                        <ul class="list-unstyled mt-1">
                            @foreach($role->users->take(5) as $user)
                                <li><i class="bi bi-person me-1"></i>{{ $user->name }}</li>
                            @endforeach
                            @if($role->users->count() > 5)
                                <li class="text-muted">... y {{ $role->users->count() - 5 }} más</li>
                            @endif
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
