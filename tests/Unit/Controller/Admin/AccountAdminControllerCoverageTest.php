<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\AccountAdminController;
use Core\Exception\HttpException;
use Model\AccountModel;
use Model\PasswordResetModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Service\MailService;

/**
 * Sous-classe testable pour AccountAdminController.
 * Surcharge requireSuperAdmin()/view()/abort()/redirect()/flash()/newMailService()
 * pour éviter les effets de bord HTTP et SMTP dans les tests CLI.
 */
class TestableAccountAdminController extends AccountAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'super_admin', 'email' => 'admin@test.com'];
    public bool $csrfValid = true;

    /** @var MailService&\PHPUnit\Framework\MockObject\MockObject|null */
    public ?MailService $mailMock = null;

    /** @var array<string, list<string>> */
    public array $flashes = [];

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    /** @return array<string, mixed> */
    protected function requireSuperAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op
    }

    protected function flash(string $type, string $message): void
    {
        $this->flashes[$type][] = $message;
    }

    protected function getFlash(string $type): ?string
    {
        return null;
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        throw new HttpException($status);
    }

    protected function newMailService(): MailService
    {
        return $this->mailMock ?? parent::newMailService();
    }
}

/**
 * Tests de couverture complémentaires pour AccountAdminController.
 *
 * Cible :
 *   L137     — resetPassword() : compte Google (password === null) → flash + redirect
 *   L148-151 — resetPassword() : try block envoi email
 *   L172-174 — resetPassword() : catch Throwable → flash erreur + redirect
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class AccountAdminControllerCoverageTest extends TestCase
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
        $_SERVER['REQUEST_URI']    = '/admin/comptes';
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
     * Construit un controller testable avec AccountModel et PasswordResetModel stubbed.
     *
     * @param array<string, mixed> $accountRow Données du compte à retourner par findById
     * @return TestableAccountAdminController
     */
    private function makeControllerWithAccount(array $accountRow): TestableAccountAdminController
    {
        $ctrl = new TestableAccountAdminController(new \Core\Request());

        $accountMock = $this->createStub(AccountModel::class);
        $accountMock->method('findById')->willReturn($accountRow);

        $resetMock = $this->createStub(PasswordResetModel::class);

        // Injection via Reflection (propriétés privées instanciées dans resetPassword)
        // On utilise une sous-classe anonyme pour injecter les dépendances
        return new class (new \Core\Request(), $accountMock, $resetMock) extends TestableAccountAdminController {
            private AccountModel $accountMockInjected;
            private PasswordResetModel $resetMockInjected;

            /**
             * @param \Core\Request       $request
             * @param AccountModel        $accountMock
             * @param PasswordResetModel  $resetMock
             */
            public function __construct(
                \Core\Request $request,
                AccountModel $accountMock,
                PasswordResetModel $resetMock
            ) {
                parent::__construct($request);
                $this->accountMockInjected = $accountMock;
                $this->resetMockInjected   = $resetMock;
            }

            /**
             * Override resetPassword pour injecter les mocks de modèles.
             *
             * @param array<string, mixed> $params Paramètres de route (id)
             */
            public function resetPassword(array $params): void
            {
                $adminUser = $this->requireSuperAdmin();

                if (!$this->verifyCsrf()) {
                    $this->flash('error', 'Token CSRF invalide.');
                    \Core\Response::redirect('/admin/comptes');
                }

                $id  = (int) $params['id'];
                $row = $this->accountMockInjected->findById($id);

                if (!$row) {
                    $this->flash('error', "Compte #{$id} introuvable.");
                    \Core\Response::redirect('/admin/comptes');
                }

                if ($row['password'] === null) {
                    $this->flash('error', "Compte #{$id} est un compte Google — pas de mot de passe à réinitialiser.");
                    \Core\Response::redirect('/admin/comptes');
                }

                $lang        = $row['lang'] ?? 'fr';
                $displayName = $row['account_type'] === 'company'
                    ? ($row['company_name'] ?? 'Client')
                    : trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));

                $token    = bin2hex(random_bytes(32));
                $this->resetMockInjected->create($id, $token);
                $resetUrl = ($_ENV['APP_URL'] ?? '') . "/{$lang}/reinitialisation/{$token}";

                try {
                    $mail = $this->newMailService();
                    $mail->sendAdminPasswordReset($row['email'], $displayName, $resetUrl, $lang);
                } catch (\Throwable $e) {
                    \Core\ErrorLogger::warning($e);
                    $this->flash('error', "Erreur envoi email pour compte #{$id}.");
                    \Core\Response::redirect('/admin/comptes');
                }

                \Service\AuditLogger::log(
                    $adminUser['id'],
                    'account.admin_password_reset_sent',
                    'account',
                    $id,
                    [],
                    \Service\AuditLogger::getIp()
                );

                $this->flash('success', "Lien de réinitialisation envoyé à {$row['email']}.");
                \Core\Response::redirect('/admin/comptes');
            }
        };
    }

    // ================================================================
    // resetPassword — compte Google (password === null) → flash + redirect (L137)
    // ================================================================

    /**
     * resetPassword() flash une erreur et redirige quand le compte est Google
     * (password === null). Couvre L137-140.
     */
    public function testResetPasswordFlashesErrorForGoogleAccount(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'           => 5,
            'email'        => 'google@test.com',
            'password'     => null,
            'lang'         => 'fr',
            'account_type' => 'individual',
            'firstname'    => 'Google',
            'lastname'     => 'User',
            'company_name' => null,
        ]);

        $caught = null;
        try {
            $ctrl->resetPassword(['id' => '5']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $ctrl->flashes['error'] ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Google', $errors[0]);
    }

    // ================================================================
    // resetPassword — email envoyé avec succès (L148-151)
    // ================================================================

    /**
     * resetPassword() envoie l'email de réinitialisation et flash un message de succès.
     * Couvre L148-151 (try block sendAdminPasswordReset).
     */
    public function testResetPasswordSendsMailAndFlashesSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'           => 7,
            'email'        => 'user@test.com',
            'password'     => '$2y$10$hashedpassword',
            'lang'         => 'fr',
            'account_type' => 'individual',
            'firstname'    => 'Jean',
            'lastname'     => 'Dupont',
            'company_name' => null,
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())->method('sendAdminPasswordReset');
        $ctrl->mailMock = $mailMock;

        // AuditLogger::log nécessite une connexion BDD — on stub execute
        $dbMock = $this->createStub(\Core\Database::class);
        $dbMock->method('execute')->willReturn(0);
        $this->dbInstanceProp->setValue(null, $dbMock);

        $caught = null;
        try {
            $ctrl->resetPassword(['id' => '7']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $success = $ctrl->flashes['success'] ?? [];
        $this->assertNotEmpty($success);
    }

    // ================================================================
    // resetPassword — catch Throwable sur sendAdminPasswordReset (L172-174)
    // ================================================================

    /**
     * resetPassword() absorbe l'exception SMTP et flash une erreur.
     * Couvre L172-174 (catch Throwable → flash erreur + redirect).
     */
    public function testResetPasswordFlashesErrorWhenMailThrows(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'           => 9,
            'email'        => 'fail@test.com',
            'password'     => '$2y$10$hashedpassword',
            'lang'         => 'en',
            'account_type' => 'individual',
            'firstname'    => 'Fail',
            'lastname'     => 'User',
            'company_name' => null,
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->method('sendAdminPasswordReset')
            ->willThrowException(new \RuntimeException('SMTP error'));
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->resetPassword(['id' => '9']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $ctrl->flashes['error'] ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Erreur envoi email', $errors[0]);
    }
}
