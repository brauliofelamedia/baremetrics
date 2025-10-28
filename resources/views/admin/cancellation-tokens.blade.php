@extends('layouts.admin')

@section('title', 'Gestión de Tokens de Cancelación')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-key"></i>
                        Gestión de Tokens de Cancelación
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="refresh" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Información:</strong> Esta página muestra todos los tokens activos de cancelación. 
                                Los tokens expiran automáticamente en 30 minutos. Puedes invalidar tokens si es necesario.
                            </div>
                        </div>
                    </div>

                    @if(count($activeTokens) > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Email del Usuario</th>
                                        <th>Token</th>
                                        <th>Tiempo Restante</th>
                                        <th>Expira en</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeTokens as $tokenData)
                                        <tr>
                                            <td>
                                                <strong>{{ $tokenData['email'] }}</strong>
                                            </td>
                                            <td>
                                                <code class="text-muted">{{ substr($tokenData['token'], 0, 20) }}...</code>
                                                <button class="btn btn-sm btn-outline-secondary ml-2" onclick="copyToClipboard('{{ $tokenData['token'] }}')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </td>
                                            <td>
                                                @if($tokenData['expires_in_minutes'] > 0)
                                                    <span class="badge badge-{{ $tokenData['expires_in_minutes'] <= 5 ? 'danger' : ($tokenData['expires_in_minutes'] <= 10 ? 'warning' : 'success') }}">
                                                        {{ $tokenData['expires_in_minutes'] }} min
                                                    </span>
                                                @else
                                                    <span class="badge badge-secondary">Expirado</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($tokenData['expires_at'])
                                                    {{ $tokenData['expires_at']->format('H:i:s') }}
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="invalidateToken('{{ $tokenData['token'] }}')"
                                                            {{ $tokenData['expires_in_minutes'] <= 0 ? 'disabled' : '' }}>
                                                        <i class="fas fa-ban"></i> Invalidar
                                                    </button>
                                                    <a href="{{ route('cancellation.verify', ['token' => $tokenData['token']]) }}" 
                                                       class="btn btn-sm btn-info" target="_blank"
                                                       {{ $tokenData['expires_in_minutes'] <= 0 ? 'disabled' : '' }}>
                                                        <i class="fas fa-external-link-alt"></i> Verificar
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-key fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No hay tokens activos</h4>
                            <p class="text-muted">No hay solicitudes de cancelación pendientes en este momento.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Invalidación</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas invalidar este token de cancelación?</p>
                <p class="text-muted">Esta acción no se puede deshacer y el usuario no podrá usar este enlace para cancelar su suscripción.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmInvalidate">Sí, Invalidar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let tokenToInvalidate = null;

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Mostrar notificación de éxito
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.innerHTML = '<i class="fas fa-check"></i> Token copiado al portapapeles';
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    });
}

function invalidateToken(token) {
    tokenToInvalidate = token;
    $('#confirmModal').modal('show');
}

$('#confirmInvalidate').click(function() {
    if (!tokenToInvalidate) return;
    
    // Mostrar loading
    $(this).html('<i class="fas fa-spinner fa-spin"></i> Invalidando...').prop('disabled', true);
    
    $.ajax({
        url: '{{ route("admin.cancellation-tokens.invalidate") }}',
        method: 'POST',
        data: {
            token: tokenToInvalidate,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                // Mostrar mensaje de éxito
                showAlert('success', response.message);
                // Recargar la página después de un breve delay
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            showAlert('danger', response?.message || 'Error al invalidar el token');
        },
        complete: function() {
            $('#confirmModal').modal('hide');
            $('#confirmInvalidate').html('Sí, Invalidar').prop('disabled', false);
            tokenToInvalidate = null;
        }
    });
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    // Insertar al inicio del card-body
    $('.card-body').prepend(alertHtml);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}

// Auto-refresh cada 30 segundos para mantener la información actualizada
setInterval(function() {
    location.reload();
}, 30000);
</script>

<style>
.toast-notification {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.table code {
    font-size: 0.85em;
    background-color: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>
@endsection
