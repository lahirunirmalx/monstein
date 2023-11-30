<?php
namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

abstract class EntityController extends BaseController {


    public  function validatePostRequest(){
        return [];
    }

    public  function validatePatchRequest() {
        return [];
    }


    public  function doPatch(Request $request , Response $response,$args,$cleanValues)
    {
        return $this->handleUnsupportedMethod($request,$response);
    }
    public  function doPost(Request $request , Response $response,$args,$cleanValues)
    {
        return $this->handleUnsupportedMethod($request,$response);
    }



}