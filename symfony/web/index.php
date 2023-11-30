<?php

use Symfony\Component\Yaml\Yaml;

require '../../vendor/autoload.php';
$value = Yaml::parseFile('../../app/Config/routing.yml');
\Monstein\Base\BaseRouter::getInstance()->initRouting($value);
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../..');
$dotenv->load();
$app = (new \Monstein\App())->get();

try {
    $app->run();
} catch (Throwable $e) {

}
