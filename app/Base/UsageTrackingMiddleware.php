<?php

namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Usage Tracking Middleware
 * 
 * Automatically tracks API endpoint usage for routes configured in routing.yml.
 * 
 * Configuration in routing.yml:
 *   todos:
 *     url: /todo
 *     controller: \Monstein\Controllers\TodoCollectionController
 *     method: [post, get]
 *     tracking:
 *       enabled: true           # Enable tracking for this endpoint
 *       name: "todos_list"      # Optional: custom name for reports
 *       track_user: true        # Track user ID (default: true)
 *       track_ip: true          # Track IP address (default: true)
 *       track_body: false       # Track request body (default: false, security concern)
 * 
 * Global configuration via environment variables:
 *   USAGE_TRACKER_ENABLED=true       # Master switch
 *   USAGE_TRACKER_DRIVER=database    # 'database', 'file', or 'memory'
 *   USAGE_TRACKER_SAMPLE_RATE=100    # Track X% of requests (1-100)
 * 
 * Supports PHP 7.4 and 8.x
 */
class UsageTrackingMiddleware
{
    /** @var UsageTracker */
    private $tracker;

    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var bool Global enabled flag */
    private $enabled;

    /** @var int Sample rate (1-100) */
    private $sampleRate;

    /** @var array Default tracking options */
    private $defaultOptions = [
        'track_user' => true,
        'track_ip' => true,
        'track_user_agent' => false,
        'track_body' => false,
    ];

    /**
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->logger = $options['logger'] ?? null;
        $this->enabled = filter_var(
            $options['enabled'] ?? $_ENV['USAGE_TRACKER_ENABLED'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
        $this->sampleRate = (int) ($options['sample_rate'] ?? $_ENV['USAGE_TRACKER_SAMPLE_RATE'] ?? 100);
        
        $this->tracker = new UsageTracker([
            'driver' => $options['driver'] ?? $_ENV['USAGE_TRACKER_DRIVER'] ?? 'database',
            'logger' => $this->logger,
        ]);
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
        // Check if tracking is globally enabled
        if (!$this->enabled) {
            return $next($request, $response);
        }

        // Sample rate check (for high-traffic APIs)
        if ($this->sampleRate < 100 && mt_rand(1, 100) > $this->sampleRate) {
            return $next($request, $response);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Skip OPTIONS requests (CORS preflight)
        if ($method === 'OPTIONS') {
            return $next($request, $response);
        }

        // Get tracking configuration for this path
        $config = BaseRouter::getInstance()->getTrackingConfig($path);

        // Skip if tracking not enabled for this route
        if (empty($config) || !($config['enabled'] ?? true)) {
            return $next($request, $response);
        }

        // Merge with defaults
        $trackingOptions = array_merge($this->defaultOptions, $config);

        // Record start time
        $startTime = microtime(true);

        // Execute the request
        /** @var Response $response */
        $response = $next($request, $response);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Build tracking data
        $trackingData = $this->buildTrackingData(
            $request,
            $response,
            $path,
            $method,
            $responseTime,
            $trackingOptions
        );

        // Track asynchronously if possible (don't block response)
        $this->tracker->track($trackingData);

        return $response;
    }

    /**
     * Build tracking data from request/response
     * 
     * @param Request $request
     * @param Response $response
     * @param string $path
     * @param string $method
     * @param float $responseTime
     * @param array $options
     * @return array
     */
    private function buildTrackingData(
        Request $request,
        Response $response,
        string $path,
        string $method,
        float $responseTime,
        array $options
    ): array {
        $data = [
            'endpoint' => $path,
            'method' => $method,
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime, 2),
            'request_size' => $this->getRequestSize($request),
            'response_size' => $response->getBody()->getSize() ?? 0,
            'route_name' => $options['name'] ?? null,
        ];

        // Track user ID if enabled and available
        if ($options['track_user']) {
            $user = $request->getAttribute('user');
            $data['user_id'] = $user ? $user->id : null;
        }

        // Track IP address if enabled
        if ($options['track_ip']) {
            $data['ip_address'] = $this->getClientIp($request);
        }

        // Track user agent if enabled
        if ($options['track_user_agent']) {
            $data['user_agent'] = $request->getHeaderLine('User-Agent');
        }

        // Track metadata
        $metadata = [];
        
        // Add query parameters (sanitized)
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $metadata['query'] = $this->sanitizeParams($queryParams);
        }

        // Track request body if explicitly enabled (security risk!)
        if ($options['track_body']) {
            $body = $request->getParsedBody();
            if (!empty($body)) {
                $metadata['body'] = $this->sanitizeParams($body);
            }
        }

        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        return $data;
    }

    /**
     * Get request size in bytes
     * 
     * @param Request $request
     * @return int
     */
    private function getRequestSize(Request $request): int
    {
        $contentLength = $request->getHeaderLine('Content-Length');
        if (!empty($contentLength)) {
            return (int) $contentLength;
        }
        
        return $request->getBody()->getSize() ?? 0;
    }

    /**
     * Get client IP address
     * 
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string
    {
        // Check trusted proxy headers
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'];
        
        foreach ($headers as $header) {
            $value = $request->getHeaderLine($header);
            if (!empty($value)) {
                $ips = array_map('trim', explode(',', $value));
                $ip = $ips[0];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Sanitize parameters for logging
     * 
     * Removes sensitive fields like passwords, tokens, etc.
     * 
     * @param array $params
     * @return array
     */
    private function sanitizeParams(array $params): array
    {
        $sensitiveFields = [
            'password', 'passwd', 'pwd', 'pass',
            'token', 'api_key', 'apikey', 'secret',
            'credit_card', 'card_number', 'cvv', 'cvc',
            'ssn', 'social_security',
        ];

        $sanitized = [];
        foreach ($params as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Check if key matches sensitive patterns
            $isSensitive = false;
            foreach ($sensitiveFields as $sensitive) {
                if (strpos($lowerKey, $sensitive) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParams($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Log message
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[UsageTracking] ' . $message, $context);
        }
    }
}
