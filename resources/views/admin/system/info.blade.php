@extends('layouts.admin')

@section('title', 'Información del Sistema')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.system.index') }}">Sistema</a></li>
    <li class="breadcrumb-item active">Información del Sistema</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Información del Sistema
                    </h5>
                    <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Información de PHP -->
                        <div class="col-md-6 mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fab fa-php me-2"></i>
                                Información de PHP
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold">Versión de PHP:</td>
                                            <td>
                                                <span class="badge bg-success">{{ $systemInfo['php_version'] }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Límite de Memoria:</td>
                                            <td>{{ $systemInfo['memory_limit'] }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Tamaño Máximo de Subida:</td>
                                            <td>{{ $systemInfo['max_upload_size'] }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Zona Horaria:</td>
                                            <td>{{ $systemInfo['timezone'] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Información de Laravel -->
                        <div class="col-md-6 mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fab fa-laravel me-2"></i>
                                Información de Laravel
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold">Versión de Laravel:</td>
                                            <td>
                                                <span class="badge bg-danger">{{ $systemInfo['laravel_version'] }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Entorno:</td>
                                            <td>
                                                <span class="badge bg-{{ $systemInfo['environment'] === 'production' ? 'success' : ($systemInfo['environment'] === 'local' ? 'warning' : 'info') }}">
                                                    {{ strtoupper($systemInfo['environment']) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Modo Debug:</td>
                                            <td>
                                                <span class="badge bg-{{ $systemInfo['debug_mode'] ? 'warning' : 'success' }}">
                                                    {{ $systemInfo['debug_mode'] ? 'ACTIVADO' : 'DESACTIVADO' }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Base de Datos:</td>
                                            <td>{{ strtoupper($systemInfo['database_type']) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Información del Servidor -->
                        <div class="col-md-6 mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-server me-2"></i>
                                Información del Servidor
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold">Software del Servidor:</td>
                                            <td>{{ $systemInfo['server_software'] }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Almacenamiento Usado:</td>
                                            <td>{{ $systemInfo['storage_used'] }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Fecha Actual:</td>
                                            <td>{{ now()->format('d/m/Y H:i:s') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Extensiones PHP -->
                        <div class="col-md-6 mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-puzzle-piece me-2"></i>
                                Extensiones PHP Importantes
                            </h6>
                            <div class="row">
                                @php
                                    $extensions = ['curl', 'gd', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'zip'];
                                @endphp
                                @foreach($extensions as $extension)
                                    <div class="col-6 mb-2">
                                        <div class="d-flex align-items-center">
                                            @if(extension_loaded($extension))
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                            @else
                                                <i class="fas fa-times-circle text-danger me-2"></i>
                                            @endif
                                            <small>{{ $extension }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Información Adicional -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-chart-pie me-2"></i>
                                Estadísticas del Sistema
                            </h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5 class="card-title text-primary">
                                                <i class="fas fa-clock"></i>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Tiempo de Actividad</strong><br>
                                                <small class="text-muted">{{ gmdate('H:i:s', time() - $_SERVER['REQUEST_TIME_FLOAT']) }}</small>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5 class="card-title text-success">
                                                <i class="fas fa-memory"></i>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Memoria Utilizada</strong><br>
                                                <small class="text-muted">{{ round(memory_get_usage(true) / 1024 / 1024, 2) }} MB</small>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5 class="card-title text-info">
                                                <i class="fas fa-hdd"></i>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Espacio en Disco</strong><br>
                                                <small class="text-muted">{{ $systemInfo['storage_used'] }}</small>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5 class="card-title text-warning">
                                                <i class="fas fa-database"></i>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Base de Datos</strong><br>
                                                <small class="text-muted">{{ strtoupper($systemInfo['database_type']) }}</small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Configuración -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-cogs me-2"></i>
                                Configuración de la Aplicación
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold">Nombre de la Aplicación:</td>
                                            <td>{{ $systemConfig->getSystemName() }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">URL de la Aplicación:</td>
                                            <td>{{ config('app.url') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Zona Horaria:</td>
                                            <td>{{ config('app.timezone') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Idioma por Defecto:</td>
                                            <td>{{ config('app.locale') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Proveedor de Cache:</td>
                                            <td>{{ config('cache.default') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Proveedor de Sesión:</td>
                                            <td>{{ config('session.driver') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.table td {
    vertical-align: middle;
}

.card.bg-light {
    border: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.badge {
    font-size: 0.85em;
}
</style>
@endpush
