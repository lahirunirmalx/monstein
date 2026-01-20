<?php
namespace Monstein\Config;

/**
 * Configuration class for Monstein API
 * 
 * All sensitive configuration values are loaded from environment variables.
 * See .env.example for required environment variables.
 */
class Config
{
    /**
     * Get database configuration
     * 
     * @return array Database connection settings
     */
    public static function db(): array
    {
        $driver = self::env('DB_DRIVER', 'mysql');
        
        // SQLite configuration
        if ($driver === 'sqlite') {
            $dbPath = self::env('DB_NAME', 'database/monstein.sqlite');
            // Make path absolute if relative
            if ($dbPath && $dbPath[0] !== '/') {
                $dbPath = dirname(__DIR__, 2) . '/' . $dbPath;
            }
            return [
                'driver'   => 'sqlite',
                'database' => $dbPath,
                'prefix'   => self::env('DB_PREFIX', ''),
            ];
        }
        
        // MySQL/PostgreSQL configuration
        return [
            'driver'    => $driver,
            'host'      => self::env('DB_HOST', 'localhost'),
            'port'      => self::env('DB_PORT', '3306'),
            'database'  => self::env('DB_NAME', ''),
            'username'  => self::env('DB_USER', ''),
            'password'  => self::env('DB_PASS', ''),
            'charset'   => self::env('DB_CHARSET', 'utf8mb4'),
            'collation' => self::env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => self::env('DB_PREFIX', ''),
        ];
    }

    /**
     * Get Slim framework configuration
     * 
     * @return array Slim settings
     */
    public static function slim(): array
    {
        return [
            'settings' => [
                'determineRouteBeforeAppMiddleware' => false,
                'displayErrorDetails' => self::isDebug(),
                'addContentLengthHeader' => false,
                'db' => self::db()
            ],
        ];
    }

    /**
     * Get authentication configuration
     * 
     * @return array Authentication settings
     */
    public static function auth(): array
    {
        $secret = self::env('JWT_SECRET', '');
        
        // Ensure JWT secret is configured in production
        if (empty($secret) && !self::isDebug()) {
            throw new \RuntimeException('JWT_SECRET environment variable must be set in production');
        }
        
        // Use a default only in debug mode (for development)
        if (empty($secret)) {
            $secret = 'dev-only-secret-change-in-production';
        }

        return [
            'secret'  => $secret,
            'expires' => (int) self::env('JWT_EXPIRES', '30'), // in minutes
            'hash'    => PASSWORD_DEFAULT,
            'jwt'     => self::env('JWT_ALGORITHM', 'HS256')
        ];
    }

    /**
     * Get CORS configuration
     * 
     * @return array CORS settings
     */
    public static function cors(): array
    {
        return [
            'origin'  => self::env('CORS_ORIGIN', '*'),
            'headers' => self::env('CORS_HEADERS', 'X-Requested-With, Content-Type, Accept, Origin, Authorization'),
            'methods' => self::env('CORS_METHODS', 'GET, POST, PUT, DELETE, PATCH, OPTIONS'),
        ];
    }

    /**
     * Check if application is in debug mode
     * 
     * @return bool
     */
    public static function isDebug(): bool
    {
        $debug = self::env('APP_DEBUG', 'false');
        return filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get application environment
     * 
     * @return string
     */
    public static function environment(): string
    {
        return self::env('APP_ENV', 'production');
    }

    /**
     * Get logging configuration
     * 
     * @return array Logging settings
     */
    public static function logging(): array
    {
        return [
            'path'  => self::env('LOG_PATH', dirname(__DIR__, 2) . '/logs/app.log'),
            'level' => self::env('LOG_LEVEL', 'INFO'),
        ];
    }

    /**
     * Safely get environment variable with fallback
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not set
     * @return mixed
     */
    private static function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        
        return $value;
    }
}
