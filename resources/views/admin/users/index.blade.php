@extends('layouts.admin')

@section('title', 'Gestión de Usuarios')

@push('styles')
<style>
    .action-btn {
        width: 36px;
        height: 36px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .content-header {
        display: none;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Gestión de Usuarios</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-circle-plus me-2"></i>Nuevo Usuario
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Fecha de Registro</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                <span class="badge bg-primary me-1">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td>{{ $user->created_at->format('d/m/Y') }}</td>
                        <td>
                            @if($user->email_verified_at)
                                <span class="badge bg-success">Activo</span>
                            @else
                                <span class="badge bg-warning">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-success rounded-1 action-btn" title="Ver">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary rounded-1 action-btn" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" style="display: none;">
                                    @csrf
                                    <button type="submit" class="btn btn-{{ $user->email_verified_at ? 'warning' : 'success' }} rounded-1 action-btn" 
                                            title="{{ $user->email_verified_at ? 'Desactivar' : 'Activar' }}">
                                        <i class="fa-solid fa-{{ $user->email_verified_at ? 'pause' : 'play' }}"></i>
                                    </button>
                                </form>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" 
                                      onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger rounded-1 action-btn" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fa-solid fa-users text-muted fs-1"></i>
                            <p class="text-muted mt-2">No hay usuarios registrados</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="d-flex justify-content-center">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
