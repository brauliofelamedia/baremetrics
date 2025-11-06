@extends('layouts.admin')

@section('title', 'Surveys de Cancelación')

@push('styles')
<style>
    .action-btn {
        width: 36px;
        height: 36px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .content-header {
        display: none;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Surveys de Cancelación</h1>
    <div class="text-muted">
        <i class="fa-solid fa-clipboard-list me-2"></i>
        Total: {{ $surveys->total() }} registros
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Widgets de Estadísticas -->
<div class="row mb-4">
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Procesos</h6>
                        <h3 class="mb-0">{{ number_format($stats['total']) }}</h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-list fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm h-100 border-top border-success border-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Completados</h6>
                        <h3 class="mb-0 text-success">{{ number_format($stats['completed']) }}</h3>
                        <small class="text-muted">{{ $stats['completion_rate'] }}%</small>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm h-100 border-top border-warning border-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Incompletos</h6>
                        <h3 class="mb-0 text-warning">{{ number_format($stats['incomplete']) }}</h3>
                        <small class="text-muted">Requieren atención</small>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm h-100 border-top border-danger border-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Solo Correo</h6>
                        <h3 class="mb-0 text-danger">{{ number_format($stats['email_only']) }}</h3>
                        <small class="text-muted">No vieron encuesta</small>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-envelope fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Vieron Encuesta</h6>
                        <h4 class="mb-0">{{ number_format($stats['survey_viewed_not_completed']) }}</h4>
                        <small class="text-muted">No completaron</small>
                    </div>
                    <i class="fa-solid fa-eye fa-2x text-info"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Encuesta Completa</h6>
                        <h4 class="mb-0">{{ number_format($stats['survey_completed_not_cancelled']) }}</h4>
                        <small class="text-muted">Falta cancelación</small>
                    </div>
                    <i class="fa-solid fa-clipboard-check fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.cancellation-surveys.index') }}" class="row g-3">
            <div class="col-md-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="text" class="form-control" id="email" name="email" 
                       value="{{ request('email') }}" placeholder="Buscar por email...">
            </div>
            <div class="col-md-3">
                <label for="reason" class="form-label">Motivo</label>
                <input type="text" class="form-control" id="reason" name="reason" 
                       value="{{ request('reason') }}" placeholder="Buscar por motivo...">
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Desde</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Hasta</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="{{ request('date_to') }}">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Estado del Proceso</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completados</option>
                    <option value="incomplete" {{ request('status') === 'incomplete' ? 'selected' : '' }}>Incompletos</option>
                    <option value="email_only" {{ request('status') === 'email_only' ? 'selected' : '' }}>Solo Correo</option>
                    <option value="survey_viewed" {{ request('status') === 'survey_viewed' ? 'selected' : '' }}>Vieron Encuesta</option>
                    <option value="survey_completed" {{ request('status') === 'survey_completed' ? 'selected' : '' }}>Encuesta Completa</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2">
                    <i class="fa-solid fa-search me-2"></i>Buscar
                </button>
                <a href="{{ route('admin.cancellation-surveys.index') }}" class="btn btn-secondary">
                    <i class="fa-solid fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de resultados -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Customer ID</th>
                        <th>Email</th>
                        <th>Estado del Proceso</th>
                        <th>Fecha de Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($surveys as $survey)
                    @php
                        $tracking = $survey->tracking;
                        $status = $tracking ? $tracking->getCurrentStatus() : 'not_started';
                        $statusLabels = [
                            'completed' => ['label' => 'Completado', 'class' => 'success', 'icon' => 'check-circle'],
                            'cancelled_both' => ['label' => 'Cancelado Ambos', 'class' => 'success', 'icon' => 'check-double'],
                            'stripe_cancelled' => ['label' => 'Solo Stripe', 'class' => 'warning', 'icon' => 'exclamation-triangle'],
                            'baremetrics_cancelled' => ['label' => 'Solo Baremetrics', 'class' => 'warning', 'icon' => 'exclamation-triangle'],
                            'survey_completed' => ['label' => 'Encuesta Completa', 'class' => 'info', 'icon' => 'clipboard-check'],
                            'survey_viewed' => ['label' => 'Vió Encuesta', 'class' => 'primary', 'icon' => 'eye'],
                            'email_requested' => ['label' => 'Solo Correo', 'class' => 'secondary', 'icon' => 'envelope'],
                            'not_started' => ['label' => 'No Iniciado', 'class' => 'secondary', 'icon' => 'circle'],
                        ];
                        $statusInfo = $statusLabels[$status] ?? ['label' => 'Desconocido', 'class' => 'secondary', 'icon' => 'question'];
                    @endphp
                    <tr>
                        <td>#{{ $survey->id }}</td>
                        <td>
                            <code class="text-primary">{{ $survey->customer_id }}</code>
                        </td>
                        <td>
                            @if($survey->email)
                                <i class="fa-solid fa-envelope me-2 text-muted"></i>{{ $survey->email }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($tracking)
                                <span class="badge bg-{{ $statusInfo['class'] }}">
                                    <i class="fa-solid fa-{{ $statusInfo['icon'] }} me-1"></i>
                                    {{ $statusInfo['label'] }}
                                </span>
                                @if($tracking->current_step)
                                    <br><small class="text-muted">{{ ucfirst(str_replace('_', ' ', $tracking->current_step)) }}</small>
                                @endif
                            @else
                                <span class="badge bg-secondary">
                                    <i class="fa-solid fa-circle me-1"></i>
                                    Sin Seguimiento
                                </span>
                            @endif
                        </td>
                        <td>
                            <i class="fa-solid fa-calendar me-2 text-muted"></i>
                            {{ $survey->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            <button type="button" 
                                    class="btn btn-success rounded-1 action-btn view-survey-btn" 
                                    title="Ver Detalle"
                                    data-bs-toggle="modal"
                                    data-bs-target="#surveyModal"
                                    data-survey-id="{{ $survey->id }}"
                                    data-customer-id="{{ e($survey->customer_id) }}"
                                    data-stripe-customer-id="{{ e($survey->stripe_customer_id ?? '') }}"
                                    data-email="{{ e($survey->email ?? '') }}"
                                    data-reason="{{ e($survey->reason) }}"
                                    data-comment="{{ e($survey->comment ?? '') }}"
                                    data-additional-comments="{{ e($survey->additional_comments ?? '') }}"
                                    data-created-at="{{ $survey->created_at->format('d/m/Y H:i:s') }}"
                                    data-updated-at="{{ $survey->updated_at->format('d/m/Y H:i:s') }}"
                                    data-created-at-diff="{{ $survey->created_at->diffForHumans() }}"
                                    data-tracking-status="{{ $status }}"
                                    data-tracking-step="{{ $tracking->current_step ?? '' }}"
                                    data-email-requested="{{ $tracking && $tracking->email_requested ? '1' : '0' }}"
                                    data-survey-viewed="{{ $tracking && $tracking->survey_viewed ? '1' : '0' }}"
                                    data-survey-completed="{{ $tracking && $tracking->survey_completed ? '1' : '0' }}"
                                    data-baremetrics-cancelled="{{ $tracking && $tracking->baremetrics_cancelled ? '1' : '0' }}"
                                    data-stripe-cancelled="{{ $tracking && $tracking->stripe_cancelled ? '1' : '0' }}"
                                    data-process-completed="{{ $tracking && $tracking->process_completed ? '1' : '0' }}">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fa-solid fa-clipboard-list text-muted fs-1"></i>
                            <p class="text-muted mt-2">No se encontraron surveys de cancelación</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($surveys->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $surveys->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>

<!-- Modal para ver detalles del survey -->
<div class="modal fade" id="surveyModal" tabindex="-1" aria-labelledby="surveyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="surveyModalLabel">
                    <i class="fa-solid fa-clipboard-list me-2"></i>
                    Detalle del Survey de Cancelación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Información Principal -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-secondary text-white py-2">
                                <small class="fw-bold">
                                    <i class="fa-solid fa-hashtag me-1"></i>ID del Survey
                                </small>
                            </div>
                            <div class="card-body py-2">
                                <span class="badge bg-secondary fs-6" id="modal-survey-id">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-success text-white py-2">
                                <small class="fw-bold">
                                    <i class="fa-solid fa-check-circle me-1"></i>Estado
                                </small>
                            </div>
                            <div class="card-body py-2">
                                <span class="badge" id="modal-process-status">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Cliente -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fa-solid fa-user me-2"></i>Información del Cliente
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small mb-1">Customer ID</label>
                                <div>
                                    <code class="text-primary fs-6" id="modal-customer-id">-</code>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3" id="modal-stripe-customer-id-row" style="display: none;">
                                <label class="form-label text-muted small mb-1">Stripe Customer ID</label>
                                <div>
                                    <code class="text-success fs-6" id="modal-stripe-customer-id">-</code>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-muted small mb-1">Email</label>
                                <div id="modal-email">
                                    <span class="text-muted">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motivo de Cancelación -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0 fw-bold">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>Motivo de Cancelación
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="p-3 bg-light rounded">
                            <p class="mb-0 fw-semibold fs-6" id="modal-reason">-</p>
                        </div>
                    </div>
                </div>

                <!-- Comentarios -->
                <div class="card border-0 shadow-sm mb-4" id="modal-comments-section" style="display: none;">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fa-solid fa-comments me-2"></i>Comentarios
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="modal-comment-row" style="display: none;" class="mb-3">
                            <label class="form-label text-muted small mb-2 fw-semibold">Comentario</label>
                            <div class="p-3 bg-light rounded border-start border-3 border-info">
                                <i class="fa-solid fa-comment me-2 text-muted"></i>
                                <span id="modal-comment">-</span>
                            </div>
                        </div>
                        <div id="modal-additional-comments-row" style="display: none;">
                            <label class="form-label text-muted small mb-2 fw-semibold">Comentarios Adicionales</label>
                            <div class="p-3 bg-light rounded border-start border-3 border-info">
                                <i class="fa-solid fa-comments me-2 text-muted"></i>
                                <span id="modal-additional-comments">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seguimiento del Proceso -->
                <div class="card border-0 shadow-sm mb-4" id="modal-tracking-section">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fa-solid fa-route me-2"></i>Seguimiento del Proceso de Cancelación
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="progress-steps" id="modal-tracking-steps">
                            <!-- Se llenará con JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Fechas e Información Adicional -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-dark text-white py-2">
                                <small class="fw-bold">
                                    <i class="fa-solid fa-calendar me-1"></i>Fechas
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <small class="text-muted d-block mb-1">Fecha de Registro</small>
                                    <span id="modal-created-at" class="fw-semibold">-</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block mb-1">Última Actualización</small>
                                    <span id="modal-updated-at" class="fw-semibold">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-secondary text-white py-2">
                                <small class="fw-bold">
                                    <i class="fa-solid fa-info-circle me-1"></i>Información Adicional
                                </small>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <small class="text-muted d-block mb-1">Tiempo desde el registro</small>
                                    <strong id="modal-created-at-diff" class="text-primary">-</strong>
                                </div>
                                <div>
                                    <small class="text-muted d-block mb-1">Información completa</small>
                                    <span class="badge bg-primary" id="modal-comments-badge">
                                        <i class="fa-solid fa-check me-1"></i>
                                        Con comentarios
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-2"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('surveyModal');
    const viewButtons = document.querySelectorAll('.view-survey-btn');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Obtener datos del botón
            const surveyId = this.getAttribute('data-survey-id');
            const customerId = this.getAttribute('data-customer-id');
            const stripeCustomerId = this.getAttribute('data-stripe-customer-id');
            const email = this.getAttribute('data-email');
            const reason = this.getAttribute('data-reason');
            const comment = this.getAttribute('data-comment');
            const additionalComments = this.getAttribute('data-additional-comments');
            const createdAt = this.getAttribute('data-created-at');
            const updatedAt = this.getAttribute('data-updated-at');
            const createdAtDiff = this.getAttribute('data-created-at-diff');
            
            // Llenar el modal con los datos
            document.getElementById('modal-survey-id').textContent = '#' + surveyId;
            document.getElementById('modal-customer-id').textContent = customerId;
            
            // Stripe Customer ID
            const stripeCustomerIdRow = document.getElementById('modal-stripe-customer-id-row');
            if (stripeCustomerId && stripeCustomerId.trim() !== '') {
                document.getElementById('modal-stripe-customer-id').textContent = stripeCustomerId;
                stripeCustomerIdRow.style.display = '';
            } else {
                stripeCustomerIdRow.style.display = 'none';
            }
            
            // Email
            const emailCell = document.getElementById('modal-email');
            emailCell.innerHTML = ''; // Limpiar contenido
            if (email && email.trim() !== '') {
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-envelope me-2 text-muted';
                const link = document.createElement('a');
                link.href = 'mailto:' + email;
                link.textContent = email;
                emailCell.appendChild(icon);
                emailCell.appendChild(link);
            } else {
                const span = document.createElement('span');
                span.className = 'text-muted';
                span.textContent = 'No proporcionado';
                emailCell.appendChild(span);
            }
            
            document.getElementById('modal-reason').textContent = reason;
            
            // Comentarios - Mostrar sección si hay comentarios
            const commentsSection = document.getElementById('modal-comments-section');
            const commentRow = document.getElementById('modal-comment-row');
            const commentElement = document.getElementById('modal-comment');
            const additionalCommentsRow = document.getElementById('modal-additional-comments-row');
            const additionalCommentsElement = document.getElementById('modal-additional-comments');
            
            let hasComments = false;
            
            if (comment && comment.trim() !== '') {
                commentElement.textContent = comment;
                commentRow.style.display = '';
                hasComments = true;
            } else {
                commentRow.style.display = 'none';
            }
            
            if (additionalComments && additionalComments.trim() !== '') {
                additionalCommentsElement.textContent = additionalComments;
                additionalCommentsRow.style.display = '';
                hasComments = true;
            } else {
                additionalCommentsRow.style.display = 'none';
            }
            
            // Mostrar/ocultar sección de comentarios
            if (hasComments) {
                commentsSection.style.display = '';
            } else {
                commentsSection.style.display = 'none';
            }
            
            document.getElementById('modal-created-at').textContent = createdAt;
            document.getElementById('modal-updated-at').textContent = updatedAt;
            document.getElementById('modal-created-at-diff').textContent = createdAtDiff;
            
            // Estado de comentarios
            const commentsBadge = document.getElementById('modal-comments-badge');
            if ((comment && comment.trim() !== '') || (additionalComments && additionalComments.trim() !== '')) {
                commentsBadge.className = 'badge bg-primary';
                commentsBadge.innerHTML = '<i class="fa-solid fa-check me-1"></i>Con comentarios';
            } else {
                commentsBadge.className = 'badge bg-warning';
                commentsBadge.innerHTML = '<i class="fa-solid fa-exclamation me-1"></i>Sin comentarios';
            }
            
            // Seguimiento del proceso
            const trackingStatus = this.getAttribute('data-tracking-status');
            const emailRequested = this.getAttribute('data-email-requested') === '1';
            const surveyViewed = this.getAttribute('data-survey-viewed') === '1';
            const surveyCompleted = this.getAttribute('data-survey-completed') === '1';
            const baremetricsCancelled = this.getAttribute('data-baremetrics-cancelled') === '1';
            const stripeCancelled = this.getAttribute('data-stripe-cancelled') === '1';
            const processCompleted = this.getAttribute('data-process-completed') === '1';
            const trackingStep = this.getAttribute('data-tracking-step');
            
            // Actualizar estado del proceso
            const processStatusBadge = document.getElementById('modal-process-status');
            const statusLabels = {
                'completed': {text: 'Proceso Completo', class: 'bg-success'},
                'cancelled_both': {text: 'Cancelado Ambos Sistemas', class: 'bg-success'},
                'stripe_cancelled': {text: 'Solo Stripe', class: 'bg-warning'},
                'baremetrics_cancelled': {text: 'Solo Baremetrics', class: 'bg-warning'},
                'survey_completed': {text: 'Encuesta Completa', class: 'bg-info'},
                'survey_viewed': {text: 'Vió Encuesta', class: 'bg-primary'},
                'email_requested': {text: 'Solo Correo', class: 'bg-secondary'},
                'not_started': {text: 'No Iniciado', class: 'bg-secondary'}
            };
            const statusInfo = statusLabels[trackingStatus] || {text: 'Desconocido', class: 'bg-secondary'};
            processStatusBadge.className = 'badge ' + statusInfo.class;
            processStatusBadge.textContent = statusInfo.text;
            
            // Actualizar pasos del proceso
            const trackingSteps = document.getElementById('modal-tracking-steps');
            trackingSteps.innerHTML = `
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle ${emailRequested ? 'bg-success' : 'bg-secondary'} text-white d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                            ${emailRequested ? '<i class="fa-solid fa-check"></i>' : '1'}
                        </div>
                        <div class="ms-3">
                            <strong>${emailRequested ? '✓' : '○'} Solicitud de Correo</strong>
                            <small class="d-block text-muted">${emailRequested ? 'Completado' : 'Pendiente'}</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle ${surveyViewed ? 'bg-success' : (emailRequested ? 'bg-warning' : 'bg-secondary')} text-white d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                            ${surveyViewed ? '<i class="fa-solid fa-check"></i>' : '2'}
                        </div>
                        <div class="ms-3">
                            <strong>${surveyViewed ? '✓' : '○'} Usuario Vio la Encuesta</strong>
                            <small class="d-block text-muted">${surveyViewed ? 'Completado' : (emailRequested ? 'Pendiente' : 'No iniciado')}</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle ${surveyCompleted ? 'bg-success' : (surveyViewed ? 'bg-warning' : 'bg-secondary')} text-white d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                            ${surveyCompleted ? '<i class="fa-solid fa-check"></i>' : '3'}
                        </div>
                        <div class="ms-3">
                            <strong>${surveyCompleted ? '✓' : '○'} Encuesta Completada</strong>
                            <small class="d-block text-muted">${surveyCompleted ? 'Completado' : (surveyViewed ? 'Pendiente' : 'No iniciado')}</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle ${baremetricsCancelled ? 'bg-success' : (surveyCompleted ? 'bg-warning' : 'bg-secondary')} text-white d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                            ${baremetricsCancelled ? '<i class="fa-solid fa-check"></i>' : '4'}
                        </div>
                        <div class="ms-3">
                            <strong>${baremetricsCancelled ? '✓' : '○'} Cancelación en Baremetrics</strong>
                            <small class="d-block text-muted">${baremetricsCancelled ? 'Completado' : (surveyCompleted ? 'Pendiente' : 'No iniciado')}</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle ${stripeCancelled ? 'bg-success' : (surveyCompleted ? 'bg-warning' : 'bg-secondary')} text-white d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                            ${stripeCancelled ? '<i class="fa-solid fa-check"></i>' : '5'}
                        </div>
                        <div class="ms-3">
                            <strong>${stripeCancelled ? '✓' : '○'} Cancelación en Stripe</strong>
                            <small class="d-block text-muted">${stripeCancelled ? 'Completado' : (surveyCompleted ? 'Pendiente' : 'No iniciado')}</small>
                        </div>
                    </div>
                </div>
                ${trackingStep ? `<div class="alert alert-info mb-0"><small><strong>Paso actual:</strong> ${trackingStep.replace(/_/g, ' ')}</small></div>` : ''}
            `;
        });
    });
});
</script>
@endpush

