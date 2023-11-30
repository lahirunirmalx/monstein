<?php

use Symfony\Component\Yaml\Yaml;

require '../../vendor/autoload.php';
$value = Yaml::parseFile('../../app/Config/routing.yml');
\Monstein\Base\BaseRouter::getInstance()->initRouting($value);

$app = (new \Monstein\App())->get();

try {
    $app->run();
} catch (Throwable $e) {

}
