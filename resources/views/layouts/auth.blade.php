<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $systemConfig->getSystemName() }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Nunito', sans-serif;
        }

        .logo {
            max-width: 70%;
        }

        .auth-container {
            min-height: 100vh;
            background-image: url('{{ asset('assets/img/banner.png') }}');
            background-size: cover;
            background-repeat:no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .auth-logo {
            color: #667eea;
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .auth-title {
            color: #667eea;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .auth-subtitle {
            color: #6b7280;
            font-size: 18px;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 0;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            position: absolute;
            left: 3px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            background: none;
            border: none;
            z-index: 10;
        }

        .input-group .form-control {
            padding-left: 40px;
            border-radius: 5px!important;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .form-check-input {
            margin: 0;
        }

        .form-check-label {
            color: #374151;
            font-size: 14px;
            margin: 0;
        }

        .btn-auth {
            width: 100%;
            padding: 12px 16px;
            background: #292272;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 16px;
        }

        .btn-auth:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .auth-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .auth-link:hover {
            color: #5a6fd8;
            text-decoration: underline;
        }

        .auth-divider {
            margin: 24px 0;
            border: none;
            height: 1px;
            background: #e5e7eb;
        }

        .auth-admin-info {
            background: #f9fafb;
            padding: 16px;
            border-radius: 0;
            border: 1px solid #e5e7eb;
        }

        .auth-admin-info small {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.4;
        }

        .text-primary {
            color: #667eea !important;
        }

        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 12px;
            margin-top: 4px;
        }

        .is-invalid {
            border-color: #dc3545;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 0;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        @yield('content')
    </div>
</body>
</html>
