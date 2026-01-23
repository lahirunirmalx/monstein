# Monstein

A lightweight RESTful API framework built on Slim 3 with JWT authentication and Eloquent ORM.

[![PHP CI](https://github.com/lahirunirmalx/monstein/actions/workflows/php.yml/badge.svg)](https://github.com/lahirunirmalx/monstein/actions/workflows/php.yml)
[![Docker](https://img.shields.io/badge/Docker-ghcr.io-blue?logo=docker)](https://ghcr.io/lahirunirmalx/monstein)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Features

- **Slim 3 Framework** - Fast, lightweight PHP micro-framework
- **JWT Authentication** - Secure token-based auth with firebase/php-jwt
- **Eloquent ORM** - Laravel's elegant database abstraction
- **Phinx Migrations** - Database version control
- **CLI Dev Tools** - Scaffolding for rapid development
- **Multi-PHP Support** - PHP 7.4, 8.0, 8.1, 8.2, 8.3
- **Docker Ready** - One-command deployment with load balancing
- **CI/CD Built-in** - GitHub Actions for testing & Docker builds
- **File Uploads** - Multipart and base64 support with configurable storage
- **Usage Tracking** - Automatic API analytics and monitoring
- **Rate Limiting** - Configurable per-route protection

## Requirements

- PHP 7.4 or higher
- Composer
- MySQL/MariaDB or SQLite
- Docker & Docker Compose (optional, for containerized deployment)

## Installation

### Option 1: Docker (Recommended)

```bash
# Clone the repository
git clone https://github.com/lahirunirmalx/monstein.git
cd monstein

# Setup (creates .env and builds images)
make setup

# Edit .env with your settings
nano .env

# Start all services
make up

# Or with Adminer (database admin)
make up-dev
```

Access the API at `http://localhost` (or custom port in .env)

### Option 2: Manual Installation

```bash
# Clone the repository
git clone https://github.com/lahirunirmalx/monstein.git
cd monstein

# Install dependencies
composer install

# Create environment file
cp .env.example .env

# Generate JWT secret
php -r "echo 'JWT_SECRET=' . bin2hex(random_bytes(32)) . PHP_EOL;" >> .env

# Run migrations
composer migrate
```

## Configuration

Edit `.env` file:

```env
# Application
APP_ENV=development
APP_DEBUG=true

# Database
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=monstein
DB_USER=root
DB_PASS=

# JWT
JWT_SECRET=your-secret-key
JWT_EXPIRES=60
JWT_ALGORITHM=HS256

# CORS
CORS_ORIGIN=*
```

## Usage

### Start Server

```bash
php -S localhost:8080 -t symfony/web
```

### API Endpoints

#### Authentication

```bash
# Get JWT token
curl -X POST http://localhost:8080/token \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "password"}'
```

#### Categories

```bash
# List categories
curl http://localhost:8080/categories \
  -H "Authorization: Bearer YOUR_TOKEN"

# Create category
curl -X POST http://localhost:8080/categories \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Work"}'

# Get category
curl http://localhost:8080/categories/1 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update category
curl -X PUT http://localhost:8080/categories/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Personal"}'

# Delete category
curl -X DELETE http://localhost:8080/categories/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Todos

```bash
# List todos
curl http://localhost:8080/todos \
  -H "Authorization: Bearer YOUR_TOKEN"

# Create todo
curl -X POST http://localhost:8080/todos \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Buy groceries", "category_id": 1}'

# Get todo
curl http://localhost:8080/todos/1 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update todo
curl -X PUT http://localhost:8080/todos/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Buy groceries and milk"}'

# Delete todo
curl -X DELETE http://localhost:8080/todos/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### File Uploads

```bash
# Upload file (multipart form)
curl -X POST http://localhost:8080/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@/path/to/document.pdf"

# Upload multiple files
curl -X POST http://localhost:8080/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file1=@image1.jpg" \
  -F "file2=@image2.png"

# Upload file (base64 encoded)
curl -X POST http://localhost:8080/files \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "file_data": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
    "file_name": "pixel.png"
  }'

# List uploaded files
curl http://localhost:8080/files \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get file info
curl http://localhost:8080/files/1 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Download file
curl http://localhost:8080/files/1?download=true \
  -H "Authorization: Bearer YOUR_TOKEN" -O

# Delete file
curl -X DELETE http://localhost:8080/files/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Usage Statistics

```bash
# Get overall usage stats
curl http://localhost:8080/usage/stats \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get stats for specific period (hour, day, week, month)
curl "http://localhost:8080/usage/stats?period=week" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get top endpoints by request count
curl "http://localhost:8080/usage/top?limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get slowest endpoints
curl "http://localhost:8080/usage/slow?limit=5" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get error rates
curl http://localhost:8080/usage/errors \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Developer Tools

Generate boilerplate code with CLI commands:

```bash
# Create full resource (model + controllers + migration)
composer make:resource Product

# Create model only
composer make:entity Product

# Create controllers only
composer make:controller Product

# Create migration only
composer make:migration Product

# Create file upload endpoint
composer make:file-endpoint Document

# List all registered routes
./bin/monstein routes:list

# Show usage tracking configuration
./bin/monstein usage:stats
```

### Generated Files

| Command | Files Created |
|---------|---------------|
| `make:resource Product` | `app/Models/Product.php`<br>`app/Controllers/ProductCollectionController.php`<br>`app/Controllers/ProductEntityController.php`<br>`db/migrations/YYYYMMDD_create_products.php` |
| `make:entity Product` | `app/Models/Product.php` |
| `make:controller Product` | `app/Controllers/ProductCollectionController.php`<br>`app/Controllers/ProductEntityController.php` |
| `make:migration Product` | `db/migrations/YYYYMMDD_create_products.php` |
| `make:file-endpoint Document` | `app/Controllers/DocumentCollectionController.php`<br>`app/Controllers/DocumentEntityController.php` |

### Customizing Stubs

Edit templates in `stubs/` directory:
- `model.stub` - Eloquent model template
- `collection-controller.stub` - Collection controller template
- `entity-controller.stub` - Entity controller template
- `migration.stub` - Phinx migration template

## Helper Utilities

Monstein provides injectable utility classes for common tasks. All helpers follow dependency injection patterns.

### Available Helpers

| Helper | Purpose | DI Available |
|--------|---------|--------------|
| `Cache` | File-based caching | Yes |
| `HttpClient` | External HTTP requests | Yes |
| `Encryption` | AES-256 data encryption | Yes |
| `Response` | Standardized API responses | Static |
| `Str` | String manipulation | Static |
| `Arr` | Array operations with dot notation | Static |

### Using Helpers in Controllers

Add the `HelperAware` trait to get Cache, HttpClient, and Encryption injected automatically:

```php
<?php
namespace Monstein\Controllers;

use Monstein\Base\BaseController;
use Monstein\Helpers\HelperAware;

class ProductController extends BaseController
{
    use HelperAware;
    
    public function doGet($request, $response, $args)
    {
        // Use cache
        $products = $this->cache->remember('products', function() {
            return Product::all()->toArray();
        }, 3600);  // Cache for 1 hour
        
        // Use HTTP client
        $api = $this->http->get('https://api.example.com/data');
        
        // Use encryption
        $token = $this->encryption->encrypt($sensitiveData);
        
        return $response->withJson(['data' => $products]);
    }
}
```

### Cache

```php
use Monstein\Helpers\Cache;

// Via DI (in controller with HelperAware trait)
$this->cache->set('key', $data, 3600);    // Store for 1 hour
$value = $this->cache->get('key');         // Retrieve
$this->cache->forget('key');               // Delete

// Cache-aside pattern
$users = $this->cache->remember('users', function() {
    return User::all()->toArray();
}, 600);

// Manual instantiation
$cache = new Cache('/path/to/cache');
$cache->increment('counter');
$cache->flush();  // Clear all
```

### HttpClient

```php
use Monstein\Helpers\HttpClient;

// Via DI
$response = $this->http->get('https://api.example.com/users');
$response = $this->http->post('https://api.example.com/users', ['name' => 'John']);
$response = $this->http->postJson('https://api.example.com/users', ['name' => 'John']);

// Response structure
[
    'success' => true,
    'status' => 200,
    'headers' => ['Content-Type' => 'application/json'],
    'body' => '{"id": 1}',
    'json' => ['id' => 1],
]

// With authentication
$http = new HttpClient(['timeout' => 60]);
$http->setBearerToken('your-token');
$result = $http->get('https://api.example.com/protected');

// Download file
$http->download('https://example.com/file.pdf', '/path/to/save.pdf');
```

### Encryption

```php
use Monstein\Helpers\Encryption;

// Via DI
$encrypted = $this->encryption->encrypt('sensitive data');
$decrypted = $this->encryption->decrypt($encrypted);

// Arrays
$encrypted = $this->encryption->encryptArray(['user_id' => 123]);
$data = $this->encryption->decryptArray($encrypted);

// Signed tokens (HMAC)
$token = $this->encryption->sign('payload');
$payload = $this->encryption->verify($token);  // Returns false if tampered

// Generate secure key
$key = Encryption::generateKey(32);
```

### Response

Standardized API responses:

```php
use Monstein\Helpers\Response;

// Success responses
return Response::apply($response, Response::success($data));
return Response::apply($response, Response::created($user, 'User created'));

// Error responses
return Response::apply($response, Response::error('Invalid input', 400));
return Response::apply($response, Response::notFound());
return Response::apply($response, Response::unauthorized());
return Response::apply($response, Response::validationError(['email' => 'Invalid']));
return Response::apply($response, Response::rateLimited(60));

// Paginated responses
return Response::apply($response, Response::paginated($items, $total, $page, $perPage));
```

### Str (String Helpers)

```php
use Monstein\Helpers\Str;

// Case conversion
Str::camel('hello_world');      // 'helloWorld'
Str::snake('helloWorld');       // 'hello_world'
Str::kebab('hello world');      // 'hello-world'
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
Arr::shuffle($array);
Arr::chunk($array, 10);
Arr::sum($orders, 'total');
Arr::avg($scores, 'value');
```

### Environment Variables

Add these to `.env` for helper configuration:

```env
# Cache
CACHE_PATH=./storage/cache

# HTTP Client  
HTTP_CLIENT_TIMEOUT=30
HTTP_VERIFY_SSL=true

# Encryption (uses JWT_SECRET if not set)
APP_KEY=your-32-character-encryption-key
```

## File Uploads

Monstein provides a robust file upload system with flexible storage options.

### Configuration in `routing.yml`

```yaml
files:
  url: /files
  controller: \Monstein\Controllers\FileCollectionController
  method: [ post, get ]
  file_upload:
    enabled: true
    max_size: 10485760          # 10MB (in bytes)
    allowed_types: all          # 'images', 'documents', 'all', or array
    storage: filesystem         # 'filesystem', 'database', or 'both'
    db_format: base64           # 'base64' or 'blob'
    strict: false               # Return error if any file fails
```

### Storage Options

| Option | Description |
|--------|-------------|
| `filesystem` | Store files on disk in `storage/uploads/` |
| `database` | Store files in database as base64 or blob |
| `both` | Store both on filesystem and in database |

### Allowed Types

| Value | Description |
|-------|-------------|
| `images` | jpeg, png, gif, webp, svg |
| `documents` | pdf, doc, docx, xls, xlsx, txt, csv |
| `all` | All supported file types |
| `['image/png', 'application/pdf']` | Custom array of MIME types |

### Environment Variables

```env
FILE_STORAGE_PATH=/app/storage/uploads    # File storage directory
FILE_BASE_URL=https://api.example.com     # Base URL for file access
```

## Usage Tracking

Automatic API usage tracking via middleware.

### Configuration in `routing.yml`

```yaml
todos:
  url: /todo
  controller: \Monstein\Controllers\TodoCollectionController
  method: [ post, get ]
  tracking: true              # Simple enable

# Or with full options:
issueToken:
  url: /issueToken
  controller: \Monstein\Controllers\IssueTokenController
  method: [ post ]
  tracking:
    enabled: true
    name: "auth_login"        # Custom name for analytics
    track_user: false         # Track user ID (default: true)
    track_ip: true            # Track IP address (default: true)
    track_user_agent: false   # Track user agent (default: false)
    track_body: false         # Track request body (security risk!)
```

### Environment Variables

```env
USAGE_TRACKER_ENABLED=true       # Master switch (default: true)
USAGE_TRACKER_DRIVER=database    # 'database', 'file', or 'memory'
USAGE_TRACKER_SAMPLE_RATE=100    # Track X% of requests (1-100)
```

### API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /usage/stats` | Overall statistics (by period, endpoint, status) |
| `GET /usage/top` | Top endpoints by request count |
| `GET /usage/slow` | Slowest endpoints by response time |
| `GET /usage/errors` | Error rates by endpoint |

### Query Parameters

| Parameter | Values | Description |
|-----------|--------|-------------|
| `period` | `hour`, `day`, `week`, `month`, `all` | Time period filter |
| `limit` | `1-100` | Max results to return |
| `endpoint` | `/path` | Filter by specific endpoint |

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
      {"status_code": 404, "count": 45}
    ],
    "by_hour": [
      {"hour": 9, "count": 250},
      {"hour": 10, "count": 320}
    ]
  }
}
```

## Project Structure

```
monstein/
├── app/
│   ├── Base/                    # Base classes & middleware
│   │   ├── BaseController.php
│   │   ├── BaseRouter.php
│   │   ├── CollectionController.php
│   │   ├── EntityController.php
│   │   ├── JwtMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── ParamValidationMiddleware.php
│   │   ├── FileUpload.php
│   │   ├── FileUploadMiddleware.php
│   │   ├── UsageTracker.php
│   │   ├── UsageTrackingMiddleware.php
│   │   └── SecurityUtils.php
│   ├── Config/
│   │   ├── Config.php           # Configuration
│   │   └── routing.yml          # Route definitions
│   ├── Controllers/             # API controllers
│   ├── Helpers/                 # Utility classes
│   │   ├── Arr.php              # Array helpers
│   │   ├── Cache.php            # File-based caching
│   │   ├── Encryption.php       # AES-256 encryption
│   │   ├── HelperAware.php      # DI trait for controllers
│   │   ├── HttpClient.php       # cURL wrapper
│   │   ├── Response.php         # Standardized responses
│   │   └── Str.php              # String helpers
│   ├── Models/                  # Eloquent models
│   ├── App.php                  # Application bootstrap
│   ├── Dependencies.php         # DI container
│   └── Middleware.php           # Middleware setup
├── bin/
│   └── monstein                 # CLI tool
├── db/
│   └── migrations/              # Phinx migrations
├── docker/                      # Docker configuration
│   ├── entrypoint.sh            # Container startup script
│   ├── nginx.conf               # App Nginx config
│   ├── nginx-lb.conf            # Load balancer config
│   ├── php-fpm.conf             # PHP-FPM config
│   ├── php.ini                  # PHP settings
│   ├── supervisord.conf         # Process manager
│   └── mysql-init/              # Database init scripts
├── storage/
│   ├── cache/                   # Cache files
│   ├── uploads/                 # File uploads
│   ├── ratelimit/               # Rate limit data
│   └── logs/                    # Application logs
├── stubs/                       # Code generation templates
├── symfony/
│   └── web/
│       └── index.php            # Entry point
├── tests/                       # PHPUnit tests
├── .env.example                 # Environment template
├── .env.docker                  # Docker environment template
├── .github/workflows/php.yml    # CI/CD pipeline
├── docker-compose.yml           # Docker Compose config
├── Dockerfile                   # Container build
├── Makefile                     # Make commands
├── setup.sh                     # One-command setup
├── composer.json
└── phinx.php                    # Migration config
```

## Database Migrations

```bash
# Run migrations
composer migrate

# Rollback last migration
composer migrate:rollback

# Run seeds
composer seed
```

## Testing

```bash
# Run tests
composer test
```

## Docker Deployment

### One-Command Setup

The easiest way to get started:

```bash
./setup.sh
```

This single command will:
- ✅ Check prerequisites (Docker, Docker Compose)
- ✅ Create `.env` with secure random passwords
- ✅ Build Docker images
- ✅ Start all services (app, database, load balancer)
- ✅ Run database migrations
- ✅ Create demo user (`demo` / `demo123`)
- ✅ Display access URLs

#### Setup Options

```bash
./setup.sh                    # Default setup (port 8080)
./setup.sh --port 80          # Custom port
./setup.sh --scale 3          # 3 app instances
./setup.sh --dev              # Include Adminer (DB admin)
./setup.sh --clean            # Clean start (removes existing data)
```

### Pull from GitHub Container Registry

Pre-built images are available from GitHub Container Registry:

```bash
# Pull latest image
docker pull ghcr.io/lahirunirmalx/monstein:latest

# Pull specific version
docker pull ghcr.io/lahirunirmalx/monstein:main
docker pull ghcr.io/lahirunirmalx/monstein:<commit-sha>
```

### Quick Start with Make

```bash
make setup    # Initial setup
make up       # Start services
make down     # Stop services
```

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Load Balancer                        │
│                   (Nginx - Port 80/443)                 │
└─────────────────────┬───────────────────────────────────┘
                      │
          ┌───────────┴───────────┐
          │                       │
┌─────────▼─────────┐   ┌─────────▼─────────┐
│    App Instance   │   │    App Instance   │
│   (PHP 8.2-FPM)   │   │   (PHP 8.2-FPM)   │
│   + Nginx + PHP   │   │   + Nginx + PHP   │
└─────────┬─────────┘   └─────────┬─────────┘
          │                       │
          └───────────┬───────────┘
                      │
          ┌───────────▼───────────┐
          │      MariaDB 10.11    │
          │      (Port 3306)      │
          └───────────────────────┘
```

### Docker Image Details

| Property | Value |
|----------|-------|
| Base Image | `php:8.2-fpm-alpine` |
| Size | ~50MB (minimal Alpine) |
| Registry | `ghcr.io/lahirunirmalx/monstein` |
| Tags | `latest`, `main`, `<commit-sha>` |

### Available Commands

| Command | Description |
|---------|-------------|
| `make up` | Start all services |
| `make up-dev` | Start with Adminer (DB admin) |
| `make down` | Stop all services |
| `make build` | Build Docker images |
| `make scale N=3` | Scale to N app instances |
| `make logs` | View all logs |
| `make db-shell` | Open database shell |
| `make migrate` | Run migrations |
| `make shell` | Open app container shell |
| `make test` | Run tests |
| `make clean` | Remove all containers/volumes |

### Port Configuration

Edit `.env` to customize ports:

```env
LB_PORT=8080            # Load balancer HTTP (main entry)
LB_SSL_PORT=8443        # Load balancer HTTPS
DB_EXTERNAL_PORT=3306   # Database (direct access)
ADMINER_PORT=8081       # Adminer (dev only)
```

### Scaling

```bash
# Scale to 3 app instances
make scale N=3

# Or with docker-compose
docker compose up -d --scale app=3
```

The load balancer automatically distributes traffic across all instances.

### Health Checks

```bash
# Load balancer health
curl http://localhost:8080/lb-health

# Application health
curl http://localhost:8080/health
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `production` | Environment (development/production) |
| `APP_DEBUG` | `false` | Debug mode |
| `DB_HOST` | `db` | Database host |
| `DB_NAME` | `monstein` | Database name |
| `DB_USER` | `monstein` | Database user |
| `DB_PASS` | (random) | Database password |
| `JWT_SECRET` | (random) | JWT signing secret |
| `JWT_EXPIRES` | `30` | Token expiry (minutes) |
| `RATE_LIMIT_MAX` | `100` | Max requests per window |
| `RATE_LIMIT_WINDOW` | `60` | Rate limit window (seconds) |

### CI/CD Pipeline

The GitHub Actions workflow automatically:

1. **Code Quality** - Syntax checks, structure validation (PHP 7.4-8.3)
2. **Security** - Dependency audit, secret detection
3. **Docker Build** - Build and push to GitHub Container Registry
4. **Docker Test** - Verify the built image works

Images are automatically built and pushed on every push to `main` branch.

## Security

### Implemented Protections

| Category | Protection |
|----------|------------|
| **SQL Injection** | Eloquent ORM with parameterized queries |
| **XSS** | Input sanitization, CSP headers, output encoding utilities |
| **Authentication** | JWT tokens with configurable expiration |
| **Password Storage** | bcrypt hashing with PASSWORD_DEFAULT |
| **Password Policy** | Minimum 8 chars, complexity requirements |
| **Rate Limiting** | Configurable per-route limits (DDoS/brute-force) |
| **MITM** | HSTS headers, HTTPS enforcement in production |
| **Clickjacking** | X-Frame-Options: DENY |
| **Information Disclosure** | Generic error messages, debug-only details |
| **IDOR** | User-scoped queries (no cross-user access) |
| **IP Spoofing** | Trusted proxy configuration |

### CSRF Protection

This is a **stateless API** using JWT Bearer tokens (not cookies). CSRF protection is inherent when:

- **JWT stored in memory/localStorage**: Immune to CSRF (tokens not auto-sent)
- **JWT stored in cookies**: Requires additional protection

**For cookie-based JWT storage, add these protections:**

```javascript
// Frontend: Set cookie with SameSite attribute
document.cookie = `token=${jwt}; SameSite=Strict; Secure; Path=/`;
```

**Recommended cookie settings:**

| Attribute | Value | Purpose |
|-----------|-------|---------|
| `SameSite` | `Strict` | Prevents cross-site requests |
| `Secure` | `true` | HTTPS only |
| `HttpOnly` | `true` | Prevents XSS access |
| `Path` | `/` | Scope to API |

### Trusted Proxies

When behind a load balancer, configure trusted proxies to prevent IP spoofing:

```env
# .env
TRUSTED_PROXIES=172.16.0.0/12,10.0.0.0/8,127.0.0.1
```

### Security Headers

All responses include:

```
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

### Environment Security

```env
# Required in production
JWT_SECRET=<generate-with: openssl rand -base64 32>
APP_ENV=production
APP_DEBUG=false
CORS_ORIGIN=https://yourdomain.com  # NOT *
```

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing`)
5. Open Pull Request

## License

MIT License - see [LICENSE](LICENSE) file.

---

**Monstein** - Built with ❤️ by [Lahiru](https://github.com/lahirunirmalx)
