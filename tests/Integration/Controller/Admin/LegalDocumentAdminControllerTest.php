<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\LegalDocumentAdminController;
use Core\Exception\HttpException;
use Core\Jwt;

/**
 * Tests d'intégration pour LegalDocumentAdminController.
 */
class LegalDocumentAdminControllerTest extends AdminIntegrationTestCase
{
    private int $superAdminId;

    protected function setUp(): void
    {
        parent::setUp();
        // Créer un super_admin distinct pour les tests de cette US
        $this->superAdminId = $this->insertAdminAccount('super_admin', 'superadmin@test.local');
        $token = Jwt::generate($this->superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($this->superAdminId, $token);
    }

    private function makeController(string $method = 'GET', string $uri = '/admin/dpo/pages-legales'): LegalDocumentAdminController
    {
        return new LegalDocumentAdminController($this->makeRequest($method, $uri));
    }

    // ----------------------------------------------------------------
    // index() — GET /admin/dpo/pages-legales
    // ----------------------------------------------------------------

    public function testIndexDisplaysAllDocuments(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Documents légaux', $output);
        $this->assertStringContainsString('cgv', $output);
        $this->assertStringContainsString('politique_confidentialite', $output);
        $this->assertStringContainsString('Modifier', $output);
    }

    public function testIndexRequiresSuperAdmin(): void
    {
        // Re-basculer sur un admin simple
        $token = Jwt::generate($this->adminId, 'admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($this->adminId, $token);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->index([]);
    }

    // ----------------------------------------------------------------
    // edit() — GET /admin/dpo/pages-legales/{slug}/modifier
    // ----------------------------------------------------------------

    public function testEditDisplaysForm(): void
    {
        ob_start();
        $this->makeController('GET', '/admin/dpo/pages-legales/cgv/modifier')
            ->edit(['slug' => 'cgv']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Modifier', $output);
        $this->assertStringContainsString('version', $output);
        $this->assertStringContainsString('updated_at', $output);
    }

    public function testEditAborts404ForUnknownSlug(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController('GET', '/admin/dpo/pages-legales/inexistant/modifier')
            ->edit(['slug' => 'inexistant']);
    }

    public function testEditRequiresSuperAdmin(): void
    {
        $token = Jwt::generate($this->adminId, 'admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($this->adminId, $token);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController('GET', '/admin/dpo/pages-legales/cgv/modifier')
            ->edit(['slug' => 'cgv']);
    }

    // ----------------------------------------------------------------
    // store() — POST /admin/dpo/pages-legales/{slug}/modifier
    // ----------------------------------------------------------------

    public function testStoreValidUpdateRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['version']    = '1.2';
        $_POST['updated_at'] = '2026-05-01';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/dpo/pages-legales/cgv/modifier')
            ->store(['slug' => 'cgv']);
    }

    public function testStoreInvalidVersionFlashesError(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['version']    = 'v1.2bad';
        $_POST['updated_at'] = '2026-05-01';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/dpo/pages-legales/cgv/modifier')
            ->store(['slug' => 'cgv']);
    }

    public function testStoreInvalidDateFlashesError(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['version']    = '1.2';
        $_POST['updated_at'] = 'not-a-date';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/dpo/pages-legales/cgv/modifier')
            ->store(['slug' => 'cgv']);
    }

    public function testStoreUnknownSlugAborts404(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['version']    = '1.0';
        $_POST['updated_at'] = '2026-05-01';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController('POST', '/admin/dpo/pages-legales/inexistant/modifier')
            ->store(['slug' => 'inexistant']);
    }

    public function testStoreRequiresSuperAdmin(): void
    {
        $token = Jwt::generate($this->adminId, 'admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($this->adminId, $token);

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['version']    = '1.0';
        $_POST['updated_at'] = '2026-05-01';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController('POST', '/admin/dpo/pages-legales/cgv/modifier')
            ->store(['slug' => 'cgv']);
    }

    // ----------------------------------------------------------------
    // store() + send_notification → redirect to confirm
    // ----------------------------------------------------------------

    public function testStoreWithNotificationRedirectsToConfirm(): void
    {
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['version']           = '1.2';
        $_POST['updated_at']        = '2026-05-01';
        $_POST['send_notification'] = '1';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/dpo/pages-legales/cgv/modifier')
            ->store(['slug' => 'cgv']);
    }

    public function testStoreWithNotificationStoresPendingInSession(): void
    {
        $_POST['csrf_token']        = self::CSRF_TOKEN;
        $_POST['version']           = '1.2';
        $_POST['updated_at']        = '2026-05-01';
        $_POST['send_notification'] = '1';
        $_POST['change_note']       = 'Résumé test.';

        try {
            $this->makeController('POST', '/admin/dpo/pages-legales/cgv/modifier')
                ->store(['slug' => 'cgv']);
        } catch (HttpException $e) {
            // attendu
        }

        $this->assertArrayHasKey('legal_notify_pending', $_SESSION);
        $this->assertSame('cgv', $_SESSION['legal_notify_pending']['slug']);
        $this->assertSame('1.2', $_SESSION['legal_notify_pending']['version']);
        $this->assertSame('Résumé test.', $_SESSION['legal_notify_pending']['change_note']);
    }

    // ----------------------------------------------------------------
    // confirmNotify() — GET
    // ----------------------------------------------------------------

    public function testConfirmNotifyDisplaysCount(): void
    {
        $_SESSION['legal_notify_pending'] = [
            'slug'        => 'cgv',
            'version'     => '1.2',
            'doc_id'      => 1,
            'doc_label'   => 'Conditions Générales de Vente',
            'updated_at'  => '2026-05-01',
            'change_note' => '',
        ];

        ob_start();
        $this->makeController('GET', '/admin/dpo/pages-legales/cgv/confirmer-notification')
            ->confirmNotify(['slug' => 'cgv']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Confirmer', $output);
        $this->assertStringContainsString('utilisateur', $output);
    }

    public function testConfirmNotifyRedirectsWhenNoPending(): void
    {
        unset($_SESSION['legal_notify_pending']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('GET', '/admin/dpo/pages-legales/cgv/confirmer-notification')
            ->confirmNotify(['slug' => 'cgv']);
    }

    // ----------------------------------------------------------------
    // sendNotifications() — POST
    // ----------------------------------------------------------------

    public function testSendNotificationsRedirectsAndClearsPending(): void
    {
        $_SESSION['legal_notify_pending'] = [
            'slug'        => 'cgv',
            'version'     => '1.2',
            'doc_id'      => 1,
            'doc_label'   => 'Conditions Générales de Vente',
            'updated_at'  => '2026-05-01',
            'change_note' => '',
        ];
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $caught = null;
        try {
            $this->makeController('POST', '/admin/dpo/pages-legales/cgv/confirmer-notification')
                ->sendNotifications(['slug' => 'cgv']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // Le pending doit être supprimé de la session
        $this->assertArrayNotHasKey('legal_notify_pending', $_SESSION);
        // Redirige vers la liste
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }
}
