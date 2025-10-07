# 📚 Guía Completa de Comandos Baremetrics y GHL

## 🎯 Descripción General

Esta guía contiene **TODOS** los comandos disponibles para gestionar usuarios, planes, suscripciones y campos personalizados en Baremetrics y GoHighLevel. Los comandos están organizados por categorías y funcionalidad.

---

## 🗑️ **ELIMINACIÓN DE USUARIOS**

### Eliminar Usuario Completo (Recomendado)
```bash
php artisan baremetrics:cleanup-duplicate-user yuvianat.holisticcoach@gmail.com
```

**¿Qué hace?**
- ✅ Busca TODAS las entradas del usuario en Baremetrics
- ✅ Elimina TODAS las suscripciones del usuario
- ✅ Elimina el customer completo
- ✅ Re-importa el usuario con datos correctos de GHL
- ✅ Respeta la fecha original de suscripción
- ✅ Incluye todos los custom fields
- ✅ Marca como migrado (`GHL: Migrate GHL = true`)

**Parámetros:**
- `email`: Email del usuario a eliminar y re-importar

**Ejemplo:**
```bash
# Eliminar usuario específico
php artisan baremetrics:cleanup-duplicate-user usuario@ejemplo.com
```

### Eliminar Usuario SIN Re-importar
```bash
php artisan baremetrics:delete-user cust_68e4e0ffdd60b --confirm
```

**¿Qué hace?**
- ✅ Elimina TODAS las suscripciones del customer
- ✅ Elimina el customer completo
- ✅ Verifica que la eliminación fue exitosa
- ❌ **NO re-importa** el usuario
- ❌ **NO crea** nuevas entradas

**Parámetros:**
- `customer_id`: ID del customer a eliminar (ej: cust_68e4e0ffdd60b)
- `--source-id`: Source ID (por defecto: source manual)
- `--confirm`: Confirmar eliminación sin preguntar

**Ejemplos:**
```bash
# Eliminar customer específico
php artisan baremetrics:delete-user cust_68e4e0ffdd60b --confirm

# Eliminar con confirmación interactiva
php artisan baremetrics:delete-user cust_68e4e0ffdd60b

# Eliminar de source específico
php artisan baremetrics:delete-user cust_123456 --source-id=otro_source_id --confirm
```

---

## 📋 **GESTIÓN DE PLANES**

### Listar Planes Disponibles
```bash
php artisan baremetrics:get-creetelo-plans
php artisan baremetrics:get-creetelo-plans-production
```

### Crear Nuevo Plan
```bash
php artisan baremetrics:test-create-endpoints --test-plan
```

### Eliminar Plan
```bash
php artisan baremetrics:test-create-endpoints --test-plan
```

### Actualizar Planes Anuales
```bash
php artisan baremetrics:update-creetelo-annual-plans
php artisan baremetrics:delete-recreate-annual-plans
php artisan baremetrics:fix-creetelo-annual-plan
```

### Limpiar Cache de Planes
```bash
php artisan baremetrics:clear-plan-cache
```

---

## 👥 **GESTIÓN DE USUARIOS**

### Importar Usuario Individual Completo
```bash
php artisan baremetrics:complete-test-import usuario@ejemplo.com
php artisan baremetrics:enhanced-import-production usuario@ejemplo.com
php artisan baremetrics:test-individual-import-production usuario@ejemplo.com
```

**¿Qué hace?**
- ✅ Elimina entradas existentes del usuario
- ✅ Obtiene datos completos de GHL
- ✅ Determina el plan correcto según tags
- ✅ Crea customer en Baremetrics
- ✅ Crea suscripción con fecha original
- ✅ Actualiza todos los custom fields
- ✅ Marca como migrado (`GHL: Migrate GHL = true`)

### Buscar Usuario en Todos los Sources
```bash
php artisan baremetrics:search-all-sources usuario@ejemplo.com
php artisan baremetrics:search-user usuario@ejemplo.com
```

