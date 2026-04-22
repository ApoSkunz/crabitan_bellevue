<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Controller\Account\AccountSecurityController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\ConnectionModel;
use Model\DeviceConfirmTokenModel;
use Model\OrderModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour AccountSecurityController.
 * Utilise TestableAccountSecurityController pour isoler auth, CSRF, view, redirect.
 */
#[AllowMockObjectsWithoutExpectations]
class AccountSecurityControllerTest extends TestCase
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
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/securite';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_ENV['APP_URL'] = 'https://example.com';
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
     * Crée un TestableAccountSecurityController avec un UserRepository mocké.
     */
    private function makeController(?UserRepository $userRepo = null): TestableAccountSecurityController
    {
        return new TestableAccountSecurityController(new Request(), $userRepo);
    }

    /**
     * Injecte un mock via réflexion sur AccountSecurityController.
     *
     * @param object $ctrl
     * @param string $prop
     * @param object $mock
     */
    private function inject(object $ctrl, string $prop, object $mock): void
    {
        $ref = new \ReflectionProperty(AccountSecurityController::class, $prop);
        $ref->setValue($ctrl, $mock);
    }

    // ----------------------------------------------------------------
    // security() — affichage page sécurité
    // ----------------------------------------------------------------

    /**
     * security() rend la vue account/security avec les données attendues.
     */
    public function testSecurityRendersView(): void
    {
        $account = ['id' => 1, 'email' => 'u@example.com', 'password' => 'hash'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getActiveForUser')->willReturn([]);

        $devices = $this->createStub(TrustedDeviceModel::class);
        $devices->method('getForUser')->willReturn([]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countActiveOrdersForUser')->willReturn(0);

        $ctrl = $this->makeController($userRepo);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->security(['lang' => 'fr']);

        $this->assertSame('account/security', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['hasPassword'] ?? false);
        $this->assertSame('fr', $ctrl->lastViewData['lang'] ?? null);
    }

    /**
     * security() lit et efface les flashes security_success et security_errors.
     */
    public function testSecurityClearsFlash(): void
    {
        $_SESSION['flash']['security_success'] = 'done';
        $_SESSION['flash']['security_errors']  = ['csrf' => 'bad'];

        $account = ['id' => 1, 'email' => 'u@example.com', 'password' => null];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getActiveForUser')->willReturn([]);
        $devices = $this->createStub(TrustedDeviceModel::class);
        $devices->method('getForUser')->willReturn([]);
        $orders = $this->createStub(OrderModel::class);
        $orders->method('countActiveOrdersForUser')->willReturn(0);

        $ctrl = $this->makeController($userRepo);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->security(['lang' => 'en']);

        $this->assertSame('done', $ctrl->lastViewData['success'] ?? null);
        $this->assertSame(['csrf' => 'bad'], $ctrl->lastViewData['errors'] ?? null);
        $this->assertArrayNotHasKey('security_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('security_errors', $_SESSION['flash'] ?? []);
    }

    /**
     * security() avec account null → hasPassword = false.
     */
    public function testSecurityWithNullAccountHasPasswordFalse(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(false);

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getActiveForUser')->willReturn([]);
        $devices = $this->createStub(TrustedDeviceModel::class);
        $devices->method('getForUser')->willReturn([]);
        $orders = $this->createStub(OrderModel::class);
        $orders->method('countActiveOrdersForUser')->willReturn(0);

        $ctrl = $this->makeController($userRepo);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->security(['lang' => 'fr']);

        $this->assertFalse($ctrl->lastViewData['hasPassword'] ?? true);
    }

    /**
     * security() expose les cookies auth_token et device_token.
     */
    public function testSecurityExposesCurrentTokensFromCookie(): void
    {
        $_COOKIE['auth_token']   = 'mytoken';
        $_COOKIE['device_token'] = 'mydevice';

        $account = ['id' => 1, 'email' => 'u@example.com', 'password' => 'hash'];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getActiveForUser')->willReturn([]);
        $devices = $this->createStub(TrustedDeviceModel::class);
        $devices->method('getForUser')->willReturn([]);
        $orders = $this->createStub(OrderModel::class);
        $orders->method('countActiveOrdersForUser')->willReturn(0);

        $ctrl = $this->makeController($userRepo);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->security(['lang' => 'fr']);

        $this->assertSame('mytoken', $ctrl->lastViewData['currentToken'] ?? null);
        $this->assertSame('mydevice', $ctrl->lastViewData['currentDeviceToken'] ?? null);
    }

    // ----------------------------------------------------------------
    // changePassword() — branches CSRF, rate limit, validation, succès
    // ----------------------------------------------------------------

    /**
     * changePassword() avec CSRF invalide → redirect avec flash csrf.
     */
    public function testChangePasswordCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('csrf', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * changePassword() rate limit dépassé → redirect avec flash rate_limit.
     */
    public function testChangePasswordRateLimitExceededRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $rateLimiter = $this->createStub(\Service\RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(false);
        $rateLimiter->method('getRetryAfter')->willReturn(600);

        $ctrl = $this->makeController();
        $ref = new \ReflectionProperty(AccountSecurityController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('rate_limit', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * changePassword() mot de passe actuel incorrect → flash current_password.
     */
    public function testChangePasswordWrongCurrentPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'      => 'wrong',
            'new_password'          => 'NewStr0ng!Pass',
            'new_password_confirm'  => 'NewStr0ng!Pass',
        ];

        $account = ['id' => 1, 'email' => 'u@example.com', 'password' => password_hash('correct', PASSWORD_BCRYPT)];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(\Service\RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController($userRepo);
        $ref = new \ReflectionProperty(AccountSecurityController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('current_password', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * changePassword() nouveau mot de passe identique à l'actuel → flash new_password.
     */
    public function testChangePasswordSameAsCurrentRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $pass = 'S@mePass1!';
        $_POST = [
            'current_password'      => $pass,
            'new_password'          => $pass,
            'new_password_confirm'  => $pass,
        ];

        $account = ['id' => 1, 'email' => 'u@example.com', 'password' => password_hash($pass, PASSWORD_BCRYPT)];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(\Service\RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController($userRepo);
        $ref = new \ReflectionProperty(AccountSecurityController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('new_password', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * changePassword() nouveau mot de passe trop faible → flash new_password.
     */
    public function testChangePasswordWeakNewPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'      => 'OldStr0ng!Pass',
            'new_password'          => 'weak',
            'new_password_confirm'  => 'weak',
        ];

        $account = [
            'id'       => 1,
            'email'    => 'u@example.com',
            'password' => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT),
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(\Service\RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController($userRepo);
        $ref = new \ReflectionProperty(AccountSecurityController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('new_password', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * changePassword() confirmations non concordantes → flash new_password_confirm.
     */
    public function testChangePasswordMismatchConfirmRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'      => 'OldStr0ng!Pass',
            'new_password'          => 'NewStr0ng!Pass1',
            'new_password_confirm'  => 'NewStr0ng!Pass2',
        ];

        $account = [
            'id'       => 1,
            'email'    => 'u@example.com',
            'password' => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT),
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(\Service\RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController($userRepo);
        $ref = new \ReflectionProperty(AccountSecurityController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('new_password_confirm', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * changePassword() valide → savePendingPassword appelé + redirect success.
     */
    public function testChangePasswordValidSavesPendingAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'      => 'OldStr0ng!Pass',
            'new_password'          => 'NewStr0ng!Pass1',
            'new_password_confirm'  => 'NewStr0ng!Pass1',
        ];

        $account = [
            'id'        => 1,
            'email'     => 'u@example.com',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
            'lang'      => 'fr',
            'password'  => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT),
        ];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);
        $userRepo->expects($this->once())->method('savePendingPassword');
        $userRepo->method('saveSecurityConfirmToken');

        $rateLimiter = $this->createStub(\Service\RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController($userRepo);
        $ref = new \ReflectionProperty(AccountSecurityController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame('account.password_change_pending', $_SESSION['flash']['security_success'] ?? null);
    }

    // ----------------------------------------------------------------
    // revokeAllUserSessions()
    // ----------------------------------------------------------------

    /**
     * revokeAllUserSessions() CSRF invalide → redirect sécurité sans revoquer.
     */
    public function testRevokeAllSessionsCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->never())->method('revokeAllSessions');

        $ctrl = $this->makeController($userRepo);
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeAllUserSessions(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * revokeAllUserSessions() CSRF valide → revokeAllSessions + redirect /.
     */
    public function testRevokeAllSessionsValidRevokesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())->method('revokeAllSessions')->with(1);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeAllUserSessions(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // revokeSession()
    // ----------------------------------------------------------------

    /**
     * revokeSession() CSRF invalide → redirect sans revoquer.
     */
    public function testRevokeSessionCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $connections = $this->createMock(ConnectionModel::class);
        $connections->expects($this->never())->method('revokeById');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $this->inject($ctrl, 'connections', $connections);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '5']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * revokeSession() id=0 → redirect sans revoquer.
     */
    public function testRevokeSessionIdZeroRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $connections = $this->createMock(ConnectionModel::class);
        $connections->expects($this->never())->method('revokeById');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'connections', $connections);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '0']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * revokeSession() token révoqué != token courant → redirect sécurité.
     */
    public function testRevokeSessionDifferentTokenRedirectsToSecurity(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_COOKIE['auth_token'] = 'current-token';

        $connections = $this->createMock(ConnectionModel::class);
        $connections->method('getTokenById')->willReturn('other-token');
        $connections->expects($this->once())->method('revokeById')->with(5, 1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'connections', $connections);

        $ex = null;

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '5']);
        } catch (HttpException $e) {
            $ex = $e;
        } finally {
            ob_end_clean();
        }

        $this->assertNotNull($ex);
        $this->assertStringContainsString('/fr/mon-compte/securite', (string) $ex->location);
    }

    /**
     * revokeSession() token révoqué == token courant → redirect /.
     */
    public function testRevokeSessionCurrentTokenRevokesAndRedirectsToRoot(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_COOKIE['auth_token'] = 'my-token';

        $connections = $this->createMock(ConnectionModel::class);
        $connections->method('getTokenById')->willReturn('my-token');
        $connections->expects($this->once())->method('revokeById')->with(5, 1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'connections', $connections);

        $ex = null;

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '5']);
        } catch (HttpException $e) {
            $ex = $e;
        } finally {
            ob_end_clean();
        }

        $this->assertNotNull($ex);
        $this->assertStringContainsString('/fr', (string) $ex->location);
    }

    // ----------------------------------------------------------------
    // untrustDevice()
    // ----------------------------------------------------------------

    /**
     * untrustDevice() CSRF invalide → redirect sans untrust.
     */
    public function testUntrustDeviceCsrfInvalidSkipsUntrust(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['device_token' => 'abc123'];

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->never())->method('untrust');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * untrustDevice() device_token vide → redirect sans untrust.
     */
    public function testUntrustDeviceEmptyTokenSkipsUntrust(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['device_token' => ''];

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->never())->method('untrust');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * untrustDevice() valide → untrust appelé + redirect.
     */
    public function testUntrustDeviceValidCallsUntrustAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['device_token' => 'device-abc'];

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('untrust')->with(1, 'device-abc');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // untrustAllDevices()
    // ----------------------------------------------------------------

    /**
     * untrustAllDevices() CSRF invalide → redirect avec flash csrf.
     */
    public function testUntrustAllDevicesCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->never())->method('deleteAllForUser');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustAllDevices(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('csrf', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * untrustAllDevices() valide → deleteAllForUser + flash success + redirect.
     */
    public function testUntrustAllDevicesValidDeletesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('deleteAllForUser')->with(1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustAllDevices(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // resetSecurity()
    // ----------------------------------------------------------------

    /**
     * resetSecurity() CSRF invalide → redirect avec flash csrf.
     */
    public function testResetSecurityCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('csrf', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * resetSecurity() mot de passe incorrect → flash reset_password.
     */
    public function testResetSecurityWrongPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'wrong'];

        $account = ['id' => 1, 'password' => password_hash('correct', PASSWORD_BCRYPT)];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('reset_password', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * resetSecurity() account null → flash reset_password.
     */
    public function testResetSecurityNullAccountRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'somepass'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(false);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * resetSecurity() account.password null → flash reset_password.
     */
    public function testResetSecurityNullPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'somepass'];

        $account = ['id' => 1, 'password' => null];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * resetSecurity() valide → revokeAllSessions + deleteAllForUser + redirect.
     */
    public function testResetSecurityValidRevokesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'Correct1!Pass'];

        $account = [
            'id'       => 1,
            'password' => password_hash('Correct1!Pass', PASSWORD_BCRYPT),
        ];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);
        $userRepo->expects($this->once())->method('revokeAllSessions')->with(1);

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('deleteAllForUser')->with(1);

        $ctrl = $this->makeController($userRepo);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAccount()
    // ----------------------------------------------------------------

    /**
     * deleteAccount() CSRF invalide → flash csrf.
     */
    public function testDeleteAccountCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() confirm_text incorrect → flash delete.
     */
    public function testDeleteAccountWrongConfirmTextRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'WRONG', 'confirm_password' => ''];

        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('delete', $_SESSION['flash']['security_errors'] ?? []);
    }

    /**
     * deleteAccount() account null → flash delete.
     */
    public function testDeleteAccountNullAccountRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'SUPPRESSION', 'confirm_password' => ''];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn(false);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() mot de passe incorrect → flash delete.
     */
    public function testDeleteAccountWrongPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'wrongpass',
        ];

        $account = ['id' => 1, 'password' => password_hash('correct', PASSWORD_BCRYPT), 'email' => 'u@example.com'];
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() compte Google (password null) → suppression sans vérif MDP.
     */
    public function testDeleteAccountGoogleAccountNoPasswordCheck(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'SUPPRESSION'];

        $account = [
            'id'           => 1,
            'email'        => 'u@example.com',
            'password'     => null,
            'firstname'    => 'Jean',
            'lang'         => 'fr',
            'company_name' => null,
        ];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);
        $userRepo->expects($this->once())->method('revokeAllSessions')->with(1);
        $userRepo->expects($this->once())->method('delete')->with(1);

        // AccountModel::getReactivationToken est appelé hors interface
        $accountModel = $this->createStub(\Model\AccountModel::class);
        $accountModel->method('getReactivationToken')->willReturn('tok123');

        $ctrl = $this->makeController($userRepo);
        $refAcc = new \ReflectionProperty(AccountSecurityController::class, 'accounts');
        $refAcc->setValue($ctrl, $accountModel);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // newDevice()
    // ----------------------------------------------------------------

    /**
     * newDevice() sans pending_device → redirect.
     */
    public function testNewDeviceWithoutPendingRedirects(): void
    {
        $_SESSION = [];

        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->newDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * newDevice() avec pending_device → vue account/new_device.
     */
    public function testNewDeviceWithPendingRendersView(): void
    {
        $_SESSION['pending_device'] = ['device_name' => 'Chrome Mac', 'mfa_token' => 'tok42'];

        $ctrl = $this->makeController();
        $ctrl->newDevice(['lang' => 'fr']);

        $this->assertSame('account/new_device', $ctrl->lastView);
        $this->assertSame('Chrome Mac', $ctrl->lastViewData['deviceName'] ?? null);
        $this->assertSame('tok42', $ctrl->lastViewData['mfaToken'] ?? null);
    }

    // ----------------------------------------------------------------
    // confirmDevice()
    // ----------------------------------------------------------------

    /**
     * confirmDevice() avec token vide → vue account/device_confirmed success=false.
     */
    public function testConfirmDeviceEmptyTokenRendersFalse(): void
    {
        $_GET = ['token' => ''];

        $deviceTokens = $this->createStub(DeviceConfirmTokenModel::class);
        $deviceTokens->method('confirm')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $ctrl->confirmDevice(['lang' => 'fr']);

        $this->assertSame('account/device_confirmed', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * confirmDevice() avec token valide → vue account/device_confirmed success=true.
     */
    public function testConfirmDeviceValidTokenRendersSuccess(): void
    {
        $_GET = ['token' => 'validtoken'];

        $deviceTokens = $this->createStub(DeviceConfirmTokenModel::class);
        $deviceTokens->method('confirm')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $ctrl->confirmDevice(['lang' => 'fr']);

        $this->assertSame('account/device_confirmed', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // cancelMfa()
    // ----------------------------------------------------------------

    /**
     * cancelMfa() token vide → vue account/mfa_cancelled revoked=false.
     */
    public function testCancelMfaEmptyTokenRendersNotRevoked(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->cancelMfa(['lang' => 'fr']);

        $this->assertSame('account/mfa_cancelled', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['revoked'] ?? true);
    }

    /**
     * cancelMfa() token inconnu → vue account/mfa_cancelled revoked=false.
     */
    public function testCancelMfaUnknownTokenRendersNotRevoked(): void
    {
        $_GET = ['token' => 'unknown'];

        $deviceTokens = $this->createStub(DeviceConfirmTokenModel::class);
        $deviceTokens->method('findByToken')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $ctrl->cancelMfa(['lang' => 'fr']);

        $this->assertSame('account/mfa_cancelled', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['revoked'] ?? true);
    }

    /**
     * cancelMfa() token valide → deleteByToken + vue revoked=true.
     */
    public function testCancelMfaValidTokenRevokesAndRendersTrue(): void
    {
        $_GET = ['token' => 'validmfatoken'];

        $deviceTokens = $this->createMock(DeviceConfirmTokenModel::class);
        $deviceTokens->method('findByToken')->willReturn(['id' => 1, 'token_hash' => 'h']);
        $deviceTokens->expects($this->once())->method('deleteByToken')->with('validmfatoken');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $ctrl->cancelMfa(['lang' => 'fr']);

        $this->assertSame('account/mfa_cancelled', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['revoked'] ?? false);
    }

    // ----------------------------------------------------------------
    // confirmSecurityAction()
    // ----------------------------------------------------------------

    /**
     * confirmSecurityAction() token vide → vue success=false, expired=false.
     */
    public function testConfirmSecurityActionEmptyTokenRendersNoSuccess(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
        $this->assertFalse($ctrl->lastViewData['expired'] ?? true);
    }

    /**
     * confirmSecurityAction() token inconnu → vue success=false, expired=true.
     */
    public function testConfirmSecurityActionUnknownTokenRendersExpired(): void
    {
        $_GET = ['token' => 'unknowntoken'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findBySecurityConfirmToken')->willReturn(false);

        $ctrl = $this->makeController($userRepo);
        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
        $this->assertTrue($ctrl->lastViewData['expired'] ?? false);
    }

    /**
     * confirmSecurityAction() token valide, requestingToken null → applyPendingPassword + vue success.
     */
    public function testConfirmSecurityActionValidTokenAppliesPasswordAndRendersSuccess(): void
    {
        $_GET = ['token' => 'validtoken'];

        $account = ['id' => 7, 'email' => 'u@example.com'];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findBySecurityConfirmToken')->willReturn($account);
        $userRepo->expects($this->once())->method('applyPendingPassword')->with(7);
        $userRepo->expects($this->once())->method('consumeSecurityConfirmToken')->with(7);

        $accountModel = $this->createStub(\Model\AccountModel::class);
        $accountModel->method('getPendingSessionToken')->willReturn(null);

        $ctrl = $this->makeController($userRepo);
        $refAcc = new \ReflectionProperty(AccountSecurityController::class, 'accounts');
        $refAcc->setValue($ctrl, $accountModel);

        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-confirm', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    /**
     * confirmSecurityAction() avec requestingToken → révoque connexion + untrust device.
     */
    public function testConfirmSecurityActionWithRequestingTokenRevokesSession(): void
    {
        $_GET = ['token' => 'validtoken'];

        $account = ['id' => 7, 'email' => 'u@example.com'];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findBySecurityConfirmToken')->willReturn($account);
        $userRepo->method('applyPendingPassword');
        $userRepo->method('consumeSecurityConfirmToken');

        $accountModel = $this->createStub(\Model\AccountModel::class);
        $accountModel->method('getPendingSessionToken')->willReturn('reqtoken');

        $connections = $this->createMock(ConnectionModel::class);
        $connections->method('getDeviceTokenByToken')->with('reqtoken')->willReturn('device-xyz');
        $connections->expects($this->once())->method('revoke')->with('reqtoken');

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('untrust')->with(7, 'device-xyz');

        $ctrl = $this->makeController($userRepo);
        $refAcc = new \ReflectionProperty(AccountSecurityController::class, 'accounts');
        $refAcc->setValue($ctrl, $accountModel);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    /**
     * confirmSecurityAction() requestingToken sans device → révoque connexion sans untrust.
     */
    public function testConfirmSecurityActionWithRequestingTokenNoDeviceRevokesConnectionOnly(): void
    {
        $_GET = ['token' => 'validtoken'];

        $account = ['id' => 7, 'email' => 'u@example.com'];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findBySecurityConfirmToken')->willReturn($account);
        $userRepo->method('applyPendingPassword');
        $userRepo->method('consumeSecurityConfirmToken');

        $accountModel = $this->createStub(\Model\AccountModel::class);
        $accountModel->method('getPendingSessionToken')->willReturn('reqtoken');

        $connections = $this->createMock(ConnectionModel::class);
        $connections->method('getDeviceTokenByToken')->willReturn(null);
        $connections->expects($this->once())->method('revoke')->with('reqtoken');

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->never())->method('untrust');

        $ctrl = $this->makeController($userRepo);
        $refAcc = new \ReflectionProperty(AccountSecurityController::class, 'accounts');
        $refAcc->setValue($ctrl, $accountModel);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // denySecurityAction()
    // ----------------------------------------------------------------

    /**
     * denySecurityAction() token vide → vue success=false, expired=false.
     */
    public function testDenySecurityActionEmptyTokenRendersNoSuccess(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->denySecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-deny', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
        $this->assertFalse($ctrl->lastViewData['expired'] ?? true);
    }

    /**
     * denySecurityAction() token inconnu → vue success=false, expired=true.
     */
    public function testDenySecurityActionUnknownTokenRendersExpired(): void
    {
        $_GET = ['token' => 'unknown'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findBySecurityConfirmToken')->willReturn(false);

        $ctrl = $this->makeController($userRepo);
        $ctrl->denySecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-deny', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
        $this->assertTrue($ctrl->lastViewData['expired'] ?? false);
    }

    /**
     * denySecurityAction() token valide → clearPendingPassword + revokeAllSessions + vue success.
     */
    public function testDenySecurityActionValidTokenClearsAndRendersSuccess(): void
    {
        $_GET = ['token' => 'validtoken'];

        $account = ['id' => 3, 'email' => 'u@example.com', 'lang' => 'fr'];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findBySecurityConfirmToken')->willReturn($account);
        $userRepo->expects($this->once())->method('clearPendingPassword')->with(3);
        $userRepo->expects($this->once())->method('revokeAllSessions')->with(3);
        $userRepo->expects($this->once())->method('consumeSecurityConfirmToken')->with(3);

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('deleteAllForUser')->with(3);

        $ctrl = $this->makeController($userRepo);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $ctrl->denySecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-deny', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // reactivateAccount()
    // ----------------------------------------------------------------

    /**
     * reactivateAccount() token vide → vue success=false.
     */
    public function testReactivateAccountEmptyTokenRendersFailure(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->reactivateAccount(['lang' => 'fr']);

        $this->assertSame('account/reactivate', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * reactivateAccount() token inconnu → vue success=false.
     */
    public function testReactivateAccountUnknownTokenRendersFailure(): void
    {
        $_GET = ['token' => 'badtoken'];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findByReactivationToken')->willReturn(false);

        $ctrl = $this->makeController($userRepo);
        $ctrl->reactivateAccount(['lang' => 'fr']);

        $this->assertSame('account/reactivate', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * reactivateAccount() token valide → reactivate + vue success=true.
     */
    public function testReactivateAccountValidTokenReactivatesAndRendersSuccess(): void
    {
        $_GET = ['token' => 'goodtoken'];

        $account = ['id' => 9];

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findByReactivationToken')->willReturn($account);
        $userRepo->expects($this->once())->method('reactivate')->with(9);

        $ctrl = $this->makeController($userRepo);
        $ctrl->reactivateAccount(['lang' => 'fr']);

        $this->assertSame('account/reactivate', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }
}
