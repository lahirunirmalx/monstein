<?php
namespace Monstein\Base;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monstein\Base\SecurityUtils;


abstract class BaseController {
    private $logger;
    private $db;
    private $validator;
    
    private $table;

    // Dependency injection via constructor
    public function __construct($depLogger, $depDB, $depValidator) {
        $this->logger = $depLogger;
        $this->db = $depDB;
        $this->validator = $depValidator;
    }

    /**
     * @return mixed
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param mixed $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return mixed
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @param mixed $db
     */
    public function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * @return mixed
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * @param mixed $validator
     */
    public function setValidator($validator)
    {
        $this->validator = $validator;
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param mixed $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    public function handle(Request $request, Response $response,$args) {
         $method = $request->getMethod();
        switch ($method) {
            case HttpMethod::GET:
                $response = $this->handleGetRequest($request,$response,$args);
                break;
            case HttpMethod::POST:
                $response = $this->handlePostRequest($request,$response,$args);
                break;
            case HttpMethod::PATCH:
                $response = $this->handlePatchRequest($request,$response,$args);
                break;
            case HttpMethod::PUT:
                $response = $this->handlePutRequest($request,$response,$args);
                break;
            case HttpMethod::DELETE:
                $response = $this->handleDeleteRequest($request,$response,$args);
                break;
            default:
                $response = $this->handleDefaultRequest($request,$response);

        }

        return $response;
    }

    private function handleGetRequest(Request $request , Response $response,$args)
    {
        $validationRules = $this->validateGetRequest();
        $cleanValues = $this->getHandleValidation($request  , $response, $args , $validationRules);
        if($cleanValues instanceof Response){
            return $cleanValues;
        }
        return $this->doGet($request,$response,$args,$cleanValues);

    }
    private function handlePostRequest(Request $request , Response $response,$args)
    {
        $validationRules = $this->validatePostRequest();
        $cleanValues = $this->getHandleValidation($request  , $response, $args , $validationRules);
        if($cleanValues instanceof Response){
            return $cleanValues;
        }
        return $this->doPost($request,$response,$args,$cleanValues);
    }
    private function handlePatchRequest(Request $request , Response $response,$args)
    {
        $validationRules = $this->validatePatchRequest();
        $cleanValues = $this->getHandleValidation($request  , $response, $args , $validationRules);
        if($cleanValues instanceof Response){
            return $cleanValues;
        }
        return $this->doPatch($request,$response,$args,$cleanValues);
    }
    private function handlePutRequest(Request $request , Response $response,$args)
    {
        $validationRules = $this->validatePutRequest();
        $cleanValues = $this->getHandleValidation($request  , $response, $args , $validationRules);
        if($cleanValues instanceof Response){
            return $cleanValues;
        }
        return $this->doPut($request,$response,$args,$cleanValues);
    }
    private function handleDeleteRequest(Request $request , Response $response,$args)
    {
        $validationRules = $this->validateDeleteRequest();
        $cleanValues = $this->getHandleValidation($request  , $response, $args , $validationRules);
        if($cleanValues instanceof Response){
            return $cleanValues;
        }
        return $this->doDelete($request,$response,$args,$cleanValues);
    }
    private function handleDefaultRequest(Request $request , Response $response)
    {
        return $response->withJson([], HttpCode::HTTP_NOT_FOUND);
    }

    protected function handleUnsupportedMethod(Request $request , Response $response){
        return $response->withJson(['errors' => 'Method not allowed'], HttpCode::HTTP_METHOD_NOT_ALLOWED);
    }

    protected function handleValidationFail( $errors, Response $response){


        return $response->withJson([
            'success' => false,
            'errors' => $errors
        ], HttpCode::HTTP_BAD_REQUEST);
    }

    /**
     * Get and sanitize request data
     * 
     * Collects data from route args and request body, sanitizing string inputs
     * to prevent XSS attacks when data is stored and later displayed.
     * 
     * @param Request $request
     * @param array $args
     * @return array
     */
    protected function getRequestData(Request $request, $args)
    {
        $dirty = array();
        
        // Collect route arguments
        foreach ($args as $key => $value) {
            if (!isset($dirty[$key])) {
                $dirty[$key] = $this->sanitizeInput($value);
            }
        }
        
        // Collect request body
        $body = $request->getParsedBody();
        if (is_array($body)) {
            foreach ($body as $key => $value) {
                if (!isset($dirty[$key])) {
                    $dirty[$key] = $this->sanitizeInput($value);
                }
            }
        }
        
        return $dirty;
    }

    /**
     * Sanitize input to prevent XSS
     * 
     * Handles strings, arrays, and other types appropriately.
     * 
     * @param mixed $value
     * @return mixed
     */
    protected function sanitizeInput($value)
    {
        if (is_string($value)) {
            return SecurityUtils::sanitizeInput($value);
        }
        
        if (is_array($value)) {
            return array_map([$this, 'sanitizeInput'], $value);
        }
        
        return $value;
    }

    public abstract function validateGetRequest();
    public abstract function validatePostRequest();
    public abstract function validatePatchRequest();
    public abstract function validatePutRequest();
    public abstract function validateDeleteRequest();

    public abstract function doDelete(Request $request , Response $response,$args,$cleanValues);
    public abstract function doPut(Request $request , Response $response,$args,$cleanValues);
    public abstract function doPatch(Request $request , Response $response,$args,$cleanValues);
    public abstract function doPost(Request $request , Response $response,$args,$cleanValues);
    public abstract function doGet(Request $request , Response $response,$args,$cleanValues);

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @param $validationRules
     */
    public function getHandleValidation(Request $request  , Response $response, $args , $validationRules)
    {
        $dirtyValues = $this->getRequestData($request , $args);
        if(!empty($validationRules)){
            $validator = $this->validator->validate($dirtyValues , $validationRules);
            if (!$validator->isValid()) {
                $errors = $validator->getErrors();
                $cleanValues = $this->handleValidationFail($errors , $response);
            } else {
                $cleanValues = $validator->getValues();
            }
        } else {
            $cleanValues = $dirtyValues;
        }

        return $cleanValues;
    }
}