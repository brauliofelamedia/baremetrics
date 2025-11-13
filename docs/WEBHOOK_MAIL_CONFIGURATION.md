# Configuración de Envío de Correos vía Webhook

## Descripción

El sistema ahora envía correos electrónicos a través de un webhook en lugar de usar SMTP. Esto permite mayor flexibilidad y control sobre el envío de correos.

## Configuración

### Variable de Entorno

Agrega la siguiente variable a tu archivo `.env`:

```env
WEBHOOK_MAIL_URL=https://tu-webhook-url.com/api/send-email
```

**Importante:** Reemplaza `https://tu-webhook-url.com/api/send-email` con la URL real de tu webhook.

## Formato de Datos Enviados

El webhook recibirá una petición POST con el siguiente formato JSON:

```json
{
    "email": "destinatario@ejemplo.com",
    "subject": "Asunto del correo",
    "html": "<!DOCTYPE html>... (HTML completo de la plantilla)"
}
```

### Campos

- **email**: Dirección de correo del destinatario
- **subject**: Asunto del correo electrónico
- **html**: Contenido HTML completo de la plantilla del correo (incluye estilos inline y estructura completa)

## Respuesta Esperada

El webhook debe responder con un código de estado HTTP:
- **200-299**: Indica que el correo se envió exitosamente
- **Otros códigos**: Se considerará como error y se registrará en los logs

## Plantillas de Correo

Las siguientes plantillas están disponibles y se envían vía webhook:

1. **emails.cancellation-verification**: Correo de verificación de cancelación
2. **emails.cancellation-summary**: Resumen de proceso de cancelación para administradores
3. **emails.ghl-processing-report**: Reporte de procesamiento de usuarios GHL

## Servicio

El servicio `WebhookMailService` se encuentra en `app/Services/WebhookMailService.php` y proporciona dos métodos:

### `send($email, $subject, $view, $data = [])`

Envía un correo usando una plantilla Blade.

**Parámetros:**
- `$email`: Dirección de correo del destinatario
- `$subject`: Asunto del correo
- `$view`: Nombre de la vista Blade (ej: 'emails.cancellation-verification')
- `$data`: Array con los datos para la vista

**Ejemplo:**
```php
$webhookMailService->send(
    'usuario@ejemplo.com',
    'Verificación de cancelación',
    'emails.cancellation-verification',
    ['verificationUrl' => 'https://...', 'email' => 'usuario@ejemplo.com']
);
```

### `sendRaw($email, $subject, $html)`

Envía un correo con HTML personalizado directamente.

**Parámetros:**
- `$email`: Dirección de correo del destinatario
- `$subject`: Asunto del correo
- `$html`: Contenido HTML completo del correo

**Ejemplo:**
```php
$html = '<html><body><p>Contenido del correo</p></body></html>';
$webhookMailService->sendRaw('usuario@ejemplo.com', 'Asunto', $html);
```

## Logs

Todos los intentos de envío de correos se registran en los logs de Laravel:
- Éxitos: Nivel `info`
- Errores: Nivel `error`

Los logs incluyen información sobre:
- Email del destinatario
- Asunto del correo
- Estado de la respuesta del webhook
- Errores y excepciones

## Migración desde SMTP

Si anteriormente usabas SMTP, simplemente:
1. Agrega la variable `WEBHOOK_MAIL_URL` a tu `.env`
2. El sistema automáticamente usará el webhook en lugar de SMTP
3. No es necesario cambiar ninguna configuración adicional

## Troubleshooting

### El correo no se envía

1. Verifica que `WEBHOOK_MAIL_URL` esté configurado en el archivo `.env`
2. Verifica que la URL del webhook sea accesible
3. Revisa los logs de Laravel para ver errores específicos
4. Verifica que el webhook responda correctamente a las peticiones POST

### Error: "WEBHOOK_MAIL_URL no está configurado"

Asegúrate de agregar la variable `WEBHOOK_MAIL_URL` a tu archivo `.env` y ejecutar:
```bash
php artisan config:clear
```

