# 🚀 Comandos Rápidos - Referencia Rápida

## 🎯 **COMANDOS MÁS UTILIZADOS**

### 🗑️ **Eliminación de Usuarios**
```bash
# Eliminar y re-importar usuario
php artisan baremetrics:cleanup-duplicate-user usuario@ejemplo.com

# Eliminar usuario SIN re-importar
php artisan baremetrics:delete-user cust_123456 --confirm

# Buscar usuario en todos los sources
php artisan baremetrics:search-all-sources usuario@ejemplo.com
```

### 👥 **Gestión de Usuarios**
```bash
# Importar usuario completo
php artisan baremetrics:complete-test-import usuario@ejemplo.com

# Marcar como migrado
php artisan baremetrics:mark-migrated usuario@ejemplo.com

# Actualizar custom fields
php artisan baremetrics:update-custom-fields usuario@ejemplo.com
```

### 📊 **Comparaciones**
```bash
# Comparar CSV con todos los sources
php artisan baremetrics:test-csv-with-stripe-search archivo.csv --name="Mi Comparación"

# Verificar usuarios faltantes
php artisan baremetrics:verify-missing-users {comparison_id} --limit=50

# Procesar usuarios de Stripe
php artisan baremetrics:process-stripe-users {comparison_id} --limit=100
```

### 🔍 **Diagnósticos**
```bash
# Verificar configuración
php artisan ghl:check-config

# Diagnosticar conexión
php artisan ghl:diagnose-connection

# Listar todos los sources
php artisan baremetrics:test-all-sources
```

### 📈 **Procesamiento Masivo**
```bash
# Contar usuarios
php artisan ghl:count-users --limit=5000

# Procesar por tags
php artisan ghl:process-by-tags-large --tags=creetelo_mensual,creetelo_anual

# Reanudar procesamiento
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50
```

---

## 📋 **COMANDOS POR CATEGORÍA**

### 🗑️ **Eliminación (2 comandos)**
- `baremetrics:cleanup-duplicate-user` - Eliminar y re-importar
- `baremetrics:delete-user` - Eliminar sin re-importar

### 📋 **Planes (6 comandos)**
- `baremetrics:get-creetelo-plans` - Listar planes
- `baremetrics:update-creetelo-annual-plans` - Actualizar planes anuales
- `baremetrics:delete-recreate-annual-plans` - Recrear planes anuales
- `baremetrics:fix-creetelo-annual-plan` - Corregir plan anual
- `baremetrics:clear-plan-cache` - Limpiar cache
- `baremetrics:test-create-endpoints` - Probar creación

### 👥 **Usuarios (8 comandos)**
- `baremetrics:complete-test-import` - Importar completo
- `baremetrics:enhanced-import-production` - Importar mejorado
- `baremetrics:search-all-sources` - Buscar en todos sources
- `baremetrics:compare-ghl-all-sources` - Comparar con GHL
- `baremetrics:check-status` - Verificar estado
- `ghl:list-baremetrics-users` - Listar usuarios
- `baremetrics:search-user` - Buscar usuario específico
- `baremetrics:test-individual-import-production` - Probar importación

### 🔧 **Custom Fields (3 comandos)**
- `baremetrics:update-custom-fields` - Actualizar campos
- `baremetrics:mark-migrated` - Marcar como migrado
- `ghl:show-baremetrics-fields` - Mostrar campos

### 📊 **Comparaciones (15 comandos)**
- `baremetrics:test-csv-with-stripe-search` - Comparar CSV con Stripe
- `baremetrics:verify-missing-users` - Verificar faltantes
- `baremetrics:find-missing-users` - Encontrar faltantes
- `baremetrics:process-stripe-users` - Procesar Stripe
- `ghl:compare-with-baremetrics` - Comparar GHL vs Baremetrics
- `ghl:show-complete-comparison` - Mostrar comparación completa
- `ghl:analyze-missing-users` - Analizar faltantes
- Y 8 comandos más de análisis y comparación

