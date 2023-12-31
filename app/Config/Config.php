<?php
namespace Monstein\Config;

class Config {
    // Database settings
    public function db() {
        return [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => 'monstein',
            'username' => 'root',
            'password' => 'root',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ];
    }
    // Slim settings
    public function slim() {
        return [
            'settings' => [
                'determineRouteBeforeAppMiddleware' => false,
                'displayErrorDetails' => true,
                'db' => self::db()
            ],
        ];
    }
    // Auth settings
    public function auth() {
        return [
            'secret' => 'monsteinbestkeptsecret',
            'expires' => 30, // in minutes
            'hash' => PASSWORD_DEFAULT,
            'jwt' => 'HS256'
        ];
    }
}