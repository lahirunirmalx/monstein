<?php
namespace Monstein\Base;

/**
 * Router singleton that manages route configuration
 * 
 * Supports:
 * - Rate limiting configuration per route
 * - Parameter validation rules
 * - Secure/public route designation
 */
class BaseRouter
{
    /** @var array Singleton instances */
    private static $instances = [];
    
    /** @var array Route configurations */
    private static $routes = [];
    
    /** @var array Non-secure (public) paths */
    private static $nonSecure = [];
    
    /** @var array Rate limit configurations per path */
    private static $rateLimits = [];
    
    /** @var array Parameter validation rules per path */
    private static $paramRules = [];

    /** @var array Default rate limits */
    private static $defaultLimits = [
        'secure' => ['max_requests' => 100, 'window_seconds' => 60],
        'public' => ['max_requests' => 30, 'window_seconds' => 60],
    ];

    protected function __construct() { }

    protected function __clone() { }

    /**
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    /**
     * Initialize routing from configuration
     * 
     * @param array $routes Route configuration array
     */
    public function initRouting(array $routes): void
    {
        foreach ($routes as $key => $route) {
            $url = '';
            $isSecure = true;
            
            // Version prefix
            if (isset($route['version'])) {
                $version = $route['version'];
                $url .= "/V{$version}";
            }
            
            $url .= $route['url'];
            
            // Security setting
            if (isset($route['is_secure'])) {
                $isSecure = boolval($route['is_secure']);
            }
            
            if ($isSecure === false) {
                self::$nonSecure[$url] = $url;
            }
            
            // Parse rate limit configuration
            $this->parseRateLimitConfig($url, $route, $isSecure);
            
            // Parse parameter validation rules
            $this->parseParamRules($url, $route);
            
            $method = $this->getMethods($route['method']);
            $service = $route['service'] ?? 'handle';
            $controller = $route['controller'];
            
            if (!empty($url) && is_array($method) && !empty($controller)) {
                self::$routes[$url] = [
                    'method' => $method,
                    'controller' => $controller,
                    'service' => $service,
                    'is_secure' => $isSecure,
                    'params' => $route['params'] ?? [],
                ];
            }
        }
    }

    /**
     * Parse rate limit configuration for a route
     * 
     * @param string $url Route URL
     * @param array $route Route configuration
     * @param bool $isSecure Whether route is secure
     */
    private function parseRateLimitConfig(string $url, array $route, bool $isSecure): void
    {
        // Get default limits based on security
        $defaults = $isSecure ? self::$defaultLimits['secure'] : self::$defaultLimits['public'];
        
        // Check if rate limiting is configured
        if (isset($route['rate_limit'])) {
            $config = $route['rate_limit'];
            
            // Check if rate limiting is explicitly disabled
            if (isset($config['enabled']) && $config['enabled'] === false) {
                self::$rateLimits[$url] = ['enabled' => false];
                return;
            }
            
            // Merge with defaults
            self::$rateLimits[$url] = [
                'enabled' => $config['enabled'] ?? true,
                'max_requests' => $config['max_requests'] ?? $defaults['max_requests'],
                'window_seconds' => $config['window_seconds'] ?? $defaults['window_seconds'],
            ];
        } else {
            // Use defaults
            self::$rateLimits[$url] = [
                'enabled' => true,
                'max_requests' => $defaults['max_requests'],
                'window_seconds' => $defaults['window_seconds'],
            ];
        }
    }

    /**
     * Parse parameter validation rules for a route
     * 
     * @param string $url Route URL
     * @param array $route Route configuration
     */
    private function parseParamRules(string $url, array $route): void
    {
        if (isset($route['params']) && is_array($route['params'])) {
            self::$paramRules[$url] = $route['params'];
        }
    }

    /**
     * Get all routes
     * 
     * @return array
     */
    public function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Get non-secure (public) paths
     * 
     * @return array
     */
    public function getIgnorePaths(): array
    {
        return self::$nonSecure;
    }

    /**
     * Get rate limit configuration for a path
     * 
     * @param string $path Request path
     * @return array Rate limit config or defaults
     */
    public function getRateLimitForPath(string $path): array
    {
        // Try exact match first
        if (isset(self::$rateLimits[$path])) {
            return self::$rateLimits[$path];
        }
        
        // Try pattern matching for paths with parameters
        foreach (self::$rateLimits as $pattern => $config) {
            if ($this->pathMatchesPattern($path, $pattern)) {
                return $config;
            }
        }
        
        // Return default
        return [
            'enabled' => true,
            'max_requests' => self::$defaultLimits['secure']['max_requests'],
            'window_seconds' => self::$defaultLimits['secure']['window_seconds'],
        ];
    }

    /**
     * Get parameter validation rules for a path
     * 
     * @param string $path Request path
     * @return array Parameter rules
     */
    public function getParamRulesForPath(string $path): array
    {
        // Try exact match first
        if (isset(self::$paramRules[$path])) {
            return self::$paramRules[$path];
        }
        
        // Try pattern matching
        foreach (self::$paramRules as $pattern => $rules) {
            if ($this->pathMatchesPattern($path, $pattern)) {
                return $rules;
            }
        }
        
        return [];
    }

    /**
     * Get all rate limit configurations
     * 
     * @return array
     */
    public function getAllRateLimits(): array
    {
        return self::$rateLimits;
    }

    /**
     * Check if a path matches a route pattern
     * 
     * @param string $path Actual request path (e.g., /todo/123)
     * @param string $pattern Route pattern (e.g., /todo/{id})
     * @return bool
     */
    private function pathMatchesPattern(string $path, string $pattern): bool
    {
        // Convert route pattern to regex
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        $regex = '#^' . $regex . '$#';
        
        return (bool) preg_match($regex, $path);
    }

    /**
     * Parse HTTP methods from configuration
     * 
     * @param array $methods Method list
     * @return array Normalized method list
     */
    protected function getMethods(array $methods): array
    {
        $restMethods = [];
        foreach ($methods as $method) {
            $method = strtoupper(trim($method));
            $restMethods[$method] = 1;
        }
        
        if (empty($restMethods)) {
            $restMethods = ['GET' => 1];
        }
        
        return array_keys($restMethods);
    }

    /**
     * Set default rate limits
     * 
     * @param string $type 'secure' or 'public'
     * @param int $maxRequests Max requests per window
     * @param int $windowSeconds Time window in seconds
     */
    public static function setDefaultLimits(string $type, int $maxRequests, int $windowSeconds): void
    {
        if (isset(self::$defaultLimits[$type])) {
            self::$defaultLimits[$type] = [
                'max_requests' => $maxRequests,
                'window_seconds' => $windowSeconds,
            ];
        }
    }
}
