# Monstein API Framework

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://php.net)

A lightweight, secure RESTful API framework built on Slim 3 with JWT authentication and Eloquent ORM. Designed for building scalable todo/task management APIs with user authentication.

## Features

- 🔐 **JWT Authentication** - Secure token-based authentication
- 🗄️ **Eloquent ORM** - Laravel's powerful database abstraction
- ✅ **Input Validation** - Built-in request validation with Respect/Validation
- 📝 **Structured Logging** - Monolog integration with rotating log files
- 🔄 **Database Migrations** - Phinx migrations for version-controlled schema changes
- 🛡️ **Security Headers** - XSS, CSRF, and clickjacking protection
- 🌐 **CORS Support** - Configurable Cross-Origin Resource Sharing
- 🧪 **Test Suite** - PHPUnit tests included
- 🛠️ **CLI Dev Tools** - Scaffolding for rapid resource/endpoint creation

## Requirements

- PHP 7.4, 8.0, 8.1, 8.2, or 8.3
- MySQL 5.7+ or MariaDB 10.2+
- Composer 2.x

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/lahirunirmalx/monstein.git
cd monstein
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and configure your settings:

```env
# Application
APP_ENV=production
APP_DEBUG=false

# Database
DB_HOST=localhost
DB_NAME=monstein
DB_USER=your_username
DB_PASS=your_password

# JWT Secret (IMPORTANT: Generate a strong secret!)
# Generate with: php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=your-super-secret-key-here
JWT_EXPIRES=30

# CORS (restrict in production)
CORS_ORIGIN=https://yourapp.com
```

### 4. Run database migrations

```bash
composer migrate
```

### 5. Configure your web server

Point your web server to the `symfony/web` directory.

**Apache (.htaccess included):**

```apache
<VirtualHost *:80>
    DocumentRoot /path/to/monstein/symfony/web
    <Directory /path/to/monstein/symfony/web>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**

```nginx
server {
    listen 80;
    root /path/to/monstein/symfony/web;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## API Endpoints

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/issueToken` | Login and get JWT token | No |

### Categories

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/categories` | List all categories | Yes |
| POST | `/categories` | Create a category | Yes |
| GET | `/category/{id}` | Get single category | Yes |
| PUT | `/category/{id}` | Update a category | Yes |
| DELETE | `/category/{id}` | Delete a category | Yes |
| GET | `/category/{id}/todos` | Get todos in category | Yes |

### Todos

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/todo` | List all todos | Yes |
| POST | `/todo` | Create a todo | Yes |
| GET | `/todo/{id}` | Get single todo | Yes |
| PUT | `/todo/{id}` | Update a todo | Yes |
| DELETE | `/todo/{id}` | Delete a todo | Yes |

## Usage Examples

### Login

```bash
curl -X POST http://localhost/issueToken \
  -H "Content-Type: application/json" \
  -d '{"username": "user", "password": "pass"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires": 1706234567
  }
}
```

### Create Category (Authenticated)

```bash
curl -X POST http://localhost/categories \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Work"}'
```

### Create Todo (Authenticated)

```bash
curl -X POST http://localhost/todo \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Complete project", "category": 1}'
```

## Development

### Running Tests

```bash
# Create test database
mysql -u root -e "CREATE DATABASE monstein_test;"

# Run migrations on test database
APP_ENV=testing composer migrate

# Run tests
composer test
```

### Code Structure

```
monstein/
├── app/
│   ├── Base/               # Base classes (controllers, router)
│   ├── Config/             # Configuration
│   ├── Controllers/        # API controllers
│   └── Models/             # Eloquent models
├── bin/
│   └── monstein            # CLI development tools
├── db/
│   └── migrations/         # Phinx migrations
├── logs/                   # Application logs
├── stubs/                  # Code generation templates
├── symfony/
│   └── web/               # Public web root
├── tests/                 # PHPUnit tests
├── .env.example           # Environment template
├── composer.json          # Dependencies
├── phinx.php             # Migration config
└── phpunit.xml.dist      # Test config
```

## Developer Tools (CLI)

Monstein includes a powerful CLI tool for scaffolding new API resources quickly. This helps maintain consistent code structure and speeds up development.

### Installation

The CLI tool is included in the `bin/` directory and is ready to use:

```bash
./bin/monstein --help
```

### Available Commands

| Command | Description |
|---------|-------------|
| `make:resource <Name>` | Create complete resource (model, controllers, migration) |
| `make:entity <Name>` | Create a new Eloquent model |
| `make:controller <Name>` | Create collection and entity controllers |
| `make:migration <Name>` | Create a new Phinx migration |

### Creating a New Resource (Recommended)

The `make:resource` command generates everything you need for a new API endpoint:

```bash
./bin/monstein make:resource Project
```

**Output:**
```
Creating resource: Project
--------------------------------------------------
✓ Created Model: app/Models/Project.php
✓ Created Controller: app/Controllers/ProjectCollectionController.php
✓ Created Controller: app/Controllers/ProjectEntityController.php
✓ Created Migration: db/migrations/20260120_create_projects_table.php
--------------------------------------------------
→ Add the following to app/Config/routing.yml:

projects:
  url: /projects
  controller: \Monstein\Controllers\ProjectCollectionController
  method: [ post, get ]

project:
  url: /project/{id}
  controller: \Monstein\Controllers\ProjectEntityController
  method: [ put, get, delete ]

→ Run migration with: ./vendor/bin/phinx migrate
```

### Step-by-Step: Adding a New "Project" Resource

#### 1. Generate the Resource

```bash
./bin/monstein make:resource Project
```

#### 2. Customize the Model (`app/Models/Project.php`)

```php
class Project extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',  // Add custom fields
        'status',
    ];

    // Add relationship to User model
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    // Add relationship to todos (optional)
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class, 'project_id');
    }
}
```

#### 3. Add Relationship to User Model (`app/Models/User.php`)

```php
public function projects(): HasMany
{
    return $this->hasMany(Project::class, 'user_id');
}
```

#### 4. Customize the Migration (`db/migrations/xxx_create_projects_table.php`)

```php
public function change(): void
{
    $table = $this->table('projects');
    
    $table
        ->addColumn('user_id', 'integer', ['signed' => false])
        ->addColumn('name', 'string', ['limit' => 255])
        ->addColumn('description', 'text', ['null' => true])
        ->addColumn('status', 'enum', [
            'values' => ['active', 'completed', 'archived'],
            'default' => 'active'
        ])
        ->addColumn('created_at', 'datetime', ['null' => true])
        ->addColumn('updated_at', 'datetime', ['null' => true])
        ->addColumn('deleted_at', 'datetime', ['null' => true])
        ->addIndex(['user_id'])
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
        ->create();
}
```

#### 5. Add Routes (`app/Config/routing.yml`)

```yaml
projects:
  url: /projects
  controller: \Monstein\Controllers\ProjectCollectionController
  method: [ post, get ]

project:
  url: /project/{id}
  controller: \Monstein\Controllers\ProjectEntityController
  method: [ put, get, delete ]
```

#### 6. Customize Validation in Controllers

**Collection Controller (`ProjectCollectionController.php`):**

```php
public function validatePostRequest(): array
{
    return [
        'name' => V::notEmpty()->length(1, 255),
        'description' => [
            'rules' => V::optional(V::length(0, 1000)),
            'message' => 'Description too long'
        ],
        'status' => [
            'rules' => V::optional(V::in(['active', 'completed', 'archived'])),
            'message' => 'Invalid status'
        ],
    ];
}
```

#### 7. Run Migration

```bash
./vendor/bin/phinx migrate
```

#### 8. Test Your New Endpoints

```bash
# Create a project
curl -X POST http://localhost/projects \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "My Project", "description": "A great project"}'

# List projects
curl http://localhost/projects \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get single project
curl http://localhost/project/1 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update project
curl -X PUT http://localhost/project/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "completed"}'

# Delete project
curl -X DELETE http://localhost/project/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Naming Conventions

| Input | Model Class | Table Name | Route (collection) | Route (entity) |
|-------|-------------|------------|-------------------|----------------|
| `Project` | `Project` | `projects` | `/projects` | `/project/{id}` |
| `TaskItem` | `TaskItem` | `task_items` | `/taskitems` | `/taskitem/{id}` |
| `Category` | `Category` | `categories` | `/categories` | `/category/{id}` |

### Customizing Stubs

