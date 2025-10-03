# Ejemplos de Uso de la API de Filtrado GHL

## Configuración Inicial

### 1. Configurar API Key

Asegúrate de tener configurada la variable de entorno `API_ROUTE_KEY` en tu archivo `.env`:

```env
API_ROUTE_KEY=tu-api-key-secreta-aqui
```

### 2. Iniciar el Servidor

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Ejemplos de Uso

### Ejemplo 1: Filtrar Usuarios con cURL

```bash
curl -X GET "http://localhost:8000/api/ghl/filter/users?tags=creetelo_mensual,creetelo_anual&exclude_tags=unsubscribe&limit=100" \
  -H "X-API-KEY: tu-api-key-secreta-aqui" \
  -H "Accept: application/json"
```

### Ejemplo 2: Obtener Estadísticas de Tags

```bash
curl -X GET "http://localhost:8000/api/ghl/tags/statistics?tags=creetelo_mensual,creetelo_anual" \
  -H "X-API-KEY: tu-api-key-secreta-aqui" \
  -H "Accept: application/json"
```

### Ejemplo 3: Usando JavaScript/Fetch

```javascript
const apiKey = 'tu-api-key-secreta-aqui';
const baseUrl = 'http://localhost:8000/api/ghl';

// Filtrar usuarios
async function filterUsers() {
  try {
    const response = await fetch(`${baseUrl}/filter/users?tags=creetelo_mensual,creetelo_anual&exclude_tags=unsubscribe&limit=100`, {
      method: 'GET',
      headers: {
        'X-API-KEY': apiKey,
        'Accept': 'application/json'
      }
    });
    
    const data = await response.json();
    
    if (data.success) {
      console.log('Total usuarios:', data.data.metadata.total_users_final);
      console.log('Usuarios:', data.data.users);
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('Error de red:', error);
  }
}

// Obtener estadísticas
async function getTagStatistics() {
  try {
    const response = await fetch(`${baseUrl}/tags/statistics?tags=creetelo_mensual,creetelo_anual`, {
      method: 'GET',
      headers: {
        'X-API-KEY': apiKey,
        'Accept': 'application/json'
      }
    });
    
    const data = await response.json();
    
    if (data.success) {
      console.log('Estadísticas:', data.data.tag_statistics);
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('Error de red:', error);
  }
}
```

### Ejemplo 4: Usando PHP

```php
<?php

$apiKey = 'tu-api-key-secreta-aqui';
$baseUrl = 'http://localhost:8000/api/ghl';

// Filtrar usuarios
function filterUsers($apiKey, $baseUrl) {
    $url = $baseUrl . '/filter/users?tags=creetelo_mensual,creetelo_anual&exclude_tags=unsubscribe&limit=100';
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'X-API-KEY: ' . $apiKey,
                'Accept: application/json'
            ]
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);
    
    if ($data['success']) {
        echo "Total usuarios: " . $data['data']['metadata']['total_users_final'] . "\n";
        echo "Usuarios encontrados: " . count($data['data']['users']) . "\n";
        
        foreach ($data['data']['users'] as $user) {
            echo "Usuario: " . $user['email'] . " - Tags: " . implode(', ', $user['tags']) . "\n";
        }
    } else {
        echo "Error: " . $data['message'] . "\n";
    }
}

// Obtener estadísticas
function getTagStatistics($apiKey, $baseUrl) {
    $url = $baseUrl . '/tags/statistics?tags=creetelo_mensual,creetelo_anual';
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'X-API-KEY: ' . $apiKey,
                'Accept: application/json'
            ]
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);
    
    if ($data['success']) {
        echo "Estadísticas de tags:\n";
        foreach ($data['data']['tag_statistics'] as $tag => $stats) {
            echo "Tag '{$tag}': {$stats['total_users']} usuarios\n";
        }
    } else {
        echo "Error: " . $data['message'] . "\n";
    }
}

// Ejecutar funciones
filterUsers($apiKey, $baseUrl);
echo "\n" . str_repeat("-", 50) . "\n";
getTagStatistics($apiKey, $baseUrl);
?>
```

### Ejemplo 5: Usando Python

