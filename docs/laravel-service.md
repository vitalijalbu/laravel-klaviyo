# Laravel Service Guide

Guida completa al servizio Laravel per l'integrazione con Klaviyo. Questo documento spiega in dettaglio come funziona l'implementazione Laravel, i componenti utilizzati e come estendere il servizio.

## 🚀 Overview del Servizio

Il Klaviyo Event Service è un microservizio Laravel enterprise-ready che gestisce l'invio di eventi e-commerce a Klaviyo in modo asincrono, scalabile e fault-tolerant.

### Caratteristiche Principali

- **API RESTful** per ricevere eventi
- **Processing asincrono** via job queue
- **Retry logic** con exponential backoff
- **Type-safe DTOs** per data handling
- **Form Request validation** per input sanitization
- **Action-based business logic** per clean code
- **Comprehensive logging** per debugging e monitoring

---

## 🏗️ Struttura del Progetto

```
app/
├── Actions/Klaviyo/           # Business logic actions
│   ├── TrackEventAction.php
│   ├── IdentifyCustomerAction.php
│   ├── DeleteProfileAction.php
│   └── SyncCatalogAction.php
├── DTO/Klaviyo/              # Data Transfer Objects
│   ├── CustomerDTO.php
│   ├── ProductDTO.php
│   ├── OrderDTO.php
│   └── EventDTO.php
├── Http/
│   ├── Controllers/          # HTTP controllers
│   │   ├── EventController.php
│   │   └── CatalogController.php
│   ├── Middleware/           # Custom middleware
│   │   └── ValidateApiKey.php
│   └── Requests/            # Form request validation
│       ├── Event/
│       │   ├── TrackEventRequest.php
│       │   ├── ProductViewRequest.php
│       │   └── OrderPlacedRequest.php
│       └── Catalog/
│           ├── SyncCatalogRequest.php
│           └── SyncSingleProductRequest.php
├── Jobs/                    # Async job classes
│   ├── TrackEventJob.php
│   ├── IdentifyCustomerJob.php
│   └── SyncCatalogJob.php
└── Services/               # Domain services
    └── KlaviyoService.php
```

---

## 📝 Data Transfer Objects (DTOs)

I DTOs sono oggetti immutabili che rappresentano i dati che transitano nel sistema. Utilizzano **readonly properties** per garantire immutabilità e type safety.

### CustomerDTO

```php
<?php

namespace App\DTO\Klaviyo;

class CustomerDTO
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $title = null,
        public readonly ?string $organization = null,
        public readonly ?array $properties = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            title: $data['title'] ?? null,
            organization: $data['organization'] ?? null,
            properties: $data['properties'] ?? []
        );
    }

    public function toKlaviyoFormat(): array
    {
        $data = array_filter([
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone_number' => $this->phoneNumber,
            'title' => $this->title,
            'organization' => $this->organization,
        ], fn($value) => $value !== null);

        return array_merge($data, $this->properties);
    }
}
```

### ProductDTO

Gestisce la rappresentazione dei prodotti con conversion methods per diversi formati:

```php
public function toEventProperties(): array
{
    // Per eventi di tracking
    return array_filter([
        'product_id' => $this->id,
        'product_name' => $this->title,
        'price' => $this->price,
        'currency' => $this->currency,
        // ... altri campi
    ]);
}

public function toCatalogItem(): array
{
    // Per sincronizzazione catalogo
    return [
        'type' => 'catalog-item',
        'id' => "\$custom:::\$default:::{$this->id}",
        'attributes' => [
            'external_id' => (string) $this->id,
            'title' => $this->title,
            // ... altri campi
        ]
    ];
}
```

### EventDTO

Rappresenta un evento Klaviyo con tutti i metadati necessari:

```php
public function toKlaviyoPayload(): array
{
    $payload = [
        'data' => [
            'type' => 'event',
            'attributes' => [
                'metric' => ['name' => $this->event],
                'properties' => $this->properties,
                'time' => ($this->time ?? now())->toIso8601String(),
            ]
        ]
    ];

    if ($this->customer) {
        $payload['data']['attributes']['profile'] = 
            $this->customer->toKlaviyoFormat();
    }

    return $payload;
}
```

---

## 🎯 Actions (Business Logic)

Le Actions implementano il **Command Pattern** per incapsulare la business logic in classi dedicate, rendendo il codice più testabile e riutilizzabile.