You can customize the code generation templates in the `stubs/` directory:

| File | Description |
|------|-------------|
| `stubs/model.stub` | Eloquent model template |
| `stubs/collection-controller.stub` | Collection controller (list, create) |
| `stubs/entity-controller.stub` | Entity controller (get, update, delete) |
| `stubs/migration.stub` | Phinx migration template |

### Example: Custom Model Stub

Edit `stubs/model.stub` to add your standard fields:

```php
protected $fillable = [
    'user_id',
    'name',
    'description',    // Added by default
    'status',         // Added by default
];

protected $casts = [
    'is_active' => 'boolean',
    'metadata' => 'array',
];
```

## Security Features

### Implemented Security Measures

- ✅ **JWT Token Authentication** - Stateless, secure token-based auth
- ✅ **Password Hashing** - bcrypt with PHP's PASSWORD_DEFAULT
- ✅ **Input Validation** - All inputs validated before processing
- ✅ **SQL Injection Protection** - Eloquent ORM with parameterized queries
- ✅ **Security Headers** - X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
- ✅ **Environment-based Config** - No hardcoded secrets
- ✅ **Error Handling** - Detailed errors only in debug mode
- ✅ **HTTPS Enforcement** - Automatic in production mode

### Security Best Practices

1. **Always use HTTPS in production**
2. **Generate a strong JWT_SECRET**: `php -r "echo bin2hex(random_bytes(32));"`
3. **Set `APP_DEBUG=false` in production**
4. **Restrict CORS_ORIGIN to your domain**
5. **Regularly update dependencies**: `composer update`
6. **Monitor logs for suspicious activity**

## Version History

### v2.0.0 (2026)

**PHP 8.x Compatibility & Security Update**

#### Breaking Changes
- Minimum PHP version: 7.4 (was 7.1)
- Environment variables now required for configuration
- Phinx config moved from YAML to PHP

#### New Features
- Full PHP 8.0, 8.1, 8.2, and 8.3 support
- Security headers (XSS, clickjacking, CSRF protection)
- Rotating log files with daily rotation
- Configurable CORS settings
- Environment-based configuration
- Improved error handling with debug mode

#### Security Fixes
- **CRITICAL**: Removed hardcoded JWT secret
- **CRITICAL**: Disabled error details in production
- **HIGH**: Added security headers to all responses
- **MEDIUM**: Removed credentials from config files
- **MEDIUM**: Added HTTPS enforcement in production

#### Dependency Updates
- slim/slim: ^3.12 (PHP 8 compatible)
- illuminate/database: ^8.0 || ^9.0 || ^10.0
- monolog/monolog: ^2.0 || ^3.0
- firebase/php-jwt: ^6.0
- phpunit/phpunit: ^9.5 || ^10.0
- robmorgan/phinx: ^0.13 || ^0.14
- symfony/yaml: ^5.0 || ^6.0

#### Bug Fixes
- Fixed Monolog deprecated method calls (addInfo → info)
- Fixed JWT encoding for firebase/php-jwt v6
- Fixed test namespaces and PHPUnit compatibility
- Fixed Category cascade delete events

## CI/CD Pipeline

This project includes GitHub Actions for continuous integration.

### Workflow: `.github/workflows/php.yml`

The CI pipeline runs on:
- Push to `dev`, `main`, or `master` branches
- Pull requests to these branches

### Jobs

| Job | Description |
|-----|-------------|
| **build** | Tests against PHP 7.4, 8.0, 8.1, 8.2, 8.3 with MySQL |
| **code-quality** | Checks PHP syntax and code structure |
| **security-check** | Audits dependencies and checks for hardcoded secrets |

### Build Matrix

```yaml
php-version: ['7.4', '8.0', '8.1', '8.2', '8.3']
```

### Running Locally

To simulate the CI environment locally:

```bash
# Syntax check
find app -name "*.php" -print0 | xargs -0 -n1 php -l

# Security audit
composer audit

# Run tests
composer test
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Pull Request Guidelines

- All tests must pass in the CI pipeline
- Code must work on PHP 7.4+ and 8.x
- Follow existing code style and conventions
- Update documentation if adding new features

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **Lahiru** - [lahirunirmalx@gmail.com](mailto:lahirunirmalx@gmail.com)
