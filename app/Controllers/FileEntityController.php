<?php

namespace Monstein\Controllers;

use Monstein\Base\EntityController;
use Monstein\Models\File;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

/**
 * File Entity Controller
 * 
 * Handles single file operations:
 * - GET /files/{id} - Get file details
 * - GET /files/{id}/download - Download file content
 * - DELETE /files/{id} - Delete file
 */
class FileEntityController extends EntityController
{
    public function validateGetRequest()
    {
        return [
            'id' => V::intVal()->positive(),
            'include_content' => [
                'rules' => V::optional(V::boolVal()),
                'message' => 'Invalid include_content value'
            ],
        ];
    }

    public function validateDeleteRequest()
    {
        return [
            'id' => V::intVal()->positive(),
        ];
    }

    /**
     * GET /files/{id} - Get file details
     * 
     * Query params:
     * - include_content: true to include base64 content in response
     */
    public function doGet(Request $request, Response $response, $args, $cleanValues)
    {
        $user = $request->getAttribute('user');
        $file = $user->files()->find($args['id']);

        if (!$file) {
            return $this->handleValidationFail(['File not found'], $response);
        }

        $data = $file->toApiArray();

        // Include content if requested
        if (!empty($cleanValues['include_content'])) {
            $data['content'] = $file->getDataUri();
        }

        return $response->withJson([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * DELETE /files/{id} - Delete file
     */
    public function doDelete(Request $request, Response $response, $args, $cleanValues)
    {
        $user = $request->getAttribute('user');
        $file = $user->files()->find($cleanValues['id']);

        if (!$file) {
            return $this->handleValidationFail(['File not found'], $response);
        }

        // Delete from storage
        $file->deleteFromStorage();
        
        // Soft delete the record
        $file->delete();

        return $response->withJson([
            'success' => true,
            'message' => 'File deleted',
        ], 200);
    }

    public function validatePutRequest()
    {
        return [];
    }

    public function doPut(Request $request, Response $response, $args, $cleanValues)
    {
        return $this->handleUnsupportedMethod($request, $response);
    }
}
