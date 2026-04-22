<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Controller\Account\AccountExportController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\RgpdExportLogModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;
use Service\MailService;
use Service\RateLimiterService;
use Service\RgpdExportService;

/**
 * Tests de couverture pour AccountExportController.
 * Cible les branches non couvertes :
 *   - newMailService() (lines 49-51)
 *   - rate-limit dépassé dans exportSend() (lines 112-114)
 *   - buildExportData() (lines 148-150)
 *   - buildExportContent() (lines 162-164)
 */
#[AllowMockObjectsWithoutExpectations]
class AccountExportControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $dbStub = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbStub);

        $_SESSION = ['csrf' => 'test-csrf'];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/export';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un TestableAccountExportController avec les dépendances injectées via réflexion.
     *
     * @param UserRepository|null      $userRepo
     * @param RateLimiterService|null  $rateLimiter
     * @param RgpdExportService|null   $exportService
     * @param RgpdExportLogModel|null  $rgpdExportLogs
     * @return TestableAccountExportController
     */
    private function makeController(
        ?UserRepository $userRepo = null,
        ?RateLimiterService $rateLimiter = null,
        ?RgpdExportService $exportService = null,
        ?RgpdExportLogModel $rgpdExportLogs = null
    ): TestableAccountExportController {
        $ctrl = new TestableAccountExportController(new Request(), $userRepo);

        if ($rateLimiter !== null) {
            $ref = new \ReflectionProperty(AccountExportController::class, 'rateLimiter');
            $ref->setValue($ctrl, $rateLimiter);
        }
        if ($exportService !== null) {
            $ref = new \ReflectionProperty(AccountExportController::class, 'exportService');
            $ref->setValue($ctrl, $exportService);
        }
        if ($rgpdExportLogs !== null) {
            $ref = new \ReflectionProperty(AccountExportController::class, 'rgpdExportLogs');
            $ref->setValue($ctrl, $rgpdExportLogs);
        }

        return $ctrl;
    }

    // ----------------------------------------------------------------
    // newMailService() — lines 49-51
    // ----------------------------------------------------------------

    /**
     * newMailService() retourne une instance MailService.
     */
    public function testNewMailServiceReturnsMailService(): void
    {
        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod(AccountExportController::class, 'newMailService');
        $result = $ref->invoke($ctrl);
        $this->assertInstanceOf(MailService::class, $result);
    }

    // ----------------------------------------------------------------
    // exportSend() — rate-limit dépassé (lines 112-114)
    // ----------------------------------------------------------------

    /**
     * exportSend() quand le rate-limit est dépassé → flash export_error + redirect.
     */
    public function testExportSendRateLimitExceededSetsFlashAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];

        $rateLimiter = $this->createStub(RateLimiterService::class);
        // checkLimit retourne false → rate limit atteint
        $rateLimiter->method('checkLimit')->willReturn(false);
        // getRetryAfter retourne 7200 secondes (2h)
        $rateLimiter->method('getRetryAfter')->willReturn(7200);

        $userRepo = $this->createStub(UserRepository::class);

        $ctrl = $this->makeController($userRepo, $rateLimiter);

        try {
            $ctrl->exportSend(['lang' => 'fr']);
            $this->fail('Expected HttpException (302 redirect)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertNotEmpty($_SESSION['flash']['export_error'] ?? '');
    }

    /**
     * exportSend() rate-limit dépassé avec retryAfter = 1800 (< 3600) → wait = 1h affiché.
     */
    public function testExportSendRateLimitExceededWithSubHourRetryAfter(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(false);
        // retryAfter = 1800s → ceil(1800/3600) = 1 → max(1, 1) = 1h
        $rateLimiter->method('getRetryAfter')->willReturn(1800);

        $userRepo = $this->createStub(UserRepository::class);

        $ctrl = $this->makeController($userRepo, $rateLimiter);

        try {
            $ctrl->exportSend(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertArrayHasKey('export_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // buildExportData() — lines 148-150
    // ----------------------------------------------------------------

    /**
     * buildExportData() délègue à RgpdExportService::buildForAccount().
     */
    public function testBuildExportDataDelegatesToExportService(): void
    {
        $exportData    = ['profile' => ['id' => 1], 'orders' => []];
        $exportService = $this->createMock(RgpdExportService::class);
        $exportService->expects($this->once())
            ->method('buildForAccount')
            ->with(1)
            ->willReturn($exportData);

        $ctrl = $this->makeController(null, null, $exportService);

        $ref    = new \ReflectionMethod(AccountExportController::class, 'buildExportData');
        $result = $ref->invoke($ctrl, 1);

        $this->assertSame($exportData, $result);
    }

    // ----------------------------------------------------------------
    // buildExportContent() — lines 162-164
    // ----------------------------------------------------------------

    /**
     * buildExportContent() délègue à RgpdExportService::buildExportContent().
     */
    public function testBuildExportContentDelegatesToExportService(): void
    {
        $expected      = ['binary_content', true];
        $exportService = $this->createMock(RgpdExportService::class);
        $exportService->expects($this->once())
            ->method('buildExportContent')
            ->with(['some' => 'data'], '2026-04-22', 'mes-donnees-2026-04-22')
            ->willReturn($expected);

        $ctrl = $this->makeController(null, null, $exportService);

        $ref    = new \ReflectionMethod(AccountExportController::class, 'buildExportContent');
        $result = $ref->invoke($ctrl, ['some' => 'data'], '2026-04-22', 'mes-donnees-2026-04-22');

        $this->assertSame($expected, $result);
    }

    /**
     * buildExportContent() avec isZip=false.
     */
    public function testBuildExportContentJsonFallback(): void
    {
        $expected      = ['{"json":"data"}', false];
        $exportService = $this->createMock(RgpdExportService::class);
        $exportService->method('buildExportContent')->willReturn($expected);

        $ctrl = $this->makeController(null, null, $exportService);

        $ref    = new \ReflectionMethod(AccountExportController::class, 'buildExportContent');
        $result = $ref->invoke($ctrl, [], '2026-04-22', 'mes-donnees-2026-04-22');

        $this->assertFalse($result[1]);
    }
}
