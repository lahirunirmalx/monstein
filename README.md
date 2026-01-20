# Monstein

A lightweight RESTful API framework built on Slim 3 with JWT authentication and Eloquent ORM.

[![PHP CI](https://github.com/lahirunirmalx/monstein/actions/workflows/php.yml/badge.svg)](https://github.com/lahirunirmalx/monstein/actions/workflows/php.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Features

- **Slim 3 Framework** - Fast, lightweight PHP micro-framework
- **JWT Authentication** - Secure token-based auth with firebase/php-jwt
- **Eloquent ORM** - Laravel's elegant database abstraction
- **Phinx Migrations** - Database version control
- **CLI Dev Tools** - Scaffolding for rapid development
- **Multi-PHP Support** - PHP 7.4, 8.0, 8.1, 8.2, 8.3

## Requirements

- PHP 7.4 or higher
- Composer
- MySQL/MariaDB or SQLite

## Installation

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
│   ├── Base/              # Base classes
│   │   ├── BaseController.php
│   │   ├── BaseRouter.php
│   │   ├── CollectionController.php
│   │   ├── EntityController.php
│   │   └── JwtMiddleware.php
│   ├── Config/
│   │   ├── Config.php     # Configuration
│   │   └── routing.yml    # Route definitions
│   ├── Controllers/       # API controllers
│   ├── Models/            # Eloquent models
│   ├── App.php            # Application bootstrap
│   ├── Dependencies.php   # DI container
│   └── Middleware.php     # Middleware setup
├── bin/
│   └── monstein           # CLI tool
├── db/
│   └── migrations/        # Phinx migrations
├── stubs/                 # Code generation templates
├── symfony/
│   └── web/
│       └── index.php      # Entry point
├── tests/                 # PHPUnit tests
├── .env.example           # Environment template
├── composer.json
└── phinx.php              # Migration config
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

## Security

- JWT tokens with configurable expiration
- Password hashing with bcrypt
- Security headers on all responses
- Environment-based configuration (no hardcoded secrets)
- CORS protection

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
