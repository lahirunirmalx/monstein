<?php
namespace Monstein;

use Monstein\Base\BaseRouter;

class App {
    private $app;
    
    public function __construct() {
        // initalize Slim App
        $app = new \Slim\App(\Monstein\Config\Config::slim());
        $this->app = $app;
        // initalize dependencies
        $this->dependencies();
        // initalize middlewares
        $this->middleware();
        // initalize routes
        $this->routes();
    }
    
    public function get() {
        return $this->app;
    }
    
    private function dependencies() {
        return new \Monstein\Dependencies($this->app);
    }
    
    private function middleware() {
        return new \Monstein\Middleware($this->app);
    }
    
    private function routes() {
        $urls = BaseRouter::getInstance()->getRoutes();
        $routes = array ();
        foreach ($urls as $url => $param ){
            $routes[] = $this->app->map($param['method'],$url, $param['controller'].":".$param['service']);
        }

        return $routes;
    }
}