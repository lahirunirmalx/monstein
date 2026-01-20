<?php

namespace Monstein\Tests;

use Monstein\App;
use Monstein\Models\Todo;
use PHPUnit\Framework\TestCase;

class TodoTest extends TestCase
{
    /** @var \Slim\App */
    protected $app;

    /** @var Helper */
    private $helper;

    /** @var string */
    private $token;

    /** @var int */
    private $category;

    protected function setUp(): void
    {
        $this->app = (new App())->get();
        $this->helper = new Helper($this->app);
        $this->token = $this->helper->getAuthToken();
        $this->category = $this->helper->apiTest('post', '/categories', $this->token, ['name' => 'PHPUnit'])['data']['id'];
    }

    public function testTodoGet(): void
    {
        $data = $this->helper->apiTest('get', '/todo', $this->token);
        $this->assertSame(200, $data['code']);
    }

    /**
     * @return array<array<mixed>>
     */
    public function dataTodoInvalidPost(): array
    {
        return [
            [null, 'abc $#$@'], // symbols
            [null, 'a'], // too short
            [null, 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'], // too long
            ['test', 'abc $#$@'], // invalid non-numeric category
            [99999, 'PHPUnit Test'], // invalid category
        ];
    }

    /**
     * @dataProvider dataTodoInvalidPost
     * @param mixed $category
     */
    public function testTodoInvalidPost($category, string $name): void
    {
        $category = $category ? $category : $this->category;
        $data = $this->helper->apiTest('post', '/todo', $this->token, ['category' => $category, 'name' => $name]);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    public function testTodoPost(): int
    {
        $data = $this->helper->apiTest('post', '/todo', $this->token, ['category' => $this->category, 'name' => 'PHPUnit Todo']);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
        return $data['data']['id'];
    }

    /**
     * @depends testTodoPost
     */
    public function testTodoDuplicatePost(int $todoId): int
    {
        $data = $this->helper->apiTest('post', '/todo', $this->token, ['category' => $this->category, 'name' => 'PHPUnit Todo2']);
        $this->assertSame(200, $data['code']);
        return $data['data']['id'];
    }

    /**
     * @depends testTodoPost
     */
    public function testTodoIdGet(int $todoId): void
    {
        $data = $this->helper->apiTest('get', '/todo/' . $todoId, $this->token);
        $this->assertSame(200, $data['code']);
        $this->assertSame($todoId, $data['data']['data']['id']);
    }

    /**
     * @return array<array<mixed>>
     */
    public function dataTodoInvalidPut(): array
    {
        return [
            [null, 'Todo Item #$#@#$', null], // symbols
            [null, 'a', null], // too short
            [null, 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', null], // too long
            [null, 'Testing', 9999], // invalid todo ID
            [null, 'PHPUnit Todo2', null], // duplicate
            [9999, 'Testing', null] // invalid category
        ];
    }

    /**
     * @dataProvider dataTodoInvalidPut
     * @depends testTodoPost
     * @param mixed $category
     * @param int|null $overwriteTodoId
     */
    public function testTodoInvalidPut($category, string $name, $overwriteTodoId, int $todoId): void
    {
        $category = $category ? $category : $this->category;
        $todoId = $overwriteTodoId ? $overwriteTodoId : $todoId;
        $data = $this->helper->apiTest('put', '/todo/' . $todoId, $this->token, ['category' => $category, 'name' => $name]);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    /**
     * @depends testTodoPost
     */
    public function testTodoUserRelationship(int $todoId): void
    {
        $todo = Todo::find($todoId);
        $this->assertTrue(is_numeric($todo->user->id));
    }

    /**
     * @depends testTodoPost
     */
    public function testTodoPut(int $todoId): void
    {
        $data = $this->helper->apiTest('put', '/todo/' . $todoId, $this->token, ['name' => 'PHPUnit Todo U ' . time()]);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    /**
     * @return array<array<mixed>>
     */
    public function dataTodoInvalidDelete(): array
    {
        return [
            ['testabc123'], // string
            [9999] // invalid
        ];
    }

    /**
     * @dataProvider dataTodoInvalidDelete
     * @param mixed $todoId
     */
    public function testTodoInvalidDelete($todoId): void
    {
        $data = $this->helper->apiTest('delete', '/todo/' . $todoId, $this->token);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    /**
     * @depends testTodoPost
     */
    public function testTodoDelete(int $todoId): void
    {
        $data = $this->helper->apiTest('delete', '/todo/' . $todoId, $this->token);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    /**
     * @depends testTodoPost
     */
    public function testTodoDeleteForce(int $todoId): void
    {
        $data = $this->helper->apiTest('delete', '/todo/' . $todoId, $this->token, ['force' => true]);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    /**
     * @depends testTodoDuplicatePost
     */
    public function testTodoTeardown(int $todoDupId): void
    {
        $this->helper->apiTest('delete', '/category/' . $this->category, $this->token, ['force' => true]);
        $data = $this->helper->apiTest('delete', '/todo/' . $todoDupId, $this->token, ['force' => true]);
        $this->assertSame(200, $data['code']);
    }

    protected function tearDown(): void
    {
        // Cleanup if needed
    }
}
