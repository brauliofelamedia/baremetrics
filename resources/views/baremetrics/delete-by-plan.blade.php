@extends('layouts.admin')

@section('title', 'Eliminar Usuarios por Plan')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">
                        <i class="fas fa-trash-alt"></i> Eliminar Usuarios de Baremetrics por Plan
                    </h4>
                    <p class="text-muted">
                        Esta herramienta permite eliminar usuarios de Baremetrics que pertenezcan a un plan específico. 
                        Se eliminarán todas las suscripciones del usuario y luego el usuario mismo.
                    </p>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>¡Advertencia!</strong> Esta acción eliminará permanentemente los usuarios y sus suscripciones de Baremetrics. 
                        Esta operación NO se puede deshacer.
                    </div>

                    <div class="mb-4">
                        <label for="planName" class="form-label">
                            <strong>Nombre del Plan:</strong>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="planName" 
                            placeholder="Ejemplo: creetelo_anual"
                            value="creetelo_anual"
                        >
                        <small class="form-text text-muted">
                            Ingresa el nombre exacto del plan tal como aparece en Baremetrics
                        </small>
                    </div>

                    <div class="mb-3">
                        <button id="deleteBtn" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Eliminar Usuarios del Plan
                        </button>
                    </div>

                    <!-- Progress Section -->
                    <div id="progressSection" style="display: none;">
                        <hr>
                        <h5>Progreso</h5>
                        <div class="mb-3">
                            <div class="progress">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" 
                                     role="progressbar" style="width: 0%">0%</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p><strong>Estado:</strong> <span id="statusText">Iniciando...</span></p>
                            <p><strong>Procesados:</strong> <span id="processedCount">0</span> / <span id="totalCount">0</span></p>
                            <p><strong>Exitosos:</strong> <span id="successCount" class="text-success">0</span></p>
                            <p><strong>Fallidos:</strong> <span id="failedCount" class="text-danger">0</span></p>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div id="resultsSection" style="display: none;">
                        <hr>
                        <h5>Resultados</h5>
                        <div id="resultsSummary" class="alert"></div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="resultsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Email</th>
                                        <th>Nombre</th>
                                        <th>Customer ID</th>
                                        <th>Suscripciones Eliminadas</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Error Section -->
                    <div id="errorSection" style="display: none;">
                        <hr>
                        <div class="alert alert-danger" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-circle"></i> Error</h5>
                            <p id="errorMessage"></p>
                        </div>
                    </div>

                    <!-- Log Section -->
                    <div class="mt-4">
                        <h5>Log de Actividad</h5>
                        <div class="card">
                            <div class="card-body p-2">
                                <pre id="log" class="bg-dark text-light p-3 mb-0" style="height:300px; overflow:auto; font-size: 12px;"></pre>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const deleteBtn = document.getElementById('deleteBtn');
    const planNameInput = document.getElementById('planName');
    const progressSection = document.getElementById('progressSection');
    const resultsSection = document.getElementById('resultsSection');
    const errorSection = document.getElementById('errorSection');
    const logEl = document.getElementById('log');

    function appendLog(text, type = 'info'){
        const now = new Date().toLocaleTimeString();
        const color = type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-info';
        const icon = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
        logEl.innerHTML = `<span class="${color}">${icon} [${now}] ${text}</span>\n` + logEl.innerHTML;
    }

    function showError(message){
        errorSection.style.display = 'block';
        document.getElementById('errorMessage').textContent = message;
        appendLog(message, 'error');
    }

    function hideError(){
        errorSection.style.display = 'none';
    }

    function updateProgress(current, total, success, failed){
        if(total > 0){
            const percent = Math.round((current / total) * 100);
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
        document.getElementById('processedCount').textContent = current;
        document.getElementById('totalCount').textContent = total;
        document.getElementById('successCount').textContent = success;
        document.getElementById('failedCount').textContent = failed;
    }

    function displayResults(data){
        resultsSection.style.display = 'block';
        
        const summary = document.getElementById('resultsSummary');
        summary.className = 'alert ' + (data.failed_count > 0 ? 'alert-warning' : 'alert-success');
        summary.innerHTML = `
            <strong>Resumen del Proceso:</strong><br>
            Plan: <strong>${data.plan_name}</strong><br>
            Total procesados: <strong>${data.total_processed}</strong><br>
            Exitosos: <strong class="text-success">${data.deleted_count}</strong><br>
            Fallidos: <strong class="text-danger">${data.failed_count}</strong>
        `;

        const tbody = document.getElementById('resultsTableBody');
        tbody.innerHTML = '';
        
        if(data.processed_users && data.processed_users.length > 0){
            data.processed_users.forEach(user => {
                const row = tbody.insertRow();
                const statusClass = user.status === 'success' ? 'text-success' : 'text-danger';
                const statusIcon = user.status === 'success' ? '✅' : '❌';
                
                row.innerHTML = `
                    <td>${user.email || 'N/A'}</td>
                    <td>${user.name || 'N/A'}</td>
                    <td><code>${user.customer_id || 'N/A'}</code></td>
                    <td class="text-center">${user.subscriptions_deleted || 0}</td>
                    <td class="${statusClass}">${statusIcon} ${user.status}</td>
                `;
                
                if(user.error){
                    const errorRow = tbody.insertRow();
                    errorRow.innerHTML = `<td colspan="5" class="text-danger small">Error: ${user.error}</td>`;
                }
            });
        }
    }

    deleteBtn.addEventListener('click', async function(){
        const planName = planNameInput.value.trim();
        
        if(!planName){
            showError('Por favor ingresa el nombre del plan');
            return;
        }

        // Confirmación
        const confirmMsg = `¿Estás seguro de que deseas eliminar TODOS los usuarios del plan "${planName}"?\n\n` +
                          `Esta acción eliminará:\n` +
                          `- Todas las suscripciones de cada usuario\n` +
                          `- Los usuarios mismos de Baremetrics\n\n` +
                          `Esta operación NO se puede deshacer.`;
        
        if(!confirm(confirmMsg)){
            appendLog('Operación cancelada por el usuario', 'info');
            return;
        }

        // Reset UI
        hideError();
        progressSection.style.display = 'block';
        resultsSection.style.display = 'none';
        deleteBtn.disabled = true;
        planNameInput.disabled = true;
        document.getElementById('statusText').textContent = 'Procesando...';
        updateProgress(0, 0, 0, 0);
        
        appendLog(`Iniciando eliminación de usuarios del plan: ${planName}`, 'info');

        try {
            const response = await fetch("{{ route('admin.baremetrics.delete-users-by-plan') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify({ plan_name: planName })
            });

            const result = await response.json();

            if(result.success){
                appendLog('✅ Proceso completado exitosamente', 'success');
                document.getElementById('statusText').textContent = 'Completado';
                
                const data = result.data;
                updateProgress(data.total_processed, data.total_processed, data.deleted_count, data.failed_count);
                displayResults(data);
                
                appendLog(`Total procesados: ${data.total_processed}`, 'success');
                appendLog(`Exitosos: ${data.deleted_count}`, 'success');
                if(data.failed_count > 0){
                    appendLog(`Fallidos: ${data.failed_count}`, 'error');
                }
            } else {
                document.getElementById('statusText').textContent = 'Error';
                showError(result.message || 'Error desconocido');
            }

        } catch(error) {
            document.getElementById('statusText').textContent = 'Error';
            showError('Error de conexión: ' + error.message);
        } finally {
            deleteBtn.disabled = false;
            planNameInput.disabled = false;
        }
    });

    // Initial log
    appendLog('Sistema listo. Ingresa el nombre del plan y haz clic en "Eliminar Usuarios del Plan"', 'info');
})();
</script>
@endpush
@endsection
