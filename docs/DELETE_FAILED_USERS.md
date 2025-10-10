# Eliminación de Usuarios Fallidos en Baremetrics

## Descripción

Esta funcionalidad permite eliminar usuarios y sus suscripciones de Baremetrics que tienen el estado "failed" en la tabla `missing_users`.

## Acceso

### Interfaz Web
- **URL:** `/admin/baremetrics/failed-users`
- **Ruta:** `admin.baremetrics.failed-users`

### API Endpoint
- **URL:** `/admin/baremetrics/delete-failed-users`
- **Método:** POST
- **Ruta:** `admin.baremetrics.delete-failed-users`

## Funcionalidad

### ¿Qué hace?

El sistema realiza las siguientes acciones para cada usuario con estado `failed`:

1. **Verifica el customer ID** en Baremetrics
2. **Obtiene las suscripciones** asociadas al usuario
3. **Elimina cada suscripción** del usuario en Baremetrics
4. **Elimina el customer** de Baremetrics
5. **Actualiza el registro** en la base de datos:
   - Cambia `import_status` de `failed` a `pending`
   - Limpia `baremetrics_customer_id`
   - Limpia `imported_at`
   - Limpia `import_error`

### Casos Especiales

- **Usuario sin customer_id:** Solo actualiza el estado a `pending` sin hacer llamadas a Baremetrics
- **Customer no encontrado en Baremetrics:** Marca como limpiado aunque no exista en Baremetrics (evita errores 404)
- **Pausa entre peticiones:** 200ms entre cada usuario para evitar sobrecargar la API

## Uso desde la Interfaz Web

1. Navega a `/admin/baremetrics/failed-users`
2. Revisa la lista de usuarios fallidos
3. Haz clic en el botón "Eliminar Todos los Usuarios Fallidos"
4. Confirma la acción en el modal
5. Espera a que el proceso se complete
6. La página se recargará mostrando los resultados

## Uso desde la API

### Request

```bash
POST /admin/baremetrics/delete-failed-users
Content-Type: application/json
X-CSRF-TOKEN: {token}
```

### Response Exitosa

```json
{
  "success": true,
  "message": "Proceso completado. 5 usuarios eliminados/limpiados",
  "data": {
    "total_processed": 5,
    "deleted_count": 5,
    "failed_count": 0,
    "errors": [],
    "processed_users": [
      {
        "email": "usuario@ejemplo.com",
        "customer_id": "ghl_12345",
        "subscriptions_deleted": 1,
        "status": "success"
      }
    ]
  }
}
```

### Response con Errores

```json
{
  "success": true,
  "message": "Proceso completado. 3 usuarios eliminados/limpiados, 2 fallidos.",
  "data": {
    "total_processed": 5,
    "deleted_count": 3,
    "failed_count": 2,
    "errors": [
      "Error eliminando usuario test@test.com (ID: ghl_xyz)"
    ],
    "processed_users": [...]
  }
}
```

## Estados de Procesamiento

- **`success`**: Usuario y suscripción eliminados exitosamente
- **`not_found_but_cleaned`**: Customer no encontrado en Baremetrics pero estado actualizado
- **`no_customer_id_cleaned`**: Usuario sin customer_id, solo estado actualizado
- **`failed`**: Error al eliminar el usuario
- **`error`**: Excepción durante el procesamiento

## Logs

Todos los procesos se registran en los logs de Laravel con el siguiente formato:

```
[timestamp] INFO: === INICIANDO ELIMINACIÓN DE USUARIOS FALLIDOS ===
[timestamp] INFO: Procesando usuario #1/5
[timestamp] INFO: Usuario eliminado exitosamente
[timestamp] INFO: === PROCESO DE ELIMINACIÓN COMPLETADO ===
```

## Consideraciones

1. **Permisos:** Requiere autenticación y rol de Admin
2. **Entorno:** Siempre usa el entorno de producción de Baremetrics
3. **Rate Limiting:** Incluye pausas de 200ms entre peticiones
4. **Transaccional:** Cada usuario se procesa individualmente
5. **Idempotente:** Puede ejecutarse múltiples veces sin efectos secundarios

## Ejemplo de Uso con JavaScript

```javascript
async function deleteFailedUsers() {
    const response = await fetch('/admin/baremetrics/delete-failed-users', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    });
    
    const data = await response.json();
    
    if (data.success) {
        console.log(`Procesados: ${data.data.total_processed}`);
        console.log(`Eliminados: ${data.data.deleted_count}`);
        console.log(`Fallidos: ${data.data.failed_count}`);
    }
}
```

## Troubleshooting

### Error: "No se pudo obtener el source ID de GHL"
- Verificar configuración de Baremetrics en `config/services.php`
- Revisar las credenciales de la API

### Error: "No hay usuarios con status 'failed' para eliminar"
- No hay registros con `import_status = 'failed'` en la tabla `missing_users`

### Usuarios que siguen apareciendo como fallidos
- Revisar los logs para identificar errores específicos
- Verificar conectividad con la API de Baremetrics
- Comprobar que los customer_id son válidos

## Ver también

- [GHL_BAREMETRICS_COMPARISON.md](./GHL_BAREMETRICS_COMPARISON.md)
- [BAREMETRICS_COMMANDS_GUIDE.md](./BAREMETRICS_COMMANDS_GUIDE.md)
- [GHL_COMPARISON_SYSTEM.md](./GHL_COMPARISON_SYSTEM.md)
