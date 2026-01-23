<?php
/**
 * Trait for controllers that need helper services
 * 
 * Use this trait in your controller to get access to
 * Cache, HttpClient, and Encryption services via DI.
 * 
 * Usage:
 *   class MyController extends BaseController {
 *       use HelperAware;
 *       
 *       public function doGet($request, $response) {
 *           $cached = $this->cache->get('key');
 *           $encrypted = $this->encryption->encrypt('secret');
 *           $result = $this->http->get('https://api.example.com');
 *       }
 *   }
 * 
 * @package Monstein\Helpers
 */
namespace Monstein\Helpers;

trait HelperAware
{
    /** @var Cache|null */
    protected $cache;

    /** @var HttpClient|null */
    protected $http;

    /** @var Encryption|null */
    protected $encryption;

    /**
     * Set cache service
     * 
     * @param Cache $cache
     * @return void
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Set HTTP client service
     * 
     * @param HttpClient $http
     * @return void
     */
    public function setHttp(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Set encryption service
     * 
     * @param Encryption $encryption
     * @return void
     */
    public function setEncryption(Encryption $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Get cache service
     * 
     * @return Cache|null
     */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
     * Get HTTP client service
     * 
     * @return HttpClient|null
     */
    protected function getHttp()
    {
        return $this->http;
    }

    /**
     * Get encryption service
     * 
     * @return Encryption|null
     */
    protected function getEncryption()
    {
        return $this->encryption;
    }
}
