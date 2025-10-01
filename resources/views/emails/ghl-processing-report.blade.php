<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .stats-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
            font-weight: bold;
        }
        .errors-section {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 0.9em;
            color: #666;
        }
        .mode-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .mode-dry-run {
            background-color: #fff3cd;
            color: #856404;
        }
        .mode-real {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Reporte de Procesamiento de Usuarios GHL</h1>
        <p>Fecha: {{ $stats['start_time']->format('Y-m-d H:i:s') }}</p>
        <span class="mode-badge {{ $is_dry_run ? 'mode-dry-run' : 'mode-real' }}">
            {{ $is_dry_run ? 'MODO DRY-RUN' : 'MODO REAL' }}
        </span>
    </div>

    <h2>📈 Estadísticas Generales</h2>
    <table class="stats-table">
        <tr>
            <th>Métrica</th>
            <th>Valor</th>
        </tr>
        <tr>
            <td>Total de usuarios procesados</td>
            <td class="info">{{ $stats['total_processed'] }}</td>
        </tr>
        <tr>
            <td>Actualizaciones exitosas</td>
            <td class="success">{{ $stats['successful_updates'] }}</td>
        </tr>
        <tr>
            <td>Actualizaciones fallidas</td>
            <td class="error">{{ $stats['failed_updates'] }}</td>
        </tr>
        <tr>
            <td>Tasa de éxito</td>
            <td class="{{ $stats['total_processed'] > 0 && ($stats['successful_updates'] / $stats['total_processed']) >= 0.8 ? 'success' : 'warning' }}">
                {{ $stats['total_processed'] > 0 ? 
                    round(($stats['successful_updates'] / $stats['total_processed']) * 100, 2) . '%' : '0%' }}
            </td>
        </tr>
        <tr>
            <td>Duración del procesamiento</td>
            <td>{{ $stats['duration'] }} minutos</td>
        </tr>
        <tr>
            <td>Hora de inicio</td>
            <td>{{ $stats['start_time']->format('Y-m-d H:i:s') }}</td>
        </tr>
        <tr>
            <td>Hora de finalización</td>
            <td>{{ $stats['end_time']->format('Y-m-d H:i:s') }}</td>
        </tr>
    </table>

    @if($stats['total_processed'] > 0)
        <h2>📊 Análisis de Rendimiento</h2>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <p><strong>Usuarios procesados por minuto:</strong> 
                {{ round($stats['total_processed'] / max($stats['duration'], 1), 2) }}
            </p>
            <p><strong>Promedio de tiempo por usuario:</strong> 
                {{ $stats['total_processed'] > 0 ? round(($stats['duration'] * 60) / $stats['total_processed'], 2) : 0 }} segundos
            </p>
        </div>
    @endif

    @if(!empty($stats['errors']))
        <h2>⚠️ Errores Encontrados</h2>
        <div class="errors-section">
            <p><strong>Total de errores:</strong> {{ count($stats['errors']) }}</p>
            
            @if(count($stats['errors']) <= 20)
                <ul>
                    @foreach($stats['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @else
                <p><strong>Primeros 20 errores:</strong></p>
                <ul>
                    @foreach(array_slice($stats['errors'], 0, 20) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p><em>... y {{ count($stats['errors']) - 20 }} errores más</em></p>
            @endif
        </div>
    @else
        <h2>✅ Sin Errores</h2>
        <div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p class="success">¡Excelente! No se encontraron errores durante el procesamiento.</p>
        </div>
    @endif

    @if($is_dry_run)
        <h2>🔍 Modo Dry-Run</h2>
        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p><strong>⚠️ Este fue un procesamiento de prueba.</strong></p>
            <p>No se realizaron cambios reales en los datos. Los números mostrados representan lo que habría ocurrido si se hubiera ejecutado en modo real.</p>
        </div>
    @endif

    <h2>🔧 Detalles Técnicos</h2>
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0;">
        <ul>
            <li><strong>Comando ejecutado:</strong> php artisan ghl:process-all-users</li>
            <li><strong>Servidor:</strong> {{ config('app.name') }}</li>
            <li><strong>Entorno:</strong> {{ config('app.env') }}</li>
            <li><strong>Versión de Laravel:</strong> {{ app()->version() }}</li>
        </ul>
    </div>

    <div class="footer">
        <p>Este reporte fue generado automáticamente por el sistema de procesamiento de usuarios GHL.</p>
        <p>Para más información, revisa los logs del sistema o contacta al administrador.</p>
    </div>
</body>
</html>
