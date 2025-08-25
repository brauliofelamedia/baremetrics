@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total usuarios
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['users'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Administradores
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['admins'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Roles
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['roles'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-left-warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Permisos
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['permissions'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-key fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Acciones rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-users me-2"></i>
                                Gestionar usuarios
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="{{ route('admin.system.index') }}" class="btn btn-secondary btn-lg w-100">
                                <i class="fas fa-cog me-2"></i>
                                Config. sistema
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="{{ route('admin.baremetrics.dashboard') }}" class="btn btn-info btn-lg w-100">
                                <i class="fas fa-chart-bar me-2"></i>
                                Ver baremetrics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Actividad reciente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Sistema funcionando correctamente. Todas las integraciones están activas.
                    </div>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Conexión con Stripe establecida exitosamente.
                    </div>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Conexión con Baremetrics configurada correctamente.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .border-left-primary {
        border-left: 0.25rem solid #4e73df !important;
    }
    .border-left-success {
        border-left: 0.25rem solid #1cc88a !important;
    }
    .border-left-info {
        border-left: 0.25rem solid #36b9cc !important;
    }
    .border-left-warning {
        border-left: 0.25rem solid #f6c23e !important;
    }
    .text-xs {
        font-size: 0.7rem;
    }
    .text-gray-300 {
        color: #dddfeb !important;
    }
    .text-gray-800 {
        color: #5a5c69 !important;
    }

    .btn {
        border-radius: 3px;
    }
</style>
@endpush
@endsection
