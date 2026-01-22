<?php

namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monstein\Config\Config;

/**
 * Rate Limiting Middleware
 * 
 * Protects against DDoS attacks and brute force attempts by limiting
 * the number of requests per IP address within a time window.
 * 
 * Supports PHP 7.4 and 8.x
 */
class RateLimitMiddleware
{
    /** @var int Maximum requests allowed in the time window */
    private $maxRequests;

    /** @var int Time window in seconds */
    private $windowSeconds;

    /** @var string Storage directory for rate limit data */
    private $storageDir;

    /** @var array Paths with custom rate limits [path => [max, window]] */
    private $customLimits = [];

    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /**
     * @param array $options Configuration options
     *   - max_requests: Default max requests per window (default: 100)
     *   - window_seconds: Time window in seconds (default: 60)
     *   - storage_dir: Directory to store rate limit data
     *   - custom_limits: Array of path => [max, window] for specific endpoints
     *   - logger: PSR-3 logger instance
     */
    public function __construct(array $options = [])
    {
        $this->maxRequests = $options['max_requests'] ?? 100;
        $this->windowSeconds = $options['window_seconds'] ?? 60;
        $this->storageDir = $options['storage_dir'] ?? sys_get_temp_dir() . '/monstein_ratelimit';
        $this->customLimits = $options['custom_limits'] ?? [];
        $this->logger = $options['logger'] ?? null;

        // Ensure storage directory exists
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        // Clean up old rate limit files periodically (1% chance per request)
        if (mt_rand(1, 100) === 1) {
            $this->cleanup();
        }
    }

    /**
     * Middleware invokable
     * 
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next): Response
    {
        $clientIp = $this->getClientIp($request);
        $path = $request->getUri()->getPath();
        
        // Get rate limit settings for this path
        list($maxRequests, $windowSeconds, $enabled) = $this->getLimitsForPath($path);
        
        // Skip rate limiting if disabled for this route
        if (!$enabled) {
            return $next($request, $response);
        }
        
        // Create unique key for this IP + path combination
        $key = $this->createKey($clientIp, $path);
        
        // Check and update rate limit
        $result = $this->checkRateLimit($key, $maxRequests, $windowSeconds);
        
        if (!$result['allowed']) {
            $this->log('warning', 'Rate limit exceeded', [
                'ip' => $clientIp,
                'path' => $path,
                'requests' => $result['count'],
                'limit' => $maxRequests
            ]);
            
            return $this->rateLimitExceededResponse($response, $result['retry_after']);
        }
        
        // Add rate limit headers to response
        /** @var Response $response */
        $response = $next($request, $response);
        
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $maxRequests - $result['count']))
            ->withHeader('X-RateLimit-Reset', (string) $result['reset_time']);
    }

    /**
     * Get rate limits for a specific path
     * 
     * Uses route-specific configuration from BaseRouter if available,
     * otherwise falls back to custom limits or defaults.
     * 
     * @param string $path
     * @return array [maxRequests, windowSeconds, enabled]
     */
    private function getLimitsForPath(string $path): array
    {
        // First, check BaseRouter for route-specific configuration
        $routeConfig = BaseRouter::getInstance()->getRateLimitForPath($path);
        
        if (!empty($routeConfig)) {
            // Check if rate limiting is disabled for this route
            if (isset($routeConfig['enabled']) && $routeConfig['enabled'] === false) {
                return [PHP_INT_MAX, 1, false]; // Effectively disabled
            }
            
            return [
                $routeConfig['max_requests'] ?? $this->maxRequests,
                $routeConfig['window_seconds'] ?? $this->windowSeconds,
                true
            ];
        }
        
        // Fall back to custom limits passed to constructor
        foreach ($this->customLimits as $pattern => $limits) {
            if ($path === $pattern || strpos($path, rtrim($pattern, '*')) === 0) {
                return [$limits[0], $limits[1], true];
            }
        }
        
        return [$this->maxRequests, $this->windowSeconds, true];
    }

    /**
     * Check if request is within rate limit
     * 
     * @param string $key Unique identifier for this client+path
     * @param int $maxRequests Maximum allowed requests
     * @param int $windowSeconds Time window
     * @return array ['allowed' => bool, 'count' => int, 'reset_time' => int, 'retry_after' => int]
     */
    private function checkRateLimit(string $key, int $maxRequests, int $windowSeconds): array
    {
        $file = $this->storageDir . '/' . $key . '.json';
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        $data = $this->loadData($file);
        
        // Filter out old requests outside the window
        $data['requests'] = array_filter(
            $data['requests'] ?? [],
            function ($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            }
        );
        
        // Re-index array
        $data['requests'] = array_values($data['requests']);
        $count = count($data['requests']);
        
        $resetTime = $now + $windowSeconds;
        if ($count > 0) {
            $resetTime = $data['requests'][0] + $windowSeconds;
        }
        
        if ($count >= $maxRequests) {
            return [
                'allowed' => false,
                'count' => $count,
                'reset_time' => $resetTime,
                'retry_after' => max(1, $resetTime - $now)
            ];
        }
        
        // Add current request
        $data['requests'][] = $now;
        $this->saveData($file, $data);
        
        return [
            'allowed' => true,
            'count' => $count + 1,
            'reset_time' => $resetTime,
            'retry_after' => 0
        ];
    }

    /**
     * Load rate limit data from file
     * 
     * @param string $file
     * @return array
     */
    private function loadData(string $file): array
    {
        if (!file_exists($file)) {
            return ['requests' => []];
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return ['requests' => []];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : ['requests' => []];
    }

    /**
     * Save rate limit data to file
     * 
     * @param string $file
     * @param array $data
     */
    private function saveData(string $file, array $data): void
    {
        $content = json_encode($data);
        @file_put_contents($file, $content, LOCK_EX);
    }

    /**
     * Create a unique key for client + path
     * 
     * @param string $clientIp
     * @param string $path
     * @return string
     */
    private function createKey(string $clientIp, string $path): string
    {
        // Sanitize and hash to create safe filename
        return hash('sha256', $clientIp . '|' . $path);
    }

    /**
     * Get client IP address, handling proxies
     * 
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string
    {
        // Check for trusted proxy headers (configure trusted proxies in production)
        $trustedHeaders = [
            'X-Forwarded-For',
            'X-Real-IP',
            'CF-Connecting-IP', // Cloudflare
        ];
        
        foreach ($trustedHeaders as $header) {
            $value = $request->getHeaderLine($header);
            if (!empty($value)) {
                // X-Forwarded-For can contain multiple IPs, take the first
                $ips = array_map('trim', explode(',', $value));
                $ip = $ips[0];
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fall back to server params
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Create rate limit exceeded response
     * 
     * @param Response $response
     * @param int $retryAfter
     * @return Response
     */
    private function rateLimitExceededResponse(Response $response, int $retryAfter): Response
    {
        $data = [
            'success' => false,
            'errors' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter
        ];
        
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withStatus(429);
    }

    /**
     * Cleanup old rate limit files
     */
    private function cleanup(): void
    {
        $files = glob($this->storageDir . '/*.json');
        $expireTime = time() - 3600; // Remove files older than 1 hour
        
        foreach ($files as $file) {
            if (filemtime($file) < $expireTime) {
                @unlink($file);
            }
        }
    }

    /**
     * Log message if logger available
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[RateLimit] ' . $message, $context);
        }
    }
}