### TrackEventAction

```php
<?php

namespace App\Actions\Klaviyo;

use App\DTO\Klaviyo\EventDTO;
use App\Services\KlaviyoService;

class TrackEventAction
{
    public function __construct(
        private KlaviyoService $klaviyo
    ) {}

    public function execute(EventDTO $event): bool
    {
        return $this->klaviyo->track($event);
    }

    public function executeOnce(EventDTO $event): bool
    {
        if (!$event->uniqueId) {
            throw new \InvalidArgumentException(
                'Event must have uniqueId for trackOnce'
            );
        }

        return $this->klaviyo->trackOnce($event);
    }
}
```

### Vantaggi delle Actions

1. **Single Responsibility**: Ogni action ha un compito specifico
2. **Dependency Injection**: Testabilità tramite mocking
3. **Reusability**: Riutilizzabili in contesti diversi
4. **Type Safety**: Input e output tipizzati

---

## 📋 Form Requests

Le Form Request classes gestiscono validazione, sanitizzazione e autorizzazione in modo centralizzato.

### TrackEventRequest

```php
<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class TrackEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorizzazione gestita da middleware
    }

    public function rules(): array
    {
        return [
            'event' => 'required|string|max:255',
            'properties' => 'required|array',
            'customer' => 'sometimes|array',
            'customer.email' => 'required_with:customer|email',
            'customer.first_name' => 'sometimes|string|max:255',
            'customer.last_name' => 'sometimes|string|max:255',
            'unique_id' => 'sometimes|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'event.required' => 'Event name is required',
            'properties.required' => 'Event properties are required',
            'customer.email.required_with' => 
                'Email is required when customer data is provided',
        ];
    }
}
```

### Validazione Avanzata

#### ProductViewRequest
```php
public function rules(): array
{
    return [
        'product_id' => 'required',
        'product_name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'currency' => 'required|string|size:3',
        'categories' => 'sometimes|array',
        'categories.*' => 'string|max:255',
        // ... altre validazioni
    ];
}
```

#### Bulk Operations Security
```php
// SyncCatalogRequest - Limitazione per sicurezza
'products' => 'required|array|min:1|max:100', // Max 100 prodotti
```

---

## ⚙️ Jobs (Async Processing)

I Jobs implementano il processing asincrono con retry logic e error handling robusto.

### TrackEventJob

```php
<?php

namespace App\Jobs;

use App\Actions\Klaviyo\TrackEventAction;
use App\DTO\Klaviyo\EventDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public $timeout = 30;
    
    public function __construct(
        public EventDTO $event
    ) {
        $this->onQueue(config('klaviyo.queue.name'));
    }
    
    public function handle(TrackEventAction $action): void
    {
        $action->execute($this->event);
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::critical('Klaviyo event tracking failed permanently', [
            'event' => $this->event->event,
            'error' => $exception->getMessage(),
        ]);
        
        // TODO: Implementare dead letter queue o notifica
    }
}
```

### Job Configuration

#### Retry Strategy
- **Tries**: 3 tentativi totali
- **Backoff**: Exponential backoff (1min, 5min, 15min)
- **Timeout**: 30 secondi per job
- **Queue**: Coda dedicata `klaviyo`

#### Error Handling
- **Automatic retries** per errori temporanei
- **Dead letter queue** dopo max retries
- **Comprehensive logging** per troubleshooting
- **Failed job tracking** per monitoring

---

## 🌐 Controllers

I Controllers sono **thin** e delegano tutta la business logic alle Actions e ai Jobs.

### EventController

