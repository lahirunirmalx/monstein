<?php

namespace Monstein\Tests;

use Monstein\App;
use Monstein\Models\User;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    /** @var \Slim\App */
    protected $app;

    /** @var Helper */
    private $helper;

    protected function setUp(): void
    {
        $this->app = (new App())->get();
        $this->helper = new Helper($this->app);

        // Delete user if exists
        if ($user = User::where('username', 'phpunit')->first()) {
            $user->forceDelete();
        }
    }

    /**
     * @return array<array<string>>
     */
    public function dataUsersInvalidPost(): array
    {
        return [
            ['phpunit$$%@', 'Testing123'], // user has symbols
            ['a', 'Testing123'], // user too short
            ['phpunittt', 'a'], // pass too short
            ['abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', 'testtest'], // user too long
            ['phpunittt', 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'], // pass too long
        ];
    }

    /**
     * @dataProvider dataUsersInvalidPost
     */
    public function testUsersInvalidPost(string $username, string $password): void
    {
        $data = $this->helper->apiTest('post', '/users', false, ['username' => $username, 'password' => $password]);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    public function testUsersPost(): int
    {
        $data = $this->helper->apiTest('post', '/users', false, ['username' => 'phpunit', 'password' => 'phpunit']);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
        return $data['data']['id'];
    }

    /**
     * @depends testUsersPost
     */
    public function testUsersDuplicatePost(int $userId): void
    {
        $this->helper->getAuthToken(); // make sure phpunit user created
        $data = $this->helper->apiTest('post', '/users', false, ['username' => 'phpunit', 'password' => 'phpunit']);
        $this->assertSame(400, $data['code']);
    }

    /**
     * @return array<array<string>>
     */
    public function dataUsersLoginInvalidPost(): array
    {
        return [
            ['phpunit$$%@', 'Testing123'], // user has symbols
            ['a', 'Testing123'], // user too short
            ['phpunittt', 'a'], // pass too short
            ['abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', 'testtest'], // user too long
            ['phpunit', 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'], // pass too long
            ['phpunitnotexist', 'Testing123'], // username doesn't exist
            ['phpunit', 'InvalidTesting123'] // user valid, invalid password
        ];
    }

    /**
     * @dataProvider dataUsersLoginInvalidPost
     */
    public function testUsersLoginInvalidPost(string $username, string $password): void
    {
        $this->helper->getAuthToken(); // make sure phpunit user created
        $data = $this->helper->apiTest('post', '/issueToken', false, ['username' => $username, 'password' => $password]);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    public function testUsersLoginPost(): void
    {
        $this->helper->getAuthToken(); // make sure phpunit user created
        $data = $this->helper->apiTest('post', '/issueToken', false, ['username' => 'phpunit', 'password' => 'phpunit']);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    protected function tearDown(): void
    {
        // Cleanup if needed
    }
}
