<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Cancelación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f7f7f7;
            border-radius: 5px;
            padding: 20px;
            border: 1px solid #e0e0e0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Verificación de Cancelación de Suscripción</h2>
        </div>
        
        <p>Hola,</p>
        
        <p>Hemos recibido una solicitud para cancelar tu suscripción. Para proceder con la cancelación, necesitamos verificar que eres tú quien lo solicita.</p>
        
        <p>Por favor, haz clic en el botón de abajo para continuar con el proceso de cancelación:</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Verificar Cancelación</a>
        </div>
        
        <p><strong>Importante:</strong> Este enlace expirará en 15 minutos por motivos de seguridad.</p>
        
        <p>Si no solicitaste cancelar tu suscripción, puedes ignorar este correo electrónico. Tu suscripción seguirá activa.</p>
        
        <p>Si tienes problemas con el botón, puedes copiar y pegar el siguiente enlace en tu navegador:</p>
        <p style="word-break: break-all;">{{ $verificationUrl }}</p>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no responda a este mensaje.</p>
        </div>
    </div>
</body>
</html>