### Comparar Usuario GHL vs Baremetrics
```bash
php artisan baremetrics:compare-ghl-all-sources usuario@ejemplo.com
```

### Listar Usuarios de Baremetrics
```bash
php artisan ghl:list-baremetrics-users --limit=20 --search=usuario@ejemplo.com
```

### Verificar Estado de Usuario
```bash
php artisan baremetrics:check-status --customer-oid=cust_123456
```

---

## 🔧 **CUSTOM FIELDS**

### Actualizar Custom Fields de Usuario
```bash
php artisan baremetrics:update-custom-fields usuario@ejemplo.com
```

### Marcar Usuario como Migrado
```bash
php artisan baremetrics:mark-migrated usuario@ejemplo.com
```

**¿Qué hace?**
- ✅ Establece `GHL: Migrate GHL = true`
- ✅ Actualiza otros custom fields de GHL
- ✅ Confirma que el usuario fue migrado correctamente

### Mostrar Campos Disponibles
```bash
php artisan ghl:show-baremetrics-fields
```

---

## 📊 **COMPARACIONES Y ANÁLISIS**

### Comparar CSV con Todos los Sources
```bash
php artisan baremetrics:test-csv-with-stripe-search archivo.csv --name="Mi Comparación"
php artisan baremetrics:test-csv-import-all-sources archivo.csv --name="Mi Comparación"
php artisan baremetrics:test-csv-exclude-stripe archivo.csv --name="Mi Comparación"
```

### Verificar Usuarios Faltantes
```bash
php artisan baremetrics:verify-missing-users {comparison_id} --limit=50
php artisan baremetrics:find-missing-users --limit=50 --dry-run
```

### Procesar Usuarios de Stripe por Separado
```bash
php artisan baremetrics:process-stripe-users {comparison_id} --limit=100
```

### Comparaciones GHL vs Baremetrics
```bash
php artisan ghl:compare-with-baremetrics
php artisan ghl:compare-production
php artisan ghl:show-complete-comparison
php artisan ghl:executive-summary
php artisan ghl:final-summary
```

### Análisis de Usuarios Faltantes
```bash
php artisan ghl:analyze-missing-users
php artisan ghl:analyze-results
php artisan ghl:get-missing-users
php artisan ghl:missing-users
php artisan ghl:list-missing-users
```

---

## 🔍 **BÚSQUEDAS Y DIAGNÓSTICOS**

### Listar Todos los Sources
```bash
php artisan baremetrics:test-all-sources
```

### Diagnosticar Conexión
```bash
php artisan ghl:diagnose-connection
php artisan ghl:diagnose-baremetrics
php artisan ghl:diagnose-pagination
php artisan ghl:diagnose-tags
```

### Verificar Configuración
```bash
php artisan ghl:check-config
```

### Probar APIs
```bash
php artisan ghl:test-api usuario@ejemplo.com --debug
php artisan ghl:test-basic-connection
php artisan ghl:test-operators usuario@ejemplo.com
php artisan ghl:test-subscriptions usuario@ejemplo.com --debug
php artisan baremetrics:test-environments
php artisan baremetrics:test-create-endpoints --all
```

### Verificar Importaciones
```bash
php artisan baremetrics:verify-import --show-all
php artisan baremetrics:verify-import --customer-oid=cust_123456
php artisan baremetrics:verify-import --subscription-oid=sub_123456
```

---

## 📈 **PROCESAMIENTO MASIVO**

### Procesar Todos los Usuarios
```bash
php artisan ghl:process-all-users --limit=100 --delay=2
php artisan ghl:process-by-tags --tags=creetelo_mensual,creetelo_anual
php artisan ghl:process-by-tags-large --tags=creetelo_mensual,creetelo_anual
php artisan ghl:process-ghl-to-baremetrics
```

