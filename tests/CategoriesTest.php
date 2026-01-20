<?php

namespace Monstein\Tests;

use Monstein\App;
use Monstein\Models\Category;
use PHPUnit\Framework\TestCase;

class CategoriesTest extends TestCase
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

    public function testCategoriesNoAuthGet(): void
    {
        $data = $this->helper->apiTest('get', '/categories');
        $this->assertSame(401, $data['code']);
    }

    public function testCategoriesGet(): void
    {
        $data = $this->helper->apiTest('get', '/categories', $this->token);
        $this->assertSame(200, $data['code']);
    }

    /**
     * @return array<array<string>>
     */
    public function dataCategoriesInvalidPost(): array
    {
        return [
            ['PHPUnit #$W@@$ ' . time()], // symbols
            ['a'], // too short
            ['abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'] // too long
        ];
    }

    /**
     * @dataProvider dataCategoriesInvalidPost
     */
    public function testCategoriesInvalidPost(string $name): void
    {
        $data = $this->helper->apiTest('post', '/categories', $this->token, [
            'name' => $name
        ]);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    public function testCategoriesPost(): int
    {
        $data = $this->helper->apiTest('post', '/categories', $this->token, [
            'name' => 'PHPUnit'
        ]);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
        return $data['data']['id'];
    }

    /**
     * @depends testCategoriesPost
     */
    public function testCategoriesUserRelationship(int $categoryId): void
    {
        $category = Category::find($categoryId);
        $this->assertTrue(is_numeric($category->user->id));
    }

    public function testCategoriesPostDuplicate(): void
    {
        $data = $this->helper->apiTest('post', '/categories', $this->token, [
            'name' => 'PHPUnit2'
        ]);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    /**
     * @return array<array<mixed>>
     */
    public function dataCategoriesInvalidIdGet(): array
    {
        return [
            ['stringID'], // non-numeric
            [-10], // negative
            [9999] // invalid
        ];
    }

    /**
     * @dataProvider dataCategoriesInvalidIdGet
     * @param mixed $categoryId
     */
    public function testCategoriesInvalidIdGet($categoryId): void
    {
        $data = $this->helper->apiTest('get', '/category/' . $categoryId, $this->token);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    /**
     * @depends testCategoriesPost
     */
    public function testCategoriesIdGet(int $categoryId): void
    {
        $data = $this->helper->apiTest('get', '/category/' . $categoryId, $this->token);
        $this->assertSame(200, $data['code']);
        $this->assertSame($categoryId, $data['data']['data']['id']);
    }

    /**
     * @depends testCategoriesPost
     */
    public function testCategoriesTodosGet(int $categoryId): void
    {
        $data = $this->helper->apiTest('get', '/category/' . $categoryId . '/todos', $this->token);
        $this->assertSame(200, $data['code']);
        $this->assertTrue(is_array($data['data']['data']));
    }

    /**
     * @return array<array<mixed>>
     */
    public function dataCategoriesPutInvalid(): array
    {
        return [
            [null, 'PHPUnit #$W@@$ ' . time()], // symbols
            [null, 'a'], // too short
            [null, 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'], // too long
            [9999, 'testing'], // invalid category
            [null, 'PHPUnit2'] // duplicate name
        ];
    }

    /**
     * @dataProvider dataCategoriesPutInvalid
     * @depends testCategoriesPost
     * @param int|null $overwriteCategory
     */
    public function testCategoriesPutInvalid($overwriteCategory, string $name, int $categoryId): void
    {
        $categoryId = $overwriteCategory ? $overwriteCategory : $categoryId;
        $data = $this->helper->apiTest('put', '/category/' . $categoryId, $this->token, ['name' => $name]);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    /**
     * @depends testCategoriesPost
     */
    public function testCategoriesPut(int $categoryId): void
    {
        $data = $this->helper->apiTest('put', '/category/' . $categoryId, $this->token, [
            'name' => 'PHPUnit U ' . time()
        ]);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    /**
     * @return array<array<mixed>>
     */
    public function dataCategoriesDeleteInvalid(): array
    {
        return [
            ['IDasString'], // string
            [99999] // invalid
        ];
    }

    /**
     * @dataProvider dataCategoriesDeleteInvalid
     * @param mixed $categoryId
     */
    public function testCategoriesDeleteInvalid($categoryId): void
    {
        $data = $this->helper->apiTest('delete', '/category/' . $categoryId, $this->token);
        $this->assertSame(400, $data['code']);
        $this->assertFalse($data['data']['success']);
    }

    /**
     * @depends testCategoriesPost
     */
    public function testCategoriesDeleteSoft(int $categoryId): void
    {
        $data = $this->helper->apiTest('delete', '/category/' . $categoryId, $this->token);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }

    /**
     * @depends testCategoriesPost
     */
    public function testCategoriesDeleteForce(int $categoryId): void
    {
        $data = $this->helper->apiTest('delete', '/category/' . $categoryId, $this->token, ['force' => true]);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['success']);
    }
}
