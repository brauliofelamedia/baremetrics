@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h3>Suscripciones Activas</h3>
                </div>
                <div class="card-body">
                    @if (session('message'))
                        <div class="alert alert-info">
                            {{ session('message') }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <strong>Email:</strong> {{ $email }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Plan</th>
                                    <th>ID de Suscripción</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Fecha de Inicio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activeSubscriptions as $subInfo)
                                    <tr>
                                        <td>{{ $subInfo['plan']['name'] ?? 'N/A' }}</td>
                                        <td>{{ $subInfo['subscription_id'] ?? 'N/A' }}</td>
                                        <td>
                                            @if(isset($subInfo['subscription']['items']['data'][0]['price']['unit_amount']))
                                                {{ number_format($subInfo['subscription']['items']['data'][0]['price']['unit_amount'] / 100, 2) }} 
                                                {{ strtoupper($subInfo['subscription']['currency'] ?? 'USD') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Activa</span>
                                        </td>
                                        <td>
                                            @if(isset($subInfo['subscription']['created']))
                                                {{ \Carbon\Carbon::createFromTimestamp($subInfo['subscription']['created'])->format('d/m/Y') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.cancellations.manual', ['customer_id' => $subInfo['customer_id'], 'subscription_id' => $subInfo['subscription_id']]) }}" 
                                               class="btn btn-danger btn-sm">
                                                Cancelar Suscripción
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
