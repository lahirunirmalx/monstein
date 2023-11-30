<?php
namespace Monstein\Base;




class BaseRouter{
    private static $instances = [];
    private static $routes = [];
    private static $nonSecure = [];


    protected function __construct() { }


    protected function __clone() { }


    /**
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }


    public static function getInstance()
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }


    public function initRouting($routes )
    {

         foreach ($routes as $key =>$route ){
             $url ='';
             $isSecure = true;
             if(isset($route['version'])){
                 $version = $route['version'];
                 $url.="/V{$version}";
             }
             $url.=  $route['url'];
             if(isset($route['is_secure'])){
                 $isSecure = boolval($route['is_secure']);
             }
             if($isSecure === false){
                  self::$nonSecure[$url]= $url;
             }
             $method = $this->getMethods($route['method']);
             $service = 'handle';
             if(isset($route['service'])){
                 $service = $route['service'];
             }
             $controller = $route['controller'];
             if(!empty($url) && is_array($method) && !empty($controller) ){
                 self::$routes[$url] =  array('method'=>$method,'controller'=>$controller,'service'=>$service);
             }
         }
    }

    public function getRoutes()
    {
        return self::$routes;
    }
    public function getIgnorePaths()
    {
        return self::$nonSecure;
    }

    protected function getMethods($methods){
        $restMethods = array();
        foreach ($methods as $method){
            $method = strtoupper($method);
            $restMethods[$method]=1;
        }
        if(empty($restMethods)){
            $restMethods = array ('GET'=>1);
        }
        return array_keys($restMethods);
    }
}
