# Importación Completa GHL → Baremetrics

## Descripción

El comando `ghl:import-complete-to-baremetrics` realiza una importación completa de usuarios de GoHighLevel a Baremetrics, respetando:

- **Planes reales de GHL**: Crea planes dinámicamente basados en las suscripciones reales de GHL
- **Fechas de renovación reales**: Usa las fechas de renovación reales de GHL o las calcula inteligentemente
- **Suscripciones reales**: Importa las suscripciones reales de GHL o crea suscripciones basadas en tags
- **Nombres de planes**: Respeta los nombres de planes de GHL (ej: CréeTelo Mensual, CréeTelo Anual)

## Características

### 🔍 Análisis Inteligente
- Obtiene suscripciones reales de cada usuario de GHL
- Analiza campos personalizados para encontrar información de planes
- Extrae fechas de renovación reales
- Mapea planes dinámicamente

### 📋 Planes Dinámicos
- **CréeTelo Anual**: $297/año
- **CréeTelo Mensual**: $39/mes
- **Otros planes**: Basados en suscripciones reales de GHL

### 📅 Fechas Reales
- Busca fechas de renovación en campos personalizados de GHL
- Calcula fechas basadas en el tipo de plan (anual/mensual)
- Usa timestamps Unix para compatibilidad con Baremetrics

### 🏷️ Mapeo de Tags
- `creetelo_anual` / `créetelo_anual` → Plan Anual ($297)
- `creetelo_mensual` / `créetelo_mensual` → Plan Mensual ($39)

## Uso

### Comando Básico
```bash
php artisan ghl:import-complete-to-baremetrics
```

### Con Parámetros Personalizados
```bash
php artisan ghl:import-complete-to-baremetrics \
  --tags=creetelo_mensual,creetelo_anual \
  --exclude-tags=unsubscribe \
  --limit=50 \
  --batch-size=5
```

### Modo Dry Run (Vista Previa)
```bash
php artisan ghl:import-complete-to-baremetrics --dry-run --limit=10
```

## Opciones

| Opción | Descripción | Valor por Defecto |
|--------|-------------|-------------------|
| `--tags` | Tags de GHL a incluir | `creetelo_mensual,creetelo_anual,créetelo_mensual,créetelo_anual` |
| `--exclude-tags` | Tags a excluir | `unsubscribe` |
| `--limit` | Máximo número de usuarios a importar | `100` |
| `--batch-size` | Usuarios por lote | `5` |
| `--dry-run` | Vista previa sin importar | `false` |
| `--force` | Forzar importación en producción | `false` |
| `--skip-existing` | Omitir usuarios existentes | `false` |

## Proceso de Importación

### 1. Verificación de Sandbox
```bash
✅ Modo sandbox confirmado: sandbox
```

### 2. Obtención de Usuarios
```bash
🔍 Obteniendo usuarios de GHL...
   📄 Procesando tag: creetelo_mensual
     • creetelo_mensual: 20 usuarios
   📄 Procesando tag: creetelo_anual
     • creetelo_anual: 20 usuarios
```

### 3. Análisis de Suscripciones
- Obtiene suscripciones reales de cada usuario
- Analiza campos personalizados
- Extrae información de planes y fechas

### 4. Creación de Planes
- Crea planes dinámicamente basados en suscripciones reales
- Reutiliza planes existentes para evitar duplicados
- Respeta nombres y precios de GHL

### 5. Importación por Lotes
```bash
📦 Procesando usuarios por lotes...
████████████████████████████████████████ 100%
```

### 6. Resumen Final
```bash
📊 RESUMEN DE IMPORTACIÓN COMPLETA
==================================
✅ Usuarios importados: 45
❌ Errores: 2
⏭️  Omitidos: 0
📋 Planes creados: 3
👤 Clientes creados: 45
📈 Tasa de éxito: 95.74%
```

## Estructura de Datos

### Usuario de GHL
```json
{
  "id": "contact_id",
  "firstName": "Juan",
  "lastName": "Pérez",
  "email": "juan@example.com",
  "tags": ["creetelo_mensual"],
  "subscriptions": [
    {
      "name": "CréeTelo Mensual",
      "amount": 3900,
      "renewal_date": 1640995200,
      "status": "active"
    }
  ],
  "customFields": [
    {
      "name": "renewal_date",
      "value": "2024-01-01"
    }
  ]
}
```

### Plan de Baremetrics
```json
{
  "name": "CréeTelo Mensual",
  "interval": "month",
  "interval_count": 1,
  "amount": 3900,
  "currency": "USD",
  "trial_days": 0,
  "notes": "Plan importado desde GHL - CréeTelo Mensual"
}
```

### Suscripción de Baremetrics
```json
{
  "customer_oid": "cust_abc123",
  "plan_oid": "plan_xyz789",
  "started_at": 1640995200,
  "status": "active",
  "notes": "Importado desde suscripción real de GHL"
}
```

## Campos de GHL Analizados

### Suscripciones Reales
- `subscriptions` - Array de suscripciones del usuario
- `name` / `product_name` / `plan_name` - Nombre del plan
- `amount` / `price` / `cost` - Monto del plan
- `renewal_date` / `next_billing` / `end_date` - Fecha de renovación

### Campos Personalizados
- `renewal_date` / `fecha_renovacion` - Fecha de renovación
- `next_billing` / `proximo_pago` - Próximo pago
- `expiration_date` / `fecha_expiracion` - Fecha de expiración
- `company` / `empresa` - Empresa del usuario

