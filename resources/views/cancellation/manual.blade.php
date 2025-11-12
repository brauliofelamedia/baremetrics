<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Realizando la cancelación - espera...</title>
</head>
<body>
    <div id="message-loading" style="text-align:center; margin-top:40px;">
        <p style="font-size: 18px;font-family:Arial;">Procesando tu cancelación, por favor espera...</p>
        @if(isset($email))
        <p style="font-size: 14px;font-family:Arial;color:#666;">Cancelando suscripción para: {{ $email }}</p>
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
    <script src="{{ config('services.baremetrics.barecancel_js_url') }}"></script>
<script>
!function(){if(window.barecancel&&window.barecancel.created)window.console&&console.error&&console.error("Barecancel snippet included twice.");else{window.barecancel={created:!0};var a=document.createElement("script");a.src="{{ config('services.baremetrics.barecancel_js_url') }}",a.async=!0;var b=document.getElementsByTagName("script")[0];b.parentNode.insertBefore(a,b),

    window.barecancel.params = {
    access_token_id: "65697af2-ed89-4a8c-bf8b-c7919fd325f2",
    customer_oid: "{{ $customer_id }}",
    test_mode: false,
    callback_send: function(data) {
        console.table('data:', data);
        $.ajax({
            url: "{{ route('cancellation.cancel') }}",
            type: "POST",
            data: {
                customer_id: "{{ $customer_id }}",
                subscription_id: "{{ $subscription_id }}",
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.success) {
                    
                    $('#message-loading').html('<p style="font-size: 18px;font-family:Arial;">Se ha cancelado la subscripción correctamente, serás redirigido en 5 segundos...</p>');

                    setTimeout(function() {
                        window.location.href = "{{ route('cancellation.index') }}";
                    }, 5000);
                }
                
            },
            error: function(xhr) {
            // Opcional: mostrar mensaje de error
                alert('Ocurrió un error al cancelar la suscripción.');
            }
        });
        // Once the cancellation is recorded in Baremetrics, you should actually cancel the customer.
        // This should use the same logic you used before adding Cancellation Insights. For example:
        // axios.delete(`/api/users/example_customer_123`)
    },
    callback_error: function(error) {
            // Mostrar mensaje de error y desactivar botón para evitar reintentos
            /*var errorDiv = document.getElementById('barecancel-error');
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Ocurrió un error al cancelar la suscripción: ' + (error && error.message ? error.message : 'Error desconocido.');
            }
            var btn = document.getElementById('barecancel-trigger');
            if (btn) {
                btn.disabled = true;
            }
            // Opcional: ocultar el mensaje de cargando
            var loadingDiv = document.getElementById('message-loading');
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }*/
        }
    }
}}();
</script>
<script>
    $(function() {
        setTimeout(function() {
            var btn = $('#barecancel-trigger');
            if (btn.length) {
                btn.trigger('click');
            }
        }, 1000);
    });
</script>
</body>
</html>



