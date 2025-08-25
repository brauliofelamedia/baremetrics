@extends('layouts.admin')

@section('title', 'Sistema - Configuraciones')

@section('breadcrumb')
    <li class="breadcrumb-item active">Sistema</li>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Alertas -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Configuración General del Sistema -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>
                        Configuración General del Sistema
                    </h5>
                    <a href="{{ route('admin.system.edit') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>
                        Editar Configuración
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre del Sistema:</label>
                                <p class="mb-0">{{ $config->getSystemName() }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Descripción:</label>
                                <p class="mb-0">{{ $config->description ?: 'No definida' }}</p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Logo del Sistema:</label>
                                @if($config->hasLogo())
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $config->getLogoUrl() }}" alt="Logo" class="me-3" style="max-height: 50px;">
                                        <a href="{{ route('admin.system.remove-logo') }}" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('¿Está seguro de eliminar el logo?')">
                                            <i class="fas fa-trash me-1"></i>
                                            Eliminar Logo
                                        </a>
                                    </div>
                                @else
                                    <p class="mb-0 text-muted">No configurado</p>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Favicon:</label>
                                @if($config->hasFavicon())
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $config->getFaviconUrl() }}" alt="Favicon" class="me-3" style="max-height: 32px;">
                                        <a href="{{ route('admin.system.remove-favicon') }}" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('¿Está seguro de eliminar el favicon?')">
                                            <i class="fas fa-trash me-1"></i>
                                            Eliminar Favicon
                                        </a>
                                    </div>
                                @else
                                    <p class="mb-0 text-muted">No configurado</p>
                                @endif
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Última Actualización:</label>
                                <p class="mb-0">{{ $config->updated_at->format('d/m/Y H:i:s') }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Fecha de Creación:</label>
                                <p class="mb-0">{{ $config->created_at->format('d/m/Y H:i:s') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection