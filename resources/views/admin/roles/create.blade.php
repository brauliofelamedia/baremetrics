@extends('layouts.admin')

@section('title', 'Crear Rol')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Crear Rol</h1>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('admin.roles.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-4">
                        <label for="name" class="form-label">Nombre del rol</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name') }}" required
                               placeholder="Ej: Editor, Moderador, etc.">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
                                               {{ in_array($permission->name, old('permissions', [])) ? 'checked' : '' }}>
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
                            <i class="bi bi-check-circle me-2"></i>Crear Rol
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
                <h6 class="mb-0">Información sobre Roles</h6>
            </div>
            <div class="card-body">
                <div class="small text-muted">
                    <p><strong>¿Qué es un rol?</strong></p>
                    <p>Un rol agrupa permisos específicos que determinan qué acciones puede realizar un usuario en el sistema.</p>
                    
                    <p><strong>Ejemplos de roles comunes:</strong></p>
                    <ul>
                        <li><strong>Admin:</strong> Acceso completo al sistema</li>
                        <li><strong>Editor:</strong> Gestionar contenido y usuarios</li>
                        <li><strong>Viewer:</strong> Solo visualizar información</li>
                    </ul>
                    
                    <p><strong>Consejo:</strong> Asigna solo los permisos necesarios para cada rol siguiendo el principio de menor privilegio.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
