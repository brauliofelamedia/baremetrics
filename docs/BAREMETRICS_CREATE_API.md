# API de Creación de Recursos en Baremetrics

Esta documentación describe los nuevos endpoints para crear clientes, planes y suscripciones en Baremetrics.

## Autenticación

Todos los endpoints requieren autenticación mediante API Key en el header:

```
X-API-KEY: tu-api-key-aqui
```

## Endpoints Disponibles

### 1. Obtener Source ID

**GET** `/api/baremetrics/source-id`

Obtiene el Source ID de Baremetrics, necesario para crear otros recursos.

#### Respuesta Exitosa (200)
```json
{
    "success": true,
    "data": {
        "source_id": "src_1234567890"
    }
}
```

#### Respuesta de Error (500)
```json
{
    "success": false,
    "message": "No se pudo obtener el Source ID de Baremetrics"
}
```

---

### 2. Crear Cliente

**POST** `/api/baremetrics/customer`

Crea un nuevo cliente en Baremetrics.

#### Parámetros de Entrada
```json
{
    "name": "Juan Pérez",
    "email": "juan.perez@example.com",
    "company": "Empresa ABC",
    "notes": "Cliente importante",
    "source_id": "src_1234567890" // Opcional, se obtiene automáticamente si no se proporciona
}
```

#### Validaciones
- `name`: Requerido, string, máximo 255 caracteres
- `email`: Requerido, email válido, máximo 255 caracteres
- `company`: Opcional, string, máximo 255 caracteres
- `notes`: Opcional, string, máximo 1000 caracteres
- `source_id`: Opcional, string

#### Respuesta Exitosa (201)
```json
{
    "success": true,
    "message": "Cliente creado exitosamente",
    "data": {
        "customer": {
            "oid": "cust_1234567890",
            "name": "Juan Pérez",
            "email": "juan.perez@example.com",
            "company": "Empresa ABC",
            "notes": "Cliente importante"
        }
    }
}
```

---

### 3. Crear Plan

**POST** `/api/baremetrics/plan`

Crea un nuevo plan en Baremetrics.

#### Parámetros de Entrada
```json
{
    "name": "Plan Premium",
    "interval": "month",
    "interval_count": 1,
    "amount": 2999,
    "currency": "USD",
    "trial_days": 7,
    "notes": "Plan premium con trial de 7 días",
    "source_id": "src_1234567890" // Opcional
}
```

#### Validaciones
- `name`: Requerido, string, máximo 255 caracteres
- `interval`: Requerido, uno de: `day`, `week`, `month`, `year`
- `interval_count`: Requerido, entero, mínimo 1
- `amount`: Requerido, entero, mínimo 0 (en centavos)
- `currency`: Requerido, string de 3 caracteres (ej: USD, EUR)
- `trial_days`: Opcional, entero, mínimo 0
- `notes`: Opcional, string, máximo 1000 caracteres
- `source_id`: Opcional, string

#### Respuesta Exitosa (201)
```json
{
    "success": true,
    "message": "Plan creado exitosamente",
    "data": {
        "plan": {
            "oid": "plan_1234567890",
            "name": "Plan Premium",
            "interval": "month",
            "interval_count": 1,
            "amount": 2999,
            "currency": "USD",
            "trial_days": 7
        }
    }
}
```

---

### 4. Crear Suscripción

**POST** `/api/baremetrics/subscription`

Crea una nueva suscripción en Baremetrics.

#### Parámetros de Entrada
```json
{
    "customer_oid": "cust_1234567890",
    "plan_oid": "plan_1234567890",
    "started_at": 1705312200,
    "status": "active",
    "notes": "Suscripción activa",
    "source_id": "src_1234567890" // Opcional
}
```

#### Validaciones
- `customer_oid`: Requerido, string (OID del cliente)
- `plan_oid`: Requerido, string (OID del plan)
- `started_at`: Requerido, timestamp Unix (entero)
- `status`: Requerido, uno de: `active`, `canceled`, `past_due`, `trialing`
- `notes`: Opcional, string, máximo 1000 caracteres
- `source_id`: Opcional, string

