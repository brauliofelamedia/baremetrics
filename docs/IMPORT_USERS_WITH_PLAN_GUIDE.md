# Guía de Importación de Usuarios con Plan y Suscripción

## Resumen

Se ha implementado una nueva funcionalidad para importar usuarios faltantes desde la comparación GHL vs Baremetrics, creando automáticamente el cliente, plan y suscripción en Baremetrics basándose en los tags del usuario.

## Características

### 1. Importación Masiva con Plan

La nueva función `importAllUsersWithPlan` permite importar todos los usuarios pendientes creando:
- **Cliente** en Baremetrics con OID único
- **Suscripción** activa basada en los tags del usuario
- **Plan** detectado automáticamente (creetelo_mensual o creetelo_anual)

### 2. Modo Prueba

Antes de importar todos los usuarios, puedes hacer pruebas con:
- **5 usuarios**: Importación rápida para validar
- **10 usuarios**: Prueba más amplia antes de importación masiva

### 3. Seguimiento de Importación

Cada usuario importado guarda:
- **OID de Baremetrics**: Identificador único del cliente en `baremetrics_customer_id`
- **Estado**: Cambia de `pending` a `imported`
- **Notas de importación**: Incluye el plan asignado y si se creó la suscripción
- **Fecha de importación**: Timestamp de cuando se importó

## Ubicación

**URL**: `https://baremetrics.local/admin/ghl-comparison/{comparison_id}/missing-users`

Donde `{comparison_id}` es el ID de la comparación que quieres gestionar.

## Uso

### Paso 1: Acceder a la Vista

1. Navega a **Admin > Comparaciones GHL vs Baremetrics**
2. Selecciona una comparación completada
3. Haz clic en **"Ver Usuarios Faltantes"**

### Paso 2: Importación de Prueba (Recomendado)

Antes de importar todos los usuarios, realiza una prueba:

1. Haz clic en el botón **"Prueba"** (amarillo)
2. Selecciona **"Importar 5 usuarios"**
3. Confirma la acción
4. Espera a que se complete el proceso
5. Verifica los resultados:
   - Los usuarios deben tener estado **"✓ Importado"**
   - Debe aparecer el **OID de Baremetrics** (código único)
   - Las notas deben indicar el plan asignado

### Paso 3: Verificar Usuarios Importados

1. Cambia el filtro de estado a **"Importados"**
2. Revisa la tabla:
   - **Estado**: Verde con ✓ Importado
   - **OID Baremetrics**: Código único (puedes copiarlo haciendo clic en el ícono de copiar)
   - **Notas**: Información del plan y suscripción

### Paso 4: Importación Masiva

Una vez validada la prueba:

1. Regresa al filtro **"Pendientes"**
2. Haz clic en **"Importar Todos (Con Plan)"** (botón azul)
3. Confirma la acción
4. El sistema procesará todos los usuarios pendientes
5. Al finalizar, verás un resumen:
   - Usuarios importados exitosamente
   - Usuarios que fallaron (si los hay)

## Tipos de Importación

### Importación Simple
- **Botón**: Verde "Importar Todos (Simple)"
- **Crea**: Solo el cliente en Baremetrics
- **Uso**: Cuando no necesitas plan ni suscripción

### Importación con Plan
- **Botón**: Azul "Importar Todos (Con Plan)"
- **Crea**: Cliente + Plan + Suscripción
- **Detección automática**: Basada en tags del usuario
- **Planes soportados**:
  - `creetelo_mensual` o `créetelo_mensual`
  - `creetelo_anual` o `créetelo_anual`

## Detección de Planes

El sistema busca automáticamente en los tags del usuario los siguientes patrones:

- **Plan Mensual**: Tags que contengan "creetelo_mensual" o "créetelo_mensual"
- **Plan Anual**: Tags que contengan "creetelo_anual" o "créetelo_anual"

Si no se encuentra un plan en los tags, la importación fallará y se marcará el usuario como "Fallido" con un mensaje de error.

## Información Guardada

Para cada usuario importado, se guarda:

### En la tabla `missing_users`:
- `import_status`: "imported"
- `baremetrics_customer_id`: OID único del cliente (ej: "ghl_17339485821234")
- `imported_at`: Fecha y hora de importación
- `import_notes`: "Importado con plan: creetelo_mensual - Suscripción creada"
- `import_error`: NULL (o mensaje si falló)

### En Baremetrics:
- **Customer**: Con OID único
- **Subscription**: Con estado "active" y fecha de inicio
- **Plan**: Asociado a la suscripción

## Ejemplos de Uso

### Ejemplo 1: Importar 5 Usuarios de Prueba

```
1. Ir a: https://baremetrics.local/admin/ghl-comparison/20/missing-users
2. Clic en botón "Prueba" > "Importar 5 usuarios"
3. Confirmar
4. Verificar que los 5 usuarios aparezcan con estado "✓ Importado"
5. Copiar el OID de uno de ellos para verificar en Baremetrics
```

### Ejemplo 2: Importar Todos los Usuarios Pendientes

```
1. Ir a: https://baremetrics.local/admin/ghl-comparison/20/missing-users
2. Verificar que hay usuarios pendientes (filtro en "Pendientes")
3. Clic en "Importar Todos (Con Plan)"
4. Confirmar la acción
5. Esperar a que termine el proceso
6. Cambiar filtro a "Importados" para ver resultados
```

### Ejemplo 3: Verificar un Usuario Importado

```
1. Filtrar por "Importados"
2. Buscar el usuario por email
3. Verificar:
   - Estado: ✓ Importado
   - OID Baremetrics: ghl_xxxxxxxxxxxxx
   - Notas: "Importado con plan: creetelo_mensual - Suscripción creada"
4. Copiar OID (clic en ícono de copiar)
5. Buscar en Baremetrics con ese OID
```

