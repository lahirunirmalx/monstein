#!/bin/sh
set -e

echo "=========================================="
echo "  Monstein API - Container Starting"
echo "=========================================="

# Wait for database to be ready using PHP (no mysql-client needed)
wait_for_db() {
    echo "Waiting for database connection..."
    
    DB_HOST="${DB_HOST:-db}"
    DB_PORT="${DB_PORT:-3306}"
    DB_USER="${DB_USER:-monstein}"
    DB_PASS="${DB_PASS:-}"
    DB_NAME="${DB_NAME:-monstein}"
    
    max_attempts=30
    attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        # Use PHP to test database connection (PDO is available)
        if php -r "
            try {
                \$dsn = 'mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME';
                new PDO(\$dsn, '$DB_USER', '$DB_PASS', [PDO::ATTR_TIMEOUT => 5]);
                exit(0);
            } catch (Exception \$e) {
                exit(1);
            }
        " 2>/dev/null; then
            echo "✓ Database connection established"
            return 0
        fi
        
        attempt=$((attempt + 1))
        echo "  Attempt $attempt/$max_attempts - waiting for database..."
        sleep 2
    done
    
    echo "✗ Could not connect to database after $max_attempts attempts"
    return 1
}

# Run database migrations
run_migrations() {
    echo "Running database migrations..."
    
    cd /app
    
    if [ -f "./vendor/bin/phinx" ]; then
        ./vendor/bin/phinx migrate -e "${APP_ENV:-production}" || {
            echo "⚠ Migration failed or already up to date"
        }
        echo "✓ Migrations complete"
    else
        echo "⚠ Phinx not found, skipping migrations"
    fi
}

# Create .env from environment variables if not exists
create_env_file() {
    if [ ! -f /app/.env ]; then
        echo "Creating .env file from environment variables..."
        
        cat > /app/.env << EOF
# Auto-generated from Docker environment
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}

# Database
DB_DRIVER=${DB_DRIVER:-mysql}
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-monstein}
DB_USER=${DB_USER:-monstein}
DB_PASS=${DB_PASS:-}
DB_CHARSET=${DB_CHARSET:-utf8mb4}

# JWT
JWT_SECRET=${JWT_SECRET:-}
JWT_EXPIRES=${JWT_EXPIRES:-30}
JWT_ALGORITHM=${JWT_ALGORITHM:-HS256}

# CORS
CORS_ORIGIN=${CORS_ORIGIN:-*}

# Logging
LOG_PATH=${LOG_PATH:-/app/logs/app.log}
LOG_LEVEL=${LOG_LEVEL:-INFO}

# Rate Limiting
RATE_LIMIT_MAX=${RATE_LIMIT_MAX:-100}
RATE_LIMIT_WINDOW=${RATE_LIMIT_WINDOW:-60}
EOF
        
        chown monstein:monstein /app/.env
        echo "✓ .env file created"
    else
        echo "✓ Using existing .env file"
    fi
}

# Ensure directories have proper permissions
fix_permissions() {
    echo "Setting directory permissions..."
    
    chown -R monstein:monstein /app/logs /app/storage 2>/dev/null || true
    chmod -R 755 /app/logs /app/storage 2>/dev/null || true
    
    echo "✓ Permissions set"
}

# Validate required environment variables
validate_env() {
    echo "Validating environment..."
    
    if [ -z "$JWT_SECRET" ] && [ "${APP_ENV:-production}" = "production" ]; then
        echo "✗ ERROR: JWT_SECRET must be set in production!"
        exit 1
    fi
    
    if [ -z "$JWT_SECRET" ]; then
        export JWT_SECRET="dev-only-secret-$(date +%s)"
        echo "⚠ Using auto-generated JWT_SECRET (development only)"
    fi
    
    echo "✓ Environment validated"
}

# Main execution
main() {
    validate_env
    create_env_file
    fix_permissions
    
    # Only wait for DB if not using SQLite
    if [ "${DB_DRIVER:-mysql}" != "sqlite" ]; then
        wait_for_db
        run_migrations
    fi
    
    echo ""
    echo "=========================================="
    echo "  Monstein API Ready!"
    echo "  Environment: ${APP_ENV:-production}"
    echo "  Debug: ${APP_DEBUG:-false}"
    echo "=========================================="
    echo ""
    
    # Execute the main command
    exec "$@"
}

main "$@"