```php
<?php

namespace App\Http\Controllers;

use App\DTO\Klaviyo\CustomerDTO;
use App\DTO\Klaviyo\EventDTO;
use App\DTO\Klaviyo\OrderDTO;
use App\DTO\Klaviyo\ProductDTO;
use App\Http\Requests\Event\TrackEventRequest;
use App\Jobs\IdentifyCustomerJob;
use App\Jobs\TrackEventJob;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function track(TrackEventRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $event = EventDTO::create(
            $validated['event'],
            $validated['properties'],
            $validated['customer'] ?? null
        );
        
        TrackEventJob::dispatch($event);
        
        return response()->json([
            'success' => true,
            'message' => 'Event queued for processing'
        ]);
    }
    
    public function productView(ProductViewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $product = ProductDTO::fromSylius($validated);
        
        $event = EventDTO::create(
            'Viewed Product',
            $product->toEventProperties(),
            $validated['customer'] ?? null
        );
        
        TrackEventJob::dispatch($event);
        
        return response()->json([
            'success' => true,
            'message' => 'Product view event queued'
        ]);
    }
    
    public function orderPlaced(OrderPlacedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Identify customer first
        $customer = CustomerDTO::fromArray($validated['customer']);
        IdentifyCustomerJob::dispatch($customer);
        
        // Track order
        $order = OrderDTO::fromSylius($validated);
        $event = EventDTO::create(
            'Placed Order',
            $order->toEventProperties(),
            $validated['customer']
        );
        
        TrackEventJob::dispatch($event);
        
        return response()->json([
            'success' => true,
            'message' => 'Order placed event queued'
        ]);
    }
}
```

### Response Format

Tutti gli endpoint seguono un formato di response consistente:

```json
{
    "success": true,
    "message": "Event queued for processing",
    "data": {
        // Dati aggiuntivi se necessari
    }
}
```

---

## 🔧 Services Layer

### KlaviyoService

Il service principale che gestisce tutte le comunicazioni con le API Klaviyo.

```php
<?php

namespace App\Services;

use App\DTO\Klaviyo\CustomerDTO;
use App\DTO\Klaviyo\EventDTO;
use App\DTO\Klaviyo\ProductDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class KlaviyoService
{
    private string $apiKey;
    private string $apiUrl;
    private string $apiVersion;
    
    public function __construct()
    {
        $this->apiKey = config('klaviyo.api_key');
        $this->apiUrl = config('klaviyo.api_url');
        $this->apiVersion = config('klaviyo.api_version');
    }

    // Event Tracking
    public function track(EventDTO $event): bool
    {
        $response = $this->sendRequest(
            'POST',
            '/events/',
            $event->toKlaviyoPayload()
        );

        if ($response->successful()) {
            Log::info('Klaviyo event tracked', [
                'event' => $event->event,
                'has_customer' => $event->customer !== null
            ]);
            return true;
        }

        $this->handleError($response, 'track', $event->event);
        return false;
    }

    // Customer Management
    public function identify(CustomerDTO $customer): bool
    {
        $payload = [
            'data' => [
                'type' => 'profile',
                'attributes' => $customer->toKlaviyoFormat()
            ]
        ];

        $response = $this->sendRequest('POST', '/profiles/', $payload);
        return $response->successful();
    }

    // HTTP Client con retry logic
    private function sendRequest(
        string $method,
        string $endpoint,
        array $payload = [],
        array $queryParams = []
    ): Response {
        $http = Http::withHeaders([
            'Authorization' => 'Klaviyo-API-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'revision' => $this->apiVersion,
        ])
        ->timeout(30)
        ->retry(2, 100, function ($exception) {
            // Retry solo su errori 5xx, non 4xx
            return $exception->response && 
                   $exception->response->status() >= 500;
        });

        $url = $this->apiUrl . $endpoint;

        return match($method) {
            'GET' => $http->get($url, $queryParams),
            'POST' => $http->post($url, $payload),
            'PATCH' => $http->patch($url, $payload),
            'DELETE' => $http->delete($url, $payload),
        };
    }
}
```

#### Features del KlaviyoService

1. **Event Tracking**: Singoli eventi e batch processing
2. **Customer Management**: Identificazione e aggiornamento profili
3. **Catalog Management**: Sincronizzazione prodotti
4. **GDPR Compliance**: Cancellazione profili
5. **List Management**: Gestione liste e segmenti
6. **Retry Logic**: Automatic retry per errori temporanei
7. **Error Handling**: Logging strutturato degli errori

---

## 🔐 Middleware e Sicurezza

### ValidateApiKey Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');
        
        if ($apiKey !== config('services.api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}
```

### Registrazione Middleware

```php
// bootstrap/app.php
return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key' => \App\Http\Middleware\ValidateApiKey::class,
        ]);
    });
```

---

## 📊 API Endpoints Dettagliati

### Health Check

```http
GET /api/health
```

**Response:**
```json
{
    "status": "ok",
    "service": "klaviyo-integration",
    "timestamp": "2025-10-15T10:30:00Z"
}
```

### Generic Event Tracking

```http
POST /api/events/track
Content-Type: application/json
X-API-Key: your_api_key