## Columnas de la Tabla

| Columna | Descripción | Ejemplo |
|---------|-------------|---------|
| Email | Correo del usuario | `usuario@ejemplo.com` |
| Nombre | Nombre completo | `Juan Pérez` |
| Empresa | Nombre de empresa (opcional) | `Mi Empresa` |
| Teléfono | Teléfono (opcional) | `+1234567890` |
| Tags | Tags del usuario en GHL | `creetelo_mensual, activo` |
| Estado | Estado de importación | `✓ Importado`, `Pendiente`, `Fallido` |
| OID Baremetrics | Identificador único en Baremetrics | `ghl_17339485821234` |
| Acciones | Botones de acción | Importar, Ver error, etc. |

## Identificación de Usuarios Importados

Los usuarios importados se pueden identificar fácilmente porque:

1. **Fila verde**: La fila completa tiene fondo verde claro
2. **Estado verde**: Badge con ✓ "Importado"
3. **OID visible**: Código único en verde
4. **Notas de importación**: Información adicional bajo el estado

## Logs y Debugging

Todos los procesos se registran en los logs de Laravel:

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Buscar importaciones
grep "IMPORTACIÓN MASIVA" storage/logs/laravel.log

# Ver errores de importación
grep "Error importando usuario" storage/logs/laravel.log
```

## Mensajes de Log

Durante la importación verás logs como:

```
[2024-10-10 10:00:00] local.INFO: === INICIANDO IMPORTACIÓN MASIVA CON PLAN ===
[2024-10-10 10:00:01] local.INFO: Procesando usuario #1/5
[2024-10-10 10:00:02] local.INFO: Plan identificado {"plan_oid":"creetelo_mensual","email":"usuario@ejemplo.com"}
[2024-10-10 10:00:03] local.INFO: Creando cliente en Baremetrics {"oid":"ghl_17339485821234","email":"usuario@ejemplo.com"}
[2024-10-10 10:00:04] local.INFO: Cliente creado exitosamente
[2024-10-10 10:00:05] local.INFO: Creando suscripción en Baremetrics
[2024-10-10 10:00:06] local.INFO: Suscripción creada exitosamente
[2024-10-10 10:00:07] local.INFO: Usuario importado exitosamente
[2024-10-10 10:00:10] local.INFO: === IMPORTACIÓN MASIVA COMPLETADA ===
```

## Manejo de Errores

### Usuario sin Plan Tag

Si un usuario no tiene tags que identifiquen un plan:

```
Estado: Fallido
Error: "No se pudo determinar el plan del cliente. Tags: otros_tags"
```

**Solución**: Agregar manualmente el tag correcto en GHL y reintentar.

### Error al Crear Cliente

Si hay error al crear el cliente en Baremetrics:

```
Estado: Fallido
Error: "Error creando el cliente"
```

**Solución**: Verificar configuración de Baremetrics y API keys.

### Error al Crear Suscripción

Si el cliente se crea pero la suscripción falla:

```
Estado: Importado
Notas: "Importado con plan: creetelo_mensual - Sin suscripción"
```

**Nota**: El cliente SÍ se importó, pero sin suscripción. Puedes crear la suscripción manualmente en Baremetrics.

## Configuración del Entorno

La función usa automáticamente la configuración del archivo `.env`:

```env
# Producción
BAREMETRICS_ENVIRONMENT=production
BAREMETRICS_PRODUCTION_API_KEY=live_xxxxxxxxxxxxx
BAREMETRICS_PRODUCTION_SOURCE_ID=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8

# O Sandbox para pruebas
BAREMETRICS_ENVIRONMENT=sandbox
BAREMETRICS_SANDBOX_API_KEY=test_xxxxxxxxxxxxx
BAREMETRICS_SANDBOX_SOURCE_ID=xxxxxxxxxxxxx
```

## Borrar Usuarios Importados

Si necesitas revertir una importación:

1. Cambiar filtro a **"Importados"**
2. Aparecerá el botón rojo **"Borrar usuarios importados (X)"**
3. Clic en el botón
4. Confirmar la acción (¡ADVERTENCIA! Es irreversible)
5. El sistema:
   - Eliminará los clientes de Baremetrics
   - Eliminará las suscripciones asociadas
   - Cambiará el estado a "Pendiente"
   - Limpiará el OID de Baremetrics

## Resumen de Flujo Completo

```
1. Acceder a vista de usuarios faltantes
   ↓
2. Hacer prueba con 5 usuarios
   ↓
3. Verificar que se importaron correctamente
   ↓
4. Copiar OID de un usuario y verificar en Baremetrics
   ↓
5. Si todo está bien, importar el resto
   ↓
6. Verificar el resumen de importación
   ↓
7. Cambiar filtro a "Importados" para ver todos los usuarios
```

## Soporte

Para cualquier problema o duda:
- Revisar los logs en `storage/logs/laravel.log`
- Verificar la configuración en `.env`
- Verificar que los planes existan en Baremetrics
- Contactar al equipo de desarrollo

## Notas Importantes

⚠️ **IMPORTANTE**: 
- Los usuarios se importan de uno en uno con pausas de 200ms entre cada uno
- La función guarda el OID único de cada cliente para rastreo
- Los usuarios importados NO se pueden importar nuevamente (estado cambia a "imported")
- El borrado masivo es IRREVERSIBLE
- Se recomienda SIEMPRE hacer prueba con 5-10 usuarios antes de importación masiva

✅ **RECOMENDACIONES**:
- Hacer pruebas en sandbox antes de producción
- Verificar los tags de los usuarios antes de importar
- Revisar los logs durante la importación
- Mantener backup de la base de datos antes de operaciones masivas