## Mapeo de Planes

### Planes CréeTelo
| Tag GHL | Nombre Plan | Monto | Intervalo |
|---------|-------------|-------|-----------|
| `creetelo_anual` | CréeTelo Anual | $297 | Año |
| `creetelo_mensual` | CréeTelo Mensual | $39 | Mes |
| `créetelo_anual` | CréeTelo Anual | $297 | Año |
| `créetelo_mensual` | CréeTelo Mensual | $39 | Mes |

### Planes Personalizados
- Se crean dinámicamente basados en suscripciones reales de GHL
- Respeta nombres y precios originales
- Intervalos detectados automáticamente

## Fechas de Renovación

### Prioridad de Búsqueda
1. **Suscripciones reales**: `renewal_date`, `next_billing`, `end_date`
2. **Campos personalizados**: `renewal_date`, `fecha_renovacion`, `proximo_pago`
3. **Cálculo por tag**: Basado en el tipo de plan (anual/mensual)
4. **Default**: 1 mes desde ahora

### Formatos Soportados
- **Unix timestamp**: `1640995200`
- **Fecha ISO**: `2024-01-01T00:00:00Z`
- **Fecha simple**: `2024-01-01`
- **Texto**: `next month`, `+1 year`

## Manejo de Errores

### Errores Comunes
- **Usuario sin email**: Se omite el usuario
- **Plan no encontrado**: Se crea plan basado en tags
- **Fecha inválida**: Se usa fecha calculada
- **API rate limit**: Pausa automática entre lotes

### Logs
```bash
# Ver logs de importación
tail -f storage/logs/laravel.log | grep "Baremetrics"
```

## Verificación

### Comando de Verificación
```bash
php artisan baremetrics:verify-import --show-all
```

### Verificación Manual
1. Acceder a Baremetrics sandbox
2. Verificar planes creados
3. Verificar clientes importados
4. Verificar suscripciones activas

## Ejemplos de Uso

### Importación Básica
```bash
# Importar 50 usuarios con configuración por defecto
php artisan ghl:import-complete-to-baremetrics --limit=50
```

### Importación Personalizada
```bash
# Importar solo usuarios anuales
php artisan ghl:import-complete-to-baremetrics \
  --tags=creetelo_anual,créetelo_anual \
  --limit=25 \
  --batch-size=3
```

### Vista Previa
```bash
# Ver qué se importaría sin hacer cambios
php artisan ghl:import-complete-to-baremetrics \
  --dry-run \
  --limit=10 \
  --tags=creetelo_mensual
```

### Importación Forzada
```bash
# Importar en producción (requiere --force)
php artisan ghl:import-complete-to-baremetrics \
  --force \
  --limit=100
```

## Consideraciones

### Rate Limiting
- Pausa de 3 segundos entre lotes
- Pausa de 0.2 segundos entre páginas de GHL
- Tamaño de lote recomendado: 5 usuarios

### Memoria
- Usa caché para evitar consultas repetidas
- Procesa usuarios por lotes
- Limpia memoria automáticamente

### Seguridad
- Solo funciona en modo sandbox por defecto
- Requiere `--force` para producción
- Valida todos los datos antes de importar

## Troubleshooting

### Problemas Comunes

#### "No se encontraron usuarios de GHL"
```bash
# Verificar tags
php artisan ghl:count-users-by-tags --tags=creetelo_mensual

# Verificar conexión
php artisan ghl:test-connection
```

#### "Error al crear plan en Baremetrics"
```bash
# Verificar API key
php artisan baremetrics:test-connection

# Verificar sandbox
php artisan baremetrics:verify-import
```

#### "Fecha de renovación inválida"
```bash
# Verificar formato de fecha
php artisan ghl:import-complete-to-baremetrics --dry-run --limit=1
```

#### "Clientes aparecen como Inactive en Baremetrics"
```bash
# Verificar estado actual
php artisan baremetrics:check-status

# Intentar reactivar clientes
php artisan baremetrics:fix-customers --limit=5

# Ver documentación completa del problema
# Ver: docs/BAREMETRICS_SYNC_ISSUE.md
```

### Logs de Debug
```bash
# Ver logs detallados
tail -f storage/logs/laravel.log | grep -E "(GHL|Baremetrics|Import)"

# Ver logs de Baremetrics específicos
Get-Content storage/logs/laravel.log -Tail 50 | Select-String -Pattern "Baremetrics"
```

## Próximos Pasos

1. **Ejecutar importación completa**:
   ```bash
   php artisan ghl:import-complete-to-baremetrics --limit=100
   ```

2. **Verificar resultados**:
   ```bash
   php artisan baremetrics:verify-import --show-all
   ```

3. **Revisar en Baremetrics sandbox**:
   - Planes creados
   - Clientes importados
   - Suscripciones activas

4. **Si los clientes aparecen como "Inactive"**:
   ```bash
   # Verificar estado
   php artisan baremetrics:check-status
   
   # Ver documentación del problema
   # docs/BAREMETRICS_SYNC_ISSUE.md
   ```

5. **Ajustar configuración** si es necesario:
   - Tags específicos
   - Límites de importación
   - Tamaños de lote

¡La importación completa está lista para usar! 🚀

## Documentación Relacionada

- [Problema de Sincronización en Baremetrics](docs/BAREMETRICS_SYNC_ISSUE.md)
- [API de Creación de Recursos](docs/BAREMETRICS_CREATE_API.md)
- [Filtrado de Usuarios GHL](docs/GHL_FILTER_API.md)
