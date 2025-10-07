@extends('layouts.admin')

@section('title', 'Procesando Comparación')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cogs"></i>
                        Procesando Comparación: {{ $comparison->name }}
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Barra de Progreso Principal -->
                    <div class="progress mb-4" style="height: 30px;">
                        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <span id="progress-text">0%</span>
                        </div>
                    </div>

                    <!-- Información del Progreso -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info">
                                    <i class="fas fa-file-csv"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Usuarios GHL</span>
                                    <span id="ghl-users-count" class="info-box-number">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-success">
                                    <i class="fas fa-users"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Usuarios Baremetrics</span>
                                    <span id="baremetrics-users-count" class="info-box-number">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas Detalladas -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="small-box bg-primary overflow-hidden">
                                <div class="inner">
                                    <h3 id="processed-count">0</h3>
                                    <p>Procesados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-success overflow-hidden">
                                <div class="inner">
                                    <h3 id="found-count">0</h3>
                                    <p>Encontrados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-warning overflow-hidden">
                                <div class="inner">
                                    <h3 id="missing-count">0</h3>
                                    <p>Faltantes</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-user-times"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-info overflow-hidden">
                                <div class="inner">
                                    <h3 id="sync-percentage">0%</h3>
                                    <p>Sincronización</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-percentage"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estado Actual -->
                    <div class="alert alert-info" role="alert">
                        <h4 class="alert-heading">
                            <i class="fas fa-info-circle"></i>
                            Estado Actual
                        </h4>
                        <p id="current-step">Iniciando procesamiento...</p>
                        <hr>
                        <p class="mb-0">
                            <small>
                                Última actualización: <span id="last-update">-</span>
                            </small>
                        </p>
                    </div>

                    <!-- Log de Actividad -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i>
                                Log de Actividad
                            </h3>
                        </div>
                        <div class="card-body">
                            <div id="activity-log" class="activity-log" style="height: 200px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;">
                                <div class="log-entry">
                                    <span class="text-muted">[{{ now()->format('H:i:s') }}]</span>
                                    <span class="text-info">Iniciando monitoreo de progreso...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <a href="{{ route('admin.ghl-comparison.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Volver a Comparaciones
                            </a>
                            <button id="refresh-btn" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i>
                                Actualizar
                            </button>
                            <button id="view-results-btn" class="btn btn-success" style="display: none;">
                                <i class="fas fa-eye"></i>
                                Ver Resultados
                            </button>
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
.progress {
    background-color: #e9ecef;
    border-radius: 0.375rem;
}

.progress-bar {
    transition: width 0.6s ease;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.activity-log {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.log-entry {
    margin-bottom: 5px;
    padding: 2px 0;
}

.log-entry .text-muted {
    margin-right: 10px;
}

.info-box {
    background: #fff;
    border-radius: 0.375rem;
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    display: flex;
    margin-bottom: 1rem;
    min-height: 80px;
    padding: 0.5rem;
    position: relative;
    width: 100%;
}

.info-box-icon {
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
}

.info-box-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.8;
    margin-left: 0.5rem;
    padding: 0.5rem;
}

.small-box {
    border-radius: 0.375rem;
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    display: block;
    margin-bottom: 20px;
    position: relative;
}

.small-box .inner {
    padding: 10px 30px;
    color: white;
}

.small-box .icon {
    color: rgba(0,0,0,.15);
    z-index: 0;
    position: absolute;
    right: 10px;
    top: 10px;
    font-size: 90px;
}
</style>
@endpush

@push('scripts')
<script>
let progressInterval;
let comparisonId = {{ $comparison->id }};
let isCompleted = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('JavaScript cargado correctamente');
    console.log('Comparación ID:', comparisonId);
    console.log('Estado inicial:', '{{ $comparison->status }}');
    
    // Iniciar monitoreo de progreso
    startProgressMonitoring();
    
    // Si la comparación está pendiente, iniciar procesamiento
    if ('{{ $comparison->status }}' === 'pending') {
        console.log('Iniciando procesamiento automático...');
        startProcessing();
    }
    
    // Botón de actualizar manual
    document.getElementById('refresh-btn').addEventListener('click', function() {
        console.log('Actualizando progreso manualmente...');
        updateProgress();
    });
    
    // Botón para ver resultados (aparece cuando se completa)
    document.getElementById('view-results-btn').addEventListener('click', function() {
        window.location.href = "{{ route('admin.ghl-comparison.show', $comparison->id) }}";
    });
});

