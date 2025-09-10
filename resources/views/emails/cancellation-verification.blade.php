<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Cancelación</title>
    <style>
        body {
            font-family: 'Nunito', Arial, sans-serif;
            line-height: 1.7;
            color: #333;
            background-color: #f9fafb;
            padding: 0;
            margin: 0;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            margin: 20px auto;
            max-width: 600px;
        }
        .email-header {
            background: linear-gradient(135deg, #ffffff 0%, #f4f4f4 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 180px;
            margin: 0 auto;
            display: block;
        }
        .email-body {
            padding: 30px;
            color: #4b5563;
            max-width: 600px;
            margin: 0 auto;
        }
        .email-title {
            color: #292272;
            font-size: 24px;
            margin: 20px 0 0;
            font-weight: 700;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #292272;
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .button:hover {
            background-color: #353092;
            transform: translateY(-1px);
        }
        .info-box {
            background-color: #f0f4ff;
            border-left: 4px solid #5a6fd8;
            padding: 15px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #9ca3af;
            text-align: center;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .help-text {
            font-size: 14px;
            color: #6b7280;
        }
        p {
            margin: 15px 0;
        }
        .link-text {
            color: #5a6fd8;
            word-break: break-all;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="{{ asset('assets/img/logo.png') }}" alt="Logo" class="logo">
            <h1 class="email-title">Verificación de Cancelación</h1>
        </div>
        
        <div class="email-body">
            <p>Hola,</p>
            
            <p>Hemos recibido una solicitud para cancelar tu suscripción. Para proceder con la cancelación, necesitamos verificar que eres tú quien lo solicita.</p>
            
            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="button" style="color:white;">Verificar cancelación</a>
            </div>
            
            <div class="info-box">
                <p style="margin: 0"><strong>Importante:</strong> Este enlace expirará en 15 minutos por motivos de seguridad.</p>
            </div>
            
            <p>Si no solicitaste cancelar tu suscripción, puedes ignorar este correo electrónico. Tu suscripción seguirá activa.</p>
            
            <p class="help-text">Si tienes problemas con el botón, puedes copiar y pegar el siguiente enlace en tu navegador:</p>
            <p class="link-text">{{ $verificationUrl }}</p>
            
            <div class="footer">
                <p>Este es un correo automático, por favor no responda a este mensaje.</p>
            </div>
        </div>
    </div>
</body>
</html>