### 🔍 **Diagnósticos (12 comandos)**
- `baremetrics:test-all-sources` - Probar todos sources
- `ghl:diagnose-connection` - Diagnosticar conexión
- `ghl:check-config` - Verificar configuración
- `baremetrics:test-environments` - Probar entornos
- `baremetrics:verify-import` - Verificar importaciones
- Y 7 comandos más de diagnóstico

### 📈 **Procesamiento Masivo (8 comandos)**
- `ghl:process-all-users` - Procesar todos usuarios
- `ghl:process-by-tags` - Procesar por tags
- `ghl:resume-processing` - Reanudar procesamiento
- `ghl:import-to-baremetrics` - Importar masivamente
- Y 4 comandos más de procesamiento

### 📊 **Estadísticas (8 comandos)**
- `ghl:count-users` - Contar usuarios
- `ghl:get-total-contacts` - Obtener total contactos
- `ghl:list-contacts` - Listar contactos
- Y 5 comandos más de conteo y estadísticas

### 🧪 **Pruebas (15 comandos)**
- `ghl:test-processing` - Probar procesamiento
- `ghl:test-api` - Probar API
- `ghl:test-system` - Probar sistema
- Y 12 comandos más de prueba

### 🔧 **Mantenimiento (4 comandos)**
- `ghl:refresh-token` - Refrescar token
- `baremetrics:fix-customers` - Corregir clientes
- `ghl:filter-users-by-tags-json` - Filtrar usuarios
- `ghl:process-comparison` - Procesar comparación

---

## 🎯 **FLUJOS COMUNES**

### Eliminar y Re-importar Usuario
```bash
php artisan baremetrics:cleanup-duplicate-user usuario@ejemplo.com
php artisan baremetrics:search-all-sources usuario@ejemplo.com
```

### Eliminar Usuario Completamente
```bash
php artisan baremetrics:delete-user cust_123456 --confirm
php artisan baremetrics:search-all-sources usuario@ejemplo.com
```

### Marcar como Migrado
```bash
php artisan baremetrics:mark-migrated usuario@ejemplo.com
php artisan baremetrics:update-custom-fields usuario@ejemplo.com
```

### Comparar CSV Completo
```bash
php artisan baremetrics:test-csv-with-stripe-search archivo.csv --name="Comparación"
php artisan baremetrics:verify-missing-users {comparison_id} --limit=100
php artisan baremetrics:process-stripe-users {comparison_id} --limit=50
```

### Procesamiento Masivo
```bash
php artisan ghl:count-users --limit=5000
php artisan ghl:process-by-tags-large --tags=creetelo_mensual,creetelo_anual --limit=100
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50
```

---

## ⚠️ **NOTAS IMPORTANTES**

### Campos Personalizados
- **`GHL: Migrate GHL`**: Campo booleano que indica migración desde GHL
- **`GHL: Contact ID`**: ID del contacto en GoHighLevel
- **`GHL: Subscription Status`**: Estado de la suscripción en GHL
- **`GHL: Tags`**: Tags del usuario en GHL

### Sources de Baremetrics
- **Manual**: `d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8` (baremetrics)
- **Stripe**: Múltiples sources con provider "stripe"

### Modo Dry-Run
```bash
php artisan baremetrics:find-missing-users --dry-run
php artisan ghl:import-to-baremetrics --dry-run
```

### Entornos
- **Sandbox**: Para pruebas y desarrollo
- **Production**: Para datos reales

---

## 📞 **EMERGENCIA**

### Cancelar Comparación
```bash
php artisan tinker --execute="
\$comparison = App\Models\ComparisonRecord::where('status', 'processing')->first();
if (\$comparison) {
    \$comparison->update(['status' => 'failed', 'error_message' => 'Cancelado manualmente']);
    echo 'Comparación cancelada: ' . \$comparison->id;
}
"
```

### Verificar Sistema
```bash
php artisan ghl:check-config
php artisan baremetrics:test-all-sources
php artisan baremetrics:test-environments
```

---

**📋 Documentación Completa**: `docs/COMPLETE_COMMANDS_GUIDE.md` (81 comandos)
**📚 Guía Detallada**: `docs/BAREMETRICS_COMMANDS_GUIDE.md`
