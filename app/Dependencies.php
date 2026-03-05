<?php
namespace Monstein;

use Monstein\Base\BaseRouter;
use Monstein\Base\HttpCode;
use Monstein\Config\Config;
use Monstein\Helpers\Cache;
use Monstein\Helpers\HttpClient;
use Monstein\Helpers\Encryption;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Dependency injection container configuration
 */
class Dependencies
{
    /** @var \Psr\Container\ContainerInterface */
    private $container;

    /**
     * @param \Slim\App $app
     */
    public function __construct($app)
    {
        $this->container = $app->getContainer();
        $this->dependencies();
        $this->inject($app);
        $this->handlers();
    }

    /**
     * Setup dependency container
     */
    private function dependencies(): void
    {
        // Monolog Logger
        $this->container['logger'] = function ($c) {
            $logConfig = Config::logging();
            $logLevel = $this->getLogLevel($logConfig['level']);
            
            $logger = new Logger('monstein');
            
            // Create logs directory if it doesn't exist
            $logDir = dirname($logConfig['path']);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Use rotating file handler for production (daily rotation)
            if (!Config::isDebug()) {
                $handler = new RotatingFileHandler(
                    $logConfig['path'],
                    30, // Keep 30 days of logs
                    $logLevel
                );
            } else {
                $handler = new StreamHandler($logConfig['path'], $logLevel);
            }
            
            // Custom formatter for better readability
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            );
            $handler->setFormatter($formatter);
            
            $logger->pushHandler($handler);
            
            return $logger;
        };

        // Eloquent ORM Database Connection
        $this->container['db'] = function ($c) {
            $capsule = new \Illuminate\Database\Capsule\Manager();
            $capsule->addConnection($c['settings']['db']);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            
            return $capsule;
        };

        // Validation Service
        $this->container['validator'] = function ($c) {
            return new \Awurth\SlimValidation\Validator();
        };

        // Cache Service
        $this->container['cache'] = function ($c) {
            $cachePath = getenv('CACHE_PATH') ?: __DIR__ . '/../storage/cache';
            return new Cache($cachePath);
        };

        // HTTP Client Service
        $this->container['http'] = function ($c) {
            return new HttpClient([
                'timeout' => (int) (getenv('HTTP_CLIENT_TIMEOUT') ?: 30),
                'verify_ssl' => getenv('HTTP_VERIFY_SSL') !== 'false',
            ]);
        };

        // Encryption Service
        $this->container['encryption'] = function ($c) {
            $key = getenv('APP_KEY') ?: getenv('JWT_SECRET') ?: 'monstein-default-key';
            return new Encryption($key);
        };
    }

    /**
     * Inject dependencies into controllers
     * 
     * @param \Slim\App $app
     */
    private function inject($app): void
    {
        $urls = BaseRouter::getInstance()->getRoutes();
        
        foreach ($urls as $url => $param) {
            $controllerClass = $param['controller'];
            $this->container[$controllerClass] = function ($c) use ($controllerClass) {
                $controller = new $controllerClass(
                    $c->get('logger'),
                    $c->get('db'),
                    $c->get('validator')
                );
                
                // Inject optional helper services if controller accepts them
                if (method_exists($controller, 'setCache')) {
                    $controller->setCache($c->get('cache'));
                }
                if (method_exists($controller, 'setHttp')) {
                    $controller->setHttp($c->get('http'));
                }
                if (method_exists($controller, 'setEncryption')) {
                    $controller->setEncryption($c->get('encryption'));
                }
                
                return $controller;
            };
        }
    }

    /**
     * Setup custom error handlers
     */
    private function handlers(): void
    {
        // 404 Not Found handler
        $this->container['notFoundHandler'] = function ($c) {
            return function ($request, $response) use ($c) {
                $c->get('logger')->warning('Resource not found', [
                    'uri' => (string) $request->getUri(),
                    'method' => $request->getMethod()
                ]);
                
                return $response->withJson(
                    ['success' => false, 'errors' => 'Resource not found'],
                    HttpCode::HTTP_NOT_FOUND
                );
            };
        };

        // 405 Method Not Allowed handler
        $this->container['notAllowedHandler'] = function ($c) {
            return function ($request, $response, $allowedMethods) use ($c) {
                $c->get('logger')->warning('Method not allowed', [
                    'uri' => (string) $request->getUri(),
                    'method' => $request->getMethod(),
                    'allowed' => $allowedMethods
                ]);
                
                return $response
                    ->withHeader('Allow', implode(', ', $allowedMethods))
                    ->withJson(
                        ['success' => false, 'errors' => 'Method not allowed'],
                        HttpCode::HTTP_METHOD_NOT_ALLOWED
                    );
            };
        };

        // PHP Error handler (for PHP 7 errors)
        $this->container['phpErrorHandler'] = function ($c) {
            return function ($request, $response, $error) use ($c) {
                $c->get('logger')->error('PHP Error', [
                    'error' => $error->getMessage(),
                    'file' => $error->getFile(),
                    'line' => $error->getLine()
                ]);
                
                $data = ['success' => false, 'errors' => 'Internal server error'];
                
                // Include error details only in debug mode
                if (Config::isDebug()) {
                    $data['debug'] = [
                        'message' => $error->getMessage(),
                        'file' => $error->getFile(),
                        'line' => $error->getLine()
                    ];
                }
                
                return $response->withJson($data, HttpCode::HTTP_INTERNAL_SERVER_ERROR);
            };
        };

        // Exception handler
        $this->container['errorHandler'] = function ($c) {
            return function ($request, $response, $exception) use ($c) {
                $c->get('logger')->error('Application Error', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ]);
                
                $data = ['success' => false, 'errors' => 'Internal server error'];
                
                // Include error details only in debug mode
                if (Config::isDebug()) {
                    $data['debug'] = [
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine()
                    ];
                }
                
                return $response->withJson($data, HttpCode::HTTP_INTERNAL_SERVER_ERROR);
            };
        };
    }

    /**
     * Convert log level string to Monolog constant
     * 
     * @param string $level
     * @return int
     */
    private function getLogLevel(string $level): int
    {
        $levels = [
            'DEBUG' => Logger::DEBUG,
            'INFO' => Logger::INFO,
            'NOTICE' => Logger::NOTICE,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
            'CRITICAL' => Logger::CRITICAL,
            'ALERT' => Logger::ALERT,
            'EMERGENCY' => Logger::EMERGENCY,
        ];
        
        return $levels[strtoupper($level)] ?? Logger::INFO;
    }
}
