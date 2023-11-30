<?php

namespace Monstein\Controllers;

use Monstein\Base\EntityController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

class CategoryEntityController extends EntityController
{

    public function validateGetRequest()
    {
        return [
            'id' => V::numeric()->positive(),
        ];
    }

    public function validatePutRequest()
    {
        return [
            'id' => V::numeric()->positive(),
            'name' => [
                'rules' => V::optional(V::length(3, 25)->alnum('-')),
                'message' => 'Invalid name'
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
        // check category ID exists
        $category = $user->categories()->withTrashed()->find($data['id']);
        if (!$errors && !$category) {
            $errors = ['Category not found: '.$data['id']];
        }
        if (!$errors) {
            $deleted = (isset($data['force']) && !empty($data['force'])) ? $category->forceDelete() : $category->delete();
            return $response->withJson(['success' => true], 200);
        } else {
            // Errors found
            return $this->handleValidationFail($errors,$response);
        }
    }

    public function doPut(Request $request , Response $response , $args , $cleanValues)
    {

        $data = $cleanValues;
        $user = $request->getAttribute('user');
        $errors = [];

        $category = $user->categories()->find($data['id']);
        if (!$errors && !$category) {
            $errors = ['Category not found: '.$data['id']];
        }
        if (!$errors && isset($data['name']) && $user->categories()->where('name', $data['name'])->where('id', '!=', $category->id)->first()) {
            $errors = ['Category name already exists'];
        }
        if (!$errors) {
            if (isset($data['name'])) {
                $category->name = $data['name'];
            }
            $category->save();
            return $response->withJson(['success' => true], 200);
        } else {
            return $this->handleValidationFail($errors,$response);
        }
    }

    public function doGet(Request $request , Response $response , $args , $cleanValues)
    {

        $user = $request->getAttribute('user');
        $category = $user->categories()->withCount('todos')->find($args['id']);
        if ($category) {
            return $response->withJson([
                'success' => true,
                'data' => $category
            ], 200);
        } else {
            return $this->handleValidationFail(['Category not found'],$response);
        }
    }
}