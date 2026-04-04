<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\CartApiController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CartApiController.
 * La base de données est mockée — aucune connexion réelle requise.
 */
class CartApiControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/api/cart/add';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_ENV['JWT_SECRET']         = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): CartApiController
    {
        return new CartApiController(new Request());
    }

    /**
     * Remplace le singleton Database par un stub configurable.
     *
     * @return \PHPUnit\Framework\MockObject\Stub&Database
     */
    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $dbMock);
        return $dbMock;
    }

    // ================================================================
    // details
    // ================================================================

    public function testDetailsReturnsEmptyArrayWhenNoIds(): void
    {
        $_GET = ['ids' => ''];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $output = '';
        ob_start();
        try {
            $this->makeController()->details([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $this->assertSame([], json_decode((string) $output, true));
    }

    public function testDetailsReturnsWineDataForValidId(): void
    {
        $_GET = ['ids' => '1'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn([
            'label_name' => 'Bordeaux',
            'price'      => '12.50',
            'quantity'   => 10,
            'image_path' => 'bordeaux.jpg',
        ]);

        $output = '';
        ob_start();
        try {
            $this->makeController()->details([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame(1, $data[0]['wine_id']);
        $this->assertSame('Bordeaux', $data[0]['name']);
        $this->assertSame(12.5, $data[0]['price']);
    }

    public function testDetailsSkipsUnknownWine(): void
    {
        $_GET = ['ids' => '99'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(false);

        $output = '';
        ob_start();
        try {
            $this->makeController()->details([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $this->assertSame([], json_decode((string) $output, true));
    }

    // ================================================================
    // count
    // ================================================================

    public function testCountReturnsZeroWhenNoCart(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(false);

        $output = '';
        ob_start();
        try {
            $this->makeController()->count([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertSame(0, $data['total_quantity']);
    }

    public function testCountReturnsTotalQuantityWhenCartHasItems(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn([
            'id'             => 1,
            'user_id'        => 1,
            'content'        => '[{"wine_id":1,"qty":3},{"wine_id":2,"qty":2}]',
            'total_quantity' => 5,
        ]);

        $output = '';
        ob_start();
        try {
            $this->makeController()->count([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertSame(5, $data['total_quantity']);
    }

    // ================================================================
    // add — guards (auth / csrf / wine_id)
    // ================================================================

    public function testAddReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testAddReturns403WhenCalledByAdmin(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'admin')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testAddReturns403WhenCsrfInvalid(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'INVALID-TOKEN'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testAddReturns422WhenWineIdMissing(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testAddReturns422WhenWineNotFound(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 99, 'quantity' => 1, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(false);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testAddReturns422WhenWineOutOfStock(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn([
            'id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'quantity' => 0, 'image_path' => '',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // add — happy paths
    // ================================================================

    public function testAddSuccessCreatesNewItem(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        // fetchOne : 1er appel WineModel::getById, 2e appel CartModel::findByUserId
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'quantity' => 5, 'image_path' => ''],
            false
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->add([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['total_quantity']);
        $this->assertCount(1, $data['items']);
    }

    public function testAddCumulatesExistingItem(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'quantity' => 10, 'image_path' => ''],
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":1,"qty":3}]', 'total_quantity' => 3]
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->add([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success']);
        $this->assertSame(5, $data['total_quantity']); // 3 + 2
    }

    public function testAddCapsQuantityAtStock(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 10, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'quantity' => 3, 'image_path' => ''],
            false
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->add([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success']);
        $this->assertSame(3, $data['total_quantity']); // plafonné au stock
    }

    // ================================================================
    // update — guards
    // ================================================================

    public function testUpdateReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_POST   = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->update([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testUpdateReturns403WhenCsrfInvalid(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'BAD'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->update([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testUpdateReturns422WhenWineIdMissing(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 0, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->update([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // update — happy paths
    // ================================================================

    public function testUpdateRemovesItemWhenQuantityIsZero(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 0, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":1,"qty":3}]', 'total_quantity' => 3]
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->update([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success']);
        $this->assertSame(0, $data['total_quantity']);
        $this->assertCount(0, $data['items']);
    }

    public function testUpdateReplacesQuantity(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 5, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        // fetchOne : 1er appel CartModel::findByUserId, 2e appel WineModel::getById
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":1,"qty":3}]', 'total_quantity' => 3],
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'quantity' => 10, 'image_path' => '']
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->update([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success']);
        $this->assertSame(5, $data['total_quantity']); // remplacé, pas cumulé
    }

    // ================================================================
    // remove — guards
    // ================================================================

    public function testRemoveReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_POST   = ['wine_id' => 1, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testRemoveReturns403WhenCsrfInvalid(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'csrf_token' => 'WRONG'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testRemoveReturns422WhenWineIdMissing(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 0, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }

    public function testRemoveReturns404WhenItemNotInCart(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 99, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":2,"qty":3}]', 'total_quantity' => 3]
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // remove — happy path
    // ================================================================

    public function testRemoveSuccessfully(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":1,"qty":3}]', 'total_quantity' => 3]
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->remove([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success']);
        $this->assertSame(0, $data['total_quantity']);
    }

    // ================================================================
    // Helper : génère un JWT valide pour les tests
    // ================================================================

    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }
}