{
    "event": "Custom Event",
    "properties": {
        "custom_property": "value",
        "amount": 42.99
    },
    "customer": {
        "email": "user@example.com",
        "first_name": "John",
        "last_name": "Doe"
    },
    "unique_id": "optional-unique-id"
}
```

### Product View Tracking

```http
POST /api/events/product-view
Content-Type: application/json
X-API-Key: your_api_key

{
    "product_id": "123",
    "product_name": "Amazing Product",
    "price": 29.99,
    "currency": "EUR",
    "product_url": "https://shop.com/products/123",
    "image_url": "https://shop.com/images/123.jpg",
    "categories": ["Electronics", "Smartphones"],
    "sku": "SKU-123",
    "customer": {
        "email": "user@example.com"
    }
}
```

### Order Placed Tracking

```http
POST /api/events/order-placed
Content-Type: application/json
X-API-Key: your_api_key

{
    "order_id": "456",
    "order_number": "ORD-2025-456",
    "total": 99.99,
    "currency": "EUR",
    "subtotal": 89.99,
    "tax": 8.00,
    "shipping": 2.00,
    "items": [
        {
            "product_id": "123",
            "product_name": "Product 1",
            "quantity": 2,
            "price": 29.99,
            "sku": "SKU-123"
        }
    ],
    "customer": {
        "email": "user@example.com",
        "first_name": "John",
        "last_name": "Doe"
    },
    "billing_address": {
        "first_name": "John",
        "last_name": "Doe",
        "address1": "123 Main St",
        "city": "Rome",
        "postal_code": "00100",
        "country": "Italy"
    }
}
```

### Catalog Sync

```http
POST /api/catalog/sync
Content-Type: application/json
X-API-Key: your_api_key

{
    "products": [
        {
            "product_id": "123",
            "product_name": "Product 1",
            "price": 29.99,
            "currency": "EUR",
            "product_url": "https://shop.com/products/123",
            "categories": ["Category 1"]
        },
        {
            "product_id": "124",
            "product_name": "Product 2", 
            "price": 39.99,
            "currency": "EUR"
        }
    ]
}
```

---

## 🚀 Queue Management

### Configurazione Code

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

### Configurazione Klaviyo Queue

```php
// config/klaviyo.php
'queue' => [
    'connection' => env('KLAVIYO_QUEUE_CONNECTION', 'redis'),
    'name' => env('KLAVIYO_QUEUE_NAME', 'klaviyo'),
],
```

### Avvio Workers

```bash
# Worker singolo
php artisan queue:work --queue=klaviyo

# Worker con opzioni
php artisan queue:work --queue=klaviyo --tries=3 --timeout=30

# Worker con restart automatico
php artisan queue:work --queue=klaviyo --tries=3 --timeout=30 --sleep=3

# Multiple workers (production)
php artisan queue:work --queue=klaviyo --tries=3 --timeout=30 --sleep=3 &
php artisan queue:work --queue=klaviyo --tries=3 --timeout=30 --sleep=3 &
php artisan queue:work --queue=klaviyo --tries=3 --timeout=30 --sleep=3 &
```

### Monitoring Code

```bash
# Statistiche
php artisan queue:monitor redis:klaviyo

# Failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## 🧪 Testing

### Test Structure

```
tests/
├── Feature/
│   ├── EventTrackingTest.php
│   ├── CatalogSyncTest.php
│   └── ApiAuthenticationTest.php
├── Unit/
│   ├── DTO/
│   │   ├── CustomerDTOTest.php
│   │   ├── ProductDTOTest.php
│   │   └── EventDTOTest.php
│   ├── Actions/
│   │   └── TrackEventActionTest.php
│   └── Services/
│       └── KlaviyoServiceTest.php
└── TestCase.php
```

### Example Test

```php
<?php

namespace Tests\Feature;

use App\DTO\Klaviyo\EventDTO;
use App\Jobs\TrackEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.api_key' => 'test-key']);
    }
    
    public function test_product_view_creates_job(): void
    {
        Queue::fake();
        
        $response = $this->postJson('/api/events/product-view', [
            'product_id' => 123,
            'product_name' => 'Test Product',
            'price' => 29.99,
            'currency' => 'EUR',
        ], [
            'X-API-Key' => 'test-key',
        ]);
        
        $response->assertStatus(200);
        
        Queue::assertPushed(TrackEventJob::class, function ($job) {
            return $job->event instanceof EventDTO && 
                   $job->event->event === 'Viewed Product';
        });
    }
}
```

