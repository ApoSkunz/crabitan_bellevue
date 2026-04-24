<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\MarketingAdminController;
use Core\Exception\HttpException;
use Model\AccountModel;
use Model\NewsletterModel;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour MarketingAdminController.
 * Surcharge requireAdmin()/view()/abort()/flash() pour éviter les effets de bord HTTP.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableMarketingAdminController extends MarketingAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'email' => 'admin@test.com', 'name' => 'Admin'];

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return true;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    protected function abort(int $status = 404, string $message = ''): never
    {
        throw new HttpException($status);
    }
}

/**
 * Tests de couverture pour MarketingAdminController.
 *
 * Cible :
 *   L51 — catch (\Throwable) quand RatingModel::getTopRated() lève une exception
 *          (table wine_ratings absente en migration partielle).
 */
#[CoversClass(MarketingAdminController::class)]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class MarketingAdminControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(\Core\Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/marketing';
        $_ENV['APP_URL'] ??= 'http://crabitan.local';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Construit un controller avec des modèles stubés qui retournent des valeurs valides.
     * Laisse RatingModel être instancié normalement via la BDD stubée.
     *
     * @return TestableMarketingAdminController
     */
    private function makeController(): TestableMarketingAdminController
    {
        $dbMock = $this->createStub(\Core\Database::class);

        // OrderModel::getAvgOrderValue → fetchOne
        // OrderModel::getConversionStats → fetchOne
        $dbMock->method('fetchOne')->willReturn([
            'avg'    => 0.0,
            'rate'   => 0.0,
            'orders' => 0,
            'carts'  => 0,
        ]);
        // AccountModel::getSegmentation → fetchAll
        // NewsletterModel::getStats → fetchAll
        // RatingModel::getTopRated → fetchAll
        $dbMock->method('fetchAll')->willReturn([]);

        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        return new TestableMarketingAdminController(new \Core\Request());
    }

    /**
     * index() absorbe silencieusement l'exception Throwable levée par RatingModel::getTopRated()
     * quand la table wine_ratings n'existe pas (migration partielle).
     * Couvre L51 — le bloc catch (\Throwable).
     */
    public function testIndexCatchesRatingModelExceptionAndContinues(): void
    {
        $dbMock = $this->createStub(\Core\Database::class);
        $dbMock->method('fetchOne')->willReturn([
            'avg'    => 50.0,
            'rate'   => 0.1,
            'orders' => 5,
            'carts'  => 50,
        ]);
        // fetchAll lève une exception simulant une table manquante
        $dbMock->method('fetchAll')->willThrowException(
            new \RuntimeException('Table wine_ratings does not exist')
        );
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $ctrl = new TestableMarketingAdminController(new \Core\Request());

        // OrderModel & NewsletterModel utilisent fetchAll aussi — on injecte des stubs
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('getAvgOrderValue')->willReturn(50.0);
        $ordersMock->method('getConversionStats')->willReturn([
            'rate'   => 0.1,
            'orders' => 5,
            'carts'  => 50,
        ]);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('getSegmentation')->willReturn([]);

        $newsletterMock = $this->createStub(NewsletterModel::class);
        $newsletterMock->method('getStats')->willReturn([]);

        $refOrders = new \ReflectionProperty(MarketingAdminController::class, 'orders');
        $refOrders->setValue($ctrl, $ordersMock);

        $refAccounts = new \ReflectionProperty(MarketingAdminController::class, 'accounts');
        $refAccounts->setValue($ctrl, $accountsMock);

        $refNewsletter = new \ReflectionProperty(MarketingAdminController::class, 'newsletter');
        $refNewsletter->setValue($ctrl, $newsletterMock);

        // La BDD lèvera une exception lors de RatingModel::getTopRated → catch absorbe
        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException, 'index() doit absorber l\'exception RatingModel sans la propager');
    }
}
