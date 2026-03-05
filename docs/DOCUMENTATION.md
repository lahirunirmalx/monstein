# Monstein Framework Documentation

A lightweight, production-ready RESTful API framework built on Slim 3.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Routing System](#routing-system)
3. [Controllers](#controllers)
4. [Authentication](#authentication)
5. [Middleware](#middleware)
6. [Database & Models](#database--models)
7. [Helper Utilities](#helper-utilities)
8. [File Uploads](#file-uploads)
9. [Usage Tracking](#usage-tracking)
10. [Rate Limiting](#rate-limiting)
11. [Security](#security)
12. [CLI Tools](#cli-tools)
13. [Docker Deployment](#docker-deployment)

---

## Architecture Overview

Monstein follows a clean, layered architecture:

```
Request → Nginx → index.php → Slim App
                      ↓
              Middleware Stack
              (Security Headers)
              (CORS)
              (Rate Limiting)
              (JWT Auth)
              (Param Validation)
              (File Upload)
              (Usage Tracking)
                      ↓
              Router → Controller → Model → Database
                      ↓
              Response (JSON)
```

### Core Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `App.php` | `app/` | Application bootstrap |
| `Dependencies.php` | `app/` | Dependency injection container |
| `Middleware.php` | `app/` | Middleware configuration |
| `BaseRouter.php` | `app/Base/` | Route parsing from YAML |
| `BaseController.php` | `app/Base/` | Controller foundation |
| `Config.php` | `app/Config/` | Configuration management |
| `routing.yml` | `app/Config/` | Route definitions |

### Request Lifecycle

1. **Entry Point** (`symfony/web/index.php`)
   - Loads autoloader and environment
   - Creates Slim application

2. **Bootstrap** (`App.php`)
   - Initializes dependencies
   - Registers middleware
   - Loads routes

3. **Routing** (`BaseRouter.php`)
   - Parses `routing.yml`
   - Maps URLs to controllers

4. **Middleware** (executed in reverse order)
   - Security headers
   - CORS handling
   - Rate limiting
   - JWT authentication
   - Parameter validation
   - File upload processing
   - Usage tracking

5. **Controller** (business logic)
   - Validates input
   - Interacts with models
   - Returns JSON response

---

## Routing System

Routes are defined in `app/Config/routing.yml` using a declarative YAML format.

### Basic Route

```yaml
todos:
  url: /todo
  controller: \Monstein\Controllers\TodoCollectionController
  method: [ get, post ]
```

### Route with URL Parameters

```yaml
todoEntity:
  url: /todo/{id}
  controller: \Monstein\Controllers\TodoEntityController
  method: [ get, put, delete ]
```

### Full Route Configuration

```yaml
products:
  url: /products/{id}
  controller: \Monstein\Controllers\ProductEntityController
  method: [ get, put, delete ]
  
  # Security - requires JWT token
  is_secure: true
  
  # Rate limiting
  rate_limit:
    max_requests: 100
    window: 60
  
  # Parameter validation
  params:
    id:
      type: integer
      min: 1
  
  # File upload handling
  file_upload:
    enabled: true
    max_size: 10485760
    allowed_types: images
    storage: filesystem
  
  # Usage tracking
  tracking:
    enabled: true
    name: "product_view"
    track_user: true
```

### Route Options Reference

| Option | Type | Description |
|--------|------|-------------|
| `url` | string | URL pattern with optional `{param}` placeholders |
| `controller` | string | Fully qualified controller class name |
| `method` | array | HTTP methods: `get`, `post`, `put`, `delete`, `patch` |
| `is_secure` | bool | Require JWT authentication (default: `true`) |
| `rate_limit` | object | Rate limiting configuration |
| `params` | object | URL parameter validation rules |
| `file_upload` | object | File upload configuration |
| `tracking` | bool/object | Usage tracking configuration |

### How Routes Are Parsed

The `BaseRouter` class (singleton) parses `routing.yml`:

```php
// Get all routes
$routes = BaseRouter::getInstance()->getRoutes();

// Get rate limit for a path
$limit = BaseRouter::getInstance()->getRateLimitForPath('/todo', 'GET');

// Get parameter rules
$rules = BaseRouter::getInstance()->getParamRulesForPath('/todo/{id}');
```

---

## Controllers

Monstein uses two controller types following REST conventions.

### Collection Controller

Handles operations on resource collections (list, create).

```php
<?php
namespace Monstein\Controllers;

use Monstein\Base\CollectionController;
use Monstein\Models\Product;

class ProductCollectionController extends CollectionController
{
    /**
     * GET /products - List all products
     */
    public function doGet($request, $response, $args)
    {
        $products = Product::where('user_id', $this->getUserId())
            ->orderBy('created_at', 'desc')
            ->get();
            
        return $this->successResponse($response, $products->toArray());
    }
    
    /**
     * POST /products - Create new product
     */
    public function doPost($request, $response, $args)
    {
        $data = $request->getParsedBody();
        
        // Validate input
        $this->validator->validate($request, [
            'name' => v::notEmpty()->length(1, 255),
            'price' => v::numeric()->positive(),
        ]);
        
        if (!$this->validator->isValid()) {
            return $this->validationError($response, $this->validator->getErrors());
        }
        
        $product = Product::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'user_id' => $this->getUserId(),
        ]);
        
        return $this->createdResponse($response, $product->toArray());
    }
}
```

### Entity Controller

Handles operations on single resources (read, update, delete).

```php
<?php
namespace Monstein\Controllers;

use Monstein\Base\EntityController;
use Monstein\Models\Product;

class ProductEntityController extends EntityController
{
    /**
     * GET /products/{id} - Get single product
     */
    public function doGet($request, $response, $args)
    {
        $product = Product::where('id', $args['id'])
            ->where('user_id', $this->getUserId())
            ->first();
            
        if (!$product) {
            return $this->notFound($response, 'Product not found');
        }
        
        return $this->successResponse($response, $product->toArray());
    }
    
    /**
     * PUT /products/{id} - Update product
     */
    public function doPut($request, $response, $args)
    {
        $product = Product::where('id', $args['id'])
            ->where('user_id', $this->getUserId())
            ->first();
            
        if (!$product) {
            return $this->notFound($response, 'Product not found');
        }
        
        $data = $request->getParsedBody();
        $product->update($data);
        
        return $this->successResponse($response, $product->fresh()->toArray());
    }
    
    /**
     * DELETE /products/{id} - Delete product
     */
    public function doDelete($request, $response, $args)
    {
        $product = Product::where('id', $args['id'])
            ->where('user_id', $this->getUserId())
            ->first();
            
        if (!$product) {
            return $this->notFound($response, 'Product not found');
        }
        
        $product->delete();
        
        return $this->noContent($response);
    }
}
```

### BaseController Methods

All controllers extend `BaseController` which provides:

| Method | Description |
|--------|-------------|
| `successResponse($response, $data, $status = 200)` | Return success JSON |
| `createdResponse($response, $data)` | Return 201 Created |
| `noContent($response)` | Return 204 No Content |
| `notFound($response, $message)` | Return 404 Not Found |
| `badRequest($response, $message)` | Return 400 Bad Request |
| `unauthorized($response, $message)` | Return 401 Unauthorized |
| `forbidden($response, $message)` | Return 403 Forbidden |
| `validationError($response, $errors)` | Return 422 with validation errors |
| `serverError($response, $message)` | Return 500 Server Error |
| `getUserId()` | Get authenticated user's ID |
| `getUser()` | Get authenticated user object |

### Using Helper Utilities in Controllers

Add the `HelperAware` trait for automatic injection:

```php
use Monstein\Base\BaseController;
use Monstein\Helpers\HelperAware;

class MyController extends BaseController
{
    use HelperAware;
    
    public function doGet($request, $response, $args)
    {
        // Cache is now available
        $data = $this->cache->remember('key', function() {
            return expensiveOperation();
        }, 3600);
        
        // HTTP client is available
        $api = $this->http->get('https://api.example.com');
        
        // Encryption is available
        $token = $this->encryption->encrypt($sensitive);
    }
}
```

---

## Authentication

Monstein uses JWT (JSON Web Tokens) for stateless authentication.

### Login Flow

```
1. POST /issueToken with credentials
   ↓
2. Server validates credentials
   ↓
3. Server generates JWT with user data
   ↓
4. Client stores token
   ↓
5. Client sends token in Authorization header
   ↓
6. JwtMiddleware validates token on secure routes
```

### Token Issuance

```php
// POST /issueToken
{
    "username": "demo",
    "password": "demo123"
}

// Response
{
    "success": true,
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "user": {
            "id": 1,
            "username": "demo"
        }
    }
}
```

### Using the Token

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://api.example.com/todo
```

### JWT Configuration

Environment variables:

```env
JWT_SECRET=your-256-bit-secret-key
JWT_EXPIRES=60          # Minutes until expiration
JWT_ALGORITHM=HS256     # Algorithm (HS256, HS384, HS512)
```

### Token Structure

```php
// Payload
[
    'iat' => time(),                    // Issued at
    'exp' => time() + (60 * 60),        // Expires in 1 hour
    'sub' => $user->id,                 // User ID
    'username' => $user->username,      // Username
]
```

### JwtMiddleware

The middleware:
1. Extracts token from `Authorization: Bearer <token>` header
2. Validates signature and expiration
3. Attaches user to request: `$request->getAttribute('user')`
4. Skips validation for routes with `is_secure: false`

---

## Middleware

Middleware are executed in order for requests, reverse order for responses.

### Middleware Stack (as configured)

```
1. Security Headers    - Adds security response headers
2. CORS                - Handles cross-origin requests
3. Rate Limiting       - Prevents abuse
4. JWT Authentication  - Validates tokens
5. Parameter Validation - Validates URL parameters
6. File Upload         - Processes uploaded files
7. Usage Tracking      - Logs API usage
```

### Creating Custom Middleware

```php
<?php
namespace Monstein\Base;

class CustomMiddleware
{
    public function __invoke($request, $response, $next)
    {
        // Before controller
        $request = $request->withAttribute('custom', 'value');
        
        // Call next middleware
        $response = $next($request, $response);
        
        // After controller
        return $response->withHeader('X-Custom', 'Header');
    }
}
```

### Registering Middleware

In `app/Middleware.php`:

```php
public function __construct($app)
{
    $this->app = $app;
    
    // Add in reverse order (last added = first executed)
    $this->addUsageTracking();
    $this->addFileUpload();
    $this->addParamValidation();
    $this->addJwt();
    $this->addRateLimiting();
    $this->addCors();
    $this->addSecurityHeaders();
}
```

---

## Database & Models

Monstein uses Eloquent ORM (from Laravel) for database operations.

### Configuration

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=monstein
DB_USER=root
DB_PASS=secret
```

### Creating a Model

```php
<?php
namespace Monstein\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    
    protected $table = 'products';
    
    protected $fillable = [
        'name',
        'price',
        'description',
        'user_id',
    ];
    
    protected $hidden = [
        'deleted_at',
    ];
    
    protected $casts = [
        'price' => 'float',
        'created_at' => 'datetime',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
}
```

### Database Migrations

Using Phinx for migrations:

```php
<?php
use Phinx\Migration\AbstractMigration;

class CreateProductsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('products', ['signed' => false]);
        $table
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addIndex('user_id')
            ->create();
    }
}
```

Run migrations:

```bash
composer migrate          # Run all pending
composer migrate:rollback # Rollback last
composer migrate:status   # Show status
```

---

## Helper Utilities

Injectable utility classes for common operations.

### Cache

File-based caching with TTL support.

```php
use Monstein\Helpers\Cache;

$cache = new Cache('/path/to/cache');

// Basic operations
$cache->set('key', $data, 3600);    // TTL in seconds
$value = $cache->get('key', 'default');
$cache->forget('key');
$cache->flush();

// Cache-aside pattern
$users = $cache->remember('users', function() {
    return User::all()->toArray();
}, 600);

// Atomic counters
$cache->increment('views');
$cache->decrement('stock', 5);
```

### HttpClient

cURL wrapper for external API calls.

```php
use Monstein\Helpers\HttpClient;

$http = new HttpClient([
    'timeout' => 30,
    'verify_ssl' => true,
]);

// GET request
$response = $http->get('https://api.example.com/users', [
    'page' => 1,
    'limit' => 10,
]);

// POST with JSON
$response = $http->postJson('https://api.example.com/users', [
    'name' => 'John',
    'email' => 'john@example.com',
]);

// Response structure
[
    'success' => true,
    'status' => 200,
    'headers' => ['Content-Type' => 'application/json'],
    'body' => '{"id": 1}',
    'json' => ['id' => 1],  // Parsed JSON
]

// Authentication
$http->setBearerToken('your-token');

// Download file
$http->download('https://example.com/file.pdf', '/local/path.pdf');
```

### Encryption

AES-256-GCM encryption with HMAC signing.

```php
use Monstein\Helpers\Encryption;

$crypt = new Encryption('your-secret-key');

// Encrypt/decrypt strings
$encrypted = $crypt->encrypt('sensitive data');
$decrypted = $crypt->decrypt($encrypted);

// Encrypt/decrypt arrays
$encrypted = $crypt->encryptArray(['user_id' => 123]);
$data = $crypt->decryptArray($encrypted);

// Sign data (HMAC)
$token = $crypt->sign('payload');
$payload = $crypt->verify($token);  // false if tampered

// Generate secure key
$key = Encryption::generateKey(32);
```

### Response

Standardized API response builder.

```php
use Monstein\Helpers\Response;

// Success responses
Response::success($data);                    // 200
Response::created($data, 'Created');         // 201
Response::noContent();                       // 204

// Error responses
Response::error('Message', 400);             // Custom error
Response::notFound('Resource not found');    // 404
Response::unauthorized('Invalid token');     // 401
Response::forbidden('Access denied');        // 403
Response::validationError(['field' => 'error']); // 422
Response::rateLimited(60);                   // 429
Response::serverError('Something broke');    // 500

// Paginated response
Response::paginated($items, $total, $page, $perPage);

// Apply to Slim response
return Response::apply($response, Response::success($data));
```

### Str (String Helpers)

```php
use Monstein\Helpers\Str;

// Case conversion
Str::camel('hello_world');      // 'helloWorld'
Str::snake('helloWorld');       // 'hello_world'
Str::kebab('helloWorld');       // 'hello-world'
Str::studly('hello_world');     // 'HelloWorld'
Str::slug('Hello World!');      // 'hello-world'

// String operations
Str::truncate($text, 100);      // 'text...'
Str::words($text, 10);          // Limit by word count
Str::random(32);                // Random alphanumeric
Str::uuid();                    // UUID v4
Str::mask('1234567890', 4);     // '1234**7890'

// Checks
Str::startsWith($str, 'prefix');
Str::endsWith($str, 'suffix');
Str::contains($str, 'needle');
Str::isEmail('test@example.com');
Str::isUrl('https://example.com');
Str::isJson('{"key": "value"}');

// Extract
Str::between('<div>content</div>', '<div>', '</div>');  // 'content'
```

### Arr (Array Helpers)

```php
use Monstein\Helpers\Arr;

// Dot notation access
Arr::get($array, 'user.profile.name', 'default');
Arr::set($array, 'user.settings.theme', 'dark');
Arr::has($array, 'user.email');
Arr::forget($array, 'user.temp');

// Filtering
Arr::only($user, ['id', 'name', 'email']);
Arr::except($user, ['password', 'token']);
Arr::where($users, 'active', true);

// Collection operations
Arr::pluck($users, 'email');           // ['a@b.com', 'c@d.com']
Arr::pluck($users, 'name', 'id');      // [1 => 'John', 2 => 'Jane']
Arr::groupBy($orders, 'status');
Arr::keyBy($users, 'id');
Arr::sortBy($users, 'created_at', 'desc');

// Utilities
Arr::first($array);
Arr::last($array);
Arr::flatten($nested);
Arr::random($array, 3);
Arr::chunk($array, 10);
Arr::sum($orders, 'total');
Arr::avg($scores, 'value');
```

---

## File Uploads

Flexible file upload handling with filesystem or database storage.

### Configuration in routing.yml

```yaml
uploads:
  url: /uploads
  controller: \Monstein\Controllers\UploadController
  method: [ post ]
  file_upload:
    enabled: true
    max_size: 10485760      # 10MB in bytes
    allowed_types: images   # 'images', 'documents', 'all', or array
    storage: filesystem     # 'filesystem', 'database', 'both'
    db_format: base64       # 'base64' or 'blob' (for database storage)
    strict: false           # Fail entire request if any file fails
```

### Allowed Types

| Value | MIME Types |
|-------|------------|
| `images` | jpeg, png, gif, webp, svg |
| `documents` | pdf, doc, docx, xls, xlsx, txt, csv |
| `all` | All supported types |
| `['image/png', 'application/pdf']` | Custom array |

### Multipart Upload

```bash
curl -X POST \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@photo.jpg" \
  -F "name=My Photo" \
  https://api.example.com/uploads
```

### Base64 Upload

```bash
curl -X POST \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "file": "data:image/png;base64,iVBORw0KGgo...",
    "name": "My Photo"
  }' \
  https://api.example.com/uploads
```

### Accessing Files in Controller

```php
public function doPost($request, $response, $args)
{
    // Files are attached by FileUploadMiddleware
    $files = $request->getAttribute('uploaded_files', []);
    
    foreach ($files as $file) {
        // $file contains:
        // - original_name: Original filename
        // - stored_name: Unique stored filename
        // - mime_type: MIME type
        // - size: File size in bytes
        // - path: Storage path (if filesystem)
        // - hash: File content hash
    }
}
```

### FileUpload Utility

```php
use Monstein\Base\FileUpload;

$uploader = new FileUpload([
    'max_size' => 5 * 1024 * 1024,  // 5MB
    'allowed_types' => 'images',
    'storage' => 'filesystem',
    'storage_path' => '/path/to/uploads',
]);

// Handle multipart upload
$result = $uploader->handleMultipart($_FILES['file']);

// Handle base64 upload
$result = $uploader->handleBase64($base64String, 'photo.jpg');

// Validate file
$isValid = $uploader->validateFile($tempPath, 'image.jpg');
```

---

## Usage Tracking

Automatic API usage analytics via middleware.

### Configuration in routing.yml

```yaml
# Simple enable
todos:
  url: /todo
  tracking: true

# Full configuration
products:
  url: /products
  tracking:
    enabled: true
    name: "product_list"      # Custom name for analytics
    track_user: true          # Track user ID
    track_ip: true            # Track IP address
    track_user_agent: false   # Track user agent
    track_body: false         # Track request body (security risk!)
```

### Environment Variables

```env
USAGE_TRACKER_ENABLED=true       # Master switch
USAGE_TRACKER_DRIVER=database    # 'database', 'file', 'memory'
USAGE_TRACKER_SAMPLE_RATE=100    # Track X% of requests (1-100)
```

### API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /usage/stats` | Overall statistics |
| `GET /usage/top` | Top endpoints by count |
| `GET /usage/slow` | Slowest endpoints |
| `GET /usage/errors` | Error rates |

### Query Parameters

| Parameter | Values | Description |
|-----------|--------|-------------|
| `period` | `hour`, `day`, `week`, `month`, `all` | Time period |
| `limit` | `1-100` | Max results |
| `endpoint` | `/path` | Filter by endpoint |

### Example Response

```json
{
  "success": true,
  "data": {
    "total_requests": 1250,
    "period_start": "2026-01-23 00:00:00",
    "by_endpoint": [
      {
        "endpoint": "/todo",
        "method": "GET",
        "count": 450,
        "avg_response_time": "12.50",
        "error_count": 2
      }
    ],
    "by_status_code": [
      {"status_code": 200, "count": 1200},
      {"status_code": 404, "count": 35}
    ]
  }
}
```

---

## Rate Limiting

Per-route rate limiting to prevent abuse.

### Configuration in routing.yml

```yaml
issueToken:
  url: /issueToken
  is_secure: false
  rate_limit:
    max_requests: 5       # Max requests
    window: 60            # Per time window (seconds)
```

### Default Limits

For routes without explicit configuration:
- Secure routes: 100 requests per minute
- Public routes: 30 requests per minute

### Environment Variables

```env
RATE_LIMIT_MAX=100        # Default max requests
RATE_LIMIT_WINDOW=60      # Default window (seconds)
TRUSTED_PROXIES=127.0.0.1 # Trust X-Forwarded-For from these IPs
```

### Response Headers

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1706012400
```

### Rate Limit Exceeded Response

```json
{
  "success": false,
  "errors": "Rate limit exceeded. Try again in 45 seconds.",
  "retry_after": 45
}
```

---

## Security

### Security Headers

Automatically added to all responses:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
Strict-Transport-Security: max-age=31536000; includeSubDomains
Permissions-Policy: geolocation=(), camera=(), microphone=()
Referrer-Policy: strict-origin-when-cross-origin
```

### Input Validation

Using Respect Validation:

```php
use Respect\Validation\Validator as v;

$this->validator->validate($request, [
    'email' => v::notEmpty()->email(),
    'password' => v::notEmpty()->length(8, 100),
    'age' => v::optional(v::intVal()->between(18, 120)),
]);

if (!$this->validator->isValid()) {
    return $this->validationError($response, $this->validator->getErrors());
}
```

### URL Parameter Validation

In routing.yml:

```yaml
products:
  url: /products/{id}
  params:
    id:
      type: integer
      min: 1
```

### Security Utilities

```php
use Monstein\Base\SecurityUtils;

// XSS prevention
$safe = SecurityUtils::sanitizeInput($userInput);

// Password strength
$result = SecurityUtils::validatePasswordStrength($password);
// Returns: ['valid' => false, 'errors' => ['Password too short']]

// Safe filename
$safe = SecurityUtils::sanitizeFilename($filename);

// Check HTTPS
$secure = SecurityUtils::isHttps($request);

// Mask sensitive data
$masked = SecurityUtils::maskSensitive($email);  // 'jo***@example.com'
```

### CORS Configuration

```env
CORS_ORIGIN=https://yourapp.com
CORS_HEADERS=X-Requested-With, Content-Type, Accept, Origin, Authorization
CORS_METHODS=GET, POST, PUT, DELETE, PATCH, OPTIONS
```

---

## CLI Tools

Generate boilerplate code with the CLI tool.

### Commands

```bash
# Create full resource (model + controllers + migration)
./bin/monstein make:resource Product

# Create model only
./bin/monstein make:entity Product

# Create controllers only
./bin/monstein make:controller Product

# Create migration only
./bin/monstein make:migration Product

# Create file upload endpoint
./bin/monstein make:file-endpoint Document

# List all routes
./bin/monstein routes:list

# Show usage tracking config
./bin/monstein usage:stats
```

### Generated Files

| Command | Files Created |
|---------|---------------|
| `make:resource Product` | Model + 2 Controllers + Migration |
| `make:entity Product` | `app/Models/Product.php` |
| `make:controller Product` | Collection + Entity Controllers |
| `make:migration Product` | `db/migrations/YYYYMMDD_create_products.php` |

### Customizing Templates

Edit files in `stubs/` directory:
- `model.stub`
- `collection-controller.stub`
- `entity-controller.stub`
- `migration.stub`

---

## Docker Deployment

### Quick Start

```bash
# Clone and setup
git clone https://github.com/lahirunirmalx/monstein.git
cd monstein

# One-command setup
./setup.sh

# Or manually
make setup
make up
```

### Architecture

```
                    ┌─────────────────┐
                    │   Nginx LB      │ :80/:443
                    │  (Load Balancer)│
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
        ┌─────▼─────┐  ┌─────▼─────┐  ┌─────▼─────┐
        │  App #1   │  │  App #2   │  │  App #3   │
        │ (PHP-FPM) │  │ (PHP-FPM) │  │ (PHP-FPM) │
        └─────┬─────┘  └─────┬─────┘  └─────┬─────┘
              │              │              │
              └──────────────┼──────────────┘
                             │
                    ┌────────▼────────┐
                    │    MariaDB      │
                    │   (Database)    │
                    └─────────────────┘
```

### Services

| Service | Port | Description |
|---------|------|-------------|
| `nginx` | 80, 443 | Load balancer |
| `app` | 9000 (internal) | PHP-FPM application |
| `db` | 3306 (internal) | MariaDB database |
| `adminer` | 8080 | Database admin UI |

### Make Commands

```bash
make setup      # Initial setup
make up         # Start services
make down       # Stop services
make logs       # View logs
make shell      # Shell into app container
make test       # Run tests in container
make clean      # Remove everything
make scale N=3  # Scale to N app instances
```

### Environment Variables

```env
# Docker
COMPOSE_PROJECT_NAME=monstein
APP_PORT=80
ADMINER_PORT=8080

# Database
DB_ROOT_PASSWORD=rootsecret
DB_NAME=monstein
DB_USER=monstein
DB_PASS=secret

# Application
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=your-secret-key
```

### Scaling

```bash
# Scale to 5 app instances
docker compose up -d --scale app=5

# Using make
make scale N=5
```

---

## Quick Reference

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | Environment |
| `APP_DEBUG` | `true` | Debug mode |
| `DB_DRIVER` | `mysql` | Database driver |
| `DB_HOST` | `localhost` | Database host |
| `DB_NAME` | `monstein` | Database name |
| `JWT_SECRET` | - | JWT signing key |
| `JWT_EXPIRES` | `60` | Token expiration (minutes) |
| `CORS_ORIGIN` | `*` | Allowed origins |
| `RATE_LIMIT_MAX` | `100` | Default rate limit |
| `TRUSTED_PROXIES` | `127.0.0.1` | Trusted proxy IPs |
| `CACHE_PATH` | `storage/cache` | Cache directory |
| `FILE_STORAGE_PATH` | `storage/uploads` | Upload directory |
| `USAGE_TRACKER_ENABLED` | `true` | Enable tracking |
| `USAGE_TRACKER_DRIVER` | `database` | Tracking storage |

### HTTP Status Codes Used

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### Response Format

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}

{
  "success": false,
  "errors": "Error message or object"
}
```

---

## License

MIT License - see LICENSE file for details.
