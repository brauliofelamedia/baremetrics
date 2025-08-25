@extends('layouts.admin')

@section('title', 'Dashboard de Créetelo')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-4">
                        <a href="{{ route('admin.baremetrics.account') }}" class="btn btn-primary me-2">
                            <i class="fas fa-user me-1"></i>Obtener Información de Cuenta (JSON)
                        </a>
                        <a href="{{ route('admin.baremetrics.sources') }}" class="btn btn-info me-2">
                            <i class="fas fa-database me-1"></i>Obtener Fuentes de Datos (JSON)
                        </a>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-home me-1"></i>Volver al Dashboard
                        </a>
                    </div>

                    @if($account)
                        <div class="card bg-light">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Información de la Cuenta</h4>
                            </div>
                            <div class="card-body">
                                <pre class="bg-dark text-light p-3 rounded"><code>{{ json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <h4 class="alert-heading">Error</h4>
                            <p>No se pudo obtener la información de la cuenta de {{ $systemConfig->getSystemName() }}.</p>
                            <hr>
                            <p class="mb-0">Verifique:</p>
                            <ul class="mb-0">
                                <li>Que el token BAREMETRICS_SANDBOX_KEY esté configurado correctamente en el archivo .env</li>
                                <li>Que la API de Baremetrics esté disponible</li>
                                <li>Que tenga permisos para acceder a la cuenta</li>
                            </ul>
                        </div>
                    @endif

                    <div class="card mt-4">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Cómo usar el servicio</h4>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3 rounded"><code>// En un controlador
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
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
