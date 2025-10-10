@extends('layouts.admin')

@section('title', 'Usuarios Faltantes')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ $comparison->name }}
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.ghl-comparison.show', $comparison) }}" class="btn btn-secondary btn-sm">
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

                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <form method="GET" action="{{ route('admin.ghl-comparison.missing-users', $comparison) }}">
                                <div class="row">
                                    <div class="col-md-4">
                                        <select name="status" class="form-control">
                                            <option value="">Todos los estados</option>
                                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendientes</option>
                                            <option value="importing" {{ request('status') === 'importing' ? 'selected' : '' }}>Importando</option>
                                            <option value="imported" {{ request('status') === 'imported' ? 'selected' : '' }}>Importados</option>
                                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Fallidos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" 
                                               name="search" 
                                               class="form-control" 
                                               placeholder="Buscar por email, nombre o empresa..."
                                               value="{{ request('search') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <a href="{{ route('admin.ghl-comparison.missing-users', $comparison) }}" class="btn btn-secondary">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4 text-right">
                            <!-- Formularios ocultos para importación de prueba -->
                            <form method="POST" 
                                  id="import-5-form"
                                  action="{{ route('admin.ghl-comparison.import-all-users-with-plan', $comparison) }}"
                                  style="display: none;">
                                @csrf
                                <input type="hidden" name="limit" value="5">
                            </form>
                            
                            <form method="POST" 
                                  id="import-10-form"
                                  action="{{ route('admin.ghl-comparison.import-all-users-with-plan', $comparison) }}"
                                  style="display: none;">
                                @csrf
                                <input type="hidden" name="limit" value="10">
                            </form>

                            <!-- Botones de importación -->
                            <div class="btn-group" role="group">
                                <!-- Botón de importación simple -->
                                <button type="button" 
                                        class="btn btn-success btn-sm"
                                        onclick="if(confirm('¿Estás seguro de importar TODOS los usuarios faltantes (solo clientes)?')) { document.getElementById('import-simple-form').submit(); }">
                                    <i class="fas fa-upload"></i>
                                    Simple
                                </button>
                                
                                <!-- Botón de importación con plan -->
                                <button type="button"
                                        class="btn btn-primary btn-sm"
                                        onclick="if(confirm('¿Estás seguro de importar TODOS los usuarios con plan y suscripción?')) { document.getElementById('import-with-plan-form').submit(); }">
                                    <i class="fas fa-plus-circle"></i>
                                    Con Plan
                                </button>
                                
                                <!-- Botón prueba 5 usuarios -->
                                <button type="button" 
                                        class="btn btn-warning btn-sm"
                                        onclick="if(confirm('¿Importar primeros 5 usuarios como prueba?')) { document.getElementById('import-5-form').submit(); }">
                                    <i class="fas fa-vial"></i>
                                    5 Usuarios
                                </button>
                                
                                <!-- Botón prueba 10 usuarios -->
                                <button type="button" 
                                        class="btn btn-info btn-sm"
                                        onclick="if(confirm('¿Importar primeros 10 usuarios como prueba?')) { document.getElementById('import-10-form').submit(); }">
                                    <i class="fas fa-users"></i>
                                    10 Usuarios
                                </button>
                            </div>
                            
                            <!-- Formularios para importación -->
                            <form method="POST" 
                                  id="import-simple-form"
                                  action="{{ route('admin.ghl-comparison.import-all-users', $comparison) }}"
                                  style="display: none;">
                                @csrf
                            </form>
                            
                            <form method="POST" 
                                  id="import-with-plan-form"
                                  action="{{ route('admin.ghl-comparison.import-all-users-with-plan', $comparison) }}"
                                  style="display: none;">
                                @csrf
                            </form>
                            
                            @if(request('status') === 'imported')
                                @php
                                    $importedCount = $comparison->missingUsers()->where('import_status', 'imported')->count();
                                @endphp
                                @if($importedCount > 0)
                                    <form method="POST" 
                                          action="{{ route('admin.ghl-comparison.delete-imported-users', $comparison) }}" 
                                          style="display: inline; margin-left: 10px;"
                                          onsubmit="return confirm('⚠️ ADVERTENCIA: Esta acción eliminará PERMANENTEMENTE {{ $importedCount }} usuarios importados de Baremetrics y cambiará su estado a pendiente. ¿Estás seguro?')">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i>
                                            Borrar usuarios importados ({{ $importedCount }})
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- Información sobre tipos de importación -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Opciones de Importación:</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <strong><i class="fas fa-upload text-success"></i> Simple:</strong> Solo crea el cliente en Baremetrics
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fas fa-plus-circle text-primary"></i> Con Plan:</strong> Crea cliente + plan + suscripción
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fas fa-vial text-warning"></i> 5 Usuarios:</strong> Importa 5 usuarios de prueba con plan
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fas fa-users text-info"></i> 10 Usuarios:</strong> Importa 10 usuarios de prueba con plan
                            </div>
                        </div>
                        <small class="text-muted">
                            <strong>💡 Recomendación:</strong> Haz clic en "5 Usuarios" primero para probar la importación antes de importar todos.
                            El sistema detecta automáticamente el plan basado en tags (creetelo_anual, creetelo_mensual, etc.) y guarda el OID de cada cliente.
                        </small>
                    </div>

                    @if(request('status') === 'imported')
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Función de Borrado Masivo:</h6>
                            <p class="mb-1">
                                <strong>El botón "Borrar usuarios importados" eliminará PERMANENTEMENTE:</strong>
                            </p>
                            <ul class="mb-1">
                                <li>Todos los usuarios con estado "Importado" de Baremetrics</li>
                                <li>Sus suscripciones y planes asociados</li>
                                <li>Cambiará el estado de los usuarios a "Pendiente" para permitir re-importación</li>
                            </ul>
                            <small class="text-muted">
                                <strong>⚠️ ADVERTENCIA:</strong> Esta acción es irreversible. Los datos se eliminarán completamente de Baremetrics.
                            </small>
                        </div>
                    @endif

                    <!-- Formulario para importación masiva -->
                    <form method="POST" action="{{ route('admin.ghl-comparison.import-users', $comparison) }}" id="bulk-import-form">
                        @csrf
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" id="select-all">
                                        <i class="fas fa-check-square"></i>
                                        Seleccionar todos
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" id="select-none">
                                        <i class="fas fa-square"></i>
                                        Deseleccionar todos
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info" id="select-pending">
                                        <i class="fas fa-clock"></i>
                                        Solo pendientes
                                    </button>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success ml-2" id="import-selected" disabled>
                                    <i class="fas fa-upload"></i>
                                    Importar seleccionados (<span id="selected-count">0</span>)
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="master-checkbox">
                                        </th>
                                        <th>Email</th>
                                        <th>Nombre</th>
                                        <th>Empresa</th>
                                        <th>Teléfono</th>
                                        <th>Tags</th>
                                        <th>Estado</th>
                                        <th>OID Baremetrics</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($missingUsers as $user)
                                        <tr class="{{ $user->import_status === 'imported' ? 'table-success' : '' }}">
                                            <td>
                                                @if($user->import_status === 'pending')
                                                    <input type="checkbox" 
                                                           name="user_ids[]" 
                                                           value="{{ $user->id }}" 
                                                           class="user-checkbox">
                                                @endif
                                            </td>
                                            <td>{{ $user->email }}</td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->company ?: '-' }}</td>
                                            <td>{{ $user->phone ?: '-' }}</td>
                                            <td>
                                                @if($user->tags)
                                                    <small class="text-muted">{{ Str::limit($user->tags, 50) }}</small>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @switch($user->import_status)
                                                    @case('pending')
                                                        <span class="badge text-bg-warning">Pendiente</span>
                                                        @break
                                                    @case('importing')
                                                        <span class="badge text-bg-info">Importando</span>
                                                        @break
                                                    @case('imported')
                                                        <span class="badge text-bg-success">✓ Importado</span>
                                                        @if($user->import_notes)
                                                            <br><small class="text-muted">{{ $user->import_notes }}</small>
                                                        @endif
                                                        @break
                                                    @case('failed')
                                                        <span class="badge text-bg-danger">Fallido</span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td>
                                                @if($user->baremetrics_customer_id)
                                                    <code class="text-success" style="font-size: 11px;">{{ Str::limit($user->baremetrics_customer_id, 20) }}</code>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-link p-0 ml-1" 
                                                            onclick="copyToClipboard('{{ $user->baremetrics_customer_id }}')"
                                                            title="Copiar OID">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    @if($user->import_status === 'pending')
                                                        
                                                        <!-- Botón de importación con plan -->
                                                        <form method="POST" 
                                                              action="{{ route('admin.ghl-comparison.import-with-plan', $user) }}" 
                                                              style="display: inline;"
                                                              onsubmit="return confirm('¿Importar usuario con plan y suscripción basado en sus tags?')">
                                                            @csrf
                                                            <button type="submit" class="btn btn-primary" title="Importar con plan y suscripción">
                                                                <i class="fas fa-plus-circle"></i>
                                                            </button>
                                                        </form>
                                                    @elseif($user->import_status === 'failed')
                                                        <!-- Botón de reintento simple -->
                                                        <form method="POST" 
                                                              action="{{ route('admin.ghl-comparison.retry-import', $user) }}" 
                                                              style="display: inline;">
                                                            @csrf
                                                            <button type="submit" class="btn btn-warning" title="Reintentar importación simple">
                                                                <i class="fas fa-redo"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Botón de reintento con plan -->
                                                        <form method="POST" 
                                                              action="{{ route('admin.ghl-comparison.import-with-plan', $user) }}" 
                                                              style="display: inline;"
                                                              onsubmit="return confirm('¿Reintentar importación con plan y suscripción?')">
                                                            @csrf
                                                            <button type="submit" class="btn btn-info" title="Reintentar con plan y suscripción">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    
                                                    @if($user->import_status === 'imported')
                                                        <span class="text-success" title="Importado el {{ $user->imported_at->format('d/m/Y H:i') }}">
                                                            <i class="fas fa-check-circle"></i>
                                                        </span>
                                                    @endif
                                                    
                                                    @if($user->import_status === 'failed' && $user->import_error)
                                                        <button type="button" 
                                                                class="btn btn-danger" 
                                                                title="Ver error"
                                                                data-toggle="tooltip" 
                                                                data-placement="top" 
                                                                data-content="{{ $user->import_error }}">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                No hay usuarios faltantes
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $missingUsers->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Función para copiar OID al portapapeles
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                alert('OID copiado al portapapeles: ' + text);
            }, function(err) {
                console.error('Error al copiar: ', err);
            });
        } else {
            // Fallback para navegadores antiguos
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert('OID copiado al portapapeles: ' + text);
            } catch (err) {
                console.error('Error al copiar: ', err);
            }
            document.body.removeChild(textArea);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const masterCheckbox = document.getElementById('master-checkbox');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const selectedCountSpan = document.getElementById('selected-count');
        const importButton = document.getElementById('import-selected');
        
        // Master checkbox
        masterCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
        
        // Individual checkboxes
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Select all buttons
        document.getElementById('select-all').addEventListener('click', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            masterCheckbox.checked = true;
            updateSelectedCount();
        });
        
        document.getElementById('select-none').addEventListener('click', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            masterCheckbox.checked = false;
            updateSelectedCount();
        });
        
        document.getElementById('select-pending').addEventListener('click', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            masterCheckbox.checked = true;
            updateSelectedCount();
        });
        
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkedBoxes.length;
            
            selectedCountSpan.textContent = count;
            importButton.disabled = count === 0;
            
            // Update master checkbox state
            if (count === 0) {
                masterCheckbox.indeterminate = false;
                masterCheckbox.checked = false;
            } else if (count === userCheckboxes.length) {
                masterCheckbox.indeterminate = false;
                masterCheckbox.checked = true;
            } else {
                masterCheckbox.indeterminate = true;
            }
        }
        
        // Initialize tooltips
        jQuery('[data-toggle="tooltip"]').tooltip();
        
        // Initialize count
        updateSelectedCount();
    });
    </script>
@endpush
