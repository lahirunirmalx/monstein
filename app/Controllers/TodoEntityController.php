<?php

namespace Monstein\Controllers;

use Monstein\Base\EntityController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

class TodoEntityController extends EntityController
{

    public function validateGetRequest()
    {
        return [
            'id' => V::numeric()->positive()
        ];
    }

    public function validatePutRequest()
    {
        return [
            'name' => [
                'rules' => V::optional(V::length(3, 25)->alnum('-')), // optional
                'message' => 'Invalid name'
            ],
            'category' => [
                'rules' => V::optional(V::numeric()->positive()), // optional
                'message' => 'Invalid category ID'
            ]
        ];
    }

    public function validateDeleteRequest()
    {
       return [
           'id' => V::numeric()->positive(),
           'force' => V::optional(V::boolType()),
       ];
    }

    public function doDelete(Request $request , Response $response , $args , $cleanValues)
    {
        $data = $cleanValues;
        $user = $request->getAttribute('user');
        $errors = [];
        $todo = $user->todos()->withTrashed()->find($data['id']);
        if (!$errors && !$todo) {
            $errors = ['Todo not found: '.$data['id']];
        }
        if (!$errors) {
            $deleted = (isset($data['force']) && !empty($data['force'])) ? $todo->forceDelete() : $todo->delete();
            return $response->withJson(['success' => true], 200);
        }
        // Errors found
        return $this->handleValidationFail($errors,$response);
    }

    public function doPut(Request $request , Response $response , $args , $cleanValues)
    {
        $data = $cleanValues;
        $user = $request->getAttribute('user');
        $errors = [];

        $todo = $user->todos()->find($args['id']);
        if (!$errors && !$todo) {
            $errors = ['Todo not found: '.$args['id']];
        }
        if (!$errors) {
            if (isset($data['category'])) {
                // validate category input
                if (!$user->categories()->find($data['category'])) {
                    $errors = ['Category not found'];
                }
            }
        }
        if (!$errors && isset($data['name'])) {
            $whereCond = [
                ['name', '=', $data['name']],
                ['category_id', '=', ($data['category'] ?? $todo->category)],
                ['id', '!=', $todo->id]
            ];
            if ($user->todos()->where($whereCond)->first()) {
                $errors = ['Todo item name already exists in category'];
            }
        }
        if (!$errors) {
            if (isset($data['name'])) { $todo->name = $data['name']; }
            if (isset($data['category'])) { $todo->category_id = $data['category']; }
            $todo->save();
            return $response->withJson(['success' => true], 200);
        }
        // Errors found
        return $this->handleValidationFail($errors,$response);
    }

    public function doGet(Request $request , Response $response , $args , $cleanValues)
    {

        $user = $request->getAttribute('user');
        $todo = $user->todos()->find($cleanValues['id']);
        return $response->withJson(['data' => $todo ? $todo : []], 200);
    }
}