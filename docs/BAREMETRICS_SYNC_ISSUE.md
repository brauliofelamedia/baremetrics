# Problema de Sincronización en Baremetrics Sandbox

## Descripción del Problema

Durante la importación de usuarios desde GoHighLevel a Baremetrics, se observó que aunque las suscripciones se crean correctamente, los clientes aparecen como "Inactive" en la interfaz de Baremetrics sandbox.

### Síntomas Observados

- ✅ **Clientes creados**: Se crean correctamente en Baremetrics
- ✅ **Suscripciones creadas**: Se crean correctamente con estado "active"
- ❌ **Clientes inactivos**: Todos aparecen como "Inactive" en la UI
- ❌ **MRR $0.00**: Monthly Recurring Revenue aparece como $0.00
- ❌ **Sin planes asignados**: Columna "Plans" aparece vacía

## Estado Actual

### Datos en Baremetrics Sandbox
```
Total de clientes: 22
Total de suscripciones: 20
Estado de clientes: Todos "Inactive"
MRR promedio: $0.00
Planes asignados: 0 para todos los clientes
```

### Planes Creados
- **CréeTelo Mensual**: $39/mes (múltiples instancias)
- **CréeTelo Anual**: $297/año (múltiples instancias)

## Causas Probables

### 1. Retraso de Sincronización
- Baremetrics sandbox puede tener retrasos en la sincronización
- Las suscripciones existen pero no se asocian inmediatamente a los clientes
- Tiempo estimado: 24-48 horas

### 2. Problemas con Fechas de Inicio
- Las fechas de inicio futuras pueden causar que los clientes aparezcan como inactivos
- Se corrigió para usar fechas actuales, pero el problema persiste

### 3. Configuración del Sandbox
- Posibles problemas en la configuración del entorno sandbox
- API keys o permisos incorrectos

## Soluciones Implementadas

### 1. Corrección de Fechas de Inicio
```php
// Antes: Fechas futuras
$started_at = strtotime('+1 month', now()->timestamp);

// Después: Fechas actuales
$started_at = now()->timestamp;
```

### 2. Comando de Verificación
```bash
php artisan baremetrics:check-status
```
Muestra el estado actual de clientes y suscripciones en Baremetrics.

### 3. Comando de Reactivación
```bash
php artisan baremetrics:fix-customers --limit=5
```
Intenta reactivar clientes inactivos creando nuevas suscripciones.

### 4. Corrección de Deduplicación de Planes
```php
// Clave única normalizada para evitar planes duplicados
$normalizedName = str_replace(['CréeTelo', 'CréeTelo'], 'Creetelo', $planInfo['name']);
$planKey = $normalizedName . '_' . $planInfo['amount'] . '_' . $planInfo['interval'];
```

## Comandos de Diagnóstico

### Verificar Estado Actual
```bash
# Ver clientes y suscripciones
php artisan baremetrics:check-status

# Verificar importaciones recientes
php artisan baremetrics:verify-import --show-all
```

### Intentar Reactivación
```bash
# Reactivar clientes inactivos
php artisan baremetrics:fix-customers --limit=10

# Importar nuevos usuarios
php artisan ghl:import-complete-to-baremetrics --limit=5
```

### Verificar Logs
```bash
# Ver logs de Baremetrics
Get-Content storage/logs/laravel.log -Tail 50 | Select-String -Pattern "Baremetrics"

# Ver errores específicos
Get-Content storage/logs/laravel.log -Tail 100 | Select-String -Pattern "Error"
```

## Soluciones Recomendadas

### 1. Esperar Sincronización (Recomendado)
- **Tiempo**: 24-48 horas
- **Acción**: Monitorear el estado con `baremetrics:check-status`
- **Ventaja**: Solución automática sin intervención

### 2. Contactar Soporte de Baremetrics
- **Problema**: Sandbox no sincroniza correctamente
- **Información a proporcionar**:
  - Source ID: `3e41e7e8-1f00-4f0d-92d9-fc738d7124ba`
  - Número de clientes: 22
  - Número de suscripciones: 20
  - Estado: Clientes inactivos a pesar de suscripciones activas

### 3. Probar en Producción
```bash
# Importar en producción (requiere --force)
php artisan ghl:import-complete-to-baremetrics --force --limit=5
```
**⚠️ Advertencia**: Solo para pruebas, no usar con datos reales.

### 4. Verificar Configuración
```bash
# Verificar configuración actual
php artisan config:show services.baremetrics

# Verificar variables de entorno
echo $BAREMETRICS_ENVIRONMENT
echo $BAREMETRICS_SANDBOX_KEY
```

## Estructura de Datos Esperada

### Cliente Activo Correcto
```json
{
  "oid": "cust_xxx",
  "name": "Usuario GHL",
  "email": "usuario@example.com",
  "is_active": true,
  "current_mrr": 3900,
  "current_plans": ["plan_xxx"]
}
```

### Suscripción Activa Correcta
```json
{
  "oid": "sub_xxx",
  "customer_oid": "cust_xxx",
  "plan_oid": "plan_xxx",
  "status": "active",
  "started_at": 1759449180
}
```

## Monitoreo Continuo

### Script de Monitoreo
```bash
#!/bin/bash
# Verificar estado cada hora
while true; do
    echo "$(date): Verificando estado de Baremetrics..."
    php artisan baremetrics:check-status | grep "Activo"
    sleep 3600
done
```

### Alertas
- Clientes inactivos > 24 horas
- MRR = $0.00 para clientes con suscripciones
- Planes no asignados

## Troubleshooting

### Problema: "API Key not found"
```bash
# Verificar configuración
php artisan config:clear
php artisan cache:clear

# Verificar variables de entorno
cat .env | grep BAREMETRICS
```

### Problema: "Subscription oid can't be blank"
```php
// Asegurar que se genere OID único
'oid' => 'sub_' . uniqid()
```

### Problema: Planes duplicados
```php
// Usar clave normalizada
$planKey = $normalizedName . '_' . $planInfo['amount'] . '_' . $planInfo['interval'];
```

## Próximos Pasos

1. **Monitorear** el estado durante 24-48 horas
2. **Contactar soporte** de Baremetrics si el problema persiste
3. **Documentar** la resolución para futuras referencias
4. **Implementar** monitoreo automático del estado

## Referencias

- [Documentación de Baremetrics API](https://developers.baremetrics.com/)
- [Comando de Importación Completa](docs/GHL_BAREMETRICS_COMPLETE_IMPORT.md)
- [API de Creación de Recursos](docs/BAREMETRICS_CREATE_API.md)

## Contacto de Soporte

- **Baremetrics Support**: [support@baremetrics.com](mailto:support@baremetrics.com)
- **Source ID**: `3e41e7e8-1f00-4f0d-92d9-fc738d7124ba`
- **Entorno**: Sandbox
- **Fecha del problema**: 2025-10-02
