@extends('layouts.admin')

@section('title', 'Editar Permiso')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Editar Permiso</h1>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('admin.permissions.update', $permission) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4">
                        <label for="name" class="form-label">Nombre del permiso</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name', $permission->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Usa un formato descriptivo como "manage-usuarios" o "view-reports". 
                            Evita espacios y usa guiones para separar palabras.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Actualizar Permiso
                        </button>
                        <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h6 class="mb-0">Información del Permiso</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Nombre:</strong></td>
                        <td>{{ $permission->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Creado:</strong></td>
                        <td>{{ $permission->created_at->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Actualizado:</strong></td>
                        <td>{{ $permission->updated_at->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Roles:</strong></td>
                        <td>{{ $permission->roles->count() }} roles</td>
                    </tr>
                </table>
                
                @if($permission->roles->count() > 0)
                    <hr>
                    <div class="small">
                        <strong>Roles que usan este permiso:</strong>
                        <ul class="list-unstyled mt-1">
                            @foreach($permission->roles as $role)
                                <li><i class="bi bi-shield me-1"></i>{{ $role->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <div class="alert alert-warning small mt-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Atención:</strong> Cambiar el nombre del permiso puede afectar los roles que lo usan.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
