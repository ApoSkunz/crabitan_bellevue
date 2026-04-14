<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\ProfileAdminController;
use Core\Exception\HttpException;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\RateLimiterService;

/**
 * Sous-classe testable de ProfileAdminController : remplace requireAdmin/verifyCsrf.
 *
 * Bypass parent::__construct pour éviter l'instanciation de SecurityAlertService
 * (qui crée un MailService → setFrom() incompatible avec l'env de test).
 */
class TestableProfileAdminController extends ProfileAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 7, 'role' => 'admin', 'name' => 'Admin'];
    public bool $csrfValid = true;

    public function __construct(\Core\Request $request)
    {
        // Bypass parent::__construct — injection manuelle des dépendances dans les tests
        $reflRequest = new \ReflectionProperty(\Core\Controller::class, 'request');
        $reflRequest->setValue($this, $request);
    }

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function flash(string $key, string $message): void
    {
        $_SESSION['admin_flash'][$key] = $message;
    }
}

/**
 * Tests unitaires ProfileAdminController — révocation sessions + appareils.
 *
 * Couvre les redirections vers /fr lors de la révocation de la session courante.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class ProfileAdminControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        // Ne PAS require_once config/config.php : parse_ini_file('.env') écrase $_ENV
        // avec des valeurs prod (ex. MAIL_FROM='=' si quote ini incompatible),
        // ce qui casse MailService::__construct dans les tests suivants.

        $dbMock = $this->createStub(\Core\Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $_ENV['MAIL_FROM']      = 'noreply@example.com';
        $_ENV['MAIL_FROM_NAME'] = 'Crabitan Bellevue';
        $_ENV['APP_URL']        = 'http://localhost';

        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    private function makeController(): TestableProfileAdminController
    {
        return new TestableProfileAdminController(new \Core\Request());
    }

    private function injectProp(TestableProfileAdminController $ctrl, string $name, object $mock): void
    {
        $prop = new ReflectionProperty(ProfileAdminController::class, $name);
        $prop->setValue($ctrl, $mock); // NOSONAR — test unitaire
    }

    // ----------------------------------------------------------------
    // revokeSession() — révocation de la session COURANTE → redirect /fr
    // ----------------------------------------------------------------

    public function testRevokeSessionCurrentTokenRedirectsToHomeFr(): void
    {
        $_COOKIE['auth_token'] = 'current-token';

        $ctrl = $this->makeController();

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getTokenById')->willReturn('current-token');
        $this->injectProp($ctrl, 'connections', $connections);

        $location = null;
        try {
            $ctrl->revokeSession(['id' => '5']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/fr', $location);
    }

    // ----------------------------------------------------------------
    // revokeSession() — révocation d'une AUTRE session → redirect /admin/securite
    // ----------------------------------------------------------------

    public function testRevokeSessionOtherTokenRedirectsToSecurity(): void
    {
        $_COOKIE['auth_token'] = 'current-token';

        $ctrl = $this->makeController();

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getTokenById')->willReturn('other-token');
        $this->injectProp($ctrl, 'connections', $connections);

        $location = null;
        try {
            $ctrl->revokeSession(['id' => '5']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/admin/securite', $location);
    }

    // ----------------------------------------------------------------
    // revokeAllSessions() — redirect vers /fr (pas /admin)
    // ----------------------------------------------------------------

    public function testRevokeAllSessionsRedirectsToHomeFr(): void
    {
        $ctrl = $this->makeController();

        $accounts = $this->createStub(AccountModel::class);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $location = null;
        try {
            $ctrl->revokeAllSessions([]);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/fr', $location);
    }

    // ----------------------------------------------------------------
    // revokeAllSessions() — CSRF invalide → redirect /admin/securite
    // ----------------------------------------------------------------

    public function testRevokeAllSessionsCsrfInvalidRedirectsToSecurity(): void
    {
        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $location = null;
        try {
            $ctrl->revokeAllSessions([]);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/admin/securite', $location);
    }

    // ----------------------------------------------------------------
    // resetSecurity() — succès → redirect vers /fr
    // ----------------------------------------------------------------

    public function testResetSecurityRedirectsToHomeFr(): void
    {
        $password = 'Sup3rStr0ng!Pass';
        $hash     = password_hash($password, PASSWORD_BCRYPT);
        $_POST['password'] = $password;

        $ctrl = $this->makeController();

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 7, 'password' => $hash]);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $location = null;
        try {
            $ctrl->resetSecurity([]);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/fr', $location);
    }

    // ----------------------------------------------------------------
    // resetSecurity() — mot de passe incorrect → redirect /admin/securite
    // ----------------------------------------------------------------

    public function testResetSecurityWrongPasswordRedirectsToSecurity(): void
    {
        $_POST['password'] = 'wrong';

        $ctrl = $this->makeController();

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 7, 'password' => password_hash('actual', PASSWORD_BCRYPT)]);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $location = null;
        try {
            $ctrl->resetSecurity([]);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/admin/securite', $location);
    }
}
