# Importación de Usuarios de GoHighLevel a Baremetrics

Este documento describe el comando para importar usuarios de GoHighLevel a Baremetrics usando los nuevos endpoints de creación.

## Comando

```bash
php artisan ghl:import-to-baremetrics [opciones]
```

## Opciones Disponibles

| Opción | Descripción | Valor por defecto |
|--------|-------------|-------------------|
| `--tags` | Tags de GHL a incluir (lógica OR) | `creetelo_mensual,creetelo_anual,créetelo_mensual,créetelo_anual` |
| `--exclude-tags` | Tags de GHL a excluir | `unsubscribe` |
| `--limit` | Número máximo de usuarios a importar | `100` |
| `--batch-size` | Tamaño del lote para procesamiento | `10` |
| `--dry-run` | Vista previa sin importar realmente | `false` |
| `--force` | Forzar importación aunque no esté en sandbox | `false` |
| `--plan-name` | Nombre del plan en Baremetrics | `GHL Import Plan` |
| `--plan-amount` | Monto del plan en centavos | `2999` ($29.99) |

## Ejemplos de Uso

### 1. Vista Previa (Dry Run)
```bash
# Ver qué usuarios se importarían sin hacer la importación real
php artisan ghl:import-to-baremetrics --dry-run --limit=10
```

### 2. Importación Básica
```bash
# Importar 50 usuarios con configuración por defecto
php artisan ghl:import-to-baremetrics --limit=50
```

### 3. Importación con Configuración Personalizada
```bash
# Importar con plan personalizado y tags específicos
php artisan ghl:import-to-baremetrics \
  --limit=25 \
  --plan-name="Plan Premium GHL" \
  --plan-amount=4999 \
  --batch-size=5
```

### 4. Importación con Tags Personalizados
```bash
# Importar usuarios con tags específicos
php artisan ghl:import-to-baremetrics \
  --tags=tag1,tag2,tag3 \
  --exclude-tags=exclude1,exclude2 \
  --limit=100
```

## Proceso de Importación

### 1. Verificación de Modo Sandbox
- El comando verifica que Baremetrics esté en modo sandbox
- Para importar en producción, usar `--force`

### 2. Obtención de Usuarios de GHL
- Consulta usuarios por cada tag de inclusión (lógica OR)
- Aplica filtros de exclusión
- Deduplica usuarios por ID
- Aplica límite especificado

### 3. Creación de Plan en Baremetrics
- Crea un plan con la configuración especificada
- Genera OID automáticamente
- Configura trial de 7 días por defecto

### 4. Procesamiento por Lotes
- Procesa usuarios en lotes para evitar rate limiting
- Crea cliente y suscripción para cada usuario
- Maneja errores individualmente sin detener el proceso

### 5. Mapeo de Datos

#### Cliente (Customer)
- **Nombre**: `firstName + lastName` o `name` o email
- **Email**: Email del usuario de GHL
- **Empresa**: Campo personalizado "company", "empresa", "business", "negocio"
- **Notas**: Tags GHL, teléfono, fuente

#### Plan
- **Nombre**: Configurable via `--plan-name`
- **Intervalo**: Mensual
- **Monto**: Configurable via `--plan-amount`
- **Moneda**: USD
- **Trial**: 7 días

#### Suscripción
- **Estado**: Activa
- **Fecha de inicio**: Timestamp actual
- **Notas**: "Importado desde GoHighLevel"

## Características de Seguridad

### Modo Sandbox
- Solo permite importación en modo sandbox por defecto
- Requiere `--force` para producción
- Verifica configuración antes de comenzar

### Rate Limiting
- Pausa de 2 segundos entre lotes
- Pausa de 0.1 segundos entre requests de GHL
- Procesamiento por lotes configurables

### Manejo de Errores
- Continúa procesando aunque falle un usuario individual
- Logs detallados de errores
- Resumen final con estadísticas

## Salida del Comando

### Vista Previa (Dry Run)
```
🔍 VISTA PREVIA DE IMPORTACIÓN (DRY RUN)
=====================================
+---+--------------------------+----------------------------------+---------+-------------------------------------------+
| # | Nombre                   | Email                            | Empresa | Tags                                      |
+---+--------------------------+----------------------------------+---------+-------------------------------------------+
| 1 | Isabel Torres            | isabelbtorres@gmail.com          | N/A     | optin_sales, creetelo_mensual, directorio |
| 2 | Karem Campero            | gabriela29100@gmail.com          | N/A     | creetelo_mensual                          |
+---+--------------------------+----------------------------------+---------+-------------------------------------------+

📊 Resumen:
   • Total usuarios: 5
   • Plan a crear: GHL Import Plan ($29.99)
   • Suscripciones a crear: 5
```

