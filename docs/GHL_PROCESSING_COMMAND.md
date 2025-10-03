# Comando de Procesamiento de Usuarios GHL

## Descripción

El comando `ghl:process-all-users` procesa todos los usuarios de GoHighLevel y actualiza sus campos personalizados en Baremetrics. Este comando está diseñado para sincronizar datos entre ambas plataformas de manera masiva.

## Características

- ✅ Obtiene todos los usuarios de Baremetrics (fuentes de Stripe)
- ✅ Busca cada usuario en GoHighLevel por email
- ✅ Extrae campos personalizados y datos de suscripción
- ✅ Actualiza campos personalizados en Baremetrics
- ✅ Genera estadísticas detalladas del procesamiento
- ✅ Envía reporte por correo electrónico
- ✅ Modo dry-run para pruebas sin cambios reales
- ✅ Barra de progreso en tiempo real
- ✅ Manejo robusto de errores

## Comandos disponibles

### 1. Procesamiento completo
```bash
php artisan ghl:process-all-users
```

### 2. Prueba de usuario individual
```bash
php artisan ghl:test-processing usuario@ejemplo.com
```

### 3. Verificación de configuración
```bash
php artisan ghl:check-config
```

### 4. Listar contactos de GoHighLevel
```bash
php artisan ghl:list-contacts --search=braulio@felamedia.com
```

### 5. Diagnosticar problemas de conexión
```bash
php artisan ghl:diagnose-connection
```

### 6. Refrescar token de GoHighLevel
```bash
php artisan ghl:refresh-token
```

### 7. Probar operadores de búsqueda
```bash
php artisan ghl:test-operators braulio@felamedia.com
```

### 8. Probar suscripciones de GoHighLevel
```bash
php artisan ghl:test-subscriptions braulio@felamedia.com --debug
```

### 9. Probar API de GoHighLevel
```bash
php artisan ghl:test-api braulio@felamedia.com --debug
```

### 10. Reanudar procesamiento optimizado
```bash
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50
```

### 11. Diagnosticar datos de Baremetrics
```bash
php artisan ghl:diagnose-baremetrics --limit=20 --check-oid
```

### 12. Listar usuarios de Baremetrics
```bash
php artisan ghl:list-baremetrics-users --limit=20 --offset=0
```

### 13. Mostrar campos de Baremetrics
```bash
php artisan ghl:show-baremetrics-fields
```

### 14. Procesar GHL hacia Baremetrics
```bash
php artisan ghl:process-ghl-to-baremetrics --delay=2 --batch-size=50
```

### 15. Analizar usuarios faltantes
```bash
php artisan ghl:analyze-missing-users --latest
```

## Uso

### Comando básico
```bash
php artisan ghl:process-all-users
```

### Con límite de usuarios
```bash
php artisan ghl:process-all-users --limit=100
```

### Modo dry-run (sin cambios reales)
```bash
php artisan ghl:process-all-users --dry-run
```

### Con correo de notificación personalizado
```bash
php artisan ghl:process-all-users --email=admin@tudominio.com
```

### Combinando opciones
```bash
php artisan ghl:process-all-users --limit=50 --dry-run --email=test@tudominio.com
```

## Opciones disponibles

| Opción | Descripción | Ejemplo |
|--------|-------------|---------|
| `--limit` | Limita el número de usuarios a procesar | `--limit=100` |
| `--dry-run` | Ejecuta sin hacer cambios reales | `--dry-run` |
| `--email` | Correo para notificaciones | `--email=admin@tudominio.com` |

## Configuración requerida

### Variables de entorno

Agrega estas variables a tu archivo `.env`:

```env
# Configuración de GoHighLevel
GHL_CLIENT_ID=tu_client_id
GHL_CLIENT_SECRET=tu_client_secret
GHL_LOCATION=tu_location_id
GHL_TOKEN=tu_token

# Correo para notificaciones
GHL_NOTIFICATION_EMAIL=admin@tudominio.com

# Configuración de Baremetrics
BAREMETRICS_ENVIRONMENT=sandbox
BAREMETRICS_SANDBOX_KEY=tu_sandbox_key
BAREMETRICS_LIVE_KEY=tu_live_key

# Configuración de correo
MAIL_MAILER=smtp
MAIL_HOST=tu_smtp_host
MAIL_PORT=587
MAIL_USERNAME=tu_email
MAIL_PASSWORD=tu_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Sistema GHL"
```

