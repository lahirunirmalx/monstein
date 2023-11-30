<?php
namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

abstract class CollectionController extends BaseController {


    public  function doPut(Request $request , Response $response,$args,$cleanValues)
    {
        return $this->handleUnsupportedMethod($request,$response);
    }

    public  function doPatch(Request $request , Response $response , $args , $cleanValues)
    {
        return $this->handleUnsupportedMethod($request,$response);
    }

    public  function doDelete(Request $request , Response $response , $args , $cleanValues)
    {
        return $this->handleUnsupportedMethod($request,$response);
    }

    public function validatePutRequest()
     {
        return [];
     }

    public function validatePatchRequest()
    {
        return [];
    }

    public function validateDeleteRequest()
    {
        return [];
    }
}