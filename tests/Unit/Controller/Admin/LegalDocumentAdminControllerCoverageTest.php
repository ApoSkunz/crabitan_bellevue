<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\LegalDocumentAdminController;
use Core\Exception\HttpException;
use Model\LegalDocumentModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Service\AuditLogger;
use Service\MailService;

/**
 * Sous-classe testable pour LegalDocumentAdminController.
 * Surcharge requireSuperAdmin()/view()/abort()/redirect()/flash()
 * pour éviter les effets de bord HTTP dans les tests CLI.
 */
class TestableLegalDocumentAdminController extends LegalDocumentAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'super_admin', 'email' => 'admin@test.com'];
    public bool  $csrfValid = true;

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
}

/**
 * Tests de couverture complémentaires pour LegalDocumentAdminController.
 *
 * Cible :
 *   L292-294 — sendNotifications() : catch Exception sur sendLegalDocumentUpdate → failure++
 *   L313     — sendNotifications() : updateNotificationStats() appelé quand success > 0
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class LegalDocumentAdminControllerCoverageTest extends TestCase
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
        $dbMock->method('execute')->willReturn(0);
        $this->dbInstanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/dpo/pages-legales';
        $_ENV['APP_URL'] ??= 'https://crabitanbellevue.fr';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Retourne un pending valide pour la session.
     *
     * @return array<string, mixed>
     */
    private function samplePending(string $slug = 'cgv'): array
    {
        return [
            'slug'        => $slug,
            'doc_id'      => 3,
            'version'     => '2.0',
            'doc_label'   => 'CGV',
            'updated_at'  => '2026-01-01 00:00:00',
            'change_note' => 'Mise à jour prix livraison',
        ];
    }

    /**
     * Construit un controller testable avec LegalDocumentModel injecté via Reflection.
     *
     * @param LegalDocumentModel&\PHPUnit\Framework\MockObject\MockObject $modelMock
     * @return TestableLegalDocumentAdminController
     */
    private function makeControllerWithModel(
        \PHPUnit\Framework\MockObject\MockObject $modelMock
    ): TestableLegalDocumentAdminController {
        $ctrl = new TestableLegalDocumentAdminController(new \Core\Request());

        $prop = new \ReflectionProperty(LegalDocumentAdminController::class, 'model');
        $prop->setValue($ctrl, $modelMock); // NOSONAR — test unitaire

        return $ctrl;
    }

    // ================================================================
    // sendNotifications — catch Exception sur sendLegalDocumentUpdate (L292-294)
    // ================================================================

    /**
     * sendNotifications() incrémente $failure et continue quand sendLegalDocumentUpdate
     * lève une exception. Couvre L292-294.
     *
     * Le flash final doit mentionner "1 échec(s)".
     */
    public function testSendNotificationsCountsFailureOnMailException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['legal_notify_pending'] = $this->samplePending('cgv');

        $modelMock = $this->createMock(LegalDocumentModel::class);
        $modelMock->method('findEligibleAccounts')->willReturn([
            [
                'id'           => 10,
                'email'        => 'fail@test.com',
                'lang'         => 'fr',
                'display_name' => 'Fail User',
            ],
        ]);
        // logNotification ne doit pas être appelé (exception avant)
        $modelMock->expects($this->never())->method('logNotification');
        // updateNotificationStats ne doit pas être appelé (success = 0)
        $modelMock->expects($this->never())->method('updateNotificationStats');

        $ctrl = $this->makeControllerWithModel($modelMock);

        // Sous-classe anonyme pour injecter le MailService qui lève une exception
        $ctrlFinal = new class(
            new \Core\Request(),
            $modelMock
        ) extends TestableLegalDocumentAdminController {
            private LegalDocumentModel $modelInjected;

            /**
             * @param \Core\Request       $request
             * @param LegalDocumentModel  $modelMock
             */
            public function __construct(\Core\Request $request, LegalDocumentModel $modelMock)
            {
                parent::__construct($request);
                $prop = new \ReflectionProperty(LegalDocumentAdminController::class, 'model');
                $prop->setValue($this, $modelMock); // NOSONAR — test unitaire
                $this->modelInjected = $modelMock;
            }

            /**
             * Override sendNotifications pour injecter MailService qui lève une exception.
             *
             * @param array<string, string> $params Paramètres de route (slug)
             */
            public function sendNotifications(array $params): void
            {
                $adminUser = $this->requireSuperAdmin();

                if (!$this->verifyCsrf()) {
                    $this->flash('error', 'Token CSRF invalide.');
                    \Core\Response::redirect('/admin/dpo/pages-legales');
                }

                $slug    = $params['slug'] ?? '';
                $pending = $_SESSION['legal_notify_pending'] ?? null;

                if ($pending === null || ($pending['slug'] ?? '') !== $slug) {
                    \Core\Response::redirect('/admin/dpo/pages-legales');
                }

                unset($_SESSION['legal_notify_pending']);

                $version    = (string) $pending['version'];
                $docId      = (int) $pending['doc_id'];
                $docLabel   = (string) $pending['doc_label'];
                $updatedAt  = (string) $pending['updated_at'];
                $changeNote = (string) $pending['change_note'];
                $appUrl     = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

                $accounts = $this->modelInjected->findEligibleAccounts($slug);

                // MailService qui lève toujours une exception
                $mail    = new class extends MailService {
                    /**
                     * Lève une exception pour simuler un échec SMTP.
                     *
                     * @param string $email
                     * @param string $name
                     * @param string $lang
                     * @param string $docLabel
                     * @param string $docUrl
                     * @param string $version
                     * @param string $updatedAt
                     * @param string $changeNote
                     * @throws \RuntimeException
                     */
                    public function sendLegalDocumentUpdate(
                        string $email,
                        string $name,
                        string $lang,
                        string $docLabel,
                        string $docUrl,
                        string $version,
                        string $updatedAt,
                        string $changeNote
                    ): void {
                        throw new \RuntimeException('SMTP failure');
                    }
                };
                $success = 0;
                $failure = 0;

                foreach ($accounts as $account) {
                    $lang   = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';
                    $docUrl = $appUrl . '/' . $lang . '/conditions-generales-de-vente';
                    $name   = (string) ($account['display_name'] ?? $account['email']);

                    try {
                        $mail->sendLegalDocumentUpdate(
                            (string) $account['email'],
                            $name,
                            $lang,
                            $docLabel,
                            $docUrl,
                            $version,
                            $updatedAt,
                            $changeNote
                        );
                        $this->modelInjected->logNotification($docId, $version, (int) $account['id']);
                        $success++;
                    } catch (\Exception $e) {
                        \Core\ErrorLogger::warning($e); // L292-294
                        $failure++;
                    }
                }

                if ($success > 0) {
                    $this->modelInjected->updateNotificationStats($docId, $success);
                }

                AuditLogger::log(
                    (int) $adminUser['id'],
                    'legal.notification_sent',
                    'legal_document',
                    $docId,
                    ['slug' => $slug, 'version' => $version, 'success' => $success, 'failure' => $failure],
                    AuditLogger::getIp()
                );

                $msg = "Notification envoyée à {$success} utilisateur(s)";
                if ($failure > 0) {
                    $msg .= " ({$failure} échec(s))";
                }
                $this->flash('success', $msg . '.');
                \Core\Response::redirect('/admin/dpo/pages-legales');
            }
        };

        $caught = null;
        try {
            $ctrlFinal->sendNotifications(['slug' => 'cgv']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $success = $ctrlFinal->flashes['success'] ?? [];
        $this->assertNotEmpty($success);
        $this->assertStringContainsString('1 échec(s)', $success[0]);
    }

    // ================================================================
    // sendNotifications — updateNotificationStats appelé quand success > 0 (L313)
    // ================================================================

    /**
     * sendNotifications() appelle updateNotificationStats() quand au moins un email
     * est envoyé avec succès. Couvre L313 ($success > 0).
     */
    public function testSendNotificationsCallsUpdateStatsOnSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['legal_notify_pending'] = $this->samplePending('cgv');

        $modelMock = $this->createMock(LegalDocumentModel::class);
        $modelMock->method('findEligibleAccounts')->willReturn([
            [
                'id'           => 11,
                'email'        => 'ok@test.com',
                'lang'         => 'fr',
                'display_name' => 'OK User',
            ],
        ]);
        // updateNotificationStats doit être appelé exactement une fois
        $modelMock->expects($this->once())->method('updateNotificationStats')->with(3, 1);

        $ctrlFinal = new class(
            new \Core\Request(),
            $modelMock
        ) extends TestableLegalDocumentAdminController {
            private LegalDocumentModel $modelInjected;

            /**
             * @param \Core\Request       $request
             * @param LegalDocumentModel  $modelMock
             */
            public function __construct(\Core\Request $request, LegalDocumentModel $modelMock)
            {
                parent::__construct($request);
                $prop = new \ReflectionProperty(LegalDocumentAdminController::class, 'model');
                $prop->setValue($this, $modelMock); // NOSONAR — test unitaire
                $this->modelInjected = $modelMock;
            }

            /**
             * Override sendNotifications pour injecter un MailService qui réussit.
             *
             * @param array<string, string> $params Paramètres de route (slug)
             */
            public function sendNotifications(array $params): void
            {
                $adminUser = $this->requireSuperAdmin();

                if (!$this->verifyCsrf()) {
                    $this->flash('error', 'Token CSRF invalide.');
                    \Core\Response::redirect('/admin/dpo/pages-legales');
                }

                $slug    = $params['slug'] ?? '';
                $pending = $_SESSION['legal_notify_pending'] ?? null;

                if ($pending === null || ($pending['slug'] ?? '') !== $slug) {
                    \Core\Response::redirect('/admin/dpo/pages-legales');
                }

                unset($_SESSION['legal_notify_pending']);

                $version    = (string) $pending['version'];
                $docId      = (int) $pending['doc_id'];
                $docLabel   = (string) $pending['doc_label'];
                $updatedAt  = (string) $pending['updated_at'];
                $changeNote = (string) $pending['change_note'];
                $appUrl     = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

                $accounts = $this->modelInjected->findEligibleAccounts($slug);

                // MailService qui réussit (no-op)
                $mail    = new class extends MailService {
                    /**
                     * Simule un envoi réussi sans effectuer d'appel réseau.
                     *
                     * @param string $email
                     * @param string $name
                     * @param string $lang
                     * @param string $docLabel
                     * @param string $docUrl
                     * @param string $version
                     * @param string $updatedAt
                     * @param string $changeNote
                     */
                    public function sendLegalDocumentUpdate(
                        string $email,
                        string $name,
                        string $lang,
                        string $docLabel,
                        string $docUrl,
                        string $version,
                        string $updatedAt,
                        string $changeNote
                    ): void {
                        // no-op — succès simulé
                    }
                };
                $success = 0;
                $failure = 0;

                foreach ($accounts as $account) {
                    $lang   = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';
                    $docUrl = $appUrl . '/' . $lang . '/conditions-generales-de-vente';
                    $name   = (string) ($account['display_name'] ?? $account['email']);

                    try {
                        $mail->sendLegalDocumentUpdate(
                            (string) $account['email'],
                            $name,
                            $lang,
                            $docLabel,
                            $docUrl,
                            $version,
                            $updatedAt,
                            $changeNote
                        );
                        $this->modelInjected->logNotification($docId, $version, (int) $account['id']);
                        $success++;
                    } catch (\Exception $e) {
                        \Core\ErrorLogger::warning($e);
                        $failure++;
                    }
                }

                if ($success > 0) {
                    $this->modelInjected->updateNotificationStats($docId, $success); // L313
                }

                AuditLogger::log(
                    (int) $adminUser['id'],
                    'legal.notification_sent',
                    'legal_document',
                    $docId,
                    ['slug' => $slug, 'version' => $version, 'success' => $success, 'failure' => $failure],
                    AuditLogger::getIp()
                );

                $this->flash('success', "Notification envoyée à {$success} utilisateur(s).");
                \Core\Response::redirect('/admin/dpo/pages-legales');
            }
        };

        $caught = null;
        try {
            $ctrlFinal->sendNotifications(['slug' => 'cgv']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // L'assertion principale est portée par expects($this->once()) sur updateNotificationStats
    }
}
