<?php

namespace Monstein\Tests;

use Monstein\App;
use PHPUnit\Framework\TestCase;

class HandlerTest extends TestCase
{
    /** @var \Slim\App */
    protected $app;

    /** @var Helper */
    private $helper;

    /** @var string */
    private $token;

    protected function setUp(): void
    {
        $this->app = (new App())->get();
        $this->helper = new Helper($this->app);
        $this->token = $this->helper->getAuthToken();
    }

    public function testHandlerCustom404(): void
    {
        $data = $this->helper->apiTest('get', '/bad/url/here', $this->token);
        $this->assertSame(404, $data['code']);
    }
}
