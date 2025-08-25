@extends('layouts.admin')

@section('title', 'Gestión de cancelaciones')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Indicador de estado del sistema de cancelación -->
                    <div id="cancellationSystemStatus" class="alert alert-info d-none mb-3">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <span>Verificando estado del sistema de cancelación...</span>
                        </div>
                    </div>

                    <!-- Formulario de búsqueda principal -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <form action="{{ route('admin.cancellations.search') }}" method="POST" id="searchForm">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="email" class="form-label">Correo electrónico</label>
                                        <input 
                                            type="email" 
                                            class="form-control @error('email') is-invalid @enderror" 
                                            id="email" 
                                            name="email" 
                                            placeholder="Ejemplo: jorge@felamedia.com"
                                            value="{{ old('email', $searchedEmail ?? '') }}"
                                            required
                                        >
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2" id="searchBtn">
                                            <i class="fas fa-search me-1"></i>Buscar cliente
                                        </button>
                                        <a href="{{ route('admin.cancellations.index') }}" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i>Limpiar
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Mensajes de estado -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(isset($error) && $error)
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ $error }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(isset($searchedEmail) && !$showSearchForm)
                        <div class="alert alert-success">
                            <strong><i class="fas fa-check-circle me-1"></i>Resultados para: {{ $searchedEmail }}</strong>
                            <a href="{{ route('admin.cancellations.index') }}" class="btn btn-sm btn-outline-success ms-3">
                                <i class="fas fa-plus me-1"></i>Nueva búsqueda
                            </a>
                        </div>
                    @endif
                    
                    @if(count($customers) > 0)
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Se encontraron <strong>{{ count($customers) }}</strong> cliente(s) 
                            @if(isset($searchedEmail))
                                con el email: <strong>{{ $searchedEmail }}</strong>
                            @endif
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID cliente</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Fecha de Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customers as $index => $customer)
                                        <tr>
                                            <td><code class="text-muted">{{ $customer['id'] }}</code></td>
                                            <td>{{ $customer['name'] ?? 'Sin nombre' }}</td>
                                            <td>{{ $customer['email'] ?? 'Sin email' }}</td>
                                            <td>{{ date('d/m/Y H:i', $customer['created']) }}</td>
                                            <td>
                                                <a href="{{ route('admin.cancellations.manual', $customer['id']) }}" target="_blank" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-pencil-alt me-1"></i>Cancelar
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif(!isset($showSearchForm) || !$showSearchForm)
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <p class="mb-0">No se encontraron customers. Utiliza el formulario de búsqueda para encontrar clientes.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Include this before the closing `body` tag -->
<script>
// Mejorar experiencia de usuario en el formulario de búsqueda
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const searchBtn = document.getElementById('searchBtn');
    const emailInput = document.getElementById('email');
    
    if (searchForm && searchBtn && emailInput) {
        searchForm.addEventListener('submit', function(e) {
            // Deshabilitar el botón para evitar múltiples envíos
            searchBtn.disabled = true;
            searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Buscando...';
            
            // Si hay algún error, re-habilitar después de un tiempo
            setTimeout(function() {
                if (searchBtn.disabled) {
                    searchBtn.disabled = false;
                    searchBtn.innerHTML = '<i class="fas fa-search me-1"></i>Buscar Customer';
                }
            }, 10000); // 10 segundos máximo
        });
        
        // Limpiar mensajes de error al empezar a escribir
        emailInput.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const errorDiv = this.nextElementSibling;
            if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                errorDiv.style.display = 'none';
            }
        });
    }
});
</script>
@endpush