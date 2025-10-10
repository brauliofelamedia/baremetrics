# Eliminar Usuarios de Baremetrics por Plan

## Descripción

Esta funcionalidad permite eliminar usuarios de Baremetrics que pertenezcan a un plan específico. El sistema:

1. ✅ Busca todas las suscripciones del plan especificado
2. ✅ Identifica los usuarios únicos con ese plan
3. ✅ Elimina **TODAS** las suscripciones de cada usuario (no solo del plan especificado)
4. ✅ Elimina el customer completo de Baremetrics
5. ✅ Proporciona un reporte detallado del proceso

## ⚠️ ADVERTENCIA

**Esta acción es IRREVERSIBLE**. Una vez eliminados los usuarios y sus suscripciones de Baremetrics, no se pueden recuperar.

## Acceso

### Vía Web (Interfaz Gráfica)
```
URL: /admin/baremetrics/delete-users-by-plan
```

Navega a esta URL en tu navegador mientras estés autenticado como administrador.

### Vía API
```bash
POST /admin/baremetrics/delete-users-by-plan
Content-Type: application/json

{
  "plan_name": "creetelo_anual"
}
```

## Ejemplo de Uso

### Paso 1: Acceder a la interfaz
1. Inicia sesión como administrador
2. Ve a `/admin/baremetrics/delete-users-by-plan`

### Paso 2: Especificar el plan
- Ingresa el nombre exacto del plan (ejemplo: `creetelo_anual`)
- El nombre debe coincidir exactamente como aparece en Baremetrics

### Paso 3: Ejecutar
1. Haz clic en "Eliminar Usuarios del Plan"
2. Confirma la acción en el mensaje de confirmación
3. Espera a que el proceso termine

### Paso 4: Revisar resultados
El sistema mostrará:
- Total de usuarios procesados
- Usuarios eliminados exitosamente
- Usuarios con errores (si los hay)
- Detalles de cada usuario procesado

## Respuesta JSON (API)

### Respuesta Exitosa
```json
{
  "success": true,
  "message": "Proceso completado para el plan 'creetelo_anual'. 15 usuarios eliminados",
  "data": {
    "plan_name": "creetelo_anual",
    "total_processed": 15,
    "deleted_count": 15,
    "failed_count": 0,
    "errors": [],
    "processed_users": [
      {
        "customer_id": "cust_abc123",
        "email": "usuario@example.com",
        "name": "Usuario Ejemplo",
        "subscriptions_deleted": 2,
        "status": "success"
      },
      // ... más usuarios
    ]
  }
}
```

### Respuesta con Errores
```json
{
  "success": true,
  "message": "Proceso completado para el plan 'creetelo_anual'. 13 usuarios eliminados, 2 fallidos.",
  "data": {
    "plan_name": "creetelo_anual",
    "total_processed": 15,
    "deleted_count": 13,
    "failed_count": 2,
    "errors": [
      "Error eliminando usuario usuario@example.com (ID: cust_xyz789)"
    ],
    "processed_users": [...]
  }
}
```

### Respuesta de Error
```json
{
  "success": false,
  "message": "No se encontraron suscripciones para el plan 'plan_inexistente'."
}
```

## Ejemplo Práctico con cURL

```bash
# Eliminar usuarios del plan "creetelo_anual"
curl -X POST http://tu-dominio.com/admin/baremetrics/delete-users-by-plan \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: tu-csrf-token" \
  -d '{"plan_name": "creetelo_anual"}' \
  --cookie "laravel_session=tu-session-cookie"
```

## Logs

Todos los eventos son registrados en el log de Laravel:

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log
```

Información registrada:
- ✅ Inicio del proceso
- ✅ Suscripciones encontradas por plan
- ✅ Usuarios únicos identificados
- ✅ Eliminación de cada suscripción
- ✅ Eliminación de cada customer
- ✅ Resultado final del proceso
- ❌ Errores y excepciones

## Consideraciones Importantes

1. **Entorno**: El proceso se ejecuta en el entorno de **PRODUCCIÓN** de Baremetrics
2. **Source ID**: Usa el source ID configurado en `config/services.php`
3. **Pausas**: Hay una pausa de 300ms entre cada usuario para evitar saturar la API
4. **Suscripciones**: Se eliminan **TODAS** las suscripciones del usuario, no solo la del plan especificado
5. **Validación**: El nombre del plan es case-sensitive (distingue mayúsculas/minúsculas)

## Nombres de Planes Comunes

- `creetelo_anual`
- `creetelo_mensual`
- `creetelo_trimestral`
- (Verifica los nombres exactos en tu configuración de Baremetrics)

## Troubleshooting

### "No se encontraron suscripciones para el plan"
- Verifica que el nombre del plan sea exacto
- Verifica que existan suscripciones activas para ese plan
- Revisa los logs para más detalles

### "Error eliminando usuario"
- Puede ser un error de conexión con la API de Baremetrics
- Verifica las credenciales en `config/services.php`
- Revisa los logs para el mensaje de error específico

### Timeouts
- Si hay muchos usuarios, el proceso puede tardar
- Considera aumentar el timeout en la configuración de PHP

## Verificación Post-Eliminación

Después de ejecutar la eliminación, verifica en Baremetrics:
1. Ve a tu dashboard de Baremetrics
2. Busca el plan especificado
3. Verifica que los usuarios hayan sido eliminados

## Soporte

Para más información o soporte, consulta:
- Logs: `storage/logs/laravel.log`
- Documentación de Baremetrics API
- Equipo de desarrollo
