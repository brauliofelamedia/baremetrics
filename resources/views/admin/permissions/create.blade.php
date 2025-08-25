@extends('layouts.admin')

@section('title', 'Crear Permiso')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Crear Permiso</h1>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('admin.permissions.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-4">
                        <label for="name" class="form-label">Nombre del permiso</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name') }}" required
                               placeholder="Ej: manage-products, view-reports, etc.">
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
                            <i class="bi bi-check-circle me-2"></i>Crear Permiso
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
                <h6 class="mb-0">Información sobre Permisos</h6>
            </div>
            <div class="card-body">
                <div class="small text-muted">
                    <p><strong>¿Qué es un permiso?</strong></p>
                    <p>Un permiso define una acción específica que puede realizar un usuario, como "crear usuarios" o "ver reportes".</p>
                    
                    <p><strong>Nomenclatura recomendada:</strong></p>
                    <ul>
                        <li><code>manage-[recurso]</code> - Control total</li>
                        <li><code>view-[recurso]</code> - Solo lectura</li>
                        <li><code>create-[recurso]</code> - Solo crear</li>
                        <li><code>edit-[recurso]</code> - Solo editar</li>
                        <li><code>delete-[recurso]</code> - Solo eliminar</li>
                    </ul>
                    
                    <p><strong>Ejemplos:</strong></p>
                    <ul class="small">
                        <li>manage-users</li>
                        <li>view-dashboard</li>
                        <li>create-posts</li>
                        <li>manage-settings</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
