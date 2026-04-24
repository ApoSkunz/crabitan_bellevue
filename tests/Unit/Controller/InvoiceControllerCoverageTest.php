<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\InvoiceController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour InvoiceController.
 * Surcharge abort()/view() pour éviter les effets de bord HTTP.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableInvoiceController extends InvoiceController
{
    protected function abort(int $status = 404, string $message = ''): never
    {
        throw new HttpException($status);
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op
    }
}

/**
 * Tests de couverture pour InvoiceController.
 *
 * Cible :
 *   L72 — download() : branche admin (role = 'admin' ou 'super_admin')
 *          → appelle $this->orders->findByIdForAdmin($orderId)
 *          La branche else (L75) est déjà couverte ; cette branche admin ne l'est pas.
 */
#[CoversClass(InvoiceController::class)]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class InvoiceControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/commandes/1/facture';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_ENV['APP_URL'] ??= 'http://crabitan.local';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_COOKIE  = [];
    }

    /**
     * Génère un JWT valide pour les tests.
     *
     * @param int    $userId Identifiant de l'utilisateur
     * @param string $role   Rôle (admin, super_admin, customer…)
     * @return string Token JWT signé
     */
    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }

    /**
     * download() avec un rôle 'admin' appelle findByIdForAdmin() (L72).
     * Le résultat étant null → abort(404) déclenche HttpException.
     */
    public function testDownloadAsAdminCallsFindByIdForAdmin(): void
    {
        $_COOKIE['auth_token'] = $this->makeValidJwt(1, 'admin');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Stub DB : AuthMiddleware.fetchOne retourne le payload (token JWT décodé, pas BDD)
        // puis OrderModel::findByIdForAdmin retourne null → abort(404)
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->expects($this->once())
            ->method('findByIdForAdmin')
            ->with(42)
            ->willReturn(null);
        // findByIdForUser ne doit pas être appelé pour un admin
        $ordersMock->expects($this->never())
            ->method('findByIdForUser');

        $ctrl = new TestableInvoiceController(new Request());

        $refOrders = new \ReflectionProperty(InvoiceController::class, 'orders');
        $refOrders->setValue($ctrl, $ordersMock);

        $caught = null;
        try {
            $ctrl->download(['lang' => 'fr', 'id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(404, $caught->status);
    }

    /**
     * download() avec un rôle 'super_admin' appelle aussi findByIdForAdmin() (L72).
     */
    public function testDownloadAsSuperAdminCallsFindByIdForAdmin(): void
    {
        $_COOKIE['auth_token'] = $this->makeValidJwt(2, 'super_admin');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 2]);
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->expects($this->once())
            ->method('findByIdForAdmin')
            ->with(7)
            ->willReturn(null);
        $ordersMock->expects($this->never())
            ->method('findByIdForUser');

        $ctrl = new TestableInvoiceController(new Request());

        $refOrders = new \ReflectionProperty(InvoiceController::class, 'orders');
        $refOrders->setValue($ctrl, $ordersMock);

        $caught = null;
        try {
            $ctrl->download(['lang' => 'fr', 'id' => '7']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(404, $caught->status);
    }
}
