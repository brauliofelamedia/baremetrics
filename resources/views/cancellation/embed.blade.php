<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Cancelación de Suscripción</title>
</head>
<body>
    <div id="message-loading" style="text-align:center; margin-top:40px;">
        <p style="font-size: 18px;font-family:Arial;">Cargando el formulario de cancelación...</p>
        @if(isset($email))
        <p style="font-size: 14px;font-family:Arial;color:#666;">Suscripción para: {{ $email }}</p>
        @endif
        @if(isset($selectedSubscription) && isset($selectedSubscription['plan']))
        <p style="font-size: 14px;font-family:Arial;color:#666;">
            Plan: {{ $selectedSubscription['plan']['name'] ?? 'No disponible' }}
            @if(isset($selectedSubscription['plan']['amount']) && isset($selectedSubscription['plan']['currency']))
            ({{ $selectedSubscription['plan']['amount'] }} {{ strtoupper($selectedSubscription['plan']['currency']) }})
            @endif
        </p>
        @endif
    </div>

    <button id="barecancel-trigger" target="_blank" style="display: none;">Cancelar</button>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
@php
    // Determinar el subscription_oid correcto ANTES de generar JavaScript
    $subscriptionOid = null;
    if (isset($selectedSubscription) && isset($selectedSubscription['baremetrics_subscription_oid'])) {
        $subscriptionOid = $selectedSubscription['baremetrics_subscription_oid'];
    } elseif (isset($selectedSubscription) && isset($selectedSubscription['subscription_id'])) {
        $subscriptionOid = $selectedSubscription['subscription_id'];
    } elseif (isset($subscription_id) && !empty($subscription_id)) {
        $subscriptionOid = $subscription_id;
    }
    
    // Construir el objeto params completo en PHP
    $params = [
        'access_token_id' => '65697af2-ed89-4a8c-bf8b-c7919fd325f2',
        'customer_oid' => $customer_id ?? '',
        'test_mode' => false
    ];
    
    if (!empty($subscriptionOid)) {
        $params['subscription_oid'] = $subscriptionOid;
    }
    
    // Convertir a JSON para generar JavaScript válido
    $paramsJson = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

<script>
!function(){
    if (window.barecancel && window.barecancel.created) {
        window.console && console.error && console.error("Barecancel snippet included twice.");
        return;
    }

    // Prepare params and validate before loading remote script to avoid 422 errors
    window.barecancel = window.barecancel || {};
    window.barecancel.created = true;

    var _params = {!! $paramsJson !!};
    console.log('Barecancel params:', _params);

    // Basic validation: ensure required fields are present
    if (!_params.customer_oid || _params.customer_oid === '') {
        var msg = 'Falta customer_oid en los parámetros de Barecancel. No se cargará el script.';
        console.error(msg, _params);
        document.getElementById('message-loading').innerHTML = '<p style="font-size: 18px;font-family:Arial;color:red;">' + msg + '</p>';
        return;
    }

    window.barecancel.params = _params;
    window.barecancel.params.callback_send = function(data) {
    console.log('Barecancel completado - Baremetrics ya canceló automáticamente la suscripción', data);
    
    $('#message-loading').html('<p style="font-size: 18px;font-family:Arial;">¡Listo! Tu suscripción ha sido cancelada exitosamente. Gracias por completar la encuesta.</p>');
    
    var ajaxData = {
        customer_id: "{{ $customer_id }}",
        subscription_id: "{{ $subscription_id ?? '' }}",
        cancellation_reason: data.reason || data.cancellation_reason || '',
        cancellation_comments: data.comment || data.comments || '',
        barecancel_data: JSON.stringify(data),
        sync_only: true,
        _token: "{{ csrf_token() }}"
    };
    
    @if(!empty($subscriptionOid))
    ajaxData.baremetrics_subscription_oid = "{{ $subscriptionOid }}";
    @endif
    
    $.ajax({
        url: "{{ route('cancellation.cancel.embed') }}",
        type: "POST",
        data: ajaxData,
        success: function(response) {
            setTimeout(function() {
                window.location.href = "{{ route('cancellation.index') }}";
            }, 3000);
        },
        error: function(xhr) {
            console.log('Error en sincronización (no crítico):', xhr);
            setTimeout(function() {
                window.location.href = "{{ route('cancellation.index') }}";
            }, 3000);
        }
    });
};
window.barecancel.params.callback_error = function(error) {
    $('#message-loading').html('<p style="font-size: 18px;font-family:Arial;color:red;">Error al cargar el formulario de cancelación de Baremetrics: ' + (error && error.message ? error.message : 'Error desconocido.') + '</p>');
    console.error('Error en Barecancel embed:', error);
};
}}();
</script>
<script>
    $(function() {
        setTimeout(function() {
            var btn = $('#barecancel-trigger');
            if (btn.length) {
                console.log('Activando botón de cancelación...');
                btn.trigger('click');
            } else {
                console.warn('Botón barecancel-trigger no encontrado');
            }
        }, 1000);
    });
</script>
</body>
</html>
