<?php
namespace Monstein;

use Monstein\Base\BaseRouter;
use Monstein\Base\JwtMiddleware;
use Monstein\Base\RateLimitMiddleware;
use Monstein\Config\Config;

/**
 * Middleware configuration for the Monstein application
 * 
 * Implements comprehensive security measures:
 * - Rate limiting (DDoS/brute-force protection)
 * - Security headers (XSS, clickjacking, MITM protection)
 * - CORS configuration
 * - JWT authentication
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
        
        // Order matters: rate limiting first, then security headers, CORS, and JWT
        $this->rateLimit();
        $this->securityHeaders();
        $this->cors();
        $this->jwt();
    }

    /**
     * Configure rate limiting to prevent DDoS and brute-force attacks
     */
    private function rateLimit(): void
    {
        $ignorePaths = array_keys(BaseRouter::getInstance()->getIgnorePaths());
        
        // Stricter limits for authentication endpoints
        $customLimits = [];
        foreach ($ignorePaths as $path) {
            // Login/token endpoints: 10 requests per minute (brute-force protection)
            if (strpos($path, 'token') !== false || strpos($path, 'login') !== false) {
                $customLimits[$path] = [10, 60];
            } else {
                // Other public endpoints: 30 requests per minute
                $customLimits[$path] = [30, 60];
            }
        }
        
        $this->app->add(new RateLimitMiddleware([
            'max_requests' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 100),
            'window_seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
            'storage_dir' => dirname(__DIR__) . '/storage/ratelimit',
            'custom_limits' => $customLimits,
            'logger' => $this->container['logger'],
        ]));
    }

    /**
     * Add comprehensive security headers to all responses
     * 
     * Protects against:
     * - XSS attacks (Content-Security-Policy, X-XSS-Protection)
     * - Clickjacking (X-Frame-Options, frame-ancestors)
     * - MIME sniffing (X-Content-Type-Options)
     * - Man-in-the-middle attacks (Strict-Transport-Security)
     * - Information leakage (Referrer-Policy, Permissions-Policy)
     */
    private function securityHeaders(): void
    {
        $isDebug = Config::isDebug();
        
        $this->app->add(function ($req, $res, $next) use ($isDebug) {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $next($req, $res);
            
            // XSS Protection Headers
            $response = $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('X-Frame-Options', 'DENY')
                ->withHeader('X-XSS-Protection', '1; mode=block');
            
            // Content Security Policy - strict for API
            // Prevents inline scripts and restricts all resources
            $csp = implode('; ', [
                "default-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'none'",
                "form-action 'none'",
                "upgrade-insecure-requests"
            ]);
            $response = $response->withHeader('Content-Security-Policy', $csp);
            
            // Privacy and Information Leakage Protection
            $response = $response
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=(), usb=()')
                ->withHeader('X-Permitted-Cross-Domain-Policies', 'none');
            
            // Cache Control - prevent caching of sensitive data
            $response = $response
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0');
            
            // MITM Protection - HSTS (only in production with HTTPS)
            if (!$isDebug) {
                // Strict-Transport-Security: enforces HTTPS for 1 year
                // includeSubDomains: applies to all subdomains
                // preload: allows inclusion in browser preload lists
                $response = $response->withHeader(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains; preload'
                );
                
                // Expect-CT: Certificate Transparency (deprecated but still useful)
                $response = $response->withHeader(
                    'Expect-CT',
                    'max-age=86400, enforce'
                );
            }
            
            return $response;
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