## Campos que se sincronizan

El comando sincroniza los siguientes campos de GoHighLevel a Baremetrics:

| Campo GHL | Campo Baremetrics | Descripción |
|-----------|-------------------|-------------|
| `relationship_status` | Estado de relación | Si está casado/soltero |
| `community_location` | Ubicación de comunidad | Lugar de nacimiento |
| `country` | País | País del usuario |
| `engagement_score` | Puntuación de engagement | Score de participación |
| `has_kids` | Tiene hijos | Si tiene hijos o no |
| `state` | Estado | Estado/provincia |
| `location` | Ubicación | Ciudad |
| `zodiac_sign` | Signo zodiacal | Signo del zodiaco |
| `subscriptions` | Suscripciones | Estado de suscripción |
| `coupon_code` | Código de cupón | Código promocional |

## Reporte por correo

El comando envía un reporte detallado por correo que incluye:

- 📊 Estadísticas generales (total procesado, exitosos, fallidos)
- 📈 Análisis de rendimiento
- ⚠️ Lista de errores encontrados
- 🔧 Detalles técnicos del procesamiento
- ✅ Confirmación de modo dry-run si aplica

## Manejo de errores

El comando maneja los siguientes tipos de errores:

- **Usuario no encontrado en GHL**: Se registra pero no detiene el proceso
- **Usuario sin email**: Se registra como error
- **Error de API**: Se registra y continúa con el siguiente usuario
- **Error de actualización en Baremetrics**: Se registra y continúa

## Logs

El comando genera logs detallados en:
- `storage/logs/laravel.log`
- Logs específicos para cada operación
- Logs de errores con stack trace completo

## Rendimiento

- **Pausa entre requests**: 0.1 segundos para no sobrecargar las APIs
- **Procesamiento por lotes**: Maneja grandes volúmenes de usuarios
- **Memoria eficiente**: Procesa usuarios uno por uno
- **Tiempo estimado**: ~0.1-0.2 segundos por usuario

## Ejemplos de uso

### Procesamiento completo
```bash
php artisan ghl:process-all-users
```

### Prueba con 10 usuarios
```bash
php artisan ghl:process-all-users --limit=10 --dry-run
```

### Procesamiento nocturno programado
```bash
# En crontab
0 2 * * * cd /path/to/project && php artisan ghl:process-all-users
```

## Comandos de utilidad

### Verificar configuración
Antes de ejecutar el procesamiento, es recomendable verificar que toda la configuración esté correcta:

```bash
php artisan ghl:check-config
```

Este comando verificará:
- ✅ Configuración de GoHighLevel
- ✅ Configuración de Baremetrics  
- ✅ Configuración de Stripe
- ✅ Configuración de correo
- ✅ Conexiones con las APIs

### Probar con un usuario específico
Para probar el sistema con un usuario específico antes del procesamiento masivo:

```bash
# Modo dry-run (recomendado para pruebas)
php artisan ghl:test-processing usuario@ejemplo.com --dry-run

# Modo real (actualizará datos)
php artisan ghl:test-processing usuario@ejemplo.com

# Con debugging para ver respuestas completas
php artisan ghl:test-processing usuario@ejemplo.com --debug
```

Este comando mostrará:
- 📋 Datos del usuario en ambas plataformas
- 📊 Campos extraídos de GoHighLevel
- 🔍 Vista previa de la actualización (en dry-run)
- 🔍 Respuesta completa de APIs (con --debug)

### Listar contactos de GoHighLevel
Para buscar y listar contactos en GoHighLevel:

```bash
# Buscar contactos por término
php artisan ghl:list-contacts --search=braulio@felamedia.com

# Ver todos los contactos (limitado)
php artisan ghl:list-contacts --limit=20

# Con información de debugging
php artisan ghl:list-contacts --search=braulio --debug
```

Este comando es útil para:
- 🔍 Verificar que un contacto existe en GoHighLevel
- 📋 Ver todos los datos disponibles del contacto
- 🐛 Debugging de problemas de búsqueda

### Diagnosticar problemas de conexión
Para diagnosticar problemas específicos con GoHighLevel:

```bash
php artisan ghl:diagnose-connection
```

