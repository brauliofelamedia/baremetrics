# Corrección de Datos de Usuarios Importados en Baremetrics

## Descripción

Este conjunto de comandos permite corregir los datos de usuarios que ya fueron importados desde GoHighLevel a Baremetrics, específicamente:

- **Fechas de inicio de suscripciones**: Corrige las fechas para que reflejen la fecha original de registro en GHL
- **Campos personalizados**: Actualiza todos los campos personalizados con datos reales de GHL
- **Cupones**: Detecta y actualiza información de cupones desde GHL

## Problema Identificado

Los usuarios importados aparecen con fechas de suscripción del día de hoy en lugar de sus fechas originales de registro, lo que hace que aparezcan como "inactivos" cuando deberían estar "activos".

## Comandos Disponibles

### 1. Comando Principal - Corrección Completa

```bash
php artisan baremetrics:fix-all-imported-data
```

**Opciones:**
- `--email=usuario@ejemplo.com` - Corregir un usuario específico
- `--all` - Corregir todos los usuarios importados
- `--dry-run` - Solo mostrar qué se haría sin hacer cambios
- `--limit=50` - Límite de usuarios a procesar (por defecto: 50)
- `--skip-dates` - Omitir corrección de fechas
- `--skip-fields` - Omitir actualización de campos personalizados
- `--skip-coupons` - Omitir actualización de cupones

**Ejemplos:**

```bash
# Corregir un usuario específico
php artisan baremetrics:fix-all-imported-data --email=luisa@ejemplo.com

# Corregir todos los usuarios (modo dry-run primero)
php artisan baremetrics:fix-all-imported-data --all --dry-run --limit=10

# Corregir solo fechas y campos, omitir cupones
php artisan baremetrics:fix-all-imported-data --all --skip-coupons

# Corrección completa de todos los usuarios
php artisan baremetrics:fix-all-imported-data --all --limit=100
```

### 2. Comando Específico - Solo Fechas y Campos

```bash
php artisan baremetrics:fix-imported-users
```

**Opciones:**
- `--email=usuario@ejemplo.com` - Corregir un usuario específico
- `--all` - Corregir todos los usuarios importados
- `--dry-run` - Solo mostrar qué se haría sin hacer cambios
- `--limit=50` - Límite de usuarios a procesar

### 3. Comando Específico - Solo Cupones

```bash
php artisan baremetrics:update-coupons
```

**Opciones:**
- `--email=usuario@ejemplo.com` - Actualizar un usuario específico
- `--all` - Actualizar todos los usuarios importados
- `--dry-run` - Solo mostrar qué se haría sin hacer cambios
- `--limit=50` - Límite de usuarios a procesar
- `--coupon=CODIGO` - Aplicar un cupón específico

## Cómo Funciona

### 1. Identificación de Usuarios Importados

Los comandos identifican usuarios importados buscando el campo personalizado `GHL: Migrate GHL = true` en Baremetrics.

### 2. Obtención de Datos Reales

Para cada usuario identificado:
- Se obtienen los datos reales desde GoHighLevel usando el email
- Se extraen las fechas originales (`dateAdded` o `dateCreated`)
- Se obtienen suscripciones, pagos y campos personalizados
- Se detectan cupones en campos personalizados o tags

### 3. Corrección de Fechas

- Se convierte la fecha original de GHL a timestamp Unix
- Se actualiza el campo `started_at` de todas las suscripciones del usuario
- Esto hace que las suscripciones aparezcan con la fecha correcta de inicio

### 4. Actualización de Campos Personalizados

Se actualizan los siguientes campos:
- `subscriptions` - Datos de suscripciones de GHL
- `payments` - Datos de pagos de GHL
- `total_paid` - Total pagado calculado
- `last_payment_date` - Fecha del último pago
- `last_payment_amount` - Monto del último pago
- `contact_id` - ID del contacto en GHL
- `tags` - Tags del usuario en GHL
- `date_added` - Fecha de registro original
- `ghl_migrate` - Marca como migrado

### 5. Detección y Actualización de Cupones

Se buscan cupones en:
- Campos personalizados: `coupon`, `coupon_code`, `discount_code`, `promo_code`, `codigo_descuento`
- Tags que contengan patrones como: `wowfriday`, `creetelo`, `descuento`, `promo`, `cupon`

## Recomendaciones de Uso

### 1. Siempre Usar Dry Run Primero

```bash
# Primero ver qué se haría
php artisan baremetrics:fix-all-imported-data --all --dry-run --limit=5

# Si todo se ve bien, ejecutar realmente
php artisan baremetrics:fix-all-imported-data --all --limit=50
```

### 2. Procesar en Lotes Pequeños

```bash
# Procesar de a 10 usuarios para evitar timeouts
php artisan baremetrics:fix-all-imported-data --all --limit=10
```

### 3. Verificar un Usuario Específico

```bash
# Corregir un usuario específico para verificar
php artisan baremetrics:fix-all-imported-data --email=usuario@ejemplo.com
```

## Logs y Monitoreo

Los comandos registran todas las operaciones en los logs de Laravel. Revisa los logs para:
- Errores durante el procesamiento
- Usuarios que no se pudieron procesar
- Detalles de las actualizaciones realizadas

## Solución al Problema de Fechas

El problema principal era que las suscripciones se creaban con `started_at = now()->timestamp` (fecha actual) en lugar de usar la fecha original de registro del usuario en GHL.

**Antes:**
```php
'started_at' => now()->timestamp, // Fecha actual
```

**Después:**
```php
$originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
if ($originalDate) {
    $startDate = new \DateTime($originalDate);
    $subscriptionData['started_at'] = $startDate->getTimestamp();
}
```

Esto hace que las suscripciones aparezcan con la fecha correcta de inicio, resolviendo el problema de usuarios que aparecen como "inactivos" cuando deberían estar "activos".

## Campos Personalizados Actualizados

Los comandos actualizan automáticamente todos los campos personalizados relevantes:

- **Datos de GHL**: Información completa del contacto
- **Pagos**: Historial de pagos y totales
- **Suscripciones**: Estado de suscripciones en GHL
- **Tags**: Todos los tags del usuario
- **Cupones**: Códigos de descuento detectados
- **Fechas**: Fechas originales de registro

Esto asegura que Baremetrics tenga la información más completa y actualizada de cada usuario importado.