function startProgressMonitoring() {
    console.log('Iniciando monitoreo de progreso...');
    // Actualizar inmediatamente
    updateProgress();
    
    // Actualizar cada 2 segundos
    progressInterval = setInterval(updateProgress, 2000);
    
    addLogEntry('Monitoreo de progreso iniciado', 'info');
}

function startProcessing() {
    console.log('Iniciando procesamiento...');
    addLogEntry('Iniciando procesamiento de comparación...', 'info');
    
    fetch("{{ route('admin.ghl-comparison.start-processing', $comparison->id) }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('Procesamiento iniciado:', data);
        addLogEntry('Procesamiento iniciado exitosamente', 'success');
    })
    .catch(error => {
        console.error('Error iniciando procesamiento:', error);
        addLogEntry('Error iniciando procesamiento: ' + error.message, 'danger');
    });
}

function updateProgress() {
    console.log('Actualizando progreso...');
    
    fetch("{{ route('admin.ghl-comparison.progress', $comparison->id) }}")
    .then(response => response.json())
    .then(data => {
        console.log('Datos de progreso recibidos:', data);
        updateUI(data);
        
        // Si está completado o fallido, detener el monitoreo
        if (data.is_completed || data.is_failed) {
            stopProgressMonitoring();
            
            if (data.is_completed) {
                addLogEntry('¡Procesamiento completado exitosamente!', 'success');
                document.getElementById('view-results-btn').style.display = 'inline-block';
            } else if (data.is_failed) {
                addLogEntry('Procesamiento falló: ' + data.error_message, 'danger');
            }
        }
    })
    .catch(error => {
        console.error('Error obteniendo progreso:', error);
        addLogEntry('Error obteniendo progreso: ' + error.message, 'danger');
    });
}

function updateUI(data) {
    // Barra de progreso principal
    const percentage = Math.round(data.progress_percentage || 0);
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    
    progressBar.style.width = percentage + '%';
    progressBar.setAttribute('aria-valuenow', percentage);
    progressText.textContent = percentage + '%';
    
    // Información básica
    document.getElementById('ghl-users-count').textContent = data.total_rows_processed || 0;
    document.getElementById('baremetrics-users-count').textContent = data.baremetrics_users_fetched || 0;
    
    // Estadísticas detalladas
    document.getElementById('processed-count').textContent = data.ghl_users_processed || 0;
    document.getElementById('found-count').textContent = data.users_found_count || 0;
    document.getElementById('missing-count').textContent = data.users_missing_count || 0;
    
    // Porcentaje de sincronización
    const syncPercentage = data.users_found_count && data.total_rows_processed ? 
        Math.round((data.users_found_count / data.total_rows_processed) * 100) : 0;
    document.getElementById('sync-percentage').textContent = syncPercentage + '%';
    
    // Estado actual
    document.getElementById('current-step').textContent = data.current_step || 'Procesando...';
    
    // Última actualización
    if (data.last_progress_update) {
        const lastUpdate = new Date(data.last_progress_update);
        document.getElementById('last-update').textContent = lastUpdate.toLocaleString();
    }
    
    // Cambiar color de la barra según el estado
    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
    
    if (data.is_completed) {
        progressBar.classList.add('bg-success');
    } else if (data.is_failed) {
        progressBar.classList.add('bg-danger');
    } else if (percentage > 0) {
        progressBar.classList.add('bg-primary');
    }
}

function addLogEntry(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const logContainer = document.getElementById('activity-log');
    
    const logEntry = document.createElement('div');
    logEntry.className = 'log-entry';
    logEntry.innerHTML = `
        <span class="text-muted">[${timestamp}]</span>
        <span class="text-${type}">${message}</span>
    `;
    
    logContainer.appendChild(logEntry);
    logContainer.scrollTop = logContainer.scrollHeight;
}

function stopProgressMonitoring() {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
        addLogEntry('Monitoreo de progreso detenido', 'warning');
    }
}

// Limpiar intervalo cuando se cierra la página
window.addEventListener('beforeunload', function() {
    stopProgressMonitoring();
});
</script>
@endpush
