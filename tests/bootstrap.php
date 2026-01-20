<?php
/**
 * PHPUnit Bootstrap File
 * 
 * This file is loaded before tests run.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables for testing
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Initialize routing for tests
$routingPath = dirname(__DIR__) . '/app/Config/routing.yml';
if (file_exists($routingPath)) {
    $routes = \Symfony\Component\Yaml\Yaml::parseFile($routingPath);
    \Monstein\Base\BaseRouter::getInstance()->initRouting($routes);
}
