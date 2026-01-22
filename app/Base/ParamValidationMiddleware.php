<?php

namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Validator as V;
use Respect\Validation\Exceptions\ValidationException;

/**
 * Parameter Validation Middleware
 * 
 * Validates route parameters (like {id}) using Respect Validation.
 * Configuration is defined in routing.yml per route.
 * 
 * Supported validation types:
 * - id: Positive integer (for database IDs)
 * - integer: Any integer
 * - positive: Positive number
 * - uuid: UUID v4 format
 * - slug: URL-safe slug (alphanumeric with hyphens)
 * - alpha: Alphabetic characters only
 * - alphanumeric: Alphanumeric characters only
 * - email: Valid email address
 * 
 * Supports PHP 7.4 and 8.x
 */
class ParamValidationMiddleware
{
    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var array Validation type to Respect Validator mapping */
    private $validators;

    /**
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->logger = $options['logger'] ?? null;
        $this->initValidators();
    }

    /**
     * Initialize validator mappings
     */
    private function initValidators(): void
    {
        $this->validators = [
            'id' => function () {
                return V::intVal()->positive();
            },
            'integer' => function () {
                return V::intVal();
            },
            'positive' => function () {
                return V::numericVal()->positive();
            },
            'uuid' => function () {
                return V::uuid();
            },
            'slug' => function () {
                return V::slug();
            },
            'alpha' => function () {
                return V::alpha();
            },
            'alphanumeric' => function () {
                return V::alnum();
            },
            'email' => function () {
                return V::email();
            },
            'date' => function () {
                return V::date();
            },
            'noWhitespace' => function () {
                return V::noWhitespace();
            },
        ];
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
        $route = $request->getAttribute('route');
        
        // Get parameter rules for this path
        $rules = BaseRouter::getInstance()->getParamRulesForPath($path);
        
        if (empty($rules)) {
            return $next($request, $response);
        }

        // Get route arguments (parameters from URL)
        $args = [];
        if ($route !== null && method_exists($route, 'getArguments')) {
            $args = $route->getArguments();
        }

        // Validate each parameter
        $errors = [];
        foreach ($rules as $param => $validationType) {
            if (!isset($args[$param])) {
                continue; // Parameter not in route, skip
            }

            $value = $args[$param];
            $error = $this->validateParam($param, $value, $validationType);
            
            if ($error !== null) {
                $errors[$param] = $error;
            }
        }

        if (!empty($errors)) {
            $this->log('warning', 'Parameter validation failed', [
                'path' => $path,
                'errors' => $errors
            ]);
            
            return $this->validationErrorResponse($response, $errors);
        }

        return $next($request, $response);
    }

    /**
     * Validate a single parameter
     * 
     * @param string $param Parameter name
     * @param mixed $value Parameter value
     * @param string $validationType Validation type from config
     * @return string|null Error message or null if valid
     */
    private function validateParam(string $param, $value, string $validationType): ?string
    {
        // Handle multiple validation types (comma-separated)
        $types = array_map('trim', explode(',', $validationType));
        
        foreach ($types as $type) {
            // Check for custom validation pattern (regex)
            if (strpos($type, 'regex:') === 0) {
                $pattern = substr($type, 6);
                if (!preg_match($pattern, $value)) {
                    return "Parameter '{$param}' does not match required format";
                }
                continue;
            }

            // Check for length validation
            if (strpos($type, 'length:') === 0) {
                $parts = explode(':', $type);
                if (count($parts) >= 3) {
                    $min = (int) $parts[1];
                    $max = (int) $parts[2];
                    $len = strlen($value);
                    if ($len < $min || $len > $max) {
                        return "Parameter '{$param}' must be between {$min} and {$max} characters";
                    }
                }
                continue;
            }

            // Use predefined validator
            if (!isset($this->validators[$type])) {
                $this->log('warning', "Unknown validation type: {$type}");
                continue;
            }

            try {
                $validator = $this->validators[$type]();
                $validator->assert($value);
            } catch (ValidationException $e) {
                return "Invalid {$param}: " . $this->getReadableError($type, $param);
            } catch (\Exception $e) {
                return "Parameter '{$param}' validation failed";
            }
        }

        return null;
    }

    /**
     * Get human-readable error message for validation type
     * 
     * @param string $type Validation type
     * @param string $param Parameter name
     * @return string
     */
    private function getReadableError(string $type, string $param): string
    {
        $messages = [
            'id' => 'must be a positive integer',
            'integer' => 'must be an integer',
            'positive' => 'must be a positive number',
            'uuid' => 'must be a valid UUID',
            'slug' => 'must be a valid URL slug (lowercase letters, numbers, and hyphens)',
            'alpha' => 'must contain only letters',
            'alphanumeric' => 'must contain only letters and numbers',
            'email' => 'must be a valid email address',
            'date' => 'must be a valid date',
            'noWhitespace' => 'must not contain whitespace',
        ];

        return $messages[$type] ?? 'is invalid';
    }

    /**
     * Create validation error response
     * 
     * @param Response $response
     * @param array $errors
     * @return Response
     */
    private function validationErrorResponse(Response $response, array $errors): Response
    {
        $data = [
            'success' => false,
            'errors' => $errors
        ];

        $payload = json_encode($data);
        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
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
            $this->logger->$level('[ParamValidation] ' . $message, $context);
        }
    }
}
