# Changelog

All notable changes to Monstein API Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-01-20

### Security
- **CRITICAL**: Upgraded `firebase/php-jwt` from ^5.5 to ^6.0 to fix CVE-2021-46743 (Key/algorithm type confusion)
- Removed abandoned `tuupola/slim-jwt-auth` package
- Added custom `JwtMiddleware` class for secure JWT handling

### Changed
- JWT authentication now uses custom middleware (`app/Base/JwtMiddleware.php`)
- Updated User model for firebase/php-jwt 6.x API compatibility

---

## [2.0.0] - 2026-01-20

### Added
- Full PHP 8.0, 8.1, 8.2, and 8.3 compatibility
- **CLI Development Tools** (`bin/monstein`) for rapid scaffolding:
  - `make:resource` - Generate full resource (model, controllers, migration)
  - `make:entity` - Generate Eloquent model
  - `make:controller` - Generate collection and entity controllers
  - `make:migration` - Generate Phinx migration
- Customizable code stubs in `stubs/` directory
- Environment-based configuration (.env file support)
- Security headers (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy)
- Rotating log files with configurable retention
- Configurable CORS settings via environment variables
- HTTPS enforcement in production mode
- PHPUnit 9.x/10.x test compatibility
- Comprehensive README with developer tools documentation
- This CHANGELOG file
- `.gitignore` file
- `.env.example` template
- `phpunit.xml.dist` configuration
- Test bootstrap file for proper test initialization
- Apache `.htaccess` with security configurations
- Composer scripts for common tasks (`composer make:resource`, etc.)
- GitHub Actions CI/CD pipeline (`.github/workflows/php.yml`)
  - Multi-version PHP testing (7.4, 8.0, 8.1, 8.2, 8.3)
  - MySQL integration tests
  - Code quality checks
  - Security auditing

### Changed
- **BREAKING**: Minimum PHP version raised to 7.4
- **BREAKING**: Environment variables now required for sensitive configuration
- **BREAKING**: Phinx configuration moved from YAML to PHP for environment variable support
- Updated `slim/slim` to ^3.12 (PHP 8 compatible)
- Updated `illuminate/database` to ^8.0 || ^9.0 || ^10.0
- Updated `illuminate/events` to ^8.0 || ^9.0 || ^10.0
- Updated `monolog/monolog` to ^2.0 || ^3.0
- Updated `firebase/php-jwt` to ^6.0 (with Key object support)
- Updated `robmorgan/phinx` to ^0.13 || ^0.14
- Updated `symfony/yaml` to ^5.0 || ^6.0
- Updated `phpunit/phpunit` to ^9.5 || ^10.0
- Replaced `addInfo()` with `info()` for Monolog 2.x/3.x compatibility
- Improved error handlers with proper logging and debug mode support
- Test files updated with proper namespaces and PHPUnit 9+ syntax
- Category model now handles cascade deletes in boot method

### Removed
- Hardcoded JWT secret (now required via environment variable)
- Hardcoded database credentials in phinx.yml
- `app/Models/Events/Category.php` (logic moved to model boot method)
- Old `phinx.yml` file (replaced with `phinx.php`)

### Security
- **CRITICAL**: Removed hardcoded JWT secret - now loaded from `JWT_SECRET` environment variable
- **CRITICAL**: Error details now hidden in production (`APP_DEBUG=false`)
- **HIGH**: Added security headers to all HTTP responses
- **HIGH**: HTTPS enforcement in production (relaxed for localhost)
- **MEDIUM**: Removed hardcoded database credentials from configuration files
- **MEDIUM**: Added proper CORS configuration with environment variable support
- **LOW**: Added cache control headers to prevent caching of sensitive data

## [1.0.0] - 2018-06-09

### Added
- Initial release
- Slim 3 framework integration
- JWT authentication with tuupola/slim-jwt-auth
- Eloquent ORM for database operations
- Phinx database migrations
- Todo and Category CRUD operations
- User authentication endpoints
- Basic input validation with Respect/Validation
- Monolog logging
- PHPUnit tests

---

## Upgrade Guide

### Upgrading from 1.x to 2.0

1. **Update PHP** to version 7.4 or higher

2. **Create `.env` file** from the template:
   ```bash
   cp .env.example .env
   ```

3. **Configure environment variables** - especially:
   - `JWT_SECRET` - Generate a new strong secret:
     ```bash
     php -r "echo bin2hex(random_bytes(32));"
     ```
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - Set `APP_DEBUG=false` for production

4. **Update dependencies**:
   ```bash
   composer update
   ```

5. **Run migrations** (if using new phinx.php):
   ```bash
   composer migrate
   ```

6. **Update any custom code** that:
   - Uses `addInfo()`, `addWarning()`, etc. → change to `info()`, `warning()`, etc.
   - Directly accesses config values → use environment variables instead
   - Extends the Category events class → events are now in model boot method
