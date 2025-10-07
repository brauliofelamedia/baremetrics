@extends('layouts.admin')

@section('title', 'Comparaciones GHL vs Baremetrics')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-tools">
                        <a href="{{ route('admin.ghl-comparison.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i>
                            Nueva comparación
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Archivo CSV</th>
                                    <th>Estado</th>
                                    <th>Estadísticas</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($comparisons as $comparison)
                                    <tr>
                                        <td>{{ $comparison->id }}</td>
                                        <td>{{ $comparison->name }}</td>
                                        <td>
                                            <small class="text-muted">{{ $comparison->csv_file_name }}</small>
                                        </td>
                                        <td>
                                            @switch($comparison->status)
                                                @case('pending')
                                                    <span class="badge text-bg-warning">Pendiente</span>
                                                    @break
                                                @case('processing')
                                                    <span class="badge text-bg-info">Procesando</span>
                                                    @break
                                                @case('completed')
                                                    <span class="badge text-bg-success">Completado</span>
                                                    @break
                                                @case('failed')
                                                    <span class="badge text-bg-danger">Fallido</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($comparison->status === 'completed')
                                                <div class="small">
                                                    <div>GHL: {{ number_format($comparison->total_ghl_users) }}</div>
                                                    <div>Baremetrics: {{ number_format($comparison->total_baremetrics_users) }}</div>
                                                    <div>Sincronizados: {{ $comparison->sync_percentage }}%</div>
                                                    <div class="text-danger">Faltantes: {{ number_format($comparison->users_missing_from_baremetrics) }}</div>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div>Creado: {{ $comparison->created_at->format('d/m/Y H:i') }}</div>
                                                @if($comparison->processed_at)
                                                    <div>Procesado: {{ $comparison->processed_at->format('d/m/Y H:i') }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('admin.ghl-comparison.show', $comparison) }}" 
                                                   class="btn btn-info" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                @if($comparison->status === 'completed')
                                                    <a href="{{ route('admin.ghl-comparison.missing-users', $comparison) }}" 
                                                       class="btn btn-warning" title="Ver usuarios faltantes">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                    
                                                    <a href="{{ route('admin.ghl-comparison.download-missing-users', $comparison) }}" 
                                                       class="btn btn-secondary" title="Descargar CSV">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                @endif
                                                
                                                <form method="POST" action="{{ route('admin.ghl-comparison.destroy', $comparison) }}" 
                                                      style="display: inline;" 
                                                      onsubmit="return confirm('¿Estás seguro de eliminar esta comparación?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i>
                                            No hay comparaciones registradas
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $comparisons->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
