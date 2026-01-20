<?php

namespace Monstein\Tests;

use Monstein\App;
use Slim\Http\Environment;
use Slim\Http\Request;
use Monstein\Models\User;

/**
 * Test helper class for API testing
 */
class Helper
{
    /** @var \Slim\App */
    private $app;

    /** @var string|null */
    private $token;

    /**
     * @param \Slim\App $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Simulates queries to our REST API using mock environment
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param string|false $token JWT token or false
     * @param array $postData POST data
     * @return array{code: int, data: array|null}
     */
    public function apiTest(string $method, string $endpoint, $token = false, array $postData = []): array
    {
        $envOptions = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $endpoint
        ];

        if ($postData) {
            $envOptions['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        }

        $env = Environment::mock($envOptions);

        if ($token) {
            $request = $postData
                ? Request::createFromEnvironment($env)
                    ->withHeader('Authorization', 'Bearer ' . $token)
                    ->withParsedBody($postData)
                : Request::createFromEnvironment($env)
                    ->withHeader('Authorization', 'Bearer ' . $token);
        } else {
            $request = $postData
                ? Request::createFromEnvironment($env)->withParsedBody($postData)
                : Request::createFromEnvironment($env);
        }

        $this->app->getContainer()['request'] = $request;
        $response = $this->app->run(true);

        return [
            'code' => $response->getStatusCode(),
            'data' => json_decode((string) $response->getBody(), true)
        ];
    }

    /**
     * Creates phpunit user and returns auth token
     * 
     * @return string JWT token
     */
    public function getAuthToken(): string
    {
        $user = User::where('username', 'phpunit')->first();

        if (!$user) {
            $user = User::create([
                'username' => 'phpunit',
                'password' => 'phpunit'
            ]);
        }

        $token = $user->tokenCreate();
        return $token['token'];
    }
}