#### Respuesta Exitosa (201)
```json
{
    "success": true,
    "message": "Suscripción creada exitosamente",
    "data": {
        "subscription": {
            "oid": "sub_1234567890",
            "customer_oid": "cust_1234567890",
            "plan_oid": "plan_1234567890",
            "started_at": 1705312200,
            "status": "active"
        }
    }
}
```

---

### 5. Crear Configuración Completa

**POST** `/api/baremetrics/complete-setup`

Crea un cliente, plan y suscripción en una sola operación.

#### Parámetros de Entrada
```json
{
    "customer": {
        "name": "María García",
        "email": "maria.garcia@example.com",
        "company": "Empresa XYZ",
        "notes": "Cliente nuevo"
    },
    "plan": {
        "name": "Plan Básico",
        "interval": "month",
        "interval_count": 1,
        "amount": 1999,
        "currency": "USD",
        "trial_days": 14,
        "notes": "Plan básico con trial"
    },
    "subscription": {
        "started_at": 1705312200,
        "status": "active",
        "notes": "Suscripción completa"
    }
}
```

#### Respuesta Exitosa (201)
```json
{
    "success": true,
    "message": "Configuración completa de cliente creada exitosamente",
    "data": {
        "customer": {
            "customer": {
                "oid": "cust_1234567890",
                "name": "María García",
                "email": "maria.garcia@example.com"
            }
        },
        "plan": {
            "plan": {
                "oid": "plan_1234567890",
                "name": "Plan Básico",
                "amount": 1999
            }
        },
        "subscription": {
            "subscription": {
                "oid": "sub_1234567890",
                "customer_oid": "cust_1234567890",
                "plan_oid": "plan_1234567890",
                "status": "active"
            }
        },
        "source_id": "src_1234567890"
    }
}
```

---

## Códigos de Respuesta

- **200**: Éxito (GET requests)
- **201**: Recurso creado exitosamente (POST requests)
- **400**: Datos de entrada inválidos
- **401**: No autorizado (API key inválida)
- **500**: Error interno del servidor

## Manejo de Errores

Todas las respuestas de error incluyen:

```json
{
    "success": false,
    "message": "Descripción del error",
    "errors": {
        "campo": ["mensaje de validación"]
    }
}
```

## Ejemplos de Uso

### Ejemplo 1: Crear un cliente simple
```bash
curl -X POST "http://localhost:8000/api/baremetrics/customer" \
  -H "X-API-KEY: tu-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "company": "Mi Empresa"
  }'
```

### Ejemplo 2: Crear configuración completa
```bash
curl -X POST "http://localhost:8000/api/baremetrics/complete-setup" \
  -H "X-API-KEY: tu-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "customer": {
      "name": "Ana López",
      "email": "ana@example.com"
    },
    "plan": {
      "name": "Plan Pro",
      "interval": "month",
      "interval_count": 1,
      "amount": 4999,
      "currency": "USD"
    },
    "subscription": {
      "started_at": 1705312200,
      "status": "active"
    }
  }'
```

## Notas Importantes

1. **Source ID**: Se obtiene automáticamente si no se proporciona en las requests
2. **Amounts**: Los montos se especifican en centavos (ej: $29.99 = 2999)
3. **Fechas**: Usar timestamp Unix (entero) para started_at
4. **OIDs**: Los OIDs son identificadores únicos generados por Baremetrics
5. **Entorno**: Los endpoints funcionan tanto en sandbox como en producción según la configuración

## Comando de Prueba

Para probar los endpoints, puedes usar el comando de consola:

```bash
# Probar todos los endpoints
php artisan baremetrics:test-create-endpoints --all

# Probar endpoints específicos
php artisan baremetrics:test-create-endpoints --test-customer
php artisan baremetrics:test-create-endpoints --test-plan
php artisan baremetrics:test-create-endpoints --test-subscription
php artisan baremetrics:test-create-endpoints --test-complete
```
