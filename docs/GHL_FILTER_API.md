# API de Filtrado de Usuarios de GoHighLevel

## Descripción General

Esta API permite filtrar usuarios de GoHighLevel por tags con lógica de inclusión (OR) y exclusión, devolviendo los resultados en formato JSON. Es ideal para obtener listas de usuarios que cumplan criterios específicos para procesamiento posterior.

## Autenticación

Todas las rutas requieren autenticación mediante API Key usando el middleware `api_key`.

## Endpoints

### 1. Filtrar Usuarios por Tags

**Endpoint:** `GET /api/ghl/filter/users`

**Descripción:** Filtra usuarios de GoHighLevel que tengan al menos uno de los tags especificados (lógica OR) y que NO tengan los tags de exclusión.

#### Parámetros de Query

| Parámetro | Tipo | Requerido | Descripción | Ejemplo |
|-----------|------|-----------|-------------|---------|
| `tags` | string | Sí | Tags a incluir separados por comas (lógica OR) | `creetelo_mensual,creetelo_anual` |
| `exclude_tags` | string | No | Tags a excluir separados por comas | `unsubscribe` |
| `max_pages` | integer | No | Máximo de páginas a procesar por tag (1-200) | `100` |
| `limit` | integer | No | Límite máximo de usuarios a retornar (1-5000) | `1500` |

#### Ejemplo de Request

```bash
GET /api/ghl/filter/users?tags=creetelo_mensual,creetelo_anual&exclude_tags=unsubscribe&max_pages=100&limit=1500
```

#### Respuesta Exitosa (200)

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
        "id": "user_id_123",
        "email": "usuario@ejemplo.com",
        "firstName": "Juan",
        "lastName": "Pérez",
        "phone": "+1234567890",
        "country": "US",
        "tags": ["creetelo_mensual"],
        "dateAdded": "2025-09-01T10:00:00.000Z",
        "customFields": [...],
        // ... más campos del usuario
      }
      // ... más usuarios
    ]
  }
}
```

#### Respuesta de Error (400)

```json
{
  "success": false,
  "message": "Parámetros de entrada inválidos",
  "errors": {
    "tags": ["El campo tags es requerido."]
  }
}
```

#### Respuesta de Error (500)

```json
{
  "success": false,
  "message": "Error interno del servidor",
  "error": "Descripción del error"
}
```

### 2. Estadísticas de Tags

**Endpoint:** `GET /api/ghl/tags/statistics`

**Descripción:** Obtiene estadísticas de cuántos usuarios tiene cada tag especificado.

#### Parámetros de Query

| Parámetro | Tipo | Requerido | Descripción | Ejemplo |
|-----------|------|-----------|-------------|---------|
| `tags` | string | Sí | Tags a analizar separados por comas | `creetelo_mensual,creetelo_anual` |
| `max_pages` | integer | No | Máximo de páginas a procesar por tag (1-50) | `10` |

#### Ejemplo de Request

```bash
GET /api/ghl/tags/statistics?tags=creetelo_mensual,creetelo_anual&max_pages=10
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "data": {
    "metadata": {
      "generated_at": "2025-10-02T22:30:00.000000Z",
      "tags_analyzed": ["creetelo_mensual", "creetelo_anual"],
      "max_pages_per_tag": 10
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
    }
  }
}
```

## Lógica de Filtrado

### Inclusión (Tags a Incluir)
- **Lógica:** OR - El usuario debe tener **al menos uno** de los tags especificados
- **Ejemplo:** Si se especifica `creetelo_mensual,creetelo_anual`, se incluirán usuarios que tengan:
  - Solo `creetelo_mensual`
  - Solo `creetelo_anual`
  - Ambos tags

### Exclusión (Tags a Excluir)
- **Lógica:** AND - El usuario NO debe tener **ninguno** de los tags de exclusión
- **Ejemplo:** Si se especifica `exclude_tags=unsubscribe`, se excluirán usuarios que tengan el tag `unsubscribe`

### Deduplicación
- Los usuarios se deduplican por su ID único
- Si un usuario tiene múltiples tags de inclusión, solo aparece una vez en el resultado

## Limitaciones y Consideraciones

### Rate Limiting
- La API incluye pausas automáticas entre requests para evitar rate limiting de GoHighLevel
- Tiempo estimado: ~0.1 segundos por página procesada

### Límites de Páginas
- **max_pages:** Máximo 200 páginas por tag para el endpoint principal
- **max_pages:** Máximo 50 páginas por tag para estadísticas
- Cada página contiene hasta 20 usuarios

### Límites de Usuarios
- **limit:** Máximo 5000 usuarios en la respuesta
- Si se alcanza el límite, se detiene el procesamiento

### Memoria
- Para grandes volúmenes de usuarios, considere usar el parámetro `limit` para evitar problemas de memoria

## Ejemplos de Uso

### Ejemplo 1: Usuarios con suscripción mensual o anual (sin unsubscribe)

```bash
curl -X GET "https://tu-dominio.com/api/ghl/filter/users?tags=creetelo_mensual,creetelo_anual&exclude_tags=unsubscribe&limit=1000" \
  -H "X-API-Key: tu-api-key"
```

### Ejemplo 2: Solo estadísticas de tags

```bash
curl -X GET "https://tu-dominio.com/api/ghl/tags/statistics?tags=creetelo_mensual,creetelo_anual" \
  -H "X-API-Key: tu-api-key"
```

### Ejemplo 3: Usuarios con múltiples tags de exclusión

```bash
curl -X GET "https://tu-dominio.com/api/ghl/filter/users?tags=creetelo_mensual&exclude_tags=unsubscribe,bounced,inactive&limit=500" \
  -H "X-API-Key: tu-api-key"
```

## Comando de Consola Equivalente

Este endpoint es equivalente al comando de consola:

```bash
php artisan ghl:count-users-with-exclusions \
  --include-tags=creetelo_mensual,creetelo_anual \
  --exclude-tags=unsubscribe \
  --max-pages=100 \
  --save-json
```

## Casos de Uso

1. **Sincronización con Baremetrics:** Obtener usuarios activos para comparar con la base de datos de Baremetrics
2. **Campañas de Email:** Filtrar usuarios elegibles para recibir emails promocionales
3. **Análisis de Segmentación:** Analizar distribución de usuarios por tags
4. **Migración de Datos:** Exportar usuarios específicos para procesamiento en lotes

## Troubleshooting

### Error: "No se encontraron usuarios"
- Verificar que los tags existen en GoHighLevel
- Comprobar la ortografía de los tags
- Considerar que los tags con acentos pueden no existir

### Error: "Rate Limited"
- Reducir el parámetro `max_pages`
- Implementar reintentos con backoff exponencial

### Error: "Memoria insuficiente"
- Reducir el parámetro `limit`
- Procesar en lotes más pequeños

### Respuesta vacía
- Verificar que los usuarios no tengan todos los tags de exclusión
- Confirmar que los tags de inclusión existen
- Revisar los logs del servidor para más detalles