Este comando verifica:
- ✅ Configuración básica en .env
- ✅ Configuración en base de datos
- ✅ Estado del token y expiración
- ✅ Conexión real con la API
- 🔧 Proporciona soluciones específicas

### Refrescar token de GoHighLevel
Para renovar manualmente el token:

```bash
# Renovar si está próximo a expirar
php artisan ghl:refresh-token

# Forzar renovación inmediata
php artisan ghl:refresh-token --force
```

Este comando:
- 🔄 Renueva el token usando el refresh_token
- 🧪 Prueba la conexión con el nuevo token
- 📅 Muestra nueva fecha de expiración

### Probar operadores de búsqueda
Para probar qué operadores funcionan mejor con la API de GoHighLevel:

```bash
# Probar operadores comunes
php artisan ghl:test-operators braulio@felamedia.com

# Probar todos los operadores disponibles
php artisan ghl:test-operators braulio@felamedia.com --all
```

Este comando:
- 🧪 Prueba diferentes operadores de búsqueda
- 📊 Muestra qué operadores funcionan
- 💡 Proporciona recomendaciones
- 📋 Muestra detalles del contacto encontrado

### Probar suscripciones de GoHighLevel
Para probar específicamente la obtención de suscripciones:

```bash
# Probar obtención de suscripciones
php artisan ghl:test-subscriptions braulio@felamedia.com

# Con información de debugging completa
php artisan ghl:test-subscriptions braulio@felamedia.com --debug
```

Este comando:
- 🔍 Busca el usuario en GoHighLevel
- 📋 Obtiene suscripciones usando ambos métodos
- 📊 Muestra detalles completos de la suscripción más reciente
- 🎫 Analiza códigos de cupón utilizados
- ✅ Verifica estados de suscripción (activo, cancelado, etc.)

### Probar API de GoHighLevel
Para probar la API HTTP mejorada:

```bash
# Probar API con servicios y HTTP
php artisan ghl:test-api braulio@felamedia.com

# Con información de debugging completa
php artisan ghl:test-api braulio@felamedia.com --debug

# Con URL y API key personalizados
php artisan ghl:test-api braulio@felamedia.com --url=https://tu-dominio.com --api-key=tu-api-key
```

Este comando:
- 🔍 Prueba servicios directamente (GoHighLevel, Stripe, Baremetrics)
- 🌐 Prueba la API HTTP completa
- 📊 Compara resultados entre ambos métodos
- ✅ Verifica que la API funcione correctamente
- 🔧 Útil para debugging de integraciones

### Reanudar procesamiento optimizado
Para reanudar el procesamiento desde donde se quedó con configuración optimizada:

```bash
# Reanudar desde usuario 1325 con delays optimizados
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50 --batch-delay=10

# Con modo dry-run para probar
php artisan ghl:resume-processing --from=1325 --delay=2 --batch-size=50 --dry-run

# Configuración ultra-conservadora para evitar rate limiting
php artisan ghl:resume-processing --delay=3 --batch-size=25 --batch-delay=15
```

Este comando:
- 🔄 Reanuda desde cualquier punto del procesamiento
- ⏱️ Configuración optimizada para evitar rate limiting
- 📊 Estadísticas detalladas de errores (rate limiting, servidor, etc.)
- 💡 Recomendaciones automáticas si detecta problemas
- 🚫 Manejo inteligente de errores 429 (rate limiting)
- 🔧 Manejo de errores 5xx (servidor)

### Diagnosticar datos de Baremetrics
Para diagnosticar problemas con la estructura de datos:

```bash
# Diagnóstico básico
php artisan ghl:diagnose-baremetrics

# Con más usuarios de muestra
php artisan ghl:diagnose-baremetrics --limit=50

# Verificar usuarios sin OID específicamente
php artisan ghl:diagnose-baremetrics --check-oid --limit=20
```

Este comando:
- 🔍 Analiza la estructura de datos de Baremetrics
- 📊 Muestra estadísticas de usuarios (OID, email, etc.)
- ⚠️ Identifica usuarios con estructura inválida
- 📋 Muestra ejemplos de estructura de datos
- 💡 Proporciona recomendaciones para solucionar problemas
- 🛠️ Útil para debugging de errores "Undefined array key"

### Listar usuarios de Baremetrics
Para ver usuarios disponibles y sus índices:

```bash
# Listar primeros 20 usuarios
php artisan ghl:list-baremetrics-users --limit=20

# Ver usuarios desde índice 100
php artisan ghl:list-baremetrics-users --offset=100 --limit=20

# Buscar usuario específico
php artisan ghl:list-baremetrics-users --search=usuario@ejemplo.com

# Ver todos los usuarios (paginado)
php artisan ghl:list-baremetrics-users --limit=50 --offset=0
```

Este comando:
- 👥 Muestra usuarios con sus índices
- 📊 Facilita encontrar el índice correcto para reanudar
- 🔍 Permite buscar usuarios por email
- 📋 Muestra información de navegación
- 💡 Proporciona comandos de ejemplo para reanudar

### Mostrar campos de Baremetrics
Para ver qué custom fields se están actualizando:

```bash
php artisan ghl:show-baremetrics-fields
```

Este comando:
- 📋 Muestra todos los campos que se actualizan en Baremetrics
- 🗂️ Explica el mapeo entre GoHighLevel y Baremetrics
- 📊 Agrupa campos por fuente (Custom Fields, Contact Fields, Subscription Data)
- 🔍 Muestra IDs de campos en ambas plataformas
- 💡 Proporciona información adicional sobre el proceso

### Contar usuarios con filtros
Para contar usuarios de GoHighLevel con filtros específicos antes de procesar:

```bash
# Contar usuarios activos con suscripción activa (filtros por defecto)
php artisan ghl:count-users

# Contar solo usuarios activos (sin filtro de suscripción)
php artisan ghl:count-users --with-subscription=false

# Contar todos los usuarios (sin filtros)
php artisan ghl:count-users --no-filters

# Contar con límite específico
php artisan ghl:count-users --limit=5000
```

Este comando:
- 🔍 Aplica filtros específicos a usuarios de GoHighLevel
- 📊 Muestra estadísticas de filtrado
- ⚡ Es más rápido que el procesamiento completo
- 💡 Ayuda a estimar cuántos usuarios se procesarán
- 🎯 Útil para planificar el procesamiento

### Diagnosticar conexión básica
Para diagnosticar problemas de conexión con GoHighLevel:

```bash
# Prueba básica de conexión
php artisan ghl:test-basic-connection

# Diagnóstico completo de conexión
php artisan ghl:diagnose-connection --test-api --test-token --test-location
```

Estos comandos:
- 🔧 Verifican configuración básica (variables de entorno)
- 🔑 Prueban validez del token de acceso
- 📍 Verifican configuración de ubicación
- 🌐 Prueban conexión a la API
- 👥 Verifican obtención de contactos

### Probar total de usuarios sin filtros
Para verificar el total de usuarios disponibles en GoHighLevel:

```bash
# Probar obtención de usuarios sin filtros
php artisan ghl:test-total-users --limit=1000

# Con método optimizado
php artisan ghl:test-total-users --limit=5000 --method=optimized

# Mostrar muestra de usuarios encontrados
php artisan ghl:test-total-users --limit=1000 --show-sample
```

Este comando:
- 📊 Obtiene usuarios sin filtros para verificar el total
- 🔍 Analiza estructura de tags en todos los usuarios
- 📈 Muestra velocidad de procesamiento
- 🏷️ Lista tags más comunes encontrados
- 🎯 Cuenta específicamente los tags objetivo

### Comparar conteos total vs filtrado
Para comparar el conteo total con el filtrado por tags:

```bash
# Comparar conteos con límite pequeño
php artisan ghl:compare-users-count --limit=1000

# Con método optimizado
php artisan ghl:compare-users-count --limit=5000 --method=optimized

# Con tags personalizados
php artisan ghl:compare-users-count --tags=creetelo_anual,creetelo_mensual --limit=1000
```

Este comando:
- 🔄 Compara conteo total vs filtrado por tags
- 📊 Muestra ratios y porcentajes de filtrado
- ⚡ Compara velocidades de procesamiento
- 🔍 Verifica consistencia entre métodos
- 💡 Proporciona recomendaciones basadas en resultados

### Diagnosticar problemas con tags
Para diagnosticar problemas con la búsqueda de tags:

```bash
# Diagnosticar estructura de tags
php artisan ghl:diagnose-tags --limit=500

# Mostrar todos los tags encontrados
php artisan ghl:diagnose-tags --limit=1000 --show-tags

# Diagnosticar con límite pequeño para prueba rápida
php artisan ghl:diagnose-tags --limit=100
```