### Reanudar Procesamiento
```bash
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50
```

### Importar Usuarios Masivamente
```bash
php artisan ghl:import-to-baremetrics --limit=50 --dry-run
php artisan ghl:import-complete-to-baremetrics --limit=50 --dry-run
```

### Procesar Comparaciones
```bash
php artisan ghl:process-comparison {comparison_id}
php artisan ghl:create-test-comparison
php artisan ghl:test-complete-flow
```

---

## 📊 **CONTEO Y ESTADÍSTICAS**

### Contar Usuarios GHL
```bash
php artisan ghl:count-users --limit=5000
php artisan ghl:count-users-by-tags --tags=creetelo_mensual,creetelo_anual
php artisan ghl:count-users-by-tags-separate --tags=creetelo_mensual,creetelo_anual
php artisan ghl:count-users-with-exclusions --tags=creetelo_mensual --exclude-tags=unsubscribe
php artisan ghl:compare-users-count
```

### Obtener Totales
```bash
php artisan ghl:get-total-contacts
php artisan ghl:get-total-contacts-robust
php artisan ghl:test-total-users
```

### Listar Contactos
```bash
php artisan ghl:list-contacts --search=usuario@ejemplo.com
php artisan ghl:users-first-page
```

---

## 🧪 **COMANDOS DE PRUEBA**

### Probar Procesamiento Individual
```bash
php artisan ghl:test-processing usuario@ejemplo.com
php artisan ghl:test-batch-method
php artisan ghl:test-contacts-simple
php artisan ghl:test-tags-api
php artisan ghl:test-tags-pagination
php artisan ghl:test-tags-search
```

### Probar Sistema Completo
```bash
php artisan ghl:test-system
php artisan ghl:test-progress
php artisan ghl:test-vanilla-flow
php artisan ghl:test-plan-modification
```

### Probar Comparaciones CSV
```bash
php artisan ghl:compare-csv --file=archivo.csv --tags=creetelo_mensual,creetelo_anual
php artisan ghl:simulate-csv-comparison
```

---

## 🔧 **MANTENIMIENTO Y CONFIGURACIÓN**

### Refrescar Token GHL
```bash
php artisan ghl:refresh-token
```

### Filtrar Usuarios por Tags
```bash
php artisan ghl:filter-users-by-tags-json --tags=creetelo_mensual,creetelo_anual
```

### Reactivar Clientes Inactivos
```bash
php artisan baremetrics:fix-customers --limit=5
```

---

## 🎯 **FLUJOS DE TRABAJO COMUNES**

### 1. Eliminar y Re-importar Usuario
```bash
# Paso 1: Eliminar usuario completamente
php artisan baremetrics:cleanup-duplicate-user usuario@ejemplo.com

# Paso 2: Verificar que se eliminó
php artisan baremetrics:search-all-sources usuario@ejemplo.com

# Paso 3: Re-importar con datos correctos
php artisan baremetrics:complete-test-import usuario@ejemplo.com
```

### 2. Eliminar Usuario SIN Re-importar
```bash
# Paso 1: Eliminar completamente (solo eliminación)
php artisan baremetrics:delete-user cust_68e4e0ffdd60b --confirm

# Paso 2: Verificar eliminación
php artisan baremetrics:search-all-sources usuario@ejemplo.com

# Paso 3: Si necesitas re-importar después
php artisan baremetrics:complete-test-import usuario@ejemplo.com
```

### 3. Marcar Usuario como Migrado
```bash
# Marcar como migrado
php artisan baremetrics:mark-migrated usuario@ejemplo.com

# Verificar custom fields
php artisan baremetrics:update-custom-fields usuario@ejemplo.com
```

### 4. Crear y Configurar Plan
```bash
# Crear plan
php artisan baremetrics:test-create-endpoints --test-plan

# Listar planes para obtener OID
php artisan baremetrics:get-creetelo-plans

# Actualizar planes anuales
php artisan baremetrics:update-creetelo-annual-plans
```

