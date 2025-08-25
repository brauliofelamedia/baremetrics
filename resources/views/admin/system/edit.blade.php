@extends('layouts.admin')

@section('title', 'Editar Configuración del Sistema')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.system.index') }}">Sistema</a></li>
    <li class="breadcrumb-item active">Editar Configuración</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Editar Configuración del Sistema
                    </h5>
                    <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.system.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        
                        <!-- Nombre del Sistema -->
                        <div class="mb-3">
                            <label for="system_name" class="form-label">
                                <i class="fas fa-tag me-2"></i>
                                Nombre del Sistema <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control @error('system_name') is-invalid @enderror" 
                                   id="system_name" 
                                   name="system_name" 
                                   value="{{ old('system_name', $config->system_name) }}" 
                                   required>
                            @error('system_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descripción -->
                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left me-2"></i>
                                Descripción
                            </label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="Descripción del sistema...">{{ old('description', $config->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Logo del Sistema -->
                        <div class="mb-3">
                            <label for="system_logo" class="form-label">
                                <i class="fas fa-image me-2"></i>
                                Logo del Sistema
                            </label>
                            
                            @if($config->hasLogo())
                                <div class="mb-2">
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $config->getLogoUrl() }}" alt="Logo actual" class="me-3" style="max-height: 60px; border: 1px solid #dee2e6; border-radius: 8px; padding: 8px;">
                                        <div>
                                            <small class="text-muted">Logo actual</small>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            <input type="file" 
                                   class="form-control @error('system_logo') is-invalid @enderror" 
                                   id="system_logo" 
                                   name="system_logo" 
                                   accept="image/*">
                            <div class="form-text">
                                Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB.
                            </div>
                            @error('system_logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Favicon -->
                        <div class="mb-4">
                            <label for="system_favicon" class="form-label">
                                <i class="fas fa-star me-2"></i>
                                Favicon del Sistema
                            </label>
                            
                            @if($config->hasFavicon())
                                <div class="mb-2">
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $config->getFaviconUrl() }}" alt="Favicon actual" class="me-3" style="max-height: 32px; border: 1px solid #dee2e6; border-radius: 4px; padding: 4px;">
                                        <div>
                                            <small class="text-muted">Favicon actual</small>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            
                            <input type="file" 
                                   class="form-control @error('system_favicon') is-invalid @enderror" 
                                   id="system_favicon" 
                                   name="system_favicon" 
                                   accept="image/*">
                            <div class="form-text">
                                Formatos permitidos: ICO, PNG. Tamaño recomendado: 32x32 px. Tamaño máximo: 1MB.
                            </div>
                            @error('system_favicon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Guardar Cambios
                            </button>
                        </div>
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
    // Preview de imagen para logo
    const logoInput = document.getElementById('system_logo');
    const faviconInput = document.getElementById('system_favicon');
    
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            previewImage(e.target, 'logo-preview');
        });
    }
    
    if (faviconInput) {
        faviconInput.addEventListener('change', function(e) {
            previewImage(e.target, 'favicon-preview');
        });
    }
    
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                let previewContainer = document.getElementById(previewId);
                if (!previewContainer) {
                    previewContainer = document.createElement('div');
                    previewContainer.id = previewId;
                    previewContainer.className = 'mt-2';
                    input.parentNode.insertBefore(previewContainer, input.nextSibling);
                }
                
                previewContainer.innerHTML = `
                    <div class="d-flex align-items-center">
                        <img src="${e.target.result}" alt="Vista previa" style="max-height: 60px; border: 1px solid #dee2e6; border-radius: 8px; padding: 8px;">
                        <small class="text-muted ms-2">Vista previa</small>
                    </div>
                `;
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    }
});
</script>
@endpush
