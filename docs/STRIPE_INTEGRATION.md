# Integración con Stripe

Esta integración permite conectar tu aplicación Laravel con Stripe para obtener información de customers.

## Configuración

### 1. Variables de Entorno

Agrega las siguientes variables a tu archivo `.env`:

```env
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key_here
STRIPE_SECRET_KEY=sk_test_your_secret_key_here
```

**IMPORTANTE:** 
- Reemplaza `pk_test_your_publishable_key_here` con tu clave publishable real de Stripe
- Reemplaza `sk_test_your_secret_key_here` con tu clave secreta real de Stripe
- Para producción, usa las claves que empiecen con `pk_live_` y `sk_live_`

### 2. Instalación de Dependencias

El paquete `stripe/stripe-php` ya está instalado. Si necesitas reinstalarlo:

```bash
composer require stripe/stripe-php
```

## Uso

### 1. Interfaz Web

Visita `/stripe` en tu navegador para acceder a la interfaz web donde puedes:
- Ver customers con paginación
- Cargar todos los customers
- Buscar customers por email
- Ver detalles de un customer específico

### 2. API Endpoints

#### Obtener customers (paginado)
```
GET /stripe/customers
```

Parámetros opcionales:
- `limit`: Número de customers a obtener (default: 100)
- `starting_after`: ID del customer para continuar la paginación

#### Obtener todos los customers
```
GET /stripe/customers/all
```

#### Buscar customers por email
```
GET /stripe/customers/search?email=customer@example.com
```

#### Obtener un customer específico
```
GET /stripe/customers/{customer_id}
```

#### Obtener clave publishable
```
GET /stripe/config/publishable-key
```

### 3. Comando de Consola

#### Ejemplos básicos:
```bash
# Obtener los primeros 100 customers
php artisan stripe:customers

# Obtener los primeros 50 customers
php artisan stripe:customers --limit=50

# Obtener TODOS los customers (puede tomar tiempo)
php artisan stripe:customers --all

# Buscar customers por email
php artisan stripe:customers --email=customer@example.com

# Exportar a CSV
php artisan stripe:customers --all --export=csv

# Exportar a JSON
php artisan stripe:customers --all --export=json
```

### 4. Uso Programático

#### En un Controller:
```php
use App\Services\StripeService;

class YourController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function getCustomers()
    {
        $result = $this->stripeService->getCustomerIds(100);
        
        if ($result['success']) {
            $customers = $result['data'];
            // Procesar customers...
        } else {
            // Manejar error
            $error = $result['error'];
        }
    }
}
```

#### En una Command o Job:
```php
use App\Services\StripeService;

$stripeService = app(StripeService::class);
$result = $stripeService->getAllCustomerIds();

if ($result['success']) {
    foreach ($result['data'] as $customer) {
        // Procesar cada customer
        echo "Customer ID: " . $customer['id'] . "\n";
        echo "Email: " . $customer['email'] . "\n";
    }
}
```

## Métodos Disponibles en StripeService

### `getCustomerIds($limit = 100, $startingAfter = null)`
Obtiene una lista paginada de customers.

### `getAllCustomerIds()`
Obtiene TODOS los customers automáticamente manejando la paginación.

### `getCustomer($customerId)`
Obtiene los detalles de un customer específico.

### `searchCustomersByEmail($email)`
Busca customers por dirección de email.

### `getPublishableKey()`
Obtiene la clave publishable para uso en el frontend.

## Estructura de Respuesta

Todos los métodos devuelven un array con la siguiente estructura:

```php
[
    'success' => true|false,
    'data' => array|null,
    'error' => string|null,
    'total_count' => int (cuando aplique),
    'has_more' => bool (para métodos paginados)
]
```

### Estructura de datos de Customer:
```php
[
    'id' => 'cus_stripe_customer_id',
    'email' => 'customer@example.com',
    'name' => 'Customer Name',
    'created' => 1234567890, // timestamp Unix
]
```

## Manejo de Errores

La integración incluye manejo robusto de errores:
- Errores de API de Stripe se capturan y se loggean
- Errores de red y timeout se manejan apropiadamente
- Los errores se devuelven en el formato estándar de respuesta

## Logs

Los errores se registran en el log de Laravel. Para ver los logs:

```bash
tail -f storage/logs/laravel.log
```

## Consideraciones de Rendimiento

- Para obtener todos los customers, el sistema usa paginación automática
- Se recomienda usar límites apropiados para evitar timeouts
- Para grandes volúmenes de datos, considera usar Jobs en cola

## Seguridad

- Las claves secretas nunca se exponen al frontend
- Solo la clave publishable es accesible públicamente
- Todas las operaciones de Stripe se realizan del lado del servidor

## Troubleshooting

### Error: "No API key provided"
- Verifica que `STRIPE_SECRET_KEY` esté configurada en `.env`
- Ejecuta `php artisan config:clear` después de cambiar el `.env`

### Error: "Invalid API key"
- Verifica que la clave secreta sea correcta
- Asegúrate de usar claves de test para desarrollo

### Error: "Customer not found"
- Verifica que el customer ID exista en tu cuenta de Stripe
- Asegúrate de estar usando las claves del ambiente correcto (test/live)