Este comando:
- 🔍 Analiza la estructura de tags en GoHighLevel
- 📊 Muestra estadísticas de contactos con/sin tags
- 🎯 Cuenta específicamente los tags objetivo
- 🏷️ Lista todos los tags encontrados (opcional)
- 🔍 Identifica posibles problemas en la búsqueda

### Probar búsqueda por tags con API directa
Para probar la búsqueda usando el método API directo:

```bash
# Probar método API directo
php artisan ghl:test-tags-api --limit=100

# Probar con tags personalizados
php artisan ghl:test-tags-api --tags=creetelo_anual,creetelo_mensual --limit=50
```

Este comando:
- 🧪 Prueba el método API directo de GoHighLevel
- 📊 Compara resultados con el método alternativo
- 🔍 Identifica errores específicos de la API
- 📋 Muestra ejemplos de contactos encontrados
- 🏷️ Analiza distribución de tags encontrados

### Probar búsqueda por tags
Para probar la búsqueda de usuarios por tags antes de procesar:

```bash
# Probar búsqueda con método alternativo (recomendado)
php artisan ghl:test-tags-search

# Probar con límite específico
php artisan ghl:test-tags-search --limit=100

# Probar con tags personalizados
php artisan ghl:test-tags-search --tags=creetelo_anual,creetelo_mensual,otro_tag

# Probar método API directo
php artisan ghl:test-tags-search --method=api --limit=50
```

Este comando:
- 🧪 Prueba la búsqueda por tags sin procesar usuarios
- 📊 Muestra estadísticas de tags encontrados
- 🔍 Compara métodos de búsqueda (API vs alternativo)
- 📋 Lista ejemplos de usuarios encontrados
- 🏷️ Analiza distribución de tags

### Probar búsqueda por tags
Para probar la búsqueda de usuarios por tags antes de procesar:

```bash
# Probar búsqueda con método alternativo (recomendado)
php artisan ghl:test-tags-search

# Probar con límite específico
php artisan ghl:test-tags-search --limit=100

# Probar con tags personalizados
php artisan ghl:test-tags-search --tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual

# Probar método API directo
php artisan ghl:test-tags-search --method=api --limit=50
```

Este comando:
- 🧪 Prueba la búsqueda por tags sin procesar usuarios
- 📊 Muestra estadísticas de tags encontrados
- 🔍 Compara métodos de búsqueda (API vs alternativo)
- 📋 Lista ejemplos de usuarios encontrados
- 🏷️ Analiza distribución de tags

### Procesar usuarios por tags (Grandes Volúmenes)
Para procesar usuarios de GoHighLevel con más de 100,000 usuarios:

```bash
# Procesar usuarios con tags (optimizado para grandes volúmenes)
php artisan ghl:process-by-tags-large

# Solo contar usuarios (método optimizado)
php artisan ghl:process-by-tags-large --count-only

# Con configuración optimizada
php artisan ghl:process-by-tags-large --delay=1 --batch-size=100 --batch-delay=5

# Con límite para prueba
php artisan ghl:process-by-tags-large --limit=1000 --count-only
```

Este comando:
- 🚀 Optimizado para 100,000+ usuarios
- ⚡ Usa pageLimit de 1000 para máximo rendimiento
- 📊 Progreso cada 1000 usuarios procesados
- 🔍 Filtrado optimizado con array_intersect
- ⏱️ Delays optimizados para grandes volúmenes
- 📈 Muestra velocidad y tiempo estimado

### Procesar usuarios por tags específicos
Para procesar usuarios de GoHighLevel filtrados por tags específicos (creetelo_anual, creetelo_mensual, créetelo_anual, créetelo_mensual):

```bash
# Procesar usuarios con tags creetelo_anual, creetelo_mensual, créetelo_anual, créetelo_mensual
php artisan ghl:process-by-tags

# Solo contar usuarios con tags (sin procesar)
php artisan ghl:process-by-tags --count-only

# Procesar con tags personalizados
php artisan ghl:process-by-tags --tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual,otro_tag

# Con límite de usuarios
php artisan ghl:process-by-tags --limit=100 --delay=2

# Modo dry-run para probar
php artisan ghl:process-by-tags --dry-run --delay=1 --batch-size=10

# Con notificaciones por email
php artisan ghl:process-by-tags --email=admin@ejemplo.com --delay=2
```

Este comando:
- 🏷️ Filtra usuarios por tags específicos (creetelo_anual, creetelo_mensual, créetelo_anual, créetelo_mensual)
- 🔄 Itera usuarios de GoHighLevel con los tags especificados
- 🔍 Los busca en Baremetrics por email
- ✅ Actualiza campos custom si existen en Baremetrics
- 📋 Genera lista de usuarios faltantes en Baremetrics con información completa
- 📊 Proporciona estadísticas detalladas
- 🚫 Maneja rate limiting y errores de servidor
- 🎫 Incluye información de membresía y suscripciones
- 🎟️ Registra cupones utilizados por los usuarios
- 📊 Modo conteo rápido disponible

### Procesar GHL hacia Baremetrics
Para procesar usuarios de GoHighLevel y actualizarlos en Baremetrics:

```bash
# Procesamiento básico (usuarios activos con suscripción activa)
php artisan ghl:process-ghl-to-baremetrics --delay=2 --batch-size=50

# Solo usuarios activos (sin filtro de suscripción)
php artisan ghl:process-ghl-to-baremetrics --with-subscription=false --delay=2

# Todos los usuarios (sin filtros)
php artisan ghl:process-ghl-to-baremetrics --no-filters --delay=2

# Con límite de usuarios
php artisan ghl:process-ghl-to-baremetrics --limit=100 --delay=2 --batch-size=25

# Modo dry-run para probar
php artisan ghl:process-ghl-to-baremetrics --dry-run --delay=1 --batch-size=10

# Con notificaciones por email
php artisan ghl:process-ghl-to-baremetrics --email=admin@ejemplo.com --delay=2
```

Este comando:
- 🔄 Itera usuarios de GoHighLevel
- 🔍 Los busca en Baremetrics por email
- ✅ Actualiza campos custom si existen en Baremetrics
- 📋 Genera lista de usuarios faltantes en Baremetrics con información completa
- 📊 Proporciona estadísticas detalladas
- 🚫 Maneja rate limiting y errores de servidor
- 🎫 Incluye información de membresía y suscripciones
- 🎟️ Registra cupones utilizados por los usuarios

### Analizar usuarios faltantes
Para analizar el reporte de usuarios que existen en GHL pero no en Baremetrics:

```bash
# Analizar el reporte más reciente
php artisan ghl:analyze-missing-users --latest

# Analizar archivo específico
php artisan ghl:analyze-missing-users --file=ghl-missing-users-2024-01-15-14-30-00.json

# Ver archivos disponibles
php artisan ghl:analyze-missing-users
```

Este comando:
- 📊 Analiza estadísticas de usuarios faltantes
- 📧 Muestra análisis de dominios de email
- 📅 Agrupa usuarios por fechas de creación
- 👥 Muestra ejemplos de usuarios faltantes
- 💡 Proporciona recomendaciones para implementación
- 🛠️ Sugiere próximos pasos
- 🎫 Analiza información de membresías
- 💳 Analiza estados de suscripciones
- 🎟️ Analiza cupones utilizados por los usuarios

## Solución de problemas

### Error de conexión con GoHighLevel
Si tienes problemas de conexión con GoHighLevel:

1. **Diagnóstico completo**:
   ```bash
   php artisan ghl:diagnose-connection
   ```

2. **Verificar configuración básica**:
   ```bash
   php artisan ghl:check-config
   ```

3. **Renovar token**:
   ```bash
   php artisan ghl:refresh-token --force
   ```

4. **Probar conexión manual**:
   ```bash
   php artisan ghl:list-contacts --limit=1
   ```

5. **Problemas comunes**:
   - **Token expirado**: Ejecuta `php artisan ghl:refresh-token`
   - **Configuración incorrecta**: Verifica variables en `.env`
   - **Permisos insuficientes**: Revisa configuración en GoHighLevel
   - **Proceso de autorización**: Ve a `/admin/ghlevel/initial`

### Error 422: Operador no válido
Si recibes un error 422 sobre operadores no válidos:

```bash
# Probar qué operadores funcionan
php artisan ghl:test-operators braulio@felamedia.com --all
```

**Operadores válidos en GoHighLevel:**
- `eq` - Igual a (exacto)
- `not_eq` - No igual a
- `contains` - Contiene
- `not_contains` - No contiene
- `wildcard` - Comodín
- `not_wildcard` - No comodín

**Solución**: El sistema ahora usa automáticamente `eq` para búsquedas exactas.

### Mejoras en obtención de suscripciones
El sistema ahora obtiene la suscripción más reciente y correcta:

**Antes:**
- ❌ Solo obtenía la primera suscripción (`data[0]`)
- ❌ No consideraba fechas de creación
- ❌ No diferenciaba entre activas e inactivas

**Ahora:**
- ✅ Ordena suscripciones por fecha de creación (más reciente primero)
- ✅ Obtiene la suscripción más reciente (sin importar el estado)
- ✅ Logs detallados para debugging
- ✅ Información completa de cupones y estados
- ✅ Siempre el estado más actual del usuario

**Campos mejorados:**
- `subscriptions`: Estado de la suscripción más reciente
- `coupon_code`: Código de cupón de la suscripción más reciente
- Información adicional: ID, fechas, precios, etc.

### Mejoras en la API HTTP
La API `POST /api/gohighlevel/contact/update` ha sido mejorada significativamente:

**Antes:**
- ❌ Búsqueda básica en GoHighLevel
- ❌ Manejo de errores limitado
- ❌ Respuestas simples
- ❌ Logs básicos

**Ahora:**
- ✅ **Búsqueda mejorada**: Primero exacta, luego con "contains"
- ✅ **Validación robusta**: Verifica existencia en GHL y Stripe
- ✅ **Respuestas detalladas**: Incluye IDs, estados, campos actualizados
- ✅ **Logs completos**: Para debugging y monitoreo
- ✅ **Manejo de errores**: Códigos HTTP apropiados y mensajes claros
- ✅ **Información de suscripción**: Estado y cupón más recientes

**Nueva respuesta de la API:**
```json
{
  "success": true,
  "message": "Actualización exitosa",
  "data": {
    "email": "usuario@ejemplo.com",
    "contact_id": "ghl_contact_id",
    "stripe_id": "stripe_customer_id",
    "subscription_status": "active",
    "coupon_code": "DESCUENTO20",
    "subscription_id": "sub_123",
    "subscription_created_at": "2024-01-15T10:30:00Z",
    "updated_fields": ["relationship_status", "subscriptions", "coupon_code"]
  },
  "baremetrics_result": {...}
}
```

### Error: "No se pudieron obtener las fuentes de Baremetrics"
- Ejecuta `php artisan ghl:check-config` para verificar la configuración
- Verifica que `BAREMETRICS_SANDBOX_KEY` o `BAREMETRICS_LIVE_KEY` esté configurado
- Verifica que `BAREMETRICS_ENVIRONMENT` sea correcto

### Error: Rate Limiting en Baremetrics
Si el procesamiento se detiene por rate limiting (error 429):

**Síntomas:**
- El proceso se detiene alrededor del 20%
- Errores 429 en los logs
- Mensaje "Rate limiting en Baremetrics"

**Soluciones:**

1. **Reanudar con delays mayores:**
```bash
php artisan ghl:resume-processing --from=1325 --delay=3 --batch-size=25 --batch-delay=15
```

2. **Usar configuración ultra-conservadora:**
```bash
php artisan ghl:resume-processing --delay=5 --batch-size=10 --batch-delay=30
```

3. **Procesar en horarios de menor tráfico:**
- Evitar horas pico (9-17 horas locales)
- Procesar en madrugada o fines de semana

4. **Configurar el comando original con delays:**
```bash
php artisan ghl:process-all-users --delay=2 --batch-size=50 --batch-delay=10
```

**Recomendaciones:**
- ⏱️ Delay mínimo recomendado: 2 segundos entre requests
- 📦 Lote máximo recomendado: 50 usuarios
- ⏸️ Delay entre lotes: 10-15 segundos
- 🔄 Usar `ghl:resume-processing` para reanudar desde donde se quedó

### Error: "Undefined array key 'oid'"
Si obtienes este error al procesar usuarios:

**Síntomas:**
- Error: `Undefined array key "oid"`
- El procesamiento se detiene inmediatamente
- Problema con estructura de datos de Baremetrics

