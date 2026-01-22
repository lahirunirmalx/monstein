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
```

### Generated Files

| Command | Files Created |
|---------|---------------|
| `make:resource Product` | `app/Models/Product.php`<br>`app/Controllers/ProductCollectionController.php`<br>`app/Controllers/ProductEntityController.php`<br>`db/migrations/YYYYMMDD_create_products.php` |
| `make:entity Product` | `app/Models/Product.php` |
| `make:controller Product` | `app/Controllers/ProductCollectionController.php`<br>`app/Controllers/ProductEntityController.php` |
| `make:migration Product` | `db/migrations/YYYYMMDD_create_products.php` |

### Customizing Stubs

Edit templates in `stubs/` directory:
- `model.stub` - Eloquent model template
- `collection-controller.stub` - Collection controller template
- `entity-controller.stub` - Entity controller template
- `migration.stub` - Phinx migration template

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
│   │   └── SecurityUtils.php
│   ├── Config/
│   │   ├── Config.php           # Configuration
│   │   └── routing.yml          # Route definitions
│   ├── Controllers/             # API controllers
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

- JWT tokens with configurable expiration
- Password hashing with bcrypt
- Security headers on all responses
- Environment-based configuration (no hardcoded secrets)
- CORS protection
- Rate limiting (configurable per route)
- XSS/MITM protection headers
- Parameter validation on routes

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
