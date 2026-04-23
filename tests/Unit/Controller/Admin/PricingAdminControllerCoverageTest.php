<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\PricingAdminController;
use Core\Database;
use Core\Exception\HttpException;
use Model\PricingModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour PricingAdminController.
 * Surcharge requireAdmin()/view()/flash()/verifyCsrf() pour éviter les effets HTTP en CLI.
 */
class TestablePricingAdminController extends PricingAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
    public bool $csrfValid = true;

    /** @var array<string, list<string>> */
    public array $flashes = [];

    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public string $lastViewTemplate = '';

    /** @return array{id: int, role: string, name: string} */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        $this->lastViewTemplate = $template;
        $this->lastViewData     = $data;
    }

    protected function flash(string $key, string $message): void
    {
        $this->flashes[$key][] = $message;
    }

    protected function getFlash(string $key): ?string
    {
        return null;
    }
}

/**
 * Tests de couverture pour PricingAdminController.
 *
 * Cible :
 *   - index()                    : rendu vue avec rules, ccDiscount, csrfToken
 *   - update()                   : CSRF invalide + loop foreach sur ids + AuditLogger::log
 *   - updateClickCollect()       : CSRF invalide + discount négatif → 0 + AuditLogger::log
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class PricingAdminControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');

        $_SESSION                    = ['csrf' => 'valid-csrf'];
        $_POST                       = [];
        $_GET                        = [];
        $_SERVER['REQUEST_METHOD']   = 'POST';
        $_SERVER['REQUEST_URI']      = '/admin/tarifs';
        $_SERVER['REMOTE_ADDR']      = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Injecte un mock Database dans le singleton et retourne le mock.
     *
     * @return \PHPUnit\Framework\MockObject\Stub&Database
     */
    private function stubDb(): Database
    {
        $stub = $this->createStub(Database::class);
        $this->dbInstanceProp->setValue(null, $stub);
        return $stub;
    }

    /**
     * Construit un controller testable avec PricingModel mocké injecté.
     *
     * @param \PHPUnit\Framework\MockObject\MockObject&PricingModel $pricingMock
     * @return TestablePricingAdminController
     */
    private function makeController(
        \PHPUnit\Framework\MockObject\MockObject $pricingMock
    ): TestablePricingAdminController {
        // GET par défaut pour new Request() — override par test si POST
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $ctrl = new TestablePricingAdminController(new \Core\Request());

        $prop = new \ReflectionProperty(PricingAdminController::class, 'pricing');
        $prop->setValue($ctrl, $pricingMock); // NOSONAR — test unitaire

        return $ctrl;
    }

    // ================================================================
    // index()
    // ================================================================

    /**
     * index() rend la vue admin/pricing/index avec rules, ccDiscount, csrfToken.
     */
    public function testIndexRendersViewWithPricingData(): void
    {
        $this->stubDb()->method('fetchOne')->willReturn(['discount_per_bottle' => '2.20']);

        $pricingMock = $this->createMock(PricingModel::class);
        $pricingMock->method('getAll')->willReturn([
            ['id' => 1, 'format' => 'bottle', 'min_quantity' => 1, 'delivery_price' => 7.50],
        ]);

        $ctrl = $this->makeController($pricingMock);
        $ctrl->index([]);

        $this->assertSame('admin/pricing/index', $ctrl->lastViewTemplate);
        $this->assertArrayHasKey('rules', $ctrl->lastViewData);
        $this->assertArrayHasKey('ccDiscount', $ctrl->lastViewData);
        $this->assertArrayHasKey('csrfToken', $ctrl->lastViewData);
        $this->assertSame('pricing', $ctrl->lastViewData['adminSection']);
        $this->assertSame(2.20, $ctrl->lastViewData['ccDiscount']);
    }

    // ================================================================
    // update() — CSRF invalide
    // ================================================================

    /**
     * update() avec CSRF invalide flash erreur et redirige, sans appeler pricing->update.
     */
    public function testUpdateWithInvalidCsrfFlashesErrorAndRedirects(): void
    {
        $this->stubDb();
        $pricingMock = $this->createMock(PricingModel::class);
        $pricingMock->expects($this->never())->method('update');

        $ctrl = $this->makeController($pricingMock);
        $ctrl->csrfValid = false;

        $caught = null;
        try {
            $ctrl->update([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/admin/tarifs', $caught->location);
        $this->assertNotEmpty($ctrl->flashes['error'] ?? []);
        $this->assertStringContainsString('CSRF', $ctrl->flashes['error'][0]);
    }

    // ================================================================
    // update() — loop foreach + ids invalides + appels pricing->update
    // ================================================================

    /**
     * update() itère sur $_POST['id'] et appelle pricing->update pour chaque id positif.
     * Les ids <= 0 sont ignorés.
     */
    public function testUpdateLoopsOverIdsAndSkipsInvalid(): void
    {
        $this->stubDb();
        $pricingMock = $this->createMock(PricingModel::class);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'csrf_token'     => 'valid-csrf',
            'id'             => ['1', '0', '2', '-3'],
            'delivery_1'     => '7,50',  // comma replaced with dot
            'withdrawal_1'   => '0',
            'label_fr_1'     => ' Livraison ', // trimmed
            'label_en_1'     => 'Delivery',
            'active_1'       => '1',
            'min_qty_1'      => '1',
            'max_qty_1'      => '',     // no max
            'delivery_2'     => '5.00',
            'withdrawal_2'   => '0',
            'label_fr_2'     => 'Livraison 6+',
            'label_en_2'     => 'Delivery 6+',
            'active_2'       => '0',    // inactive
            'min_qty_2'      => '6',
            'max_qty_2'      => '12',
        ];

        // Pricing->update doit être appelé 2× (ids 1 et 2 ; 0 et -3 ignorés)
        $calls = [];
        $pricingMock->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (...$args) use (&$calls): void {
                $calls[] = $args;
            });

        $ctrl = $this->makeController($pricingMock);
        $ctrl->csrfValid = true;

        $caught = null;
        try {
            $ctrl->update([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/admin/tarifs', $caught->location);

        // id=1 : delivery=7.5, trim label, active=true, maxQty=null
        // signature : update($id, $delivery, $withdrawal, $labelFr, $labelEn, $active, $minQty, $maxQty)
        $this->assertSame(1, $calls[0][0]);
        $this->assertSame(7.5, $calls[0][1]);
        $this->assertSame('Livraison', $calls[0][3]);
        $this->assertTrue($calls[0][5]); // active
        $this->assertNull($calls[0][7]); // maxQty null

        // id=2 : active=false, maxQty=12
        $this->assertSame(2, $calls[1][0]);
        $this->assertFalse($calls[1][5]);
        $this->assertSame(12, $calls[1][7]);

        $this->assertNotEmpty($ctrl->flashes['success'] ?? []);
    }

    /**
     * update() sans array dans $_POST['id'] saute la boucle sans planter.
     */
    public function testUpdateWithoutIdsSkipsLoop(): void
    {
        $this->stubDb();
        $pricingMock = $this->createMock(PricingModel::class);
        $pricingMock->expects($this->never())->method('update');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'valid-csrf']; // pas de 'id'

        $ctrl = $this->makeController($pricingMock);

        $caught = null;
        try {
            $ctrl->update([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // updateClickCollect() — CSRF invalide
    // ================================================================

    /**
     * updateClickCollect() avec CSRF invalide flash erreur et redirige.
     */
    public function testUpdateClickCollectWithInvalidCsrfRedirects(): void
    {
        $this->stubDb();
        $pricingMock = $this->createMock(PricingModel::class);
        $ctrl = $this->makeController($pricingMock);
        $ctrl->csrfValid = false;

        $caught = null;
        try {
            $ctrl->updateClickCollect([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertStringContainsString('CSRF', $ctrl->flashes['error'][0]);
    }

    // ================================================================
    // updateClickCollect() — remise nominale (virgule) enregistrée
    // ================================================================

    /**
     * updateClickCollect() convertit virgule en point et sauvegarde via ClickCollectSettingsModel.
     */
    public function testUpdateClickCollectSavesDiscountFromCommaDecimal(): void
    {
        $dbStub = $this->stubDb();
        $capturedParams = null;
        $dbStub->method('execute')->willReturnCallback(
            function (string $sql, array $params = []) use (&$capturedParams): int {
                if (str_contains($sql, 'click_collect_settings')) {
                    $capturedParams = $params;
                }
                return 1;
            }
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'csrf_token'  => 'valid-csrf',
            'cc_discount' => '3,75',
        ];

        $pricingMock = $this->createMock(PricingModel::class);
        $ctrl = $this->makeController($pricingMock);
        $ctrl->csrfValid = true;

        $caught = null;
        try {
            $ctrl->updateClickCollect([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/admin/tarifs', $caught->location);
        $this->assertSame(3.75, $capturedParams[0]);
        $this->assertNotEmpty($ctrl->flashes['success'] ?? []);
    }

    /**
     * updateClickCollect() avec discount négatif est clampé à 0 par le model.
     */
    public function testUpdateClickCollectClampsNegativeToZero(): void
    {
        $dbStub = $this->stubDb();
        $capturedParams = null;
        $dbStub->method('execute')->willReturnCallback(
            function (string $sql, array $params = []) use (&$capturedParams): int {
                if (str_contains($sql, 'click_collect_settings')) {
                    $capturedParams = $params;
                }
                return 1;
            }
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'csrf_token'  => 'valid-csrf',
            'cc_discount' => '-2.5',
        ];

        $pricingMock = $this->createMock(PricingModel::class);
        $ctrl = $this->makeController($pricingMock);

        $caught = null;
        try {
            $ctrl->updateClickCollect([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(0.0, $capturedParams[0]);
    }
}
