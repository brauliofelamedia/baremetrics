@extends('layouts.admin')

@section('title', 'Gestión de Permisos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Gestión de Permisos</h1>
    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Nuevo Permiso
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Permiso</th>
                        <th>Roles Asignados</th>
                        <th>Fecha de Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permissions as $permission)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $permission->name }}</div>
                            <div class="small text-muted">
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
                            </div>
                        </td>
                        <td>
                            @forelse($permission->roles as $role)
                                <span class="badge bg-primary me-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-muted">Sin roles asignados</span>
                            @endforelse
                        </td>
                        <td>{{ $permission->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.permissions.show', $permission) }}" class="btn btn-outline-info" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Estás seguro de eliminar este permiso?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <i class="bi bi-key text-muted fs-1"></i>
                            <p class="text-muted mt-2">No hay permisos creados</p>
                            <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Crear primer permiso
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header">
        <h6 class="mb-0">Información sobre Permisos</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Permisos del Sistema</h6>
                <p class="small text-muted">Los permisos definen acciones específicas que los usuarios pueden realizar en el sistema. Se agrupan en roles para facilitar la gestión.</p>
            </div>
            <div class="col-md-6">
                <h6>Buenas Prácticas</h6>
                <ul class="small text-muted">
                    <li>Usa nombres descriptivos para los permisos</li>
                    <li>Agrupa permisos relacionados en roles</li>
                    <li>Revisa regularmente los permisos asignados</li>
                    <li>Sigue el principio de menor privilegio</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
