<?php

namespace Monstein\Base;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monstein\Config\Config;
use Monstein\Models\User;

/**
 * JWT Authentication Middleware
 * 
 * Validates JWT tokens and attaches user to request.
 * Replaces tuupola/slim-jwt-auth for firebase/php-jwt 6.x compatibility.
 */
class JwtMiddleware
{
    /** @var array Paths to ignore (no auth required) */
    private $ignorePaths = [];

    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var bool Require HTTPS */
    private $secure = true;

    /** @var array Hosts where HTTP is allowed */
    private $relaxed = ['localhost', '127.0.0.1'];

    /**
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->ignorePaths = $options['ignore'] ?? [];
        $this->logger = $options['logger'] ?? null;
        $this->secure = $options['secure'] ?? true;
        $this->relaxed = $options['relaxed'] ?? ['localhost', '127.0.0.1'];
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
        $path = $request->getUri()->getPath();
        $host = $request->getUri()->getHost();

        // Check if path should be ignored
        if ($this->isIgnoredPath($path)) {
            return $next($request, $response);
        }

        // Check HTTPS requirement
        if ($this->secure && !$this->isSecureRequest($request, $host)) {
            return $this->error($response, 'HTTPS required', 401);
        }

        // Extract token from header
        $token = $this->extractToken($request);
        if ($token === null) {
            $this->log('warning', 'Token not found in request');
            return $this->error($response, 'Token not found.', 401);
        }

        // Decode and validate token
        try {
            $authConfig = Config::auth();
            $decoded = JWT::decode(
                $token,
                new Key($authConfig['secret'], $authConfig['jwt'])
            );

            // Get user from token subject
            $userId = $decoded->sub ?? null;
            if ($userId === null) {
                $this->log('warning', 'Token missing subject claim');
                return $this->error($response, 'Invalid token.', 401);
            }

            $user = User::find($userId);
            if ($user === null) {
                $this->log('warning', 'User not found for token', ['user_id' => $userId]);
                return $this->error($response, 'User not found.', 401);
            }

            // Attach decoded token and user to request
            $request = $request->withAttribute('jwt', $decoded);
            $request = $request->withAttribute('user', $user);

            $this->log('info', 'Token validated successfully', ['user_id' => $userId]);

        } catch (ExpiredException $e) {
            $this->log('info', 'Token expired');
            return $this->error($response, 'Token has expired.', 401);
        } catch (SignatureInvalidException $e) {
            $this->log('warning', 'Invalid token signature');
            return $this->error($response, 'Invalid token signature.', 401);
        } catch (\Exception $e) {
            $this->log('error', 'Token validation failed', ['error' => $e->getMessage()]);
            return $this->error($response, 'Token validation failed.', 401);
        }

        return $next($request, $response);
    }

    /**
     * Check if path should be ignored
     * 
     * @param string $path
     * @return bool
     */
    private function isIgnoredPath(string $path): bool
    {
        foreach ($this->ignorePaths as $ignorePath) {
            // Exact match or pattern match
            if ($path === $ignorePath || fnmatch($ignorePath, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if request is secure (HTTPS)
     * 
     * @param Request $request
     * @param string $host
     * @return bool
     */
    private function isSecureRequest(Request $request, string $host): bool
    {
        // Allow relaxed hosts
        if (in_array($host, $this->relaxed, true)) {
            return true;
        }

        // Check for HTTPS
        $scheme = $request->getUri()->getScheme();
        if ($scheme === 'https') {
            return true;
        }

        // Check for proxy headers
        $forwardedProto = $request->getHeaderLine('X-Forwarded-Proto');
        if (strtolower($forwardedProto) === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Extract JWT token from Authorization header
     * 
     * @param Request $request
     * @return string|null
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        
        if (empty($header)) {
            return null;
        }

        // Check for Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Create error response
     * 
     * @param Response $response
     * @param string $message
     * @param int $status
     * @return Response
     */
    private function error(Response $response, string $message, int $status): Response
    {
        $data = [
            'success' => false,
            'errors' => $message
        ];

        $payload = json_encode($data);
        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
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
            $this->logger->$level('[JWT] ' . $message, $context);
        }
    }
}