### 5. Comparar CSV Completo
```bash
# Comparar CSV con todos los sources
php artisan baremetrics:test-csv-with-stripe-search mi_archivo.csv --name="Comparación Completa"

# Verificar usuarios faltantes específicos
php artisan baremetrics:verify-missing-users {comparison_id} --limit=100

# Procesar usuarios de Stripe por separado
php artisan baremetrics:process-stripe-users {comparison_id} --limit=50
```

### 6. Procesamiento Masivo
```bash
# Contar usuarios primero
php artisan ghl:count-users --limit=5000

# Procesar por lotes
php artisan ghl:process-by-tags-large --tags=creetelo_mensual,creetelo_anual --limit=100

# Reanudar si se interrumpe
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50
```

---

## ⚠️ **NOTAS IMPORTANTES**

### Campos Personalizados Importantes
- **`GHL: Migrate GHL`**: Campo booleano que indica si el usuario fue migrado desde GHL
- **`GHL: Contact ID`**: ID del contacto en GoHighLevel
- **`GHL: Subscription Status`**: Estado de la suscripción en GHL
- **`GHL: Tags`**: Tags del usuario en GHL

### Sources de Baremetrics
- **Manual**: `d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8` (baremetrics)
- **Stripe**: Múltiples sources con provider "stripe"

### Orden de Eliminación
1. **Suscripciones** primero
2. **Customer** después
3. **Re-importar** con datos correctos

### Modo Dry-Run
Muchos comandos soportan `--dry-run` para pruebas sin cambios reales:
```bash
php artisan baremetrics:find-missing-users --dry-run
php artisan ghl:import-to-baremetrics --dry-run
```

### Entornos
- **Sandbox**: Para pruebas y desarrollo
- **Production**: Para datos reales
- Configurar con: `config(['services.baremetrics.environment' => 'production'])`

---

## 🆘 **SOLUCIÓN DE PROBLEMAS**

### Usuario No Se Elimina
```bash
# Verificar si existe
php artisan baremetrics:search-all-sources usuario@ejemplo.com

# Forzar eliminación completa
php artisan baremetrics:cleanup-duplicate-user usuario@ejemplo.com
```

### Error de Custom Fields
```bash
# Verificar campos disponibles
php artisan ghl:show-baremetrics-fields

# Actualizar campos específicos
php artisan baremetrics:update-custom-fields usuario@ejemplo.com
```

### Problemas de Conexión
```bash
# Diagnosticar conexión
php artisan ghl:diagnose-connection

# Verificar configuración
php artisan ghl:check-config

# Refrescar token
php artisan ghl:refresh-token
```

### Problemas de Procesamiento
```bash
# Diagnosticar datos
php artisan ghl:diagnose-baremetrics --limit=20

# Verificar paginación
php artisan ghl:diagnose-pagination

# Probar operadores
php artisan ghl:test-operators usuario@ejemplo.com
```

---

## 📞 **COMANDOS DE EMERGENCIA**

### Cancelar Comparación Colgada
```bash
php artisan tinker --execute="
\$comparison = App\Models\ComparisonRecord::where('status', 'processing')->first();
if (\$comparison) {
    \$comparison->update(['status' => 'failed', 'error_message' => 'Cancelado manualmente']);
    echo 'Comparación cancelada: ' . \$comparison->id;
}
"
```

### Limpiar Cache
```bash
php artisan baremetrics:clear-plan-cache
php artisan cache:clear
php artisan config:clear
```

### Verificar Estado del Sistema
```bash
php artisan ghl:check-config
php artisan baremetrics:test-all-sources
php artisan baremetrics:test-environments
```

### Verificar Importaciones
```bash
php artisan baremetrics:verify-import --show-all
php artisan baremetrics:check-status
```

---

## 📋 **RESUMEN DE COMANDOS POR CATEGORÍA**

