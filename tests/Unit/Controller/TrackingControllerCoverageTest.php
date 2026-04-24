<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\TrackingController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Core\Response;
use Model\AccountModel;
use Model\NewsletterTrackingModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour TrackingController.
 * Surcharge abort() pour éviter les effets de bord HTTP (exit/redirect).
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableTrackingController extends TrackingController
{
    protected function abort(int $status = 404, string $message = ''): never
    {
        throw new HttpException($status);
    }

    protected function sendPixel(): void
    {
        // no-op : évite les headers réels dans les tests CLI
    }
}

/**
 * Tests de couverture pour TrackingController.
 *
 * Cible :
 *   L93 — click() : l'URL du lien échoue la validation (non-HTTPS ou invalide)
 *          → $this->abort(404) est appelé.
 *          Branches :
 *            a) URL non-HTTPS mais valide (ex. http://...)
 *            b) URL vide / non valide selon FILTER_VALIDATE_URL
 */
#[CoversClass(TrackingController::class)]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TrackingControllerCoverageTest extends TestCase
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

        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/track/click/1/2/tok';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
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
     * Construit un controller avec les modèles stubés.
     *
     * @param array<string, mixed>|null $linkRow  Ligne retournée par findLink() (null = lien absent)
     * @param array<string, mixed>|false $subscriber Abonné retourné par findByUnsubscribeToken()
     * @return TestableTrackingController
     */
    private function makeController(
        ?array $linkRow,
        array|false $subscriber = false
    ): TestableTrackingController {
        $trackingMock = $this->createStub(NewsletterTrackingModel::class);
        $trackingMock->method('findLink')->willReturn($linkRow);
        $trackingMock->method('recordClick')->willReturnSelf(); // void method stub

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByUnsubscribeToken')->willReturn($subscriber);

        $ctrl = new TestableTrackingController(new Request());

        $refTracking = new \ReflectionProperty(TrackingController::class, 'tracking');
        $refTracking->setValue($ctrl, $trackingMock);

        $refAccounts = new \ReflectionProperty(TrackingController::class, 'accounts');
        $refAccounts->setValue($ctrl, $accountsMock);

        return $ctrl;
    }

    // ================================================================
    // click() — URL non-HTTPS (http://) → abort(404) [L89-93]
    // ================================================================

    /**
     * click() avec une URL HTTP (non sécurisée) doit abort(404).
     * Couvre L89-93 : filter_var OK mais regex ^https:// échoue.
     */
    public function testClickAbortsWhenUrlIsHttp(): void
    {
        $ctrl = $this->makeController([
            'url'   => 'http://example.com/promo',
            'id'    => 2,
            'nl_id' => 1,
        ]);

        $caught = null;
        try {
            $ctrl->click(['id' => '1', 'linkId' => '2', 'token' => '']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'Une HttpException 404 doit être levée pour URL non-HTTPS');
        $this->assertSame(404, $caught->status);
    }

    /**
     * click() avec une URL vide (invalide pour FILTER_VALIDATE_URL) → abort(404).
     * Couvre L89-93 : filter_var retourne false → abort immédiat.
     */
    public function testClickAbortsWhenUrlIsEmpty(): void
    {
        $ctrl = $this->makeController([
            'url'   => '',
            'id'    => 2,
            'nl_id' => 1,
        ]);

        $caught = null;
        try {
            $ctrl->click(['id' => '1', 'linkId' => '2', 'token' => '']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'Une HttpException 404 doit être levée pour URL vide');
        $this->assertSame(404, $caught->status);
    }

    /**
     * click() avec une URL javascript: (invalide FILTER_VALIDATE_URL) → abort(404).
     * Couvre L89-93 : FILTER_VALIDATE_URL retourne false sur les URIs non-HTTP.
     */
    public function testClickAbortsWhenUrlIsJavascript(): void
    {
        $ctrl = $this->makeController([
            'url'   => 'javascript:alert(1)',
            'id'    => 2,
            'nl_id' => 1,
        ]);

        $caught = null;
        try {
            $ctrl->click(['id' => '1', 'linkId' => '2', 'token' => '']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'Une HttpException 404 doit être levée pour URL javascript:');
        $this->assertSame(404, $caught->status);
    }

    // ================================================================
    // click() — lien introuvable → abort(404) [L73-75]
    // ================================================================

    /**
     * click() abort(404) quand le lien n'existe pas (findLink retourne null).
     */
    public function testClickAbortsWhenLinkNotFound(): void
    {
        $ctrl = $this->makeController(null);

        $caught = null;
        try {
            $ctrl->click(['id' => '1', 'linkId' => '99', 'token' => '']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(404, $caught->status);
    }

    // ================================================================
    // open() — nominal avec token valide → recordOpen appelé [L47-54]
    // ================================================================

    /**
     * open() enregistre l'ouverture quand newsletterId > 0 et token valide.
     */
    public function testOpenRecordsOpenWhenSubscriberFound(): void
    {
        $trackingMock = $this->createMock(NewsletterTrackingModel::class);
        $trackingMock->expects($this->once())
            ->method('recordOpen')
            ->with(5, 42);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByUnsubscribeToken')->willReturn(['id' => 42]);

        $ctrl = new TestableTrackingController(new Request());

        $refTracking = new \ReflectionProperty(TrackingController::class, 'tracking');
        $refTracking->setValue($ctrl, $trackingMock);

        $refAccounts = new \ReflectionProperty(TrackingController::class, 'accounts');
        $refAccounts->setValue($ctrl, $accountsMock);

        // open() appelle sendPixel() (no-op dans la sous-classe testable)
        $ctrl->open(['id' => '5', 'token' => 'valid-token']);
        $this->assertTrue(true); // si aucune exception, c'est OK
    }
}