**Causas:**
- Usuarios en Baremetrics sin campo `oid`
- Estructura de datos inconsistente
- Problema en la obtención de usuarios

**Soluciones:**

1. **Diagnosticar el problema:**
```bash
php artisan ghl:diagnose-baremetrics --check-oid --limit=50
```

2. **Verificar estructura de datos:**
- El comando mostrará usuarios sin OID
- Identificará problemas de estructura
- Proporcionará recomendaciones

3. **Usar comando mejorado:**
```bash
# El comando resume-processing ahora valida la estructura
php artisan ghl:resume-processing --from=1733 --delay=2 --batch-size=50
```

4. **Filtrar usuarios inválidos:**
- El comando ahora filtra automáticamente usuarios sin OID
- Muestra estadísticas de usuarios válidos vs inválidos
- Continúa solo con usuarios válidos

**Prevención:**
- Ejecutar diagnóstico antes del procesamiento masivo
- Usar comandos con validación de estructura
- Monitorear logs para detectar problemas temprano

### Error: "No hay usuarios para procesar"
Si el comando dice "quedan 0 usuarios" o "procesando 0 lotes":

**Síntomas:**
- "Reanudando desde usuario X (quedan 0 usuarios)"
- "Procesando 0 lotes de 50 usuarios cada uno"
- "Total procesados: 0"

**Causas:**
- El parámetro `--from` es mayor que el total de usuarios
- Solo hay pocos usuarios en Baremetrics (ej: 15 usuarios)
- Intentando reanudar desde índice 1733 cuando solo hay 15 usuarios

**Soluciones:**

1. **Verificar usuarios disponibles:**
```bash
php artisan ghl:list-baremetrics-users --limit=50
```

2. **Usar índice válido:**
```bash
# Si solo hay 15 usuarios, los índices válidos son 0-14
php artisan ghl:resume-processing --from=0 --delay=2 --batch-size=50
```

3. **Procesar todos los usuarios:**
```bash
php artisan ghl:resume-processing --delay=2 --batch-size=50
```

**Importante:**
- El parámetro `--from` se refiere al **índice** en el array, no al ID del usuario
- Si hay 15 usuarios, los índices válidos son 0, 1, 2, ..., 14
- Usar `--from=0` para procesar todos los usuarios

### Error: "Token de GoHighLevel inválido"
- Ejecuta `php artisan ghl:check-config` para verificar la conexión
- Verifica que `GHL_TOKEN` esté actualizado
- El comando intentará refrescar el token automáticamente

### Error: "No se pudo enviar correo"
- Ejecuta `php artisan ghl:check-config` para verificar la configuración de correo
- Verifica la configuración de correo en `.env`
- Verifica que `GHL_NOTIFICATION_EMAIL` esté configurado

### Usuario no encontrado en GoHighLevel
Si el comando dice que no encuentra el usuario en GoHighLevel:

1. **Verificar que el contacto existe**:
   ```bash
   php artisan ghl:list-contacts --search=braulio@felamedia.com
   ```

2. **Probar con debugging**:
   ```bash
   php artisan ghl:test-processing braulio@felamedia.com --debug
   ```

3. **Verificar configuración**:
   ```bash
   php artisan ghl:check-config
   ```

4. **Posibles causas**:
   - El email no existe en GoHighLevel
   - El token de GHL ha expirado
   - El contacto está en una ubicación diferente
   - Problemas de conectividad con la API

### Usuario no encontrado
- Usa `php artisan ghl:test-processing usuario@ejemplo.com` para diagnosticar
- Verifica que el email exista en ambas plataformas
- Verifica que el usuario tenga datos de Stripe en Baremetrics

### Procesamiento muy lento
- Usa `--limit` para procesar en lotes más pequeños
- Verifica la conexión a internet con `php artisan ghl:check-config`
- Considera ejecutar en horarios de menor tráfico

## Monitoreo

Para monitorear el progreso en tiempo real:

```bash
# En una terminal
tail -f storage/logs/laravel.log | grep "GHL"

# O usar el comando con verbose
php artisan ghl:process-all-users -v
```

## Integración con cron

Para ejecutar automáticamente:

```bash
# Editar crontab
crontab -e

# Agregar línea para ejecutar cada día a las 2 AM
0 2 * * * cd /path/to/project && php artisan ghl:process-all-users >> /var/log/ghl-processing.log 2>&1
```
