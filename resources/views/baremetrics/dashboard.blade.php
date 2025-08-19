<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baremetrics Dashboard</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 40px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #333; 
            border-bottom: 2px solid #007cba; 
            padding-bottom: 10px; 
        }
        .account-info { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 5px; 
            margin: 20px 0; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 5px; 
            border: 1px solid #f5c6cb; 
        }
        pre { 
            background: #e9ecef; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto; 
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #005a85;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard de Baremetrics</h1>
        
        <div>
            <a href="{{ route('baremetrics.account') }}" class="btn">Obtener Información de Cuenta (JSON)</a>
            <a href="{{ route('baremetrics.sources') }}" class="btn">Obtener Fuentes de Datos (JSON)</a>
            <a href="/" class="btn">Volver al Inicio</a>
        </div>

        @if($account)
            <div class="account-info">
                <h2>Información de la Cuenta</h2>
                <pre>{{ json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @else
            <div class="error">
                <h3>Error</h3>
                <p>No se pudo obtener la información de la cuenta de Baremetrics.</p>
                <p>Verifique:</p>
                <ul>
                    <li>Que el token BAREMETRICS_SANDBOX_KEY esté configurado correctamente en el archivo .env</li>
                    <li>Que la API de Baremetrics esté disponible</li>
                    <li>Que tenga permisos para acceder a la cuenta</li>
                </ul>
            </div>
        @endif

        <div style="margin-top: 30px;">
            <h3>Cómo usar el servicio:</h3>
            <pre>
// En un controlador
use App\Services\BaremetricsService;

public function __construct(BaremetricsService $baremetricsService)
{
    $this->baremetricsService = $baremetricsService;
}

public function getAccount()
{
    $account = $this->baremetricsService->getAccount();
    
    if ($account) {
        // Procesar datos de la cuenta
        return response()->json($account);
    }
    
    return response()->json(['error' => 'No se pudo obtener la cuenta'], 500);
}

public function getSources()
{
    $sources = $this->baremetricsService->getSources();
    
    if ($sources) {
        // Procesar fuentes de datos
        return response()->json($sources);
    }
    
    return response()->json(['error' => 'No se pudo obtener las fuentes'], 500);
}
            </pre>
        </div>
    </div>
</body>
</html>
