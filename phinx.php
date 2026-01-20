<?php
/**
 * Phinx Configuration
 * 
 * This file loads database configuration from environment variables.
 */

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$driver = $_ENV['DB_DRIVER'] ?? 'mysql';

// Build environment configuration based on driver
$buildEnvConfig = function($suffix = '') use ($driver) {
    if ($driver === 'sqlite') {
        $dbPath = $_ENV['DB_NAME'] ?? 'database/monstein.sqlite';
        if ($suffix) {
            $dbPath = str_replace('.sqlite', $suffix . '.sqlite', $dbPath);
        }
        if ($dbPath && $dbPath[0] !== '/') {
            $dbPath = __DIR__ . '/' . $dbPath;
        }
        return [
            'adapter' => 'sqlite',
            'name' => $dbPath,
        ];
    }
    
    return [
        'adapter' => $driver,
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'name' => ($_ENV['DB_NAME'] ?? '') . $suffix,
        'user' => $_ENV['DB_USER'] ?? '',
        'pass' => $_ENV['DB_PASS'] ?? '',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ];
};

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_database' => $_ENV['APP_ENV'] ?? 'development',
        'production' => $buildEnvConfig(),
        'development' => $buildEnvConfig(),
        'testing' => $buildEnvConfig('_test'),
    ],

    'version_order' => 'creation',
];
