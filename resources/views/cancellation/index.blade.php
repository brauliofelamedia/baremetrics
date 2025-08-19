<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cancelaciones - Baremetrics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .customers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .customers-table th,
        .customers-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .customers-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #555;
        }
        .customers-table tr:hover {
            background-color: #f5f5f5;
        }
        .cancel-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .cancel-btn:hover {
            background-color: #c82333;
        }
        .no-customers {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        .customer-id {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .loading-message {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        .search-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }
        .search-form h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #495057;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-control:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #545b62;
        }
        .search-results {
            margin-top: 20px;
        }
        .customer-found {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cancelación de suscripción</h1>
        
        @if(isset($error))
            <div class="error-message">
                {{ $error }}
            </div>
        @endif

        @if(isset($showSearchForm) && $showSearchForm)
            <!-- Formulario de búsqueda por email -->
            <div class="search-form">
                <form action="{{ route('cancellations.search') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="email">Introduce el correo del cliente:</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               value="{{ old('email', $searchedEmail ?? '') }}"
                               required>
                    </div>
                    <button type="submit" class="btn btn-primary">Buscar cliente</button>
                </form>
            </div>
        @endif

        @if(isset($searchedEmail) && !$showSearchForm)
            <div class="customer-found">
                <strong>Resultados para: {{ $searchedEmail }}</strong>
                <form action="{{ route('cancellations.index') }}" method="GET" style="display: inline; margin-left: 15px;">
                    <button type="submit" class="btn btn-secondary">Nueva búsqueda</button>
                </form>
            </div>
        @endif
        
        @if(count($customers) > 0)
            <div class="search-results">
                <table class="customers-table">
                    <thead>
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
                                <td class="customer-id">{{ $customer['id'] }}</td>
                                <td>{{ $customer['name'] ?? 'Sin nombre' }}</td>
                                <td>{{ $customer['email'] ?? 'Sin email' }}</td>
                                <td>{{ date('d/m/Y H:i', $customer['created']) }}</td>
                                <td>
                                    <button type="button" 
                                            class="cancel-btn" 
                                            id="cancel-btn-{{ $index }}"
                                            data-customer-id="{{ $customer['id'] }}"
                                            onclick="initiateCancellation('{{ $customer['id'] }}', {{ $index }})">
                                        Cancelar Suscripción
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif(!isset($showSearchForm) || !$showSearchForm)
            <div class="no-customers">
                <p>No se encontraron customers.</p>
            </div>
        @endif
    </div>

    <!-- Include this before the closing `body` tag -->
    <script>
    !function(){if(window.barecancel&&window.barecancel.created)window.console&&console.error&&console.error("Barecancel snippet included twice.");else{window.barecancel={created:!0};var a=document.createElement("script");a.src="https://baremetrics-barecancel.baremetrics.com/js/application.js",a.async=!0;var b=document.getElementsByTagName("script")[0];b.parentNode.insertBefore(a,b),

    window.barecancel.params = {
      access_token_id: "65697af2-ed89-4a8c-bf8b-c7919fd325f2", // Your Cancellation API public key
      customer_oid: "", // Se establecerá dinámicamente
      callback_send: function(data) {
        // Once the cancellation is recorded in Baremetrics, you should actually cancel the customer.
        // This should use the same logic you used before adding Cancellation Insights. For example:
        console.log('Cancelación registrada en Baremetrics:', data);
        // Aquí puedes agregar la lógica para cancelar en tu sistema
        // axios.delete(`/api/users/${data.customer_oid}`)
      },
      callback_error: function(error) {
        // You can also catch any errors that happen when sending the cancellation event to Baremetrics.
        // For example, if Baremetrics returns that the customer does not have an active subscription.
        console.error('Error en la cancelación:', error);
        alert('Error al procesar la cancelación: ' + (error.message || 'Error desconocido'));
      }
    }
    }}();

    function initiateCancellation(customerId, buttonIndex) {
        // Verificar si ya hay una cancelación en proceso
        if (window.cancelInProgress) {
            alert('Ya hay una cancelación en proceso. Por favor, espera a que termine antes de iniciar otra.');
            return;
        }

        // Confirmar antes de proceder
        if (confirm('¿Estás seguro de que deseas cancelar la suscripción del cliente ' + customerId + '?')) {
            // Marcar que hay una cancelación en proceso
            window.cancelInProgress = true;
            
            // Deshabilitar todos los botones de cancelación
            disableAllCancelButtons();
            
            // Cambiar el texto del botón actual
            const currentButton = document.getElementById('cancel-btn-' + buttonIndex);
            if (currentButton) {
                currentButton.textContent = 'Procesando...';
                currentButton.style.backgroundColor = '#6c757d';
            }
            
            // Solo actualizar el customer_oid DESPUÉS de la confirmación
            window.barecancel.params.customer_oid = customerId;
            
            // Actualizar los callbacks para manejar el estado
            window.barecancel.params.callback_send = function(data) {
                console.log('Cancelación registrada en Baremetrics:', data);
                
                // Resetear el estado
                window.cancelInProgress = false;
                
                // Mostrar mensaje de éxito
                alert('Cancelación procesada exitosamente para el cliente: ' + customerId);
                
                // Recargar la página para limpiar el estado
                window.location.reload();
            };
            
            window.barecancel.params.callback_error = function(error) {
                console.error('Error en la cancelación:', error);
                
                // Resetear el estado
                window.cancelInProgress = false;
                enableAllCancelButtons();
                
                // Restaurar el botón actual
                if (currentButton) {
                    currentButton.textContent = 'Cancelar Suscripción';
                    currentButton.style.backgroundColor = '#dc3545';
                }
                
                alert('Error al procesar la cancelación: ' + (error.message || 'Error desconocido'));
            };
            
            // El evento de cancelación se manejará automáticamente por el script de Baremetrics
            console.log('Iniciando cancelación para customer:', customerId);
            
            // Trigger el modal de cancelación de Baremetrics
            if (window.barecancel && window.barecancel.trigger) {
                window.barecancel.trigger();
            } else {
                // Si no se puede triggear, resetear el estado
                window.cancelInProgress = false;
                enableAllCancelButtons();
                if (currentButton) {
                    currentButton.textContent = 'Cancelar Suscripción';
                    currentButton.style.backgroundColor = '#dc3545';
                }
                alert('Error: No se pudo inicializar el sistema de cancelación.');
            }
        } else {
            console.log('Cancelación abortada por el usuario');
        }
    }

    function disableAllCancelButtons() {
        const buttons = document.querySelectorAll('.cancel-btn');
        buttons.forEach(button => {
            button.disabled = true;
            button.style.opacity = '0.6';
            button.style.cursor = 'not-allowed';
        });
    }

    function enableAllCancelButtons() {
        const buttons = document.querySelectorAll('.cancel-btn');
        buttons.forEach(button => {
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
        });
    }

    // Inicializar el estado
    window.cancelInProgress = false;
    </script>
</body>
</html>