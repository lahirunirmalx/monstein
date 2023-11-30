<?php
namespace Monstein\Controllers;

use Monstein\Base\BaseController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use \Monstein\Models\User as User;
use Respect\Validation\Validator as V;

class IssueTokenController extends BaseController {


    public function doPost(Request $request, Response $response,$args,$cleanValues) {
        $this->getLogger()->addInfo('POST /users/login');
        $data = $cleanValues;
        $errors = [];

        // validate username
        if (!$errors && !($user = User::where(['username' => $data['username']])->first())) {
            $errors[] = 'Username invalid';
        }
        // validate password
        if (!$errors && !password_verify($data['password'], $user->password)) {
            $errors[] = 'Password invalid';
        }
        if (!$errors) {
            // No errors, generate JWT
            $token = $user->tokenCreate();
            // return token
            return $response->withJson([
                "success" => true,
                "data" => [
                    "token" => $token['token'],
                    "expires" => $token['expires']
                ]
            ], 200);
        } else {
            // Error occured
            return $this->handleValidationFail($errors,$response);
        }
    }

    public function validateGetRequest()
    {
        // TODO: Implement validateGetRequest() method.
    }

    public function validatePostRequest()
    {
        return [
            'username' => V::length(3, 25)->alnum('-')->noWhitespace(),
            'password' => V::length(3, 25)->alnum('-')->noWhitespace()
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