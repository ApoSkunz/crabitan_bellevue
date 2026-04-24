<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Controller\Account\AccountProfileController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\AccountModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;

/**
 * Tests de couverture complémentaires pour AccountProfileController.
 * Cible les branches non couvertes :
 *   - updateProfile() newsletter toggle 0→1 double opt-in (lines 165-171)
 *   - requestEmailChange() catch HttpException (lines 212-219)
 *   - requestEmailChange() catch Throwable (lines 220-224)
 *   - confirmEmailChange() succès (lines 268-274)
 */
#[AllowMockObjectsWithoutExpectations]
class AccountProfileControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbStub = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbStub);

        $_SESSION = ['csrf' => 'test-csrf'];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_ENV['APP_URL'] = 'https://example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/profil';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un TestableAccountProfileController.
     *
     * @param UserRepository|null $userRepo
     * @return TestableAccountProfileController
     */
    private function makeController(?UserRepository $userRepo = null): TestableAccountProfileController
    {
        return new TestableAccountProfileController(new Request(), $userRepo);
    }

    /**
     * Injecte un AccountModel mock dans $ctrl->accounts via réflexion.
     *
     * @param TestableAccountProfileController $ctrl
     * @param AccountModel                     $accounts
     */
    private function injectAccounts(
        TestableAccountProfileController $ctrl,
        AccountModel $accounts
    ): void {
        $ref = new \ReflectionProperty(AccountProfileController::class, 'accounts');
        $ref->setValue($ctrl, $accounts);
    }

    // ----------------------------------------------------------------
    // updateProfile() — newsletter toggle 0→1 (lines 165-171)
    // ----------------------------------------------------------------

    /**
     * updateProfile() newsletter toggle 0→1 → storeNewsletterConfirmToken appelé + redirect.
     *
     * Cette branche génère un token, appelle storeNewsletterConfirmToken,
     * et tente d'envoyer un email de confirmation (MailService instancié inline).
     * En environnement de test, MailService échoue → l'exception est propagée par Response::redirect
     * ou remontée. On vérifie que storeNewsletterConfirmToken a été appelé.
     */
    public function testUpdateProfileNewsletterToggleOnCallsStoreToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'civility'   => 'M',
            'firstname'  => 'Jean',
            'lastname'   => 'Dupont',
            'newsletter' => '1',
        ];

        $account = [
            'id'           => 1,
            'account_type' => 'individual',
            'newsletter'   => false, // toggle 0→1
            'lang'         => 'fr',
            'email'        => 'jean@example.com',
        ];

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturn($account);
        $userRepo->method('updateIndividualProfile');

        $accounts = $this->createMock(AccountModel::class);
        $accounts->expects($this->once())->method('storeNewsletterConfirmToken');

        $ctrl = $this->makeController($userRepo);
        $this->injectAccounts($ctrl, $accounts);

        // sendNewsletterConfirmation via new MailService() → SMTP non dispo → exception
        // Response::redirect lance HttpException(302), ou MailService peut jeter une exception
        // Dans les deux cas on attrape toute exception et vérifie l'expectation du mock
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } catch (\Throwable) {
            // Expected: redirect or mail exception
        }

        // L'assertion $accounts->expects($this->once()) valide que storeNewsletterConfirmToken a été appelé
        $this->assertTrue(true); // Si on arrive ici sans assert PHPUnit failure, le test est bon
    }

    // ----------------------------------------------------------------
    // requestEmailChange() — catch HttpException (lines 217-219)
    // ----------------------------------------------------------------

    /**
     * requestEmailChange() quand AccountService lève HttpException → flash profile_errors + redirect.
     *
     * AccountService::requestEmailChange lève HttpException(404) car AccountModel::findById
     * retourne null avec la DB stubbée.
     */
    public function testRequestEmailChangeAccountServiceHttpExceptionSetsFlashAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'new_email'        => 'new@example.com',
            'current_password' => 'password123',
        ];

        $ctrl = $this->makeController();
        // AccountService sera instancié avec $this->accounts (AccountModel réel avec DB stub)
        // AccountModel::findById retourne null → AccountService throw HttpException(404)

        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected exception (302 redirect)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('redirect:', $e->getMessage());
        }

        $this->assertArrayHasKey('email', $_SESSION['flash']['profile_errors'] ?? []);
    }

    // ----------------------------------------------------------------
    // requestEmailChange() — catch Throwable (lines 220-224)
    // ----------------------------------------------------------------

    /**
     * requestEmailChange() quand AccountService lève un Throwable non-HttpException
     * → ErrorLogger::warning + flash profile_errors générique + redirect.
     *
     * On injecte un AccountModel mock dont findById() lève une \RuntimeException brute.
     */
    public function testRequestEmailChangeAccountServiceThrowableSetsGenericFlashAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'new_email'        => 'new@example.com',
            'current_password' => 'password123',
        ];

        // AccountModel mock : findById lève RuntimeException (pas HttpException)
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willThrowException(new \RuntimeException('DB failure'));

        $ctrl = $this->makeController();
        $this->injectAccounts($ctrl, $accounts);

        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected exception (redirect)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('redirect:', $e->getMessage());
        }

        $this->assertArrayHasKey('email', $_SESSION['flash']['profile_errors'] ?? []);
        // Le message d'erreur générique doit être __('error.generic')
        $this->assertSame(__('error.generic'), $_SESSION['flash']['profile_errors']['email'] ?? '');
    }

    // ----------------------------------------------------------------
    // requestEmailChange() — succès (lines 226-228)
    // ----------------------------------------------------------------

    /**
     * requestEmailChange() quand AccountService complète sans exception
     * → flash profile_success + email_change_sent + redirect vers #email-change.
     *
     * AccountModel mocké pour que AccountService::requestEmailChange retourne normalement :
     * findById renvoie un compte valide, findByEmail renvoie un compte existant (anti-enum branch)
     * → saveEmailChangeToken + logAuditEvent appelés + return sans throw.
     */
    public function testRequestEmailChangeSuccessSetsSuccessFlashAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'new_email'        => 'new@example.com',
            'current_password' => 'Password1!',
        ];

        // Compte existant avec mdp valide
        $hashedPw = password_hash('Password1!', PASSWORD_BCRYPT);
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn([
            'id'        => 1,
            'email'     => 'old@example.com',
            'password'  => $hashedPw,
            'google_id' => null,
        ]);
        $accounts->method('countEmailChangeRequestsLast24h')->willReturn(0);
        // findByEmail retourne non-false → branche anti-énumération → return sans email
        $accounts->method('findByEmail')->willReturn(['id' => 99, 'email' => 'new@example.com']);
        $accounts->method('saveEmailChangeToken');
        $accounts->method('logAuditEvent');

        $ctrl = $this->makeController();
        $this->injectAccounts($ctrl, $accounts);

        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
            $this->fail('Expected redirect exception');
        } catch (HttpException $e) {
            // Response::redirect lève HttpException(302) — redirect normale
            $this->assertSame(302, $e->getCode());
        } catch (\RuntimeException $e) {
            // Fallback si la sous-classe testable intercepte via protected redirect()
            $this->assertStringStartsWith('redirect:', $e->getMessage());
        }

        $this->assertArrayHasKey('profile_success', $_SESSION['flash'] ?? []);
        $this->assertTrue($_SESSION['flash']['email_change_sent'] ?? false);
    }

    // ----------------------------------------------------------------
    // AbstractAccountController::requireCustomer() — rôle non-customer → abort 404 (line 48)
    // ----------------------------------------------------------------

    /**
     * requireCustomer() lève HttpException(404) quand le JWT contient un rôle != 'customer'.
     *
     * Utilise AccountProfileController directement (sous-classe concrète d'AbstractAccountController)
     * avec un cookie JWT admin. AuthMiddleware retourne le payload, puis requireCustomer() vérifie
     * le rôle et appelle Response::abort(404).
     */
    public function testRequireCustomerAborts404WhenRoleIsNotCustomer(): void
    {
        $_ENV['JWT_SECRET'] ??= 'test-secret-key-for-unit-tests';

        // Générer un JWT valide avec rôle 'admin'
        $jwt = \Core\Jwt::generate(42, 'admin', 3600);
        $_COOKIE['auth_token'] = $jwt;

        // AuthMiddleware::handle() appelle Database::fetchOne() pour vérifier la session active
        $dbStub = $this->createStub(Database::class);
        $dbStub->method('fetchOne')->willReturn(['id' => 42]);
        $this->instanceProp->setValue(null, $dbStub);

        $request = new Request();
        $ctrl     = new AccountProfileController($request);

        $caught = null;
        try {
            $ctrl->index(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'requireCustomer() doit lancer HttpException(404) pour un admin');
        $this->assertSame(404, $caught->status);
    }

    // ----------------------------------------------------------------
    // confirmEmailChange() — succès (lines 268-274)
    // ----------------------------------------------------------------

    /**
     * confirmEmailChange() avec token valide → AccountService::confirmEmailChange réussit
     * → CookieHelper::clear() + vue success=true rendue.
     *
     * AccountModel mocké pour retourner un token valide depuis findByEmailChangeToken.
     */
    public function testConfirmEmailChangeSuccessRendersSuccessView(): void
    {
        $_GET = ['token' => 'validrawtoken123'];

        $tokenRow = [
            'id'                      => 1,
            'email'                   => 'old@example.com',
            'email_change_used_at'    => null,
            'email_change_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'email_change_new_email'  => 'new@example.com',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmailChangeToken')->willReturn($tokenRow);
        // applyEmailChange, revokeAllSessions, logAuditEvent → no-op (stub)
        $accounts->method('applyEmailChange');
        $accounts->method('revokeAllSessions');
        $accounts->method('logAuditEvent');

        $ctrl = $this->makeController();
        $this->injectAccounts($ctrl, $accounts);

        $ctrl->confirmEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
        $this->assertArrayHasKey('error', $ctrl->lastViewData ?? []);
        $this->assertNull($ctrl->lastViewData['error']);
    }
}
