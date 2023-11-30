<?php

namespace Monstein\Controllers;

use Monstein\Base\CollectionController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

class CategoryCollectionController extends CollectionController
{

    public function validateGetRequest()
    {
        return [
            'name' => [
                'rules' => V::optional(V::alnum('-')), // optional
                'message' => 'Invalid name'
            ],
            'id' => [
                'rules' => V::optional(V::oneOf(V::numeric()->positive(),V::arrayType()->each(V::numeric()->positive()))), // optional
                'message' => 'Invalid category ID(s)'
            ]
        ];
    }

    public function validatePostRequest()
    {
        return [
            'name' => V::length(3, 25)->alnum('-')
        ];
    }

    public function doPost(Request $request , Response $response , $args , $cleanValues)
    {

        $data = $cleanValues;
        $user = $request->getAttribute('user');
        $category = $user->categories()->firstOrCreate([
            'name' => $data['name']
        ]);
        return $response->withJson([
            'success' => true,
            'id' => $category->id
        ], 200);

    }

    public function doGet(Request $request , Response $response , $args , $cleanValues)
    {
        $user = $request->getAttribute('user');
        $categories = $user->categories()->withCount('todos')->get();
        return $response->withJson(['data' => $categories], 200);
    }

    public function todos(Request $request, Response $response, $args) {
        $user = $request->getAttribute('user');
        $category = $user->categories()->find($args['id']);
        return $response->withJson(['data' => $category->todos()], 200);
    }
}