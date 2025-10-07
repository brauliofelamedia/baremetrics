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
                            <form method="POST" 
                                  action="{{ route('admin.ghl-comparison.import-all-users', $comparison) }}" 
                                  style="display: inline;"
                                  onsubmit="return confirm('¿Estás seguro de importar TODOS los usuarios faltantes?')">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i>
                                    Importar todos
                                </button>
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
                                        <button type="submit" class="btn btn-danger">
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
                        <h6><i class="fas fa-info-circle"></i> Tipos de Importación Disponibles:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong><i class="fas fa-upload text-success"></i> Importación Simple:</strong> Crea solo el cliente en Baremetrics
                            </div>
                            <div class="col-md-6">
                                <strong><i class="fas fa-plus-circle text-primary"></i> Importación con Plan:</strong> Crea cliente + plan + suscripción basado en los tags del usuario
                            </div>
                        </div>
                        <small class="text-muted">
                            <strong>Nota:</strong> La importación con plan detecta automáticamente si el usuario tiene tags como "creetelo_anual", "creetelo_mensual", etc. y crea el plan correspondiente.
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
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($missingUsers as $user)
                                        <tr>
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
                                                        <span class="badge text-bg-success">Importado</span>
                                                        @break
                                                    @case('failed')
                                                        <span class="badge text-bg-danger">Fallido</span>
                                                        @break
                                                @endswitch
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
