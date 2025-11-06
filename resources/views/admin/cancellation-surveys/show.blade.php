@extends('layouts.admin')

@section('title', 'Detalle del Survey de Cancelación')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Detalle del Survey de Cancelación</h1>
    <a href="{{ route('admin.cancellation-surveys.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left me-2"></i>Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fa-solid fa-clipboard-list me-2"></i>
                    Información del Survey
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td style="width: 200px;"><strong>ID del Survey:</strong></td>
                        <td>
                            <span class="badge bg-secondary">#{{ $survey->id }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Customer ID:</strong></td>
                        <td>
                            <code class="text-primary fs-6">{{ $survey->customer_id }}</code>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td>
                            @if($survey->email)
                                <i class="fa-solid fa-envelope me-2 text-muted"></i>
                                <a href="mailto:{{ $survey->email }}">{{ $survey->email }}</a>
                            @else
                                <span class="text-muted">No proporcionado</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Motivo de Cancelación:</strong></td>
                        <td>
                            <span class="badge bg-info fs-6">{{ $survey->reason }}</span>
                        </td>
                    </tr>
                    @if($survey->comment)
                    <tr>
                        <td><strong>Comentario:</strong></td>
                        <td>
                            <div class="p-3 bg-light rounded">
                                <i class="fa-solid fa-comment me-2 text-muted"></i>
                                {{ $survey->comment }}
                            </div>
                        </td>
                    </tr>
                    @endif
                    @if($survey->additional_comments)
                    <tr>
                        <td><strong>Comentarios Adicionales:</strong></td>
                        <td>
                            <div class="p-3 bg-light rounded">
                                <i class="fa-solid fa-comments me-2 text-muted"></i>
                                {{ $survey->additional_comments }}
                            </div>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>Fecha de Registro:</strong></td>
                        <td>
                            <i class="fa-solid fa-calendar me-2 text-muted"></i>
                            {{ $survey->created_at->format('d/m/Y H:i:s') }}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Última Actualización:</strong></td>
                        <td>
                            <i class="fa-solid fa-clock me-2 text-muted"></i>
                            {{ $survey->updated_at->format('d/m/Y H:i:s') }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    Información Adicional
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Tiempo desde el registro</small>
                    <strong>{{ $survey->created_at->diffForHumans() }}</strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Estado</small>
                    <span class="badge bg-success">Completado</span>
                </div>
                @if($survey->comment || $survey->additional_comments)
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Información completa</small>
                    <span class="badge bg-primary">
                        <i class="fa-solid fa-check me-1"></i>
                        Con comentarios
                    </span>
                </div>
                @else
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Información completa</small>
                    <span class="badge bg-warning">
                        <i class="fa-solid fa-exclamation me-1"></i>
                        Sin comentarios
                    </span>
                </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0">
                    <i class="fa-solid fa-shield-halved me-2"></i>
                    Acciones
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Esta vista es de solo lectura. No se pueden editar ni crear nuevos registros desde aquí.
                </p>
                <a href="{{ route('admin.cancellation-surveys.index') }}" class="btn btn-secondary w-100">
                    <i class="fa-solid fa-arrow-left me-2"></i>
                    Volver al Listado
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