```python
import requests
import json

api_key = 'tu-api-key-secreta-aqui'
base_url = 'http://localhost:8000/api/ghl'

def filter_users():
    url = f"{base_url}/filter/users"
    params = {
        'tags': 'creetelo_mensual,creetelo_anual',
        'exclude_tags': 'unsubscribe',
        'limit': 100
    }
    headers = {
        'X-API-KEY': api_key,
        'Accept': 'application/json'
    }
    
    try:
        response = requests.get(url, params=params, headers=headers)
        data = response.json()
        
        if data['success']:
            print(f"Total usuarios: {data['data']['metadata']['total_users_final']}")
            print(f"Usuarios encontrados: {len(data['data']['users'])}")
            
            for user in data['data']['users']:
                print(f"Usuario: {user['email']} - Tags: {', '.join(user['tags'])}")
        else:
            print(f"Error: {data['message']}")
    except Exception as e:
        print(f"Error de red: {e}")

def get_tag_statistics():
    url = f"{base_url}/tags/statistics"
    params = {
        'tags': 'creetelo_mensual,creetelo_anual'
    }
    headers = {
        'X-API-KEY': api_key,
        'Accept': 'application/json'
    }
    
    try:
        response = requests.get(url, params=params, headers=headers)
        data = response.json()
        
        if data['success']:
            print("Estadísticas de tags:")
            for tag, stats in data['data']['tag_statistics'].items():
                print(f"Tag '{tag}': {stats['total_users']} usuarios")
        else:
            print(f"Error: {data['message']}")
    except Exception as e:
        print(f"Error de red: {e}")

# Ejecutar funciones
filter_users()
print("\n" + "-" * 50)
get_tag_statistics()
```

## Respuestas de Ejemplo

### Respuesta Exitosa de Filtrado

```json
{
  "success": true,
  "data": {
    "metadata": {
      "generated_at": "2025-10-02T22:30:00.000000Z",
      "include_tags": ["creetelo_mensual", "creetelo_anual"],
      "exclude_tags": ["unsubscribe"],
      "total_users_before_exclusion": 1333,
      "total_users_excluded": 171,
      "total_users_final": 1162,
      "sum_without_deduplication": 1370,
      "duplicates_removed": 37,
      "processing_time_seconds": 46,
      "max_pages_per_tag": 100,
      "limit_applied": 1500
    },
    "tag_statistics": {
      "creetelo_mensual": {
        "total_users": 703,
        "pages_processed": 36,
        "contacts_processed": 703
      },
      "creetelo_anual": {
        "total_users": 667,
        "pages_processed": 34,
        "contacts_processed": 667
      }
    },
    "users": [
      {
        "id": "faqFoVkUsWWZMNs94CxB",
        "email": "isabelbtorres@gmail.com",
        "firstName": "Isabel",
        "lastName": "Torres",
        "phone": "+18327626977",
        "country": "US",
        "tags": ["optin_sales", "creetelo_mensual", "directorio"],
        "dateAdded": "2025-09-27T12:59:11.722Z",
        "source": "💎CREETELO_PÁGINA OFICIAL DE VENTA",
        "customFields": [
          {
            "id": "2X3hXhmjMx7IlXinPTLV",
            "value": "https://directorio.creetelo.club/dashboard/assign-password/..."
          }
        ]
      }
    ]
  }
}
```

### Respuesta de Error

```json
{
  "success": false,
  "message": "Parámetros de entrada inválidos",
  "errors": {
    "tags": ["El campo tags es requerido."]
  }
}
```

## Casos de Uso Comunes

### 1. Sincronización con Baremetrics

```bash
# Obtener usuarios activos para comparar con Baremetrics
curl -X GET "http://localhost:8000/api/ghl/filter/users?tags=creetelo_mensual,creetelo_anual&exclude_tags=unsubscribe,bounced&limit=2000" \
  -H "X-API-KEY: tu-api-key" \
  -H "Accept: application/json" > usuarios_ghl.json
```

### 2. Campaña de Email

```bash
# Obtener usuarios elegibles para email
curl -X GET "http://localhost:8000/api/ghl/filter/users?tags=creetelo_mensual&exclude_tags=unsubscribe,bounced,inactive&limit=1000" \
  -H "X-API-KEY: tu-api-key" \
  -H "Accept: application/json"
```

### 3. Análisis de Segmentación

```bash
# Obtener estadísticas por tag
curl -X GET "http://localhost:8000/api/ghl/tags/statistics?tags=creetelo_mensual,creetelo_anual,creetelo_trimestral" \
  -H "X-API-KEY: tu-api-key" \
  -H "Accept: application/json"
```

## Troubleshooting

### Error 401: Unauthorized
- Verificar que la API key esté configurada correctamente
- Comprobar que el header `X-API-KEY` esté presente
- Confirmar que la API key coincida con la configurada en `.env`

### Error 400: Bad Request
- Verificar que el parámetro `tags` esté presente
- Comprobar que los parámetros numéricos estén dentro de los rangos permitidos
- Revisar la sintaxis de los parámetros

### Error 500: Internal Server Error
- Revisar los logs de Laravel para más detalles
- Verificar que GoHighLevel esté configurado correctamente
- Comprobar la conectividad con GoHighLevel

### Respuesta Vacía
- Verificar que los tags existan en GoHighLevel
- Comprobar que los usuarios no tengan todos los tags de exclusión
- Revisar los logs para ver si hay errores de conectividad
