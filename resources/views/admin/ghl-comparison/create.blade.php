@extends('layouts.admin')

@section('title', 'Nueva Comparación GHL vs Baremetrics')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-tools">
                        <a href="{{ route('admin.ghl-comparison.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i>
                            Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.ghl-comparison.store') }}" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Nombre de la Comparación</label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name') }}" 
                                           placeholder="Ej: Comparación Octubre 2025"
                                           required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        Un nombre descriptivo para identificar esta comparación
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="csv_file">Archivo CSV de GHL</label>
                                    <div class="custom-file">
                                        <input type="file" 
                                               class="custom-file-input @error('csv_file') is-invalid @enderror" 
                                               id="csv_file" 
                                               name="csv_file" 
                                               accept=".csv,.txt"
                                               required>
                                        <label class="custom-file-label" for="csv_file">
                                            Seleccionar archivo CSV...
                                        </label>
                                    </div>
                                    @error('csv_file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        Archivo CSV exportado desde GoHighLevel (máximo 10MB)
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-info-circle"></i> Información Importante</h5>
                                    <ul class="mb-0">
                                        <li>El archivo CSV debe contener las columnas: Email, First Name, Last Name, Phone, Company Name, Tags, Created, Last Activity</li>
                                        <li>La comparación se realizará contra el entorno de <strong>PRODUCCIÓN</strong> de Baremetrics</li>
                                        <li>El proceso puede tomar varios minutos dependiendo del tamaño del archivo</li>
                                        <li>Se mostrará un resumen de usuarios encontrados y faltantes</li>
                                        <li>Los usuarios faltantes podrán ser importados masivamente a Baremetrics</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-play"></i>
                                        Iniciar Comparación
                                    </button>
                                    <a href="{{ route('admin.ghl-comparison.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i>
                                        Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar label del archivo seleccionado
    document.getElementById('csv_file').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'Seleccionar archivo CSV...';
        e.target.nextElementSibling.textContent = fileName;
    });
});
</script>
@endsection