### Importación Real
```
🚀 IMPORTANDO USUARIOS DE GHL A BAREMETRICS
==========================================
✅ Modo sandbox confirmado: sandbox
📋 Configuración:
   • Tags incluidos: creetelo_mensual, creetelo_anual, créetelo_mensual, créetelo_anual
   • Tags excluidos: unsubscribe
   • Límite: 3 usuarios
   • Tamaño de lote: 1
   • Modo: IMPORTACIÓN REAL
   • Plan: GHL Import Plan ($29.99)

🔍 Obteniendo usuarios de GHL...
✅ Se encontraron 5 usuarios de GHL

📋 Creando plan en Baremetrics...
✅ Plan creado: plan_68df0b96950f6

📦 Procesando usuarios por lotes...
 5/5 [============================] 100%

📊 RESUMEN DE IMPORTACIÓN
========================
✅ Usuarios importados: 5
❌ Errores: 0
⏭️  Omitidos: 0
📈 Tasa de éxito: 100%

🎉 ¡Importación completada! Los usuarios están disponibles en Baremetrics (modo sandbox).
```

## Logs y Monitoreo

### Logs de Éxito
- `Baremetrics Plan Created Successfully`
- `Baremetrics Customer Created Successfully`
- `Baremetrics Subscription Created Successfully`

### Logs de Error
- `Baremetrics API Error - Create customer`
- `Baremetrics API Error - Create plan`
- `Baremetrics API Error - Create subscription`

### Ubicación de Logs
```
storage/logs/laravel.log
```

## Configuración Requerida

### Baremetrics
```env
BAREMETRICS_ENVIRONMENT=sandbox
BAREMETRICS_SANDBOX_KEY=tu_sandbox_key
BAREMETRICS_SANDBOX_URL=https://api-sandbox.baremetrics.com/v1
```

### GoHighLevel
```env
GOHIGHLEVEL_API_KEY=tu_ghl_api_key
GOHIGHLEVEL_API_URL=https://services.leadconnectorhq.com
```

## Casos de Uso

### 1. Migración Inicial
```bash
# Importar todos los usuarios con tags de suscripción
php artisan ghl:import-to-baremetrics --limit=1000 --batch-size=20
```

### 2. Sincronización Periódica
```bash
# Importar solo usuarios nuevos
php artisan ghl:import-to-baremetrics --limit=50 --batch-size=5
```

### 3. Testing y Desarrollo
```bash
# Importar pocos usuarios para testing
php artisan ghl:import-to-baremetrics --dry-run --limit=5
php artisan ghl:import-to-baremetrics --limit=3 --batch-size=1
```

## Troubleshooting

### Error: "Baremetrics no está en modo sandbox"
```bash
# Solución: Usar --force o cambiar configuración
php artisan ghl:import-to-baremetrics --force
```

### Error: "No se encontraron usuarios de GHL"
```bash
# Verificar tags y hacer dry run
php artisan ghl:import-to-baremetrics --dry-run --tags=tag1,tag2
```

### Error: "Rate limiting"
```bash
# Reducir batch size y aumentar pausas
php artisan ghl:import-to-baremetrics --batch-size=1
```

## Integración con Otros Comandos

### Verificar Usuarios de GHL
```bash
# Contar usuarios disponibles
php artisan ghl:count-users-by-tags-with-exclusions

# Ver usuarios específicos
php artisan ghl:filter-users-by-tags-to-json --limit=10
```

### Verificar Baremetrics
```bash
# Probar endpoints de Baremetrics
php artisan baremetrics:test-create-endpoints --all
```

## Notas Importantes

1. **Modo Sandbox**: Siempre importa en sandbox por defecto
2. **Deduplicación**: Usuarios se deduplican por ID de GHL
3. **OIDs**: Se generan automáticamente para todos los recursos
4. **Timestamps**: Se usan timestamps Unix para fechas
5. **Cache**: Los usuarios de GHL se cachean por 1 hora
6. **Logs**: Se registran todas las operaciones exitosas y errores
7. **Resiliencia**: El proceso continúa aunque fallen usuarios individuales
