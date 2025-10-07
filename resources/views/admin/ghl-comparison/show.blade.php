@extends('layouts.admin')

@section('title', 'Detalles de Comparación')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar"></i>
                        {{ $comparison->name }}
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.ghl-comparison.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i>
                            Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Información General -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Información General</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>{{ $comparison->id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nombre:</strong></td>
                                            <td>{{ $comparison->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Archivo CSV:</strong></td>
                                            <td>{{ $comparison->csv_file_name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Estado:</strong></td>
                                            <td>
                                                @switch($comparison->status)
                                                    @case('pending')
                                                        <span class="badge badge-warning">Pendiente</span>
                                                        @break
                                                    @case('processing')
                                                        <span class="badge badge-info">Procesando</span>
                                                        @break
                                                    @case('completed')
                                                        <span class="badge badge-success">Completado</span>
                                                        @break
                                                    @case('failed')
                                                        <span class="badge badge-danger">Fallido</span>
                                                        @break
                                                @endswitch
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Creado:</strong></td>
                                            <td>{{ $comparison->created_at->format('d/m/Y H:i:s') }}</td>
                                        </tr>
                                        @if($comparison->processed_at)
                                            <tr>
                                                <td><strong>Procesado:</strong></td>
                                                <td>{{ $comparison->processed_at->format('d/m/Y H:i:s') }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Estadísticas de Comparación</h5>
                                </div>
                                <div class="card-body">
                                    @if($comparison->status === 'completed')
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-info">
                                                        <i class="fas fa-users"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Usuarios GHL</span>
                                                        <span class="info-box-number">{{ number_format($comparison->total_ghl_users) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-primary">
                                                        <i class="fas fa-database"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Usuarios Baremetrics</span>
                                                        <span class="info-box-number">{{ number_format($comparison->total_baremetrics_users) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-success">
                                                        <i class="fas fa-check-circle"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Sincronizados</span>
                                                        <span class="info-box-number">{{ number_format($comparison->users_found_in_baremetrics) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-warning">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Faltantes</span>
                                                        <span class="info-box-number">{{ number_format($comparison->users_missing_from_baremetrics) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-success" 
                                                 style="width: {{ $comparison->sync_percentage }}%">
                                                {{ $comparison->sync_percentage }}%
                                            </div>
                                        </div>
                                        <small class="text-muted">Porcentaje de sincronización</small>
                                    @elseif($comparison->status === 'failed')
                                        <div class="alert alert-danger">
                                            <h6>Error en la comparación:</h6>
                                            <p class="mb-0">{{ $comparison->error_message }}</p>
                                        </div>
                                    @else
                                        <div class="text-center text-muted">
                                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                                            <p class="mt-2">Procesando comparación...</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas de Importación -->
                    @if($comparison->status === 'completed')
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Estadísticas de Importación</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-secondary">
                                                        <i class="fas fa-list"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Total Faltantes</span>
                                                        <span class="info-box-number">{{ $stats['total'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-success">
                                                        <i class="fas fa-check"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Importados</span>
                                                        <span class="info-box-number">{{ $stats['imported'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-warning">
                                                        <i class="fas fa-clock"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Pendientes</span>
                                                        <span class="info-box-number">{{ $stats['pending'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="info-box">
                                                    <span class="info-box-icon bg-danger">
                                                        <i class="fas fa-times"></i>
                                                    </span>
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Fallidos</span>
                                                        <span class="info-box-number">{{ $stats['failed'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        @if($stats['total'] > 0)
                                            <div class="progress mb-2">
                                                <div class="progress-bar bg-success" 
                                                     style="width: {{ $stats['import_percentage'] }}%">
                                                    {{ $stats['import_percentage'] }}%
                                                </div>
                                            </div>
                                            <small class="text-muted">Porcentaje de importación</small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Acciones</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.ghl-comparison.missing-users', $comparison) }}" 
                                               class="btn btn-primary">
                                                <i class="fas fa-users"></i>
                                                Ver Usuarios Faltantes
                                            </a>
                                            
                                            <a href="{{ route('admin.ghl-comparison.download-missing-users', $comparison) }}" 
                                               class="btn btn-secondary">
                                                <i class="fas fa-download"></i>
                                                Descargar CSV
                                            </a>
                                            
                                            @if($stats['pending'] > 0)
                                                <form method="POST" 
                                                      action="{{ route('admin.ghl-comparison.import-all-users', $comparison) }}" 
                                                      style="display: inline;"
                                                      onsubmit="return confirm('¿Estás seguro de importar TODOS los usuarios faltantes a Baremetrics?')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-upload"></i>
                                                        Importar Todos ({{ $stats['pending'] }})
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($comparison->status === 'processing')
<script>
// Recargar página cada 5 segundos si está procesando
setTimeout(function() {
    location.reload();
}, 5000);
</script>
@endif
@endsection
