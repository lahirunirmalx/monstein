<?php
/**
 * Monstein API Entry Point
 * 
 * This file serves as the single entry point for all API requests.
 */

use Symfony\Component\Yaml\Yaml;

// Load Composer autoloader
require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

// Validate required environment variables based on driver
$dbDriver = $_ENV['DB_DRIVER'] ?? 'mysql';
if ($dbDriver === 'sqlite') {
    $dotenv->required(['DB_NAME'])->notEmpty();
} else {
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER'])->notEmpty();
}

// In production, require JWT_SECRET
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    $dotenv->required(['JWT_SECRET'])->notEmpty();
}

try {
    // Load routing configuration
    $routingPath = dirname(__DIR__, 2) . '/app/Config/routing.yml';
    if (!file_exists($routingPath)) {
        throw new RuntimeException('Routing configuration file not found');
    }
    
    $routes = Yaml::parseFile($routingPath);
    \Monstein\Base\BaseRouter::getInstance()->initRouting($routes);

    // Initialize and run the application
    $app = (new \Monstein\App())->get();
    $app->run();

} catch (Throwable $e) {
    // Log the error
    error_log(sprintf(
        '[%s] %s in %s:%d',
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    // Return a generic error response
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'errors' => 'Internal server error'];
    
    // Include details only in debug mode
    if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $response['debug'] = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    echo json_encode($response);
    exit(1);
}
