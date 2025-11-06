<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title') - {{ $systemConfig->getSystemName() }} Admin</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    
    <!-- Custom Admin Styles -->
    <style>
        :root {
            --bs-sidebar-width: 250px;
            --bs-navbar-height: 65px;
            --bs-sidebar-bg: #ffffff;
            --bs-sidebar-color: #6c757d;
            --bs-sidebar-hover-bg: rgba(108, 117, 125, 0.1);
            --bs-sidebar-active-bg: rgba(108, 117, 125, 0.15);
            --bs-header-bg: #ffffff;
            --bs-primary-fresh: #2e2671;
            --bs-secondary-fresh: #2e2671;
            --bs-accent-fresh: #decae5;
        }
        
        /* Estilo especial para mensaje de error "No se encontró ningún cliente con ese email" */
        .alert-no-customer {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
            border: 2px solid #b02a37;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-size: 0.875rem;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding-top: var(--bs-navbar-height);
        }
        
        .navbar {
            height: var(--bs-navbar-height);
            z-index: 1030;
        }
        
        .sidebar {
            position: fixed;
            top: var(--bs-navbar-height);
            bottom: 0;
            left: 0;
            z-index: 1020;
            padding: 0;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: var(--bs-sidebar-width);
            background-color: var(--bs-sidebar-bg);
            border-right: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: var(--bs-sidebar-color);
            padding: 8px 20px;
            border-radius: 0;
            margin: 2px 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
            font-size: 16px;
            border-left: 3px solid transparent;
        }
        
        .sidebar .nav-link:hover {
            color: #495057;
            background-color: var(--bs-sidebar-hover-bg);
            border-left: 3px solid #6c757d;
            transform: none;
        }
        
        .sidebar .nav-link.active {
            color: white;
            background-color: #544d94;
            border-left: 3px solid var(--bs-primary-fresh);
            transform: none;
        }
        
        .sidebar .nav-link i {
            margin-right: 12px;
            width: 18px;
            text-align: center;
            font-size: 16px;
        }
        
        .sidebar-heading {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 20px 20px 8px;
            color: #adb5bd;
            font-weight: 600;
            margin:0;
        }
        
        .navbar-brand {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            color: var(--bs-primary-fresh) !important;
            font-weight: 700;
        }
        
        .navbar-nav .nav-link {
            padding-left: 1rem;
            padding-right: 1rem;
            color: var(--bs-primary-fresh) !important;
            font-weight: 500;
        }
        
        .main-content {
            margin-left: var(--bs-sidebar-width);
            padding: 20px;
            min-height: calc(100vh - var(--bs-navbar-height));
        }
        
        .content-header {
            margin: 0 10px;
            margin-bottom: 20px;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border-radius: 0;
            border: none;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .card {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: none;
            border-radius: 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--bs-primary-fresh) 0%, var(--bs-secondary-fresh) 100%);
            border: none;
            border-radius: 0;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-group .btn {
            box-shadow: none;
            border-radius: 0;
        }
        
        /* Responsive */
        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                top: var(--bs-navbar-height);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .navbar-toggler {
                display: block;
            }
        }
        
        .navbar-toggler {
            display: none;
            border: none;
            background: none;
            color: var(--bs-primary-fresh);
            font-size: 1.25rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--bs-primary-fresh);
            background: rgba(102, 126, 234, 0.05);
        }
        
        .table-responsive {
            border-radius: 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .alert {
            border: none;
            border-radius: 0;
            backdrop-filter: blur(10px);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
            color: #721c24;
        }
        
        .alert-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0.05) 100%);
            color: var(--bs-primary-fresh);
        }
        
        .card-header {
            background: rgba(102, 126, 234, 0.05);
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            border-radius: 0 !important;
            font-weight: 600;
            color: var(--bs-primary-fresh);
        }
        
        .breadcrumb {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 0;
            padding: 0.5rem 1rem;
        }
        
        .breadcrumb-item a {
            color: var(--bs-primary-fresh);
            text-decoration: none;
        }
        
        /* Scrollbar personalizada para el sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #f8f9fa;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #dee2e6;
            border-radius: 0;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #adb5bd;
        }
        
        /* Animación suave para elementos interactivos */
        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1) !important;
            color: var(--bs-primary-fresh) !important;
            transform: translateX(5px);
            transition: all 0.2s ease;
        }
        
        /* Mejoras en responsive */
        @media (max-width: 991.98px) {
            .content-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .main-content {
                padding: 15px;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div id="app">
        <!-- Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg fixed-top d-flex align-items-center" style="background: var(--bs-header-bg); box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08); height: var(--bs-navbar-height);">
            <div class="container-fluid">
                <button class="navbar-toggler d-md-none" type="button" onclick="toggleSidebar()" style="border: none; background: none; color: var(--bs-primary-fresh);">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand d-flex align-items-center" href="{{ route('admin.dashboard') }}" style="color: var(--bs-primary-fresh) !important; font-weight: 700;">
                    <div style="width: 120px; height: 50px;">
                        <img src="{{asset('assets/img/logo.png')}}" class="img-fluid">
                    </div>
                </a>
                <div class="navbar-nav flex-row ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" style="color: var(--bs-primary-fresh) !important; font-weight: 500; background: rgba(102, 126, 234, 0.1); border-radius: 0; padding: 0.5rem 1rem;">
                            <div style="width: 30px; height: 30px; background: linear-gradient(135deg, var(--bs-primary-fresh) 0%, var(--bs-secondary-fresh) 100%); border-radius: 0; display: flex; align-items: center; justify-content: center; margin-right: 8px;">
                                <i class="fas fa-user" style="color: white; font-size: 12px;"></i>
                            </div>
                            {{ Auth::user()->name ?? 'Admin' }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border: none; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); border-radius: 0; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                            <li><a class="dropdown-item" href="#" style="border-radius: 0; margin: 5px;"><i class="fas fa-user me-2" style="color: var(--bs-primary-fresh);"></i>Perfil</a></li>
                            <li><a class="dropdown-item" href="#" style="border-radius: 0; margin: 5px;"><i class="fas fa-cog me-2" style="color: var(--bs-primary-fresh);"></i>Configuración</a></li>
                            <li><hr class="dropdown-divider" style="margin: 10px;"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item" style="border-radius: 0; margin: 5px;">
                                        <i class="fas fa-sign-out-alt me-2" style="color: #dc3545;"></i>Cerrar Sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="position-sticky">
                
                <ul class="nav flex-column" style="padding: 0 0 20px 0;">
                    <!-- Principal -->
                    <li class="sidebar-heading">
                        PRINCIPAL
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                           href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-home"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.cancellations.*') ? 'active' : '' }}" 
                           href="{{ route('admin.cancellations.index') }}">
                            <i class="fas fa-times-circle"></i>
                            Cancelaciones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.cancellation-surveys.*') ? 'active' : '' }}" 
                           href="{{ route('admin.cancellation-surveys.index') }}">
                            <i class="fas fa-clipboard-list"></i>
                            Resumen de encuestas
                        </a>
                    </li>
                
                    <!-- Sistema -->
                    <li class="sidebar-heading">
                        SISTEMA
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" 
                           href="{{ route('admin.users.index') }}">
                            <i class="fas fa-users"></i>
                            Usuarios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.system.*') ? 'active' : '' }}" 
                           href="{{ route('admin.system.index') }}">
                            <i class="fas fa-cogs"></i>
                            Configuraciones
                        </a>
                    </li>
                    <li class="nav-item" style="display: none;">
                        <a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" 
                           href="{{ route('admin.permissions.index') }}">
                            <i class="fas fa-key"></i>
                            Permisos
                        </a>
                    </li>
                    
                    <!-- Métricas y Datos -->
                    <li class="sidebar-heading">
                        MÉTRICAS Y DATOS
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.baremetrics.*') ? 'active' : '' }}" 
                           href="{{ route('admin.baremetrics.dashboard') }}">
                            <i class="fas fa-chart-bar"></i>
                            Baremetrics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.stripe.*') ? 'active' : '' }}" 
                           href="{{ route('admin.stripe.customers') }}">
                            <i class="fab fa-stripe-s"></i>
                            Stripe
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.ghl-comparison.*') ? 'active' : '' }}" 
                           href="{{ route('admin.ghl-comparison.index') }}">
                            <i class="fas fa-exchange-alt"></i>
                            Comparaciones GHL
                        </a>
                    </li>
                    
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Content Header -->
            <div class="content-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0 text-gray-800">@yield('title', 'Dashboard')</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                            @yield('breadcrumb')
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Page Content -->
            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggler = document.querySelector('.navbar-toggler');
            
            if (window.innerWidth < 768 && 
                !sidebar.contains(event.target) && 
                !toggler.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Auto-close mobile menu on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                document.getElementById('sidebar').classList.remove('show');
            }
        });
    </script>
    
    @stack('scripts')
</body>
</html>
