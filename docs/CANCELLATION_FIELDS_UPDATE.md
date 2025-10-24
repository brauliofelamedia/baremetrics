# Actualización de Campos de Cancelación en Baremetrics

## Descripción General

Este documento explica cómo se actualizan los campos nativos de cancelación en Baremetrics cuando un usuario completa el survey de cancelación.

## Campos Nativos de Baremetrics Actualizados

Los siguientes campos son **campos nativos** de Baremetrics que se actualizan automáticamente:

### 1. **Active Subscription?** (Boolean)
- **Actualización automática**: Se actualiza a `false` cuando se cancela la suscripción en Stripe/Baremetrics
- **Ubicación**: Se actualiza mediante la API de Baremetrics cuando se elimina/cancela una suscripción

### 2. **Canceled?** (Boolean)
- **Actualización automática**: Se actualiza a `true` cuando se cancela la suscripción
- **Ubicación**: Se actualiza mediante la API de Baremetrics cuando se elimina/cancela una suscripción

### 3. **Cancellation Reason** (String)
- **Actualización manual**: Se registra mediante Barecancel Insights API
- **Ubicación**: Se registra usando el método `recordCancellationReason()` en `BaremetricsService`

## Flujo de Cancelación

### Paso 1: Usuario Completa el Survey
El usuario accede al survey de cancelación en:
- URL: `/gohighlevel/cancellation/survey/{customer_id}`
- Vista: `resources/views/cancellation/survey.blade.php`

### Paso 2: Procesamiento del Survey
Cuando el usuario envía el formulario, se ejecuta:
- Método: `CancellationController::surveyCancellationSave()`
- Archivo: `app/Http/Controllers/CancellationController.php`

El método realiza las siguientes acciones:

#### 2.1 Guardar Survey en Base de Datos
```php
CancellationSurvey::create([
    'customer_id' => $customer_id,
    'email' => $email,
    'reason' => $reason,
    'additional_comments' => $additional_comments,
]);
```

#### 2.2 Registrar Motivo en Barecancel Insights
```php
$barecancelResult = $this->baremetricsService->recordCancellationReason(
    $customer_id,      // OID del cliente en Baremetrics
    $reason,           // Motivo de cancelación seleccionado
    $additional_comments  // Comentarios adicionales opcionales
);
```

Este método envía una petición POST a:
- Endpoint: `https://api.baremetrics.com/v1/cancellations`
- Payload:
```json
{
    "customer_oid": "cus_XXXXXXXXX",
    "cancellation_reason": "Motivo seleccionado por el usuario",
    "comment": "Comentarios adicionales opcionales"
}
```

#### 2.3 Cancelar Suscripciones
Para cada suscripción activa:

**En Stripe:**
```php
$stripeResult = $this->stripeService->cancelActiveSubscription($customer_id, $subscriptionId);
```

**En Baremetrics:**
```php
$baremetricsResult = $this->baremetricsService->deleteSubscription($sourceId, $subscriptionOid);
```

## Implementación Técnica

### Nuevo Método en BaremetricsService

**Archivo**: `app/Services/BaremetricsService.php`

```php
/**
 * Registrar motivo de cancelación en Barecancel Insights
 * 
 * @param string $customerOid El OID del cliente en Baremetrics
 * @param string $reason El motivo de cancelación
 * @param string|null $additionalComments Comentarios adicionales
 * @return array|null Respuesta de la API
 */
public function recordCancellationReason(string $customerOid, string $reason, ?string $additionalComments = null): ?array
```

### Modificación en CancellationController

**Archivo**: `app/Http/Controllers/CancellationController.php`
**Método**: `surveyCancellationSave()`

Se agregó la llamada a `recordCancellationReason()` después de guardar el survey en la base de datos y antes de cancelar las suscripciones.

## Razones de Cancelación Disponibles

Las razones que el usuario puede seleccionar son:

1. "No conecté con el estilo, enfoque o dinámica de la comunidad"
2. "No logré dedicarle el tiempo que consideraba apropiado para aprovecharla"
3. "Cambiaron mis prioridades. No era lo que necesitaba en esta etapa de mi negocio o vida"
4. "No le encontré el valor que esperaba por el dinero invertido"
5. "Dificultades económicas o imprevistos financieros"
6. "Otros"

## Resultados en Baremetrics

Después de completar el proceso de cancelación, en Baremetrics se verán:

- ✅ **Active Subscription?**: `false` (No/a)
- ✅ **Canceled?**: `true` (Sí/a)
- ✅ **Cancellation Reason**: El motivo seleccionado por el usuario

## Logs para Depuración

El sistema registra logs en cada paso del proceso:

```php
// Al guardar el survey
\Log::info('Survey de cancelación guardado', [...]);

// Al registrar en Barecancel
\Log::info('Motivo de cancelación registrado en Barecancel', [...]);

// Si hay errores
\Log::error('Error registrando motivo en Barecancel', [...]);
```

## Pruebas

Para probar la funcionalidad:

1. Acceder a la URL del survey con un customer_id válido:
   ```
   https://baremetrics.local/gohighlevel/cancellation/survey/{customer_id}
   ```

2. Completar el formulario con:
   - Seleccionar un motivo
   - Agregar comentarios opcionales
   - Enviar el formulario

3. Verificar en los logs que:
   - El survey se guardó correctamente
   - El motivo se registró en Barecancel
   - Las suscripciones se cancelaron

4. Verificar en Baremetrics que los campos nativos se actualizaron correctamente

## Manejo de Errores

- Si falla el registro en Barecancel Insights, el proceso continúa (no bloquea la cancelación)
- Se registran warnings en los logs para revisión posterior
- Las cancelaciones en Stripe y Baremetrics se ejecutan independientemente del resultado de Barecancel

## Notas Importantes

1. **Barecancel Insights** es un servicio de Baremetrics que permite rastrear y analizar los motivos de cancelación
2. Los campos `Active Subscription?` y `Canceled?` se actualizan **automáticamente** cuando se cancela una suscripción
3. El campo `Cancellation Reason` **debe ser registrado manualmente** usando la API de Barecancel
4. El sistema guarda una copia del survey en la base de datos local para respaldo

## API de Barecancel

### Endpoint
```
POST https://api.baremetrics.com/v1/cancellations
```

### Headers
```
Authorization: Bearer {api_key}
Accept: application/json
Content-Type: application/json
```

### Payload
```json
{
    "customer_oid": "cus_XXXXXXXXX",
    "cancellation_reason": "Reason text",
    "comment": "Optional additional comments"
}
```

### Respuesta Exitosa
```json
{
    "cancellation": {
        "id": "cancel_XXXXXXXXX",
        "customer_oid": "cus_XXXXXXXXX",
        "reason": "Reason text",
        "comment": "Optional additional comments",
        "created_at": "2025-10-24T00:00:00Z"
    }
}
```

## Archivos Modificados

1. **app/Services/BaremetricsService.php**
   - Agregado método `recordCancellationReason()`

2. **app/Http/Controllers/CancellationController.php**
   - Modificado método `surveyCancellationSave()`
   - Agregada llamada a `recordCancellationReason()` después de guardar el survey

## Próximos Pasos

- Monitorear los logs para asegurar que los motivos se registran correctamente
- Verificar en Baremetrics que los campos se actualizan como se espera
- Considerar agregar notificaciones por email cuando se registra una cancelación
