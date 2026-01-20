<?php

namespace Monstein\Controllers;

use Monstein\Base\CollectionController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as V;

class TodoCollectionController extends CollectionController
{

    public function validateGetRequest()
    {
        return [
            'name' => [
                'rules' => V::optional(V::alnum('-')), // optional
                'message' => 'Invalid name'
            ],
            'category' => [
                'rules' => V::optional(V::oneOf(V::numeric()->positive(),V::arrayType()->each(V::numeric()->positive()))), // optional
                'message' => 'Invalid category ID(s)'
            ]
        ];
    }


    public function validatePostRequest()
    {
        return [
            'name' => V::length(3, 25)->alnum('-'),
            'category' => [
                'rules' => V::numeric()->positive(),
                'message' => 'Invalid category ID' // custom error message
            ]
        ];
    }

    public function doPost(Request $request , Response $response , $args , $cleanValues)
    {
        $user = $request->getAttribute('user');
        $errors = [];
        $category = $user->categories()->find($cleanValues['category']);
        if (!$category) {
                $errors = ['Category ID invalid'];
        }

        if (!$errors) {
            $todo = $user->todos()->firstOrCreate([
                'name' => $cleanValues['name'],
                'category_id' => $category->id
            ]);
            return $response->withJson([
                'success' => true,
                'id' => $todo->id
            ], 200);
        }
        // error
        return $this->handleValidationFail($errors,$response);
    }

    public function doGet(Request $request , Response $response , $args , $cleanValues)
    {
        $this->getLogger()->info('GET /todo');
        $user = $request->getAttribute('user');
        $todo = $this->getFilteredTodoList($user,$cleanValues);
        return $response->withJson([
            'data' => $todo ? $todo : []
        ], 200);
    }

    protected function getFilteredTodoList($obj,$cleanValues){
        $queryBuilder = $obj->todos();
        if( !empty($cleanValues['name'])){
            $queryBuilder = $queryBuilder->where('name','like','%'.$cleanValues['name'].'%');

        }
        if(isset($cleanValues['category']) ){
            $category = $cleanValues['category'];
            if(!is_array($cleanValues['category'])){
                $category = array ($cleanValues['category']);
            }
            $queryBuilder = $queryBuilder->whereIn('category', $category );
        }
        return $queryBuilder->get();
    }
}