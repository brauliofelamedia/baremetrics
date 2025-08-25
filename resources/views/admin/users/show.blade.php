@extends('layouts.admin')

@section('title', 'Detalles del Usuario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Detalles del Usuario</h1>
    <div>
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary me-2">
            <i class="bi bi-pencil me-2"></i>Editar
        </a>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 24px;">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                        <h5 class="card-title">{{ $user->name }}</h5>
                        <p class="text-muted">{{ $user->email }}</p>
                        @if($user->email_verified_at)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-warning">Inactivo</span>
                        @endif
                    </div>
                    <div class="col-md-9">
                        <h6 class="mb-3">Información del Usuario</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nombre:</strong></td>
                                <td>{{ $user->name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <td><strong>Fecha de registro:</strong></td>
                                <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Última actualización:</strong></td>
                                <td>{{ $user->updated_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Estado:</strong></td>
                                <td>
                                    @if($user->email_verified_at)
                                        <span class="badge bg-success">Activo desde {{ $user->email_verified_at->format('d/m/Y') }}</span>
                                    @else
                                        <span class="badge bg-warning">Inactivo</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">Roles y Permisos</h6>
            </div>
            <div class="card-body">
                <h6 class="text-muted mb-3">Roles Asignados</h6>
                @forelse($user->roles as $role)
                    <div class="mb-3">
                        <span class="badge bg-primary mb-2">{{ $role->name }}</span>
                        @if($role->permissions->count() > 0)
                            <div class="small text-muted">
                                <strong>Permisos:</strong>
                                <ul class="list-unstyled ms-2 mt-1">
                                    @foreach($role->permissions as $permission)
                                        <li><i class="bi bi-check-circle text-success me-1"></i>{{ $permission->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">No tiene roles asignados</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