### Mocking External Services

```php
// Test con mock del KlaviyoService
public function test_track_event_action()
{
    $mockKlaviyo = $this->mock(KlaviyoService::class);
    $mockKlaviyo->shouldReceive('track')
        ->once()
        ->andReturn(true);
    
    $action = new TrackEventAction($mockKlaviyo);
    $event = new EventDTO('Test Event', []);
    
    $result = $action->execute($event);
    
    $this->assertTrue($result);
}
```

---

## 🔧 Configuration Management

### Environment Variables

```env
# Klaviyo Configuration
KLAVIYO_API_KEY=pk_your_private_api_key
KLAVIYO_API_URL=https://a.klaviyo.com/api

# Service Configuration  
SERVICE_API_KEY=your_super_secure_random_key

# Queue Configuration
QUEUE_CONNECTION=redis
KLAVIYO_QUEUE_NAME=klaviyo
KLAVIYO_QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

### Service Provider Configuration

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    // Singleton per KlaviyoService
    $this->app->singleton(\App\Services\KlaviyoService::class);
    
    // Bind custom configurations
    $this->app->bind('klaviyo.config', function () {
        return config('klaviyo');
    });
}

public function boot(): void
{
    // Custom validations
    Validator::extend('klaviyo_event', function ($attribute, $value) {
        return in_array($value, [
            'Viewed Product',
            'Added to Cart', 
            'Placed Order',
            'Started Checkout'
        ]);
    });
}
```

---

## 📈 Performance e Ottimizzazioni

### Caching Strategies

```php
// Cache configurazioni pesanti
$apiConfig = Cache::remember('klaviyo.config', 3600, function () {
    return config('klaviyo');
});

// Cache response templates
$template = Cache::remember("event.template.{$eventType}", 1800, function () {
    return $this->loadEventTemplate($eventType);
});
```

### Database Optimizations

```php
// Queue table indexes
Schema::table('jobs', function (Blueprint $table) {
    $table->index(['queue', 'reserved_at']);
    $table->index(['available_at']);
});

// Failed jobs cleanup
Schedule::command('queue:prune-failed --hours=48')->daily();
```

### Memory Management

```php
// Job memory limits
class TrackEventJob implements ShouldQueue
{
    public $tries = 3;
    public $timeout = 30;
    public $memory = 128; // MB
    
    // Cleanup dopo ogni job
    public function __destruct()
    {
        if (defined('LARAVEL_START')) {
            gc_collect_cycles();
        }
    }
}
```

---

## 🔍 Debugging e Troubleshooting

### Logging Levels

```php
// config/logging.php
'channels' => [
    'klaviyo' => [
        'driver' => 'daily',
        'path' => storage_path('logs/klaviyo.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
],
```

### Debug Commands

```bash
# Verifica configurazione
php artisan config:show klaviyo

# Test connessione Redis
php artisan tinker
>>> Redis::ping()

# Verifica job queue
php artisan queue:work --queue=klaviyo --verbose

# Monitor performance
php artisan horizon:status
```

### Common Issues

#### Job Failing

```bash
# Verifica failed jobs
php artisan queue:failed

# Dettagli specifico job
php artisan queue:failed --id=123

# Retry specifico job
php artisan queue:retry 123
```

#### API Rate Limiting

```php
// Implementa circuit breaker
if ($this->isRateLimited()) {
    $this->release(60); // Retry tra 1 minuto
    return;
}
```

---

## 🚀 Deploy e Production

### Ottimizzazioni Production

```bash
# Cache routes e config
php artisan route:cache
php artisan config:cache
php artisan view:cache

# Ottimizza autoloader
composer dump-autoload --optimize

# Queue restart dopo deploy
php artisan queue:restart
```

### Supervisor Configuration

```ini
[program:klaviyo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=klaviyo --sleep=3 --tries=3 --timeout=30
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/worker.log
```

### Health Checks

```php
// Implement health check endpoint
Route::get('/health', function () {
    $checks = [
        'redis' => $this->checkRedis(),
        'klaviyo_api' => $this->checkKlaviyoApi(),
        'queue_size' => Queue::size('klaviyo'),
    ];
    
    return response()->json([
        'status' => 'ok',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ]);
});
```