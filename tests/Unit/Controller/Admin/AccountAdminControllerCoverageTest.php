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
     * Construit un controller testable. Stubbe Database::instance pour que
     * AccountModel::findById (instancié dans resetPassword) retourne $accountRow,
     * et que PasswordResetModel::create (execute) ne fasse pas d'I/O.
     *
     * @param array<string, mixed> $accountRow Données du compte à retourner par findById
     * @return TestableAccountAdminController
     */
    private function makeControllerWithAccount(array $accountRow): TestableAccountAdminController
    {
        $dbMock = $this->createStub(\Core\Database::class);
        $dbMock->method('fetchOne')->willReturn($accountRow);
        $dbMock->method('execute')->willReturn(1);
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        return new TestableAccountAdminController(new \Core\Request());
    }

    /**
     * Sous-classe anonyme historique — conservée pour référence mais plus utilisée
     * par les tests de coverage (qui appellent le vrai resetPassword).
     */
    private function makeLegacyOverride(array $accountRow): TestableAccountAdminController // NOSONAR — tests legacy
    {
        $accountMock = $this->createStub(AccountModel::class);
        $accountMock->method('findById')->willReturn($accountRow);

        $resetMock = $this->createStub(PasswordResetModel::class);

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
     * resetPassword() avec compte company → displayName = company_name (L145).
     */
    public function testResetPasswordUsesCompanyNameForCompanyAccount(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'           => 11,
            'email'        => 'corp@test.com',
            'password'     => '$2y$10$hashedpw',
            'lang'         => 'fr',
            'account_type' => 'company',
            'firstname'    => null,
            'lastname'     => null,
            'company_name' => 'Ma Société SARL',
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())
            ->method('sendAdminPasswordReset')
            ->with('corp@test.com', 'Ma Société SARL');
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->resetPassword(['id' => '11']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertNotEmpty($ctrl->flashes['success'] ?? []);
    }

    // ================================================================
    // cancelDeletion — CSRF invalide → flash error + redirect
    // ================================================================

    /**
     * cancelDeletion() flash une erreur et redirige si le CSRF est invalide.
     */
    public function testCancelDeletionFlashesErrorOnInvalidCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl            = new TestableAccountAdminController(new \Core\Request());
        $ctrl->csrfValid = false;

        $caught = null;
        try {
            $ctrl->cancelDeletion(['id' => '1']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $ctrl->flashes['error'] ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('CSRF', $errors[0]);
    }

    // ================================================================
    // cancelDeletion — compte non annulable → flash error + redirect
    // ================================================================

    /**
     * cancelDeletion() flash une erreur quand findCancellableById retourne false.
     */
    public function testCancelDeletionFlashesErrorForNonCancellable(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $dbMock = $this->createStub(\Core\Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $ctrl = new TestableAccountAdminController(new \Core\Request());

        $caught = null;
        try {
            $ctrl->cancelDeletion(['id' => '999']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $ctrl->flashes['error'] ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('introuvable', $errors[0]);
    }

    // ================================================================
    // cancelDeletion — succès nominal → mail envoyé + flash success
    // ================================================================

    /**
     * cancelDeletion() envoie le mail et flash un succès pour un compte en alerte.
     */
    public function testCancelDeletionFlashesSuccessAndSendsMail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'                         => 42,
            'email'                      => 'warning@test.com',
            'lang'                       => 'fr',
            'account_type'               => 'individual',
            'firstname'                  => 'Marie',
            'lastname'                   => 'Dupont',
            'company_name'               => null,
            'inactivity_warning_sent_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'deleted_at'                 => null,
            'scheduled_deletion_at'      => date('Y-m-d H:i:s', strtotime('+20 days')),
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())
            ->method('sendAccountDeletionCancelled')
            ->with('warning@test.com', 'Marie Dupont', 'fr');
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->cancelDeletion(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $success = $ctrl->flashes['success'] ?? [];
        $this->assertNotEmpty($success);
        $this->assertStringContainsString('#42', $success[0]);
    }

    // ================================================================
    // cancelDeletion — mail throws → absorbé, flash success quand même
    // ================================================================

    /**
     * cancelDeletion() absorbe l'exception SMTP et flash quand même un succès.
     */
    public function testCancelDeletionAbsorbsMailException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'                         => 43,
            'email'                      => 'fail.warning@test.com',
            'lang'                       => 'en',
            'account_type'               => 'company',
            'firstname'                  => null,
            'lastname'                   => null,
            'company_name'               => 'ACME Corp',
            'inactivity_warning_sent_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'deleted_at'                 => null,
            'scheduled_deletion_at'      => date('Y-m-d H:i:s', strtotime('+25 days')),
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->method('sendAccountDeletionCancelled')
            ->willThrowException(new \RuntimeException('SMTP down'));
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->cancelDeletion(['id' => '43']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertNotEmpty($ctrl->flashes['success'] ?? []);
        $this->assertEmpty($ctrl->flashes['error'] ?? []);
    }

    // ================================================================
    // cancelDeletion — company account → displayName = company_name
    // ================================================================

    /**
     * cancelDeletion() utilise company_name comme displayName pour les comptes société.
     */
    public function testCancelDeletionUsesCompanyNameForCompanyAccount(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'                         => 44,
            'email'                      => 'corp.warning@test.com',
            'lang'                       => 'fr',
            'account_type'               => 'company',
            'firstname'                  => null,
            'lastname'                   => null,
            'company_name'               => 'Vignobles SARL',
            'inactivity_warning_sent_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'deleted_at'                 => null,
            'scheduled_deletion_at'      => date('Y-m-d H:i:s', strtotime('+23 days')),
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())
            ->method('sendAccountDeletionCancelled')
            ->with('corp.warning@test.com', 'Vignobles SARL', 'fr');
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->cancelDeletion(['id' => '44']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // restore — CSRF invalide → flash error + redirect
    // ================================================================

    /**
     * restore() flash une erreur et redirige si le CSRF est invalide.
     */
    public function testRestoreFlashesErrorOnInvalidCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl            = new TestableAccountAdminController(new \Core\Request());
        $ctrl->csrfValid = false;

        $caught = null;
        try {
            $ctrl->restore(['id' => '1']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertNotEmpty($ctrl->flashes['error'] ?? []);
        $this->assertStringContainsString('CSRF', $ctrl->flashes['error'][0]);
    }

    // ================================================================
    // restore — compte non restaurable → flash error + redirect
    // ================================================================

    /**
     * restore() flash une erreur quand findRestorableById retourne false.
     */
    public function testRestoreFlashesErrorForNonRestorable(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $dbMock = $this->createStub(\Core\Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $ctrl = new TestableAccountAdminController(new \Core\Request());

        $caught = null;
        try {
            $ctrl->restore(['id' => '999']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $ctrl->flashes['error'] ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('introuvable', $errors[0]);
    }

    // ================================================================
    // restore — succès nominal → mail envoyé + flash success
    // ================================================================

    /**
     * restore() envoie sendAccountRestoredByAdmin et flash un succès.
     */
    public function testRestoreFlashesSuccessAndSendsMail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'                    => 50,
            'email'                 => 'softdeleted@test.com',
            'lang'                  => 'fr',
            'account_type'          => 'individual',
            'firstname'             => 'Paul',
            'lastname'              => 'Martin',
            'company_name'          => null,
            'deleted_at'            => date('Y-m-d H:i:s', strtotime('-5 days')),
            'scheduled_deletion_at' => date('Y-m-d H:i:s', strtotime('+25 days')),
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())
            ->method('sendAccountRestoredByAdmin')
            ->with('softdeleted@test.com', 'Paul Martin', 'fr');
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->restore(['id' => '50']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $success = $ctrl->flashes['success'] ?? [];
        $this->assertNotEmpty($success);
        $this->assertStringContainsString('#50', $success[0]);
    }

    // ================================================================
    // restore — mail throws → absorbé, flash success quand même
    // ================================================================

    /**
     * restore() absorbe l'exception SMTP et flash quand même un succès.
     */
    public function testRestoreAbsorbsMailException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeControllerWithAccount([
            'id'                    => 51,
            'email'                 => 'restore.fail@test.com',
            'lang'                  => 'en',
            'account_type'          => 'company',
            'firstname'             => null,
            'lastname'              => null,
            'company_name'          => 'Old Corp',
            'deleted_at'            => date('Y-m-d H:i:s', strtotime('-3 days')),
            'scheduled_deletion_at' => date('Y-m-d H:i:s', strtotime('+27 days')),
        ]);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->method('sendAccountRestoredByAdmin')
            ->willThrowException(new \RuntimeException('SMTP down'));
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->restore(['id' => '51']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertNotEmpty($ctrl->flashes['success'] ?? []);
        $this->assertEmpty($ctrl->flashes['error'] ?? []);
    }

    /**
     * newMailService() seam — invoqué via ReflectionMethod sur la vraie classe.
     * Couvre L180-182.
     */
    public function testNewMailServiceReturnsRealMailService(): void
    {
        $ctrl = new AccountAdminController(new \Core\Request());
        $ref  = new \ReflectionMethod(AccountAdminController::class, 'newMailService');
        $ref->setAccessible(true);
        $result = $ref->invoke($ctrl);

        $this->assertInstanceOf(MailService::class, $result);
    }

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
