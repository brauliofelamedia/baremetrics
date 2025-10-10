# Ejemplo Práctico: Corrección de Usuario Importado

## Escenario

Tienes un usuario llamado "Luisa Kremenetsky" que fue importado desde GoHighLevel a Baremetrics, pero aparece con fechas incorrectas y campos personalizados incompletos.

## Paso 1: Verificar el Estado Actual

Primero, vamos a ver qué se haría sin hacer cambios reales:

```bash
php artisan baremetrics:fix-all-imported-data --email=luisa@ejemplo.com --dry-run
```

**Salida esperada:**
```
🔧 CORRECCIÓN COMPLETA DE USUARIOS IMPORTADOS
=============================================
Email específico: luisa@ejemplo.com
Procesar todos: No
Modo dry-run: Sí
Límite: 50 usuarios
Omitir fechas: No
Omitir campos: No
Omitir cupones: No

🔍 Procesando usuario específico: luisa@ejemplo.com
✅ Cliente encontrado: cust_abc123
👤 Usuario GHL: Luisa Kremenetsky
📅 Fecha original: 2024-09-15T10:30:00.000Z

📅 Corrigiendo fechas de suscripciones...
   🔍 DRY RUN: Se actualizaría started_at a 1726391400

📋 Actualizando campos personalizados...
   🔍 DRY RUN: Se actualizarían los campos personalizados

🎫 Actualizando cupones...
🎫 Cupón detectado: wowfriday_registro_nov2024
   🔍 DRY RUN: Se actualizaría el cupón a: wowfriday_registro_nov2024

🎉 ¡Corrección completa finalizada!
```

## Paso 2: Ejecutar la Corrección Real

Si todo se ve bien, ejecuta la corrección real:

```bash
php artisan baremetrics:fix-all-imported-data --email=luisa@ejemplo.com
```

**Salida esperada:**
```
🔧 CORRECCIÓN COMPLETA DE USUARIOS IMPORTADOS
=============================================
Email específico: luisa@ejemplo.com
Procesar todos: No
Modo dry-run: No
Límite: 50 usuarios
Omitir fechas: No
Omitir campos: No
Omitir cupones: No

🔍 Procesando usuario específico: luisa@ejemplo.com
✅ Cliente encontrado: cust_abc123
👤 Usuario GHL: Luisa Kremenetsky
📅 Fecha original: 2024-09-15T10:30:00.000Z

📅 Corrigiendo fechas de suscripciones...
   ✅ Suscripción actualizada con fecha: 2024-09-15 10:30:00

📋 Actualizando campos personalizados...
   ✅ Campos personalizados actualizados

🎫 Actualizando cupones...
🎫 Cupón detectado: wowfriday_registro_nov2024
   ✅ Cupón actualizado: wowfriday_registro_nov2024

🎉 ¡Corrección completa finalizada!
```

## Paso 3: Corrección Masiva (Opcional)

Para corregir todos los usuarios importados:

```bash
# Primero con dry-run para ver el alcance
php artisan baremetrics:fix-all-imported-data --all --dry-run --limit=10

# Si todo se ve bien, ejecutar en lotes pequeños
php artisan baremetrics:fix-all-imported-data --all --limit=20
```

## Resultado Esperado

Después de la corrección, el usuario "Luisa Kremenetsky" debería aparecer en Baremetrics con:

### ✅ Fechas Corregidas
- **Signed up**: Sep 2024 (fecha original)
- **Started**: 15/9/24 (fecha original de registro)
- **Status**: Active (ya no aparece como "Inactive")

### ✅ Campos Personalizados Actualizados
- **Total pagado**: $39 (calculado desde pagos reales)
- **Pagos procesados**: 1
- **Suscripciones**: 1
- **Tags**: wowfriday_registro_nov2024, creetelo_mensual, etc.
- **Contact ID**: ID real de GHL
- **Fecha de registro**: 2024-09-15T10:30:00.000Z

### ✅ Cupón Actualizado
- **Coupon**: wowfriday_registro_nov2024
- **Coupon Applied**: true
- **Discount Source**: GHL Import

## Comandos Específicos por Necesidad

### Solo Corregir Fechas
```bash
php artisan baremetrics:fix-imported-users --email=luisa@ejemplo.com --skip-fields --skip-coupons
```

### Solo Actualizar Campos Personalizados
```bash
php artisan baremetrics:fix-imported-users --email=luisa@ejemplo.com --skip-dates --skip-coupons
```

### Solo Actualizar Cupones
```bash
php artisan baremetrics:update-coupons --email=luisa@ejemplo.com
```

### Aplicar Cupón Específico
```bash
php artisan baremetrics:update-coupons --email=luisa@ejemplo.com --coupon=DESCUENTO50
```

## Monitoreo y Logs

Revisa los logs de Laravel para ver detalles de las operaciones:

```bash
tail -f storage/logs/laravel.log | grep "baremetrics"
```

## Verificación Final

Después de ejecutar la corrección, verifica en Baremetrics que:

1. ✅ La fecha de inicio de suscripción sea la fecha original de registro
2. ✅ El usuario aparezca como "Active" en lugar de "Inactive"
3. ✅ Los campos personalizados contengan datos reales de GHL
4. ✅ El cupón esté correctamente aplicado
5. ✅ El MRR y totales sean correctos

## Solución de Problemas

### Error: "No se encontró el cliente en Baremetrics"
- Verifica que el email sea exacto
- Asegúrate de que el usuario esté en el source correcto

### Error: "No se encontraron datos en GHL"
- Verifica que el usuario exista en GoHighLevel
- Revisa la conexión con la API de GHL

### Error: "No se encontró cupón"
- El usuario puede no tener cupón aplicado
- Revisa los campos personalizados en GHL
- Verifica los tags del usuario

### Timeout en Corrección Masiva
- Reduce el límite: `--limit=10`
- Procesa en lotes más pequeños
- Revisa la conexión a internet
