<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Proceso de Cancelación</title>
    <style>
        body {
            font-family: 'Nunito', Arial, sans-serif;
            line-height: 1.7;
            color: #333;
            background-color: #f9fafb;
            padding: 0;
            margin: 0;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            margin: 20px auto;
            max-width: 700px;
        }
        .email-header {
            background: linear-gradient(135deg, #ffffff 0%, #f4f4f4 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 180px;
            margin: 0 auto;
            display: block;
        }
        .email-body {
            padding: 30px;
            color: #4b5563;
            max-width: 700px;
            margin: 0 auto;
        }
        .email-title {
            color: #292272;
            font-size: 24px;
            margin: 20px 0 0;
            font-weight: 700;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin: 5px 0;
        }
        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-stuck {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .step-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .step-item:last-child {
            border-bottom: none;
        }
        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 14px;
        }
        .step-icon.completed {
            background-color: #10b981;
            color: white;
        }
        .step-icon.pending {
            background-color: #f59e0b;
            color: white;
        }
        .step-icon.not-started {
            background-color: #d1d5db;
            color: #6b7280;
        }
        .step-content {
            flex: 1;
        }
        .step-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }
        .step-time {
            font-size: 13px;
            color: #6b7280;
        }
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #9ca3af;
            text-align: center;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .user-info {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .user-info-item {
            margin: 8px 0;
            font-size: 14px;
        }
        .user-info-label {
            font-weight: 600;
            color: #374151;
        }
        .user-info-value {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="{{ asset('assets/img/logo.png') }}" alt="Logo" class="logo">
            <h1 class="email-title">Resumen de Proceso de Cancelación</h1>
        </div>
        
        <div class="email-body">
            <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #dc2626; font-weight: bold;">📧 NOTIFICACIÓN ADMINISTRATIVA</p>
                <p style="margin: 5px 0 0; color: #dc2626; font-size: 14px;">Actualización del proceso de cancelación</p>
            </div>

            <div class="user-info">
                <div class="user-info-item">
                    <span class="user-info-label">Usuario:</span>
                    <span class="user-info-value">{{ $tracking->email }}</span>
                </div>
                @if($tracking->customer_id)
                <div class="user-info-item">
                    <span class="user-info-label">Customer ID:</span>
                    <span class="user-info-value">{{ $tracking->customer_id }}</span>
                </div>
                @endif
                @if($tracking->stripe_customer_id)
                <div class="user-info-item">
                    <span class="user-info-label">Stripe Customer ID:</span>
                    <span class="user-info-value">{{ $tracking->stripe_customer_id }}</span>
                </div>
                @endif
            </div>

            <h2 style="color: #292272; font-size: 20px; margin: 25px 0 15px;">Estado del Proceso</h2>
            
            <div style="margin: 20px 0;">
                @if($tracking->process_completed)
                    <span class="status-badge status-completed">✅ PROCESO COMPLETADO</span>
                @elseif($status === 'cancelled_both')
                    <span class="status-badge status-completed">✅ CANCELACIONES COMPLETADAS</span>
                @else
                    <span class="status-badge status-stuck">⚠️ PROCESO INCOMPLETO</span>
                @endif
            </div>

            <div class="status-box">
                <h3 style="color: #111827; font-size: 16px; margin: 0 0 15px;">Progreso del Proceso</h3>
                
                <div class="step-item">
                    <div class="step-icon {{ $tracking->email_requested ? 'completed' : 'not-started' }}">
                        {{ $tracking->email_requested ? '✓' : '1' }}
                    </div>
                    <div class="step-content">
                        <div class="step-title">Solicitud de Correo de Cancelación</div>
                        @if($tracking->email_requested)
                            <div class="step-time">Completado: {{ $tracking->email_requested_at->format('d/m/Y H:i:s') }}</div>
                        @else
                            <div class="step-time">No iniciado</div>
                        @endif
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-icon {{ $tracking->survey_viewed ? 'completed' : ($tracking->email_requested ? 'pending' : 'not-started') }}">
                        {{ $tracking->survey_viewed ? '✓' : '2' }}
                    </div>
                    <div class="step-content">
                        <div class="step-title">Usuario Vio la Encuesta</div>
                        @if($tracking->survey_viewed)
                            <div class="step-time">Completado: {{ $tracking->survey_viewed_at->format('d/m/Y H:i:s') }}</div>
                        @elseif($tracking->email_requested)
                            <div class="step-time" style="color: #f59e0b;">⚠️ Pendiente - El usuario recibió el correo pero aún no ha visto la encuesta</div>
                        @else
                            <div class="step-time">No iniciado</div>
                        @endif
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-icon {{ $tracking->survey_completed ? 'completed' : ($tracking->survey_viewed ? 'pending' : 'not-started') }}">
                        {{ $tracking->survey_completed ? '✓' : '3' }}
                    </div>
                    <div class="step-content">
                        <div class="step-title">Encuesta Completada</div>
                        @if($tracking->survey_completed)
                            <div class="step-time">Completado: {{ $tracking->survey_completed_at->format('d/m/Y H:i:s') }}</div>
                        @elseif($tracking->survey_viewed)
                            <div class="step-time" style="color: #f59e0b;">⚠️ Pendiente - El usuario vio la encuesta pero no la completó</div>
                        @else
                            <div class="step-time">No iniciado</div>
                        @endif
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-icon {{ $tracking->baremetrics_cancelled ? 'completed' : ($tracking->survey_completed ? 'pending' : 'not-started') }}">
                        {{ $tracking->baremetrics_cancelled ? '✓' : '4' }}
                    </div>
                    <div class="step-content">
                        <div class="step-title">Cancelación en Baremetrics</div>
                        @if($tracking->baremetrics_cancelled)
                            <div class="step-time">Completado: {{ $tracking->baremetrics_cancelled_at->format('d/m/Y H:i:s') }}</div>
                        @elseif($tracking->survey_completed)
                            <div class="step-time" style="color: #f59e0b;">⚠️ Pendiente - La encuesta se completó pero aún no se canceló en Baremetrics</div>
                        @else
                            <div class="step-time">No iniciado</div>
                        @endif
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-icon {{ $tracking->stripe_cancelled ? 'completed' : ($tracking->survey_completed ? 'pending' : 'not-started') }}">
                        {{ $tracking->stripe_cancelled ? '✓' : '5' }}
                    </div>
                    <div class="step-content">
                        <div class="step-title">Cancelación en Stripe</div>
                        @if($tracking->stripe_cancelled)
                            <div class="step-time">Completado: {{ $tracking->stripe_cancelled_at->format('d/m/Y H:i:s') }}</div>
                        @elseif($tracking->survey_completed)
                            <div class="step-time" style="color: #f59e0b;">⚠️ Pendiente - La encuesta se completó pero aún no se canceló en Stripe</div>
                        @else
                            <div class="step-time">No iniciado</div>
                        @endif
                    </div>
                </div>
            </div>

            @if($tracking->current_step)
            <div class="info-box">
                <p style="margin: 0"><strong>Paso Actual:</strong> {{ ucfirst(str_replace('_', ' ', $tracking->current_step)) }}</p>
            </div>
            @endif

            @if(!$tracking->process_completed)
            <div class="warning-box">
                <p style="margin: 0; font-weight: bold; color: #92400e;">⚠️ ACCIÓN REQUERIDA</p>
                <p style="margin: 10px 0 0; color: #92400e;">
                    @if(!$tracking->survey_viewed)
                        El usuario solicitó el correo de cancelación pero aún no ha abierto el enlace de verificación.
                    @elseif(!$tracking->survey_completed)
                        El usuario abrió la encuesta pero no la completó. Podría necesitar seguimiento.
                    @elseif(!$tracking->baremetrics_cancelled || !$tracking->stripe_cancelled)
                        La encuesta fue completada pero falta completar la cancelación en alguno de los sistemas.
                    @endif
                </p>
            </div>
            @endif

            @if($tracking->notes)
            <div class="info-box" style="background-color: #f9fafb; border-left-color: #6b7280;">
                <p style="margin: 0; font-weight: bold;">Notas:</p>
                <p style="margin: 10px 0 0;">{{ $tracking->notes }}</p>
            </div>
            @endif

            <div class="footer">
                <p>Este es un correo automático de seguimiento del proceso de cancelación.</p>
                <p style="margin-top: 10px; font-size: 12px;">Generado el {{ now()->format('d/m/Y H:i:s') }}</p>
            </div>
        </div>
    </div>
</body>
</html>

