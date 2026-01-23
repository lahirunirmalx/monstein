<?php
namespace Monstein;

use Monstein\Base\BaseRouter;
use Monstein\Base\JwtMiddleware;
use Monstein\Base\RateLimitMiddleware;
use Monstein\Base\ParamValidationMiddleware;
use Monstein\Base\FileUploadMiddleware;
use Monstein\Config\Config;

/**
 * Middleware configuration for the Monstein application
 * 
 * Implements comprehensive security measures:
 * - Rate limiting (DDoS/brute-force protection) - configurable per route
 * - Parameter validation using Respect Validation
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
        
        // Order matters: rate limiting first, then param validation, security headers, CORS, JWT, and file upload
        $this->rateLimit();
        $this->paramValidation();
        $this->securityHeaders();
        $this->cors();
        $this->jwt();
        $this->fileUpload();
    }

    /**
     * Configure rate limiting to prevent DDoS and brute-force attacks
     * 
     * Rate limits are configured per-route in routing.yml:
     *   rate_limit:
     *     enabled: true/false
     *     max_requests: 10
     *     window_seconds: 60
     * 
     * If not specified, defaults from BaseRouter are used:
     *   - Secure routes: 100 requests/minute
     *   - Public routes: 30 requests/minute
     */
    private function rateLimit(): void
    {
        // Rate limits are now configured per-route in routing.yml
        // RateLimitMiddleware reads config from BaseRouter
        $this->app->add(new RateLimitMiddleware([
            'max_requests' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 100),
            'window_seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
            'storage_dir' => dirname(__DIR__) . '/storage/ratelimit',
            'logger' => $this->container['logger'],
        ]));
    }

    /**
     * Configure parameter validation middleware
     * 
     * Validates route parameters (like {id}) using Respect Validation.
     * Rules are configured per-route in routing.yml:
     *   params:
     *     id: id
     *     slug: slug
     */
    private function paramValidation(): void
    {
        $this->app->add(new ParamValidationMiddleware([
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

    /**
     * Configure File Upload middleware
     * 
     * Processes multipart form data and base64 encoded files for routes
     * with file_upload configuration in routing.yml.
     */
    private function fileUpload(): void
    {
        $this->app->add(new FileUploadMiddleware([
            'logger' => $this->container['logger'],
        ]));
    }
}
