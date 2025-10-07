# 📚 Guía Completa de Comandos Baremetrics

## 🎯 Descripción General

Esta guía contiene todos los comandos disponibles para gestionar usuarios, planes, suscripciones y campos personalizados en Baremetrics. Los comandos están organizados por categorías para facilitar su uso.

> **📋 Documentación Completa**: Para ver TODOS los comandos disponibles (81 comandos), consulta: `docs/COMPLETE_COMMANDS_GUIDE.md`

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
```

### Crear Nuevo Plan
```bash
php artisan baremetrics:create-plan "Nombre del Plan" "interval" "amount" "currency"
```

**Ejemplo:**
```bash
# Crear plan mensual de $39
php artisan baremetrics:create-plan "Plan Mensual" "month" "3900" "usd"

# Crear plan anual de $390
php artisan baremetrics:create-plan "Plan Anual" "year" "39000" "usd"
```

### Eliminar Plan
```bash
php artisan baremetrics:delete-plan "plan_oid"
```

### Actualizar Plan (Borrar y Recrear)
```bash
php artisan baremetrics:delete-and-recreate-annual-plans
```

---

## 👥 **GESTIÓN DE USUARIOS**

### Importar Usuario Individual Completo
```bash
php artisan baremetrics:complete-test-import usuario@ejemplo.com
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
```

### Comparar Usuario GHL vs Baremetrics
```bash
php artisan baremetrics:compare-ghl-with-all-sources usuario@ejemplo.com
```

### Listar Usuarios de Baremetrics
```bash
php artisan ghl:list-baremetrics-users --limit=20 --search=usuario@ejemplo.com
```

---

## 🔧 **CUSTOM FIELDS**

### Actualizar Custom Fields de Usuario
```bash
php artisan baremetrics:update-custom-fields usuario@ejemplo.com
```

### Marcar Usuario como Migrado
```bash
php artisan baremetrics:mark-user-migrated usuario@ejemplo.com
```

**¿Qué hace?**
- ✅ Establece `GHL: Migrate GHL = true`
- ✅ Actualiza otros custom fields de GHL
- ✅ Confirma que el usuario fue migrado correctamente

### Mostrar Campos Disponibles
```bash
php artisan baremetrics:show-fields
```

---

## 📊 **COMPARACIONES Y ANÁLISIS**

### Comparar CSV con Todos los Sources
```bash
php artisan baremetrics:test-csv-with-stripe-search archivo.csv --name="Mi Comparación"
```

### Verificar Usuarios Faltantes
```bash
php artisan baremetrics:verify-missing-users {comparison_id} --limit=50
```

### Procesar Usuarios de Stripe por Separado
```bash
php artisan baremetrics:process-stripe-users {comparison_id} --limit=100
```

### Encontrar Usuarios Faltantes de GHL
```bash
php artisan baremetrics:find-missing-users --limit=50 --dry-run
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
```

### Verificar Configuración
```bash
php artisan ghl:check-config
```

### Probar API de GHL
```bash
php artisan ghl:test-api usuario@ejemplo.com --debug
```

---

## 📈 **PROCESAMIENTO MASIVO**

### Procesar Todos los Usuarios
```bash
php artisan ghl:process-all-users --limit=100 --delay=2
```

### Reanudar Procesamiento
```bash
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50
```

### Importar Usuarios Masivamente
```bash
php artisan ghl:import-users-to-baremetrics --limit=50 --dry-run
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
php artisan baremetrics:create-plan "Mi Plan" "month" "3900" "usd"

# Listar planes para obtener OID
php artisan baremetrics:get-creetelo-plans

# Eliminar plan si es necesario
php artisan baremetrics:delete-plan "plan_oid_aqui"
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
```

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
php artisan baremetrics:show-fields

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

### Limpiar Logs
```bash
php artisan log:clear
```

### Verificar Estado del Sistema
```bash
php artisan ghl:check-config
php artisan baremetrics:test-all-sources
```

---

Esta guía cubre todos los comandos principales para gestionar Baremetrics. Para comandos específicos o problemas no cubiertos, consulta los logs o usa los comandos de diagnóstico.
