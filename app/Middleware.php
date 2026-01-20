<?php
namespace Monstein;

use Monstein\Base\BaseRouter;
use Monstein\Base\JwtMiddleware;
use Monstein\Config\Config;

/**
 * Middleware configuration for the Monstein application
 */
class Middleware
{
    /** @var \Slim\App */
    private $app;
    
    /** @var \Psr\Container\ContainerInterface */
    private $container;

    /**
     * @param \Slim\App $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->container = $app->getContainer();
        
        $this->securityHeaders();
        $this->cors();
        $this->jwt();
    }

    /**
     * Add security headers to all responses
     */
    private function securityHeaders(): void
    {
        $this->app->add(function ($req, $res, $next) {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $next($req, $res);
            
            return $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('X-Frame-Options', 'DENY')
                ->withHeader('X-XSS-Protection', '1; mode=block')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        });
    }

    /**
     * Configure CORS headers
     */
    private function cors(): void
    {
        $corsConfig = Config::cors();
        
        $this->app->add(function ($req, $res, $next) use ($corsConfig) {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $next($req, $res);
            
            return $response
                ->withHeader('Access-Control-Allow-Origin', $corsConfig['origin'])
                ->withHeader('Access-Control-Allow-Headers', $corsConfig['headers'])
                ->withHeader('Access-Control-Allow-Methods', $corsConfig['methods'])
                ->withHeader('Access-Control-Max-Age', '86400');
        });
    }

    /**
     * Configure JWT Authentication middleware
     */
    private function jwt(): void
    {
        // JWT middleware callbacks are dependent on DB - ensure Eloquent is initialized
        $this->container->get('db');
        
        // Use custom JWT middleware for firebase/php-jwt 6.x compatibility
        $this->app->add(new JwtMiddleware([
            'ignore' => array_keys(BaseRouter::getInstance()->getIgnorePaths()),
            'logger' => $this->container['logger'],
            'secure' => !Config::isDebug(),
            'relaxed' => ['localhost', '127.0.0.1'],
        ]));
    }
}
