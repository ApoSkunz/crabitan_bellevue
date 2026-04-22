<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\CartApiController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour CartApiController.
 * Couvre les branches non atteintes par CartApiControllerTest.
 */
class CartApiControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
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

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $dbMock);
        return $dbMock;
    }

    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }

    // ================================================================
    // details — no ids key at all (line 58 empty path)
    // ================================================================

    public function testDetailsReturnsEmptyArrayWhenIdsKeyMissing(): void
    {
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $output = '';
        ob_start();
        try {
            $this->makeController()->details([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $this->assertSame([], json_decode((string) $output, true));
    }

    // ================================================================
    // details — wine with empty image_path (line 73 branch)
    // ================================================================

    public function testDetailsReturnsEmptyImageWhenImagePathIsEmpty(): void
    {
        $_GET = ['ids' => '5'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn([
            'id'              => 5,
            'label_name'      => 'Test',
            'price'           => '8.00',
            'stock_quantity'  => 2,
            'vintage'         => 2021,
            'is_cuvee_speciale' => 0,
            'image_path'      => '',
        ]);

        $output = '';
        ob_start();
        try {
            $this->makeController()->details([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('', $data[0]['image']);
    }

    // ================================================================
    // details — multiple ids, deduplication (line 66)
    // ================================================================

    public function testDetailsDeduplicatesIds(): void
    {
        $_GET = ['ids' => '1,1,2'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn([
            'label_name'      => 'Wine',
            'price'           => '10.00',
            'stock_quantity'  => 5,
            'vintage'         => 2020,
            'is_cuvee_speciale' => 0,
            'image_path'      => 'img.jpg',
        ]);

        $output = '';
        ob_start();
        try {
            $this->makeController()->details([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        // ids 1,1,2 → unique = [1,2] → 2 results
        $this->assertCount(2, $data);
    }

    // ================================================================
    // count — no auth cookie → 401 (line 98)
    // ================================================================

    public function testCountReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->count([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // count — admin role → 403 (line 317-319)
    // ================================================================

    public function testCountReturns403ForAdminRole(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'super_admin')];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->count([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // count — revoked session → 401 (line 322-324)
    // ================================================================

    public function testCountReturns401ForRevokedSession(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        // isActiveByToken returns false
        $dbMock->method('fetchOne')->willReturn(false);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->count([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // add — wine unavailable (available=false) → 422 (line 135-137)
    // ================================================================

    public function testAddReturns422WhenWineIsUnavailable(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'stock_quantity' => 5, 'available' => 0, 'image_path' => '']
        );

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
    // add — capped response includes capped flag (lines 163-168)
    // ================================================================

    public function testAddResponseIncludesCappedFlagWhenQuantityExceedsStock(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 10, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'stock_quantity' => 3, 'available' => 1, 'image_path' => ''],
            false
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->add([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertTrue($data['capped'] ?? false);
        $this->assertSame(3, $data['effective_qty'] ?? null);
        $this->assertSame(3, $data['stock_available'] ?? null);
        $this->assertStringContainsString('Stock', $data['message'] ?? '');
    }

    // ================================================================
    // update — wine not found → 422 (line 216-218)
    // ================================================================

    public function testUpdateReturns422WhenWineNotFound(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 99, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            false,  // cart
            false   // wine
        );

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
    // update — capped response (lines 233-238)
    // ================================================================

    public function testUpdateResponseIncludesCappedFlagWhenQuantityExceedsStock(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 20, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // isActiveByToken
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":1,"qty":2}]', 'total_quantity' => 2],  // cart
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'stock_quantity' => 5, 'available' => 1, 'image_path' => '']  // wine
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->update([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertTrue($data['capped'] ?? false);
        $this->assertSame(5, $data['stock_available'] ?? null);
    }

    // ================================================================
    // update — wine unavailable → 422 (lines 216-218)
    // ================================================================

    public function testUpdateReturns422WhenWineUnavailable(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // isActiveByToken
            false,         // cart
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'stock_quantity' => 5, 'available' => 0, 'image_path' => '']  // wine unavailable
        );

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
    // update — wine with zero stock → 422 (lines 216-218)
    // ================================================================

    public function testUpdateReturns422WhenWineHasZeroStock(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // isActiveByToken
            false,         // cart
            ['id' => 1, 'label_name' => 'Test', 'price' => '10.00', 'stock_quantity' => 0, 'available' => 1, 'image_path' => '']
        );

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
    // remove — empty cart (cart=false) + not found (lines 267-268, 281-283)
    // ================================================================

    public function testRemoveReturns404WhenCartIsEmpty(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 5, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        // isActiveByToken = active, cart = false (no cart)
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            false
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
    // requireCsrf — missing session csrf → 403 (lines 337-340)
    // ================================================================

    public function testAddReturns403WhenCsrfSessionMissing(): void
    {
        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST    = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'any'];
        $_SESSION = []; // no csrf key

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // upsertItem — replace mode, found item (lines 371-375)
    // ================================================================

    public function testUpdateReplacesExistingItemQuantity(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 2, 'quantity' => 4, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // isActiveByToken
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":2,"qty":1}]', 'total_quantity' => 1],  // cart
            ['id' => 2, 'label_name' => 'Test', 'price' => '10.00', 'stock_quantity' => 10, 'available' => 1, 'image_path' => '']  // wine
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->update([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertSame(4, $data['total_quantity'] ?? null); // replaced not cumulated
    }

    // ================================================================
    // upsertItem — add mode, new item (lines 380-385)
    // ================================================================

    public function testAddCreatesNewItemWhenNotInCart(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 3, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $dbMock = $this->setDbMock();
        // Order: isActiveByToken, WineModel::getById(3), CartModel::findByUserId
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // isActiveByToken → active
            ['id' => 3, 'label_name' => 'X', 'price' => '15.00', 'stock_quantity' => 10, 'available' => 1, 'image_path' => ''],  // wine
            ['id' => 1, 'user_id' => 1, 'content' => '[{"wine_id":1,"qty":1}]', 'total_quantity' => 1]  // cart
        );
        $dbMock->method('execute')->willReturn(1);

        $output = '';
        ob_start();
        try {
            $this->makeController()->add([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertSame(3, $data['total_quantity'] ?? null); // 1 + 2
        $this->assertCount(2, $data['items'] ?? []);
    }

    // ================================================================
    // count — cart has items (line 101 branch existing = false)
    // ================================================================

    public function testCountReturnsZeroWhenCartNotFound(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->setDbMock();
        // isActiveByToken = active, cart findByUserId = false
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            false
        );

        $output = '';
        ob_start();
        try {
            $this->makeController()->count([]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
        }

        $data = json_decode((string) $output, true);
        $this->assertSame(0, $data['total_quantity'] ?? -1);
    }
}
