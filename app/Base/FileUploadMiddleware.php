<?php

namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * File Upload Middleware
 * 
 * Processes multipart form data for routes configured with file upload support.
 * Configuration is defined in routing.yml per route.
 * 
 * Example routing.yml:
 *   uploadFile:
 *     url: /upload
 *     controller: \Monstein\Controllers\FileController
 *     method: [post]
 *     file_upload:
 *       enabled: true
 *       max_size: 10485760        # 10MB
 *       allowed_types: images     # 'images', 'documents', 'all', or array
 *       storage: filesystem       # 'filesystem', 'database', 'both'
 *       db_format: base64         # 'base64' or 'blob' (for database storage)
 * 
 * Supports PHP 7.4 and 8.x
 */
class FileUploadMiddleware
{
    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var FileUpload */
    private $fileUpload;

    /**
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->logger = $options['logger'] ?? null;
        $this->fileUpload = new FileUpload($options);
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
        $method = $request->getMethod();

        // Only process POST/PUT/PATCH requests
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request, $response);
        }

        // Get file upload configuration for this path
        $config = BaseRouter::getInstance()->getFileUploadConfig($path);

        // Skip if file upload not enabled for this route
        if (empty($config) || !($config['enabled'] ?? false)) {
            return $next($request, $response);
        }

        // Check Content-Type
        $contentType = $request->getHeaderLine('Content-Type');
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
        $isJson = stripos($contentType, 'application/json') !== false;

        // Process uploads based on content type
        $processedFiles = [];
        $errors = [];

        if ($isMultipart) {
            // Handle multipart form uploads
            $uploadedFiles = $request->getUploadedFiles();
            
            foreach ($uploadedFiles as $key => $file) {
                if (is_array($file)) {
                    // Multiple files with same field name
                    foreach ($file as $index => $singleFile) {
                        $result = $this->processUploadedFile($singleFile, $config);
                        if ($result['success']) {
                            $processedFiles[$key][$index] = $result;
                        } else {
                            $errors[$key][$index] = $result['error'];
                        }
                    }
                } else {
                    // Single file
                    $result = $this->processUploadedFile($file, $config);
                    if ($result['success']) {
                        $processedFiles[$key] = $result;
                    } else {
                        $errors[$key] = $result['error'];
                    }
                }
            }
        } elseif ($isJson) {
            // Handle base64 encoded files in JSON body
            $body = $request->getParsedBody();
            
            if (is_array($body)) {
                $processedFiles = $this->processBase64Files($body, $config, $errors);
            }
        }

        // Attach processed files and errors to request
        $request = $request->withAttribute('uploaded_files', $processedFiles);
        $request = $request->withAttribute('upload_errors', $errors);
        $request = $request->withAttribute('file_upload_config', $config);

        // If there are errors and strict mode is enabled, return error response
        if (!empty($errors) && ($config['strict'] ?? false)) {
            return $this->errorResponse($response, $errors);
        }

        return $next($request, $response);
    }

    /**
     * Process a single uploaded file
     * 
     * @param \Psr\Http\Message\UploadedFileInterface $file
     * @param array $config
     * @return array
     */
    private function processUploadedFile($file, array $config): array
    {
        $options = $this->buildUploadOptions($config);
        return $this->fileUpload->handleUpload($file, $options);
    }

    /**
     * Process base64 encoded files from JSON body
     * 
     * Looks for fields with specific patterns:
     * - 'file_data' + 'file_name' pairs
     * - Fields ending with '_base64' + corresponding '_name' field
     * - Data URI format in any field
     * 
     * @param array $body
     * @param array $config
     * @param array &$errors
     * @return array
     */
    private function processBase64Files(array $body, array $config, array &$errors): array
    {
        $processedFiles = [];
        $options = $this->buildUploadOptions($config);
        $processedKeys = [];

        // Check for explicit file_data/file_name pattern
        if (isset($body['file_data']) && isset($body['file_name'])) {
            $result = $this->fileUpload->handleBase64($body['file_data'], $body['file_name'], $options);
            if ($result['success']) {
                $processedFiles['file'] = $result;
            } else {
                $errors['file'] = $result['error'];
            }
            $processedKeys['file_data'] = true;
        }

        // Check for fields with _base64 suffix or data URI format
        foreach ($body as $key => $value) {
            // Skip already processed keys
            if (isset($processedKeys[$key])) {
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            // Check for _base64 suffix first (more specific)
            if (substr($key, -7) === '_base64') {
                $baseKey = substr($key, 0, -7);
                $nameKey = $baseKey . '_name';
                $filename = $body[$nameKey] ?? 'upload_' . $baseKey;

                $result = $this->fileUpload->handleBase64($value, $filename, $options);
                if ($result['success']) {
                    $processedFiles[$baseKey] = $result;
                } else {
                    $errors[$baseKey] = $result['error'];
                }
                $processedKeys[$key] = true;
                continue;
            }

            // Check for data URI format (but not file_data which was already handled)
            if (preg_match('/^data:([a-zA-Z0-9\/\-\+\.]+);base64,/', $value)) {
                $nameKey = $key . '_name';
                $filename = $body[$nameKey] ?? null;
                
                // Skip if no explicit filename and not a known pattern
                if ($filename === null) {
                    continue;
                }

                $result = $this->fileUpload->handleBase64($value, $filename, $options);
                if ($result['success']) {
                    $processedFiles[$key] = $result;
                } else {
                    $errors[$key] = $result['error'];
                }
                $processedKeys[$key] = true;
            }
        }

        // Check for 'files' array with base64 data
        if (isset($body['files']) && is_array($body['files'])) {
            foreach ($body['files'] as $index => $fileData) {
                if (is_array($fileData) && isset($fileData['data'])) {
                    $filename = $fileData['name'] ?? 'upload_' . $index;
                    $result = $this->fileUpload->handleBase64($fileData['data'], $filename, $options);
                    if ($result['success']) {
                        $processedFiles['files'][$index] = $result;
                    } else {
                        $errors['files'][$index] = $result['error'];
                    }
                }
            }
        }

        return $processedFiles;
    }

    /**
     * Build upload options from route config
     * 
     * @param array $config
     * @return array
     */
    private function buildUploadOptions(array $config): array
    {
        $options = [];

        if (isset($config['max_size'])) {
            $options['max_file_size'] = (int) $config['max_size'];
        }

        if (isset($config['storage'])) {
            $options['driver'] = $config['storage'];
        }

        if (isset($config['db_format'])) {
            $options['db_format'] = $config['db_format'];
        }

        if (isset($config['storage_path'])) {
            $options['storage_path'] = $config['storage_path'];
        }

        // Handle allowed_types preset or custom array
        if (isset($config['allowed_types'])) {
            if (is_string($config['allowed_types'])) {
                $preset = FileUpload::getPreset($config['allowed_types']);
                $options['allowed_types'] = $preset['allowed_types'];
                $options['allowed_extensions'] = $preset['allowed_extensions'];
            } elseif (is_array($config['allowed_types'])) {
                $options['allowed_types'] = $config['allowed_types'];
            }
        }

        if (isset($config['allowed_extensions']) && is_array($config['allowed_extensions'])) {
            $options['allowed_extensions'] = $config['allowed_extensions'];
        }

        return $options;
    }

    /**
     * Create error response
     * 
     * @param Response $response
     * @param array $errors
     * @return Response
     */
    private function errorResponse(Response $response, array $errors): Response
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
     * Log message
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[FileUploadMiddleware] ' . $message, $context);
        }
    }
}
