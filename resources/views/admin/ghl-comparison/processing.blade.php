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
                            <div class="small-box bg-primary">
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
                            <div class="small-box bg-success">
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
                            <div class="small-box bg-warning">
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
                            <div class="small-box bg-info">
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
    padding: 10px;
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
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let progressInterval;
let comparisonId = {{ $comparison->id }};
let isCompleted = false;

$(document).ready(function() {
    console.log('jQuery cargado correctamente');
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
    $('#refresh-btn').click(function() {
        console.log('Actualizando progreso manualmente...');
        updateProgress();
    });
    
    // Botón para ver resultados (aparece cuando se completa)
    $('#view-results-btn').click(function() {
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
    
    $.ajax({
        url: "{{ route('admin.ghl-comparison.start-processing', $comparison->id) }}",
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('Procesamiento iniciado:', response);
            addLogEntry('Procesamiento iniciado exitosamente', 'success');
        },
        error: function(xhr, status, error) {
            console.error('Error iniciando procesamiento:', error, xhr.responseText);
            addLogEntry('Error iniciando procesamiento: ' + error, 'danger');
        }
    });
}

function updateProgress() {
    console.log('Actualizando progreso...');
    $.ajax({
        url: "{{ route('admin.ghl-comparison.progress', $comparison->id) }}",
        method: 'GET',
        success: function(data) {
            console.log('Datos de progreso recibidos:', data);
            updateUI(data);
            
            // Si está completado o fallido, detener el monitoreo
            if (data.is_completed || data.is_failed) {
                stopProgressMonitoring();
                
                if (data.is_completed) {
                    addLogEntry('¡Procesamiento completado exitosamente!', 'success');
                    $('#view-results-btn').show();
                } else if (data.is_failed) {
                    addLogEntry('Procesamiento falló: ' + data.error_message, 'danger');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error obteniendo progreso:', error, xhr.responseText);
            addLogEntry('Error obteniendo progreso: ' + error, 'danger');
        }
    });
}

function updateUI(data) {
    // Barra de progreso principal
    const percentage = Math.round(data.progress_percentage || 0);
    $('#progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage);
    $('#progress-text').text(percentage + '%');
    
    // Información básica
    $('#ghl-users-count').text(data.total_rows_processed || 0);
    $('#baremetrics-users-count').text(data.baremetrics_users_fetched || 0);
    
    // Estadísticas detalladas
    $('#processed-count').text(data.ghl_users_processed || 0);
    $('#found-count').text(data.users_found_count || 0);
    $('#missing-count').text(data.users_missing_count || 0);
    
    // Porcentaje de sincronización
    const syncPercentage = data.users_found_count && data.total_rows_processed ? 
        Math.round((data.users_found_count / data.total_rows_processed) * 100) : 0;
    $('#sync-percentage').text(syncPercentage + '%');
    
    // Estado actual
    $('#current-step').text(data.current_step || 'Procesando...');
    
    // Última actualización
    if (data.last_progress_update) {
        const lastUpdate = new Date(data.last_progress_update);
        $('#last-update').text(lastUpdate.toLocaleString());
    }
    
    // Cambiar color de la barra según el estado
    const progressBar = $('#progress-bar');
    progressBar.removeClass('bg-primary bg-success bg-danger bg-warning');
    
    if (data.is_completed) {
        progressBar.addClass('bg-success');
    } else if (data.is_failed) {
        progressBar.addClass('bg-danger');
    } else if (percentage > 0) {
        progressBar.addClass('bg-primary');
    }
}

function addLogEntry(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = $(`
        <div class="log-entry">
            <span class="text-muted">[${timestamp}]</span>
            <span class="text-${type}">${message}</span>
        </div>
    `);
    
    $('#activity-log').append(logEntry);
    $('#activity-log').scrollTop($('#activity-log')[0].scrollHeight);
}

function stopProgressMonitoring() {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
        addLogEntry('Monitoreo de progreso detenido', 'warning');
    }
}

// Limpiar intervalo cuando se cierra la página
$(window).on('beforeunload', function() {
    stopProgressMonitoring();
});
</script>
@endpush
