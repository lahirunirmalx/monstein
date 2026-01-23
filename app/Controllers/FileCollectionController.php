<?php

namespace Monstein\Controllers;

use Monstein\Base\CollectionController;
use Monstein\Base\FileUpload;
use Monstein\Models\File;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

/**
 * File Collection Controller
 * 
 * Handles file uploads via:
 * - Multipart form data (POST /files with file field)
 * - Base64 encoded JSON (POST /files with file_data and file_name)
 * 
 * Example multipart:
 *   curl -X POST -F "file=@image.jpg" -H "Authorization: Bearer TOKEN" /files
 * 
 * Example base64:
 *   curl -X POST -H "Content-Type: application/json" \
 *     -d '{"file_data":"data:image/png;base64,...","file_name":"image.png"}' \
 *     -H "Authorization: Bearer TOKEN" /files
 */
class FileCollectionController extends CollectionController
{
    public function validateGetRequest()
    {
        return [
            'type' => [
                'rules' => V::optional(V::in(['image', 'document', 'all'])),
                'message' => 'Invalid type filter'
            ],
            'limit' => [
                'rules' => V::optional(V::intVal()->positive()->max(100)),
                'message' => 'Limit must be between 1 and 100'
            ],
        ];
    }

    public function validatePostRequest()
    {
        return [
            // For base64 uploads
            'file_data' => [
                'rules' => V::optional(V::stringType()),
                'message' => 'Invalid file data'
            ],
            'file_name' => [
                'rules' => V::optional(V::length(1, 255)),
                'message' => 'Invalid file name'
            ],
        ];
    }

    /**
     * GET /files - List user's files
     */
    public function doGet(Request $request, Response $response, $args, $cleanValues)
    {
        $user = $request->getAttribute('user');
        $query = $user->files();

        // Filter by type
        if (isset($cleanValues['type']) && $cleanValues['type'] !== 'all') {
            if ($cleanValues['type'] === 'image') {
                $query->where('mime_type', 'LIKE', 'image/%');
            } elseif ($cleanValues['type'] === 'document') {
                $query->where('mime_type', 'NOT LIKE', 'image/%');
            }
        }

        // Apply limit
        $limit = $cleanValues['limit'] ?? 50;
        $files = $query->orderBy('created_at', 'desc')
                      ->limit($limit)
                      ->get();

        return $response->withJson([
            'success' => true,
            'data' => $files->map(function ($file) {
                return $file->toApiArray();
            }),
        ], 200);
    }

    /**
     * POST /files - Upload new file
     * 
     * Supports:
     * - Multipart form data with 'file' field
     * - JSON with 'file_data' (base64) and 'file_name' fields
     */
    public function doPost(Request $request, Response $response, $args, $cleanValues)
    {
        $user = $request->getAttribute('user');
        $uploadedFiles = $request->getAttribute('uploaded_files', []);
        $uploadErrors = $request->getAttribute('upload_errors', []);

        // Check for errors first
        if (!empty($uploadErrors)) {
            return $this->handleValidationFail($uploadErrors, $response);
        }

        // Check if any files were processed by middleware
        if (!empty($uploadedFiles)) {
            return $this->handleMiddlewareUpload($uploadedFiles, $user, $response);
        }

        // Manual base64 handling if middleware didn't process
        if (isset($cleanValues['file_data']) && isset($cleanValues['file_name'])) {
            return $this->handleBase64Upload($cleanValues, $user, $request, $response);
        }

        // No files found
        return $this->handleValidationFail(['No file provided'], $response);
    }

    /**
     * Handle files processed by FileUploadMiddleware
     */
    private function handleMiddlewareUpload(array $uploadedFiles, $user, Response $response): Response
    {
        $savedFiles = [];
        $errors = [];

        foreach ($uploadedFiles as $key => $uploadResult) {
            if (is_array($uploadResult) && isset($uploadResult[0])) {
                // Multiple files
                foreach ($uploadResult as $index => $result) {
                    $file = File::createFromUpload($result, $user->id);
                    if ($file) {
                        $savedFiles[] = $file->toApiArray();
                    } else {
                        $errors[] = "Failed to save file {$key}[{$index}]";
                    }
                }
            } else {
                // Single file
                $file = File::createFromUpload($uploadResult, $user->id);
                if ($file) {
                    $savedFiles[] = $file->toApiArray();
                } else {
                    $errors[] = "Failed to save file {$key}";
                }
            }
        }

        if (!empty($errors)) {
            return $response->withJson([
                'success' => false,
                'errors' => $errors,
                'data' => $savedFiles,
            ], 400);
        }

        return $response->withJson([
            'success' => true,
            'data' => count($savedFiles) === 1 ? $savedFiles[0] : $savedFiles,
        ], 201);
    }

    /**
     * Handle base64 upload not processed by middleware
     */
    private function handleBase64Upload(array $cleanValues, $user, Request $request, Response $response): Response
    {
        $config = $request->getAttribute('file_upload_config', []);
        
        $fileUpload = new FileUpload([
            'driver' => $config['storage'] ?? 'filesystem',
            'logger' => $this->getLogger(),
        ]);

        $result = $fileUpload->handleBase64(
            $cleanValues['file_data'],
            $cleanValues['file_name'],
            [
                'driver' => $config['storage'] ?? 'filesystem',
                'db_format' => $config['db_format'] ?? 'base64',
            ]
        );

        if (!$result['success']) {
            return $this->handleValidationFail([$result['error']], $response);
        }

        $file = File::createFromUpload($result, $user->id);
        
        if (!$file) {
            return $this->handleValidationFail(['Failed to save file'], $response);
        }

        return $response->withJson([
            'success' => true,
            'data' => $file->toApiArray(),
        ], 201);
    }
}
