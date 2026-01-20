# Changelog

All notable changes to Monstein API Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-20

### Features
- **Slim 3 Framework** - Lightweight PHP micro-framework for building RESTful APIs
- **JWT Authentication** - Secure token-based authentication with `firebase/php-jwt` ^6.0
- **Eloquent ORM** - Laravel's database ORM for elegant database operations
- **Phinx Migrations** - Database version control with migration support
- **Input Validation** - Request validation using Respect/Validation
- **Monolog Logging** - PSR-3 compliant logging with rotating file support
- **Environment Configuration** - Secure configuration via `.env` files

### PHP Compatibility
- PHP 7.4, 8.0, 8.1, 8.2, 8.3 support
- Flexible dependency versions for cross-version compatibility

### Developer Tools
- **CLI Scaffolding** (`bin/monstein`):
  - `make:resource` - Generate full resource (model, controllers, migration)
  - `make:entity` - Generate Eloquent model
  - `make:controller` - Generate collection and entity controllers
  - `make:migration` - Generate Phinx migration
- Customizable code stubs in `stubs/` directory
- Composer scripts for common tasks

### API Endpoints
- `POST /token` - Obtain JWT token
- `GET /categories` - List categories
- `POST /categories` - Create category
- `GET /categories/{id}` - Get category
- `PUT /categories/{id}` - Update category
- `DELETE /categories/{id}` - Delete category
- `GET /todos` - List todos
- `POST /todos` - Create todo
- `GET /todos/{id}` - Get todo
- `PUT /todos/{id}` - Update todo
- `DELETE /todos/{id}` - Delete todo

### Security
- JWT authentication with configurable algorithm (HS256/HS384/HS512)
- Custom JWT middleware (`app/Base/JwtMiddleware.php`)
- Security headers (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy)
- Environment-based secrets (no hardcoded credentials)
- Configurable CORS support
- Apache `.htaccess` security configuration

### CI/CD
- GitHub Actions workflow for code quality and security checks
- PHP syntax validation across all supported versions
- Composer security audit
- Hardcoded secrets detection

### Testing
- PHPUnit 9.x/10.x support
- Test bootstrap for proper initialization
- Example test cases for auth, categories, and todos

### Documentation
- Comprehensive README with installation and usage guides
- `.env.example` template
- Code stubs with examples
