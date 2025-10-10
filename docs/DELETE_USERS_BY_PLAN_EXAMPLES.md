# Ejemplos de Uso - Eliminar Usuarios por Plan

## 📋 Información General

- **Endpoint**: `/admin/baremetrics/delete-users-by-plan`
- **Método**: `POST`
- **Autenticación**: Requiere sesión de admin activa
- **Content-Type**: `application/json`

## 🌐 Acceso por Navegador

### Opción 1: Interfaz Gráfica (Recomendado)
```
URL: http://tu-dominio.com/admin/baremetrics/delete-users-by-plan
```

Pasos:
1. Inicia sesión como administrador
2. Navega a la URL anterior
3. Ingresa el nombre del plan: `creetelo_anual`
4. Haz clic en "Eliminar Usuarios del Plan"
5. Confirma la acción
6. Espera a que termine el proceso
7. Revisa los resultados en pantalla

## 🔧 Prueba con cURL

### Ejemplo Básico
```bash
curl -X POST 'http://tu-dominio.com/admin/baremetrics/delete-users-by-plan' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-TOKEN: OBTENER_DEL_META_TAG' \
  --cookie 'laravel_session=TU_SESSION_COOKIE' \
  -d '{
    "plan_name": "creetelo_anual"
  }'
```

### Ejemplo con Plan Diferente
```bash
curl -X POST 'http://tu-dominio.com/admin/baremetrics/delete-users-by-plan' \
  -H 'Content-Type: application/json' \
  -H 'X-CSRF-TOKEN: OBTENER_DEL_META_TAG' \
  --cookie 'laravel_session=TU_SESSION_COOKIE' \
  -d '{
    "plan_name": "creetelo_mensual"
  }'
```

### Cómo Obtener el CSRF Token
1. Abre tu navegador
2. Ve a cualquier página del admin
3. Abre las herramientas de desarrollador (F12)
4. En la consola ejecuta:
```javascript
document.querySelector('meta[name="csrf-token"]').getAttribute('content')
```

### Cómo Obtener la Cookie de Sesión
1. Abre las herramientas de desarrollador (F12)
2. Ve a la pestaña "Application" o "Storage"
3. Busca "Cookies"
4. Copia el valor de `laravel_session`

## 📮 Prueba con Postman

### Configuración
1. **Método**: POST
2. **URL**: `http://tu-dominio.com/admin/baremetrics/delete-users-by-plan`
3. **Headers**:
   - `Content-Type`: `application/json`
   - `X-CSRF-TOKEN`: `[tu-csrf-token]`
   - `Cookie`: `laravel_session=[tu-session-cookie]`
4. **Body** (raw JSON):
```json
{
  "plan_name": "creetelo_anual"
}
```

### Colección de Postman

```json
{
  "info": {
    "name": "Baremetrics - Eliminar Usuarios por Plan",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Eliminar usuarios - creetelo_anual",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-CSRF-TOKEN",
            "value": "{{csrf_token}}"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"plan_name\": \"creetelo_anual\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/admin/baremetrics/delete-users-by-plan",
          "host": ["{{base_url}}"],
          "path": ["admin", "baremetrics", "delete-users-by-plan"]
        }
      }
    },
    {
      "name": "Eliminar usuarios - creetelo_mensual",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "X-CSRF-TOKEN",
            "value": "{{csrf_token}}"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"plan_name\": \"creetelo_mensual\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/admin/baremetrics/delete-users-by-plan",
          "host": ["{{base_url}}"],
          "path": ["admin", "baremetrics", "delete-users-by-plan"]
        }
      }
    }
  ]
}
```

## 💻 Ejemplo con JavaScript (desde el navegador)

Abre la consola del navegador (F12) mientras estás en el admin:

```javascript
// Función para eliminar usuarios de un plan
async function deleteUsersByPlan(planName) {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    const response = await fetch('/admin/baremetrics/delete-users-by-plan', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      credentials: 'same-origin',
      body: JSON.stringify({ plan_name: planName })
    });

    const result = await response.json();
    
    if (result.success) {
      console.log('✅ Proceso completado:', result.message);
      console.log('📊 Datos:', result.data);
      console.table(result.data.processed_users);
    } else {
      console.error('❌ Error:', result.message);
    }
    
    return result;
  } catch (error) {
    console.error('❌ Error de conexión:', error);
  }
}

// Uso:
deleteUsersByPlan('creetelo_anual');
```

## 🐍 Ejemplo con Python

```python
import requests
import json

# Configuración
BASE_URL = 'http://tu-dominio.com'
CSRF_TOKEN = 'tu-csrf-token'
SESSION_COOKIE = 'tu-session-cookie'

# Headers
headers = {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': CSRF_TOKEN
}

# Cookies
cookies = {
    'laravel_session': SESSION_COOKIE
}

# Datos
data = {
    'plan_name': 'creetelo_anual'
}

# Realizar la petición
response = requests.post(
    f'{BASE_URL}/admin/baremetrics/delete-users-by-plan',
    headers=headers,
    cookies=cookies,
    json=data
)

# Procesar respuesta
if response.status_code == 200:
    result = response.json()
    if result['success']:
        print('✅ Proceso completado')
        print(f"Plan: {result['data']['plan_name']}")
        print(f"Procesados: {result['data']['total_processed']}")
        print(f"Exitosos: {result['data']['deleted_count']}")
        print(f"Fallidos: {result['data']['failed_count']}")
        
        # Mostrar usuarios procesados
        for user in result['data']['processed_users']:
            status_icon = '✅' if user['status'] == 'success' else '❌'
            print(f"{status_icon} {user['email']} - Subs: {user['subscriptions_deleted']}")
    else:
        print(f"❌ Error: {result['message']}")
else:
    print(f"❌ Error HTTP: {response.status_code}")
```

## 📝 Ejemplo de Respuesta Exitosa

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
        "customer_id": "cust_abc123def456",
        "email": "usuario1@example.com",
        "name": "Usuario Uno",
        "subscriptions_deleted": 1,
        "status": "success"
      },
      {
        "customer_id": "cust_xyz789ghi012",
        "email": "usuario2@example.com",
        "name": "Usuario Dos",
        "subscriptions_deleted": 2,
        "status": "success"
      },
      {
        "customer_id": "cust_mno345pqr678",
        "email": "usuario3@example.com",
        "name": "Usuario Tres",
        "subscriptions_deleted": 1,
        "status": "success"
      }
      // ... más usuarios
    ]
  }
}
```

## 📝 Ejemplo de Respuesta con Errores Parciales

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
      "Error eliminando usuario usuario14@example.com (ID: cust_error1)",
      "Error eliminando usuario usuario15@example.com (ID: cust_error2)"
    ],
    "processed_users": [
      {
        "customer_id": "cust_success1",
        "email": "usuario1@example.com",
        "name": "Usuario Uno",
        "subscriptions_deleted": 1,
        "status": "success"
      },
      {
        "customer_id": "cust_error1",
        "email": "usuario14@example.com",
        "name": "Usuario Catorce",
        "status": "failed",
        "error": "Error eliminando usuario usuario14@example.com (ID: cust_error1)"
      }
      // ... más usuarios
    ]
  }
}
```

## ⚠️ Ejemplo de Respuesta de Error

```json
{
  "success": false,
  "message": "No se encontraron suscripciones para el plan 'plan_inexistente'."
}
```

## 🎯 Casos de Uso Comunes

### 1. Eliminar usuarios del plan anual
```bash
# Navegador
http://tu-dominio.com/admin/baremetrics/delete-users-by-plan

# API
POST /admin/baremetrics/delete-users-by-plan
{"plan_name": "creetelo_anual"}
```

### 2. Eliminar usuarios del plan mensual
```bash
POST /admin/baremetrics/delete-users-by-plan
{"plan_name": "creetelo_mensual"}
```

### 3. Eliminar usuarios del plan trimestral
```bash
POST /admin/baremetrics/delete-users-by-plan
{"plan_name": "creetelo_trimestral"}
```

## 🔍 Verificación

Después de ejecutar, verifica:

1. **En la interfaz web**: Revisa la tabla de resultados
2. **En los logs**: `tail -f storage/logs/laravel.log`
3. **En Baremetrics**: Verifica que los usuarios fueron eliminados

## ❓ Preguntas Frecuentes

**P: ¿Puedo recuperar los usuarios eliminados?**  
R: No, la eliminación es permanente e irreversible.

**P: ¿Se eliminan solo las suscripciones del plan especificado?**  
R: No, se eliminan TODAS las suscripciones del usuario y luego el usuario mismo.

**P: ¿Cuánto tiempo tarda el proceso?**  
R: Depende del número de usuarios. Aproximadamente 0.5 segundos por usuario.

**P: ¿Qué pasa si hay un error?**  
R: El proceso continúa con los demás usuarios y reporta los errores al final.

**P: ¿Puedo cancelar el proceso?**  
R: Una vez iniciado, el proceso no se puede cancelar, pero puedes cerrar el navegador.
