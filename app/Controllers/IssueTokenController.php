<?php
namespace Monstein\Controllers;

use Monstein\Base\BaseController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use \Monstein\Models\User as User;
use Respect\Validation\Validator as V;

class IssueTokenController extends BaseController {


    public function doPost(Request $request, Response $response,$args,$cleanValues) {
        $this->getLogger()->info('POST /users/login');
        $data = $cleanValues;
        $isValid = true;
        $user = null;

        // Validate username - use constant-time comparison approach
        $user = User::where(['username' => $data['username']])->first();
        
        if ($user === null) {
            // User not found - still run password_verify to prevent timing attacks
            // Use a dummy hash to prevent timing differences
            password_verify($data['password'], '$2y$10$dummyhashtopreventtimingattacksaaaaaaaaaaaaaaaaaaaaa');
            $isValid = false;
        } else {
            // Validate password with timing-safe comparison
            if (!password_verify($data['password'], $user->password)) {
                $isValid = false;
            }
        }

        // Generic error message prevents username enumeration
        if (!$isValid) {
            $this->getLogger()->info('Failed login attempt', ['username' => $data['username']]);
            return $this->handleValidationFail(['Invalid username or password'], $response);
        }
        
        // Generate JWT token for authenticated user
        $token = $user->tokenCreate();
        
        return $response->withJson([
            "success" => true,
            "data" => [
                "token" => $token['token'],
                "expires" => $token['expires']
            ]
        ], 200);
    }

    public function validateGetRequest()
    {
        // TODO: Implement validateGetRequest() method.
    }

    public function validatePostRequest()
    {
        return [
            'username' => V::length(3, 25)->alnum('-')->noWhitespace(),
            // Password: 8-72 chars (bcrypt limit), allows special chars
            'password' => V::length(8, 72)->noWhitespace()
        ];
    }

    public function validatePatchRequest()
    {
        // TODO: Implement validatePatchRequest() method.
    }

    public function validatePutRequest()
    {
        // TODO: Implement validatePutRequest() method.
    }

    public function validateDeleteRequest()
    {
        // TODO: Implement validateDeleteRequest() method.
    }

    public function doDelete(Request $request , Response $response , $args , $cleanValues)
    {
        // TODO: Implement doDelete() method.
    }

    public function doPut(Request $request , Response $response , $args , $cleanValues)
    {
        // TODO: Implement doPut() method.
    }

    public function doPatch(Request $request , Response $response , $args , $cleanValues)
    {
        // TODO: Implement doPatch() method.
    }

    public function doGet(Request $request , Response $response , $args , $cleanValues)
    {
        // TODO: Implement doGet() method.
    }
}