<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Realizando la cancelación - espera...</title>
</head>
<body>
    <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh;" id="message-loading">
        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Loading_2.gif" alt="Cargando..." style="width:80px; height:80px; margin-bottom: 20px;">
        <span style="font-size: 1.5rem; color: #333; text-align: center;">Espera un momento, estamos iniciando la cancelación...</span>
    </div>

    <button id="barecancel-trigger" target="_blank" style="display:none;"></button>
    <div id="barecancel-error" style="display:none; color: red; margin-top: 20px; text-align: center;"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include this before the closing `body` tag -->
<script>
!function(){if(window.barecancel&&window.barecancel.created)window.console&&console.error&&console.error("Barecancel snippet included twice.");else{window.barecancel={created:!0};var a=document.createElement("script");a.src="https://baremetrics-barecancel.baremetrics.com/js/application.js",a.async=!0;var b=document.getElementsByTagName("script")[0];b.parentNode.insertBefore(a,b),

window.barecancel.params = {
  access_token_id: "65697af2-ed89-4a8c-bf8b-c7919fd325f2", // Your Cancellation API public key
  customer_oid: "<example_customer_123>", // The provider id of this customer. For example, the Stripe Customer ID
  callback_send: function(data) {
    // Once the cancellation is recorded in Baremetrics, you should actually cancel the customer.
    // This should use the same logic you used before adding Cancellation Insights. For example:
    // axios.delete(`/api/users/example_customer_123`)
  },
  callback_error: function(error) {
    // You can also catch any errors that happen when sending the cancellation event to Baremetrics.
    // For example, if Baremetrics returns that the customer does not have an active subscription.
    console.error(error)
  }
}
}}();
</script>
    <script>
        !function(){if(window.barecancel&&window.barecancel.created)window.console&&console.error&&console.error("Barecancel snippet included twice.");else{window.barecancel={created:!0};var a=document.createElement("script");a.src="https://baremetrics-barecancel.baremetrics.com/js/application.js",a.async=!0;var b=document.getElementsByTagName("script")[0];b.parentNode.insertBefore(a,b),

            window.barecancel.params = {
                access_token_id: "65697af2-ed89-4a8c-bf8b-c7919fd325f2",
                customer_oid: "{{ $customer_id }}",
                test_mode: true,
                callback_send: function(data) {
                    // Once the cancellation is recorded in Baremetrics, you should actually cancel the customer.
                    // This should use the same logic you used before adding Cancellation Insights. For example:
                    // axios.delete(`/api/users/example_customer_123`)
                },
                callback_error: function(error) {
                    // Mostrar el error en el HTML
                    var loading = $('#message-loading');
                    var errorDiv = $('#barecancel-error');
                    loading.hide();
                    errorDiv.show();
                    console.log(error);
                    // Si el error es 422, mostrar mensaje personalizado
                    errorDiv.html('<h3>Ocurrio un error revisa la consola.</h3>');
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
            }, 2000); // 2 segundos
        });
    </script>
</body>
</html>