### 🗑️ Eliminación (2 comandos)
- `baremetrics:cleanup-duplicate-user` - Eliminar y re-importar
- `baremetrics:delete-user` - Eliminar sin re-importar

### 📋 Planes (6 comandos)
- `baremetrics:get-creetelo-plans` - Listar planes
- `baremetrics:update-creetelo-annual-plans` - Actualizar planes anuales
- `baremetrics:delete-recreate-annual-plans` - Recrear planes anuales
- `baremetrics:fix-creetelo-annual-plan` - Corregir plan anual
- `baremetrics:clear-plan-cache` - Limpiar cache
- `baremetrics:test-create-endpoints` - Probar creación

### 👥 Usuarios (8 comandos)
- `baremetrics:complete-test-import` - Importar completo
- `baremetrics:enhanced-import-production` - Importar mejorado
- `baremetrics:search-all-sources` - Buscar en todos sources
- `baremetrics:compare-ghl-all-sources` - Comparar con GHL
- `baremetrics:check-status` - Verificar estado
- `ghl:list-baremetrics-users` - Listar usuarios
- `baremetrics:search-user` - Buscar usuario específico
- `baremetrics:test-individual-import-production` - Probar importación

### 🔧 Custom Fields (3 comandos)
- `baremetrics:update-custom-fields` - Actualizar campos
- `baremetrics:mark-migrated` - Marcar como migrado
- `ghl:show-baremetrics-fields` - Mostrar campos

### 📊 Comparaciones (15 comandos)
- `baremetrics:test-csv-with-stripe-search` - Comparar CSV con Stripe
- `baremetrics:verify-missing-users` - Verificar faltantes
- `baremetrics:find-missing-users` - Encontrar faltantes
- `baremetrics:process-stripe-users` - Procesar Stripe
- `ghl:compare-with-baremetrics` - Comparar GHL vs Baremetrics
- `ghl:show-complete-comparison` - Mostrar comparación completa
- `ghl:analyze-missing-users` - Analizar faltantes
- Y 8 comandos más de análisis y comparación

### 🔍 Diagnósticos (12 comandos)
- `baremetrics:test-all-sources` - Probar todos sources
- `ghl:diagnose-connection` - Diagnosticar conexión
- `ghl:check-config` - Verificar configuración
- `baremetrics:test-environments` - Probar entornos
- `baremetrics:verify-import` - Verificar importaciones
- Y 7 comandos más de diagnóstico

### 📈 Procesamiento Masivo (8 comandos)
- `ghl:process-all-users` - Procesar todos usuarios
- `ghl:process-by-tags` - Procesar por tags
- `ghl:resume-processing` - Reanudar procesamiento
- `ghl:import-to-baremetrics` - Importar masivamente
- Y 4 comandos más de procesamiento

### 📊 Estadísticas (8 comandos)
- `ghl:count-users` - Contar usuarios
- `ghl:get-total-contacts` - Obtener total contactos
- `ghl:list-contacts` - Listar contactos
- Y 5 comandos más de conteo y estadísticas

### 🧪 Pruebas (15 comandos)
- `ghl:test-processing` - Probar procesamiento
- `ghl:test-api` - Probar API
- `ghl:test-system` - Probar sistema
- Y 12 comandos más de prueba

### 🔧 Mantenimiento (4 comandos)
- `ghl:refresh-token` - Refrescar token
- `baremetrics:fix-customers` - Corregir clientes
- `ghl:filter-users-by-tags-json` - Filtrar usuarios
- `ghl:process-comparison` - Procesar comparación

---

**Total: 81 comandos disponibles** para gestión completa de Baremetrics y GoHighLevel.

Esta guía cubre todos los comandos principales para gestionar Baremetrics y GoHighLevel. Para comandos específicos o problemas no cubiertos, consulta los logs o usa los comandos de diagnóstico.
