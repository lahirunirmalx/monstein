<?php
namespace Monstein;

use Monstein\Base\BaseRouter;
use Monstein\Base\HttpCode;

class Dependencies {
    private $container;
    
    function __construct($app) {
        $container = $app->getContainer(); // Dependency injection container
        $this->container = $container;
        $this->dependencies(); // Load dependencies into container
        $this->inject($app); // Inject dependencies into controllers
        $this->handlers(); // Set custom handlers
    }
    
    // Setup dependency container
    function dependencies() {
        // Monolog
        $this->container['logger'] = function($c) {
            $logger = new \Monolog\Logger('myLogger');
            $file_handler = new \Monolog\Handler\StreamHandler('../logs/app.log');
            $logger->pushHandler($file_handler);
            return $logger;
        };
        // Eloquent ORM
        $this->container['db'] = function($c) {
            $capsule = new \Illuminate\Database\Capsule\Manager;
            $capsule->addConnection($c['settings']['db']);

            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            return $capsule;
        };
        // awurth/SlimValidation
        $this->container['validator'] = function($c) {
            return new \Awurth\SlimValidation\Validator();
        };
    }
    
    // Inject dependencies into controllers
    function inject($app) {

        $urls = BaseRouter::getInstance()->getRoutes();
        foreach ($urls as $url => $param ){
            $this->container[$param['controller']] = function($c) use ($app,$param) {
                return new $param['controller']($c->get('logger'), $c->get('db'), $c->get('validator'));
            };
        }

    }
    
    // Custom handlers
    function handlers() {
        // 404 custom response
        $this->container['notFoundHandler'] = function($c) {
            return function($request, $response) use ($c) {
                return $c['response']->withJson(['errors' => 'Resource not found'], HttpCode::HTTP_NOT_FOUND);
            };
        };
        $this->container['notAllowedHandler'] = function($c) {
            return function($request, $response) use ($c) {
                return $c['response']->withJson(['errors' => 'Method not allowed'], HttpCode::HTTP_METHOD_NOT_ALLOWED);
            };
        };
//        $this->container['phpErrorHandler'] = function($c) {
//            return function($request, $response) use ($c) {
//                return $c['response']->withJson(['errors' => 'INTERNAL_SERVER_ERROR'], HttpCode::HTTP_INTERNAL_SERVER_ERROR);
//            };
//        };
//        $this->container['errorHandler'] = function($c) {
//            return function($request, $response) use ($c) {
//                return $c['response']->withJson(['errors' => 'INTERNAL_SERVER_ERROR'], HttpCode::HTTP_INTERNAL_SERVER_ERROR);
//            };
//        };


    }
}