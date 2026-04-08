<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\RgpdAdminController;
use Core\Exception\HttpException;
use Core\Jwt;
use Model\RgpdRequestModel;

/**
 * Tests d'intégration pour RgpdAdminController.
 *
 * Couvre : index, create, store, updateStatus, exportPortabilite.
 * Accès : index/exportPortabilite nécessitent super_admin, store/create nécessitent admin.
 */
class RgpdAdminControllerTest extends AdminIntegrationTestCase
{
    private RgpdRequestModel $requestModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestModel = new RgpdRequestModel();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeController(string $method = 'GET', string $uri = '/admin/rgpd/demandes'): RgpdAdminController
    {
        return new RgpdAdminController($this->makeRequest($method, $uri));
    }

    private function makeSuperAdmin(): void
    {
        $id    = $this->insertAdminAccount('super_admin', 'rgpd-super@test.local');
        $token = Jwt::generate($id, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($id, $token);
    }

    private function insertRequest(string $type = 'acces', string $email = 'user@example.com'): int
    {
        return $this->requestModel->create($type, $email, 'Test User');
    }

    // ----------------------------------------------------------------
    // index — accès
    // ----------------------------------------------------------------

    /**
     * GET /admin/rgpd/demandes sans auth → HttpException.
     */
    public function testIndexRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(HttpException::class);

        ob_start();
        try {
            $this->makeController()->index([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/rgpd/demandes avec rôle admin (pas super_admin) → 404.
     */
    public function testIndexRequiresSuperAdmin(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->index([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/rgpd/demandes avec super_admin → rendu de la liste.
     */
    public function testIndexRendersList(): void
    {
        $this->makeSuperAdmin();

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('RGPD', $output);
    }

    /**
     * GET /admin/rgpd/demandes avec super_admin et filtre status → pas d'exception.
     */
    public function testIndexWithStatusFilter(): void
    {
        $this->makeSuperAdmin();
        $_GET['status'] = 'nouvelle';

        ob_start();
        $this->makeController('GET', '/admin/rgpd/demandes?status=nouvelle')->index([]);
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    /**
     * GET /admin/rgpd/demandes avec super_admin et filtre type → pas d'exception.
     */
    public function testIndexWithTypeFilter(): void
    {
        $this->makeSuperAdmin();
        $_GET['type'] = 'effacement';

        ob_start();
        $this->makeController('GET', '/admin/rgpd/demandes?type=effacement')->index([]);
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    // ----------------------------------------------------------------
    // create — formulaire
    // ----------------------------------------------------------------

    /**
     * GET /admin/rgpd/demandes/ajouter avec admin → rendu du formulaire.
     */
    public function testCreateRendersForm(): void
    {
        ob_start();
        $this->makeController('GET', '/admin/rgpd/demandes/ajouter')->create([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('demande', strtolower($output));
    }

    /**
     * GET /admin/rgpd/demandes/ajouter sans auth → HttpException.
     */
    public function testCreateRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(HttpException::class);

        ob_start();
        try {
            $this->makeController('GET', '/admin/rgpd/demandes/ajouter')->create([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // store — validation
    // ----------------------------------------------------------------

    /**
     * POST store sans CSRF valide → redirect (302).
     */
    public function testStoreRejectsInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'wrong-token';
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
    }

    /**
     * POST store avec type invalide → redirect (302).
     */
    public function testStoreRejectsInvalidType(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['type']            = 'invalid_type';
        $_POST['requester_email'] = 'test@example.com';
        $_POST['requester_name']  = 'Test User';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
    }

    /**
     * POST store avec email invalide → redirect (302).
     */
    public function testStoreRejectsInvalidEmail(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['type']            = 'acces';
        $_POST['requester_email'] = 'not-an-email';
        $_POST['requester_name']  = 'Test User';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
    }

    /**
     * POST store avec nom vide → redirect (302).
     */
    public function testStoreRejectsEmptyName(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['type']            = 'acces';
        $_POST['requester_email'] = 'valid@example.com';
        $_POST['requester_name']  = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
    }

    /**
     * POST store avec date invalide → redirect (302).
     */
    public function testStoreRejectsInvalidDate(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['type']            = 'effacement';
        $_POST['requester_email'] = 'valid@example.com';
        $_POST['requester_name']  = 'Test User';
        $_POST['received_at']     = 'not-a-date';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
    }

    /**
     * POST store valide → demande créée en BDD + redirect.
     */
    public function testStoreCreatesRequestInDatabase(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['type']            = 'portabilite';
        $_POST['requester_email'] = 'portabilite@example.com';
        $_POST['requester_name']  = 'Jean Dupont';
        $_POST['received_at']     = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT * FROM rgpd_requests WHERE requester_email = ?",
                ['portabilite@example.com']
            );
            $this->assertNotFalse($row);
            $this->assertSame('portabilite', $row['type']);
            $this->assertSame('nouvelle', $row['status']);
            throw $e;
        }
    }

    /**
     * POST store avec date valide → demande créée avec la date fournie.
     */
    public function testStoreAcceptsCustomDate(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['type']            = 'acces';
        $_POST['requester_email'] = 'custom-date@example.com';
        $_POST['requester_name']  = 'Marie Curie';
        $_POST['received_at']     = '2026-03-01';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST', '/admin/rgpd/demandes/ajouter')->store([]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT * FROM rgpd_requests WHERE requester_email = ?",
                ['custom-date@example.com']
            );
            $this->assertNotFalse($row);
            $this->assertStringStartsWith('2026-03-01', $row['received_at']);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // updateStatus
    // ----------------------------------------------------------------

    /**
     * POST updateStatus sans CSRF → redirect (302).
     */
    public function testUpdateStatusRejectsInvalidCsrf(): void
    {
        $id = $this->insertRequest();
        $_POST['csrf_token'] = 'bad-token';
        $_POST['status']     = 'en_cours';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->updateStatus(['id' => (string) $id]);
    }

    /**
     * POST updateStatus avec ID inexistant → 404.
     */
    public function testUpdateStatusAborts404ForUnknownId(): void
    {
        $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['status']     = 'en_cours';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController('POST')->updateStatus(['id' => '999999']);
    }

    /**
     * POST updateStatus avec statut invalide → redirect (302).
     */
    public function testUpdateStatusRejectsInvalidStatus(): void
    {
        $this->makeSuperAdmin();
        $id = $this->insertRequest();
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['status']     = 'statut_invalide';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->updateStatus(['id' => (string) $id]);
    }

    /**
     * POST updateStatus valide → statut mis à jour en BDD + redirect.
     */
    public function testUpdateStatusUpdatesDatabase(): void
    {
        $this->makeSuperAdmin();
        $id = $this->insertRequest('acces', 'status-update@example.com');
        $_POST['csrf_token']     = self::CSRF_TOKEN;
        $_POST['status']         = 'en_cours';
        $_POST['response_note']  = 'Prise en charge';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->updateStatus(['id' => (string) $id]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT status FROM rgpd_requests WHERE id = ?",
                [$id]
            );
            $this->assertSame('en_cours', $row['status']);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // exportPortabilite
    // ----------------------------------------------------------------

    /**
     * GET exportPortabilite avec ID inexistant → 404.
     */
    public function testExportPortabiliteAborts404ForUnknownId(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->exportPortabilite(['id' => '999999']);
    }

    /**
     * GET exportPortabilite sur une demande de type non-portabilite → redirect (302).
     */
    public function testExportPortabiliteRejectsWrongType(): void
    {
        $id = $this->insertRequest('acces', 'wrong-type@example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController()->exportPortabilite(['id' => (string) $id]);
    }

    /**
     * GET exportPortabilite sur une demande de type portabilite → handlePortabilite déclenché + redirect.
     */
    public function testExportPortabiliteHappyPathRedirects(): void
    {
        $this->makeSuperAdmin();
        $id = $this->insertRequest('portabilite', 'export-happy@example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController()->exportPortabilite(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // updateStatus — traitee avec effacement / portabilite
    // ----------------------------------------------------------------

    /**
     * POST updateStatus status=traitee + type=effacement → handleEffacement déclenché (compte inconnu → no-op) + redirect.
     */
    public function testUpdateStatusTraiteeEffacementTriggersHandler(): void
    {
        $this->makeSuperAdmin();
        $id = $this->insertRequest('effacement', 'effacement-handler@example.com');
        $_POST['csrf_token']    = self::CSRF_TOKEN;
        $_POST['status']        = 'traitee';
        $_POST['response_note'] = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->updateStatus(['id' => (string) $id]);
    }

    /**
     * POST updateStatus status=traitee + type=portabilite → handlePortabilite déclenché + redirect.
     */
    public function testUpdateStatusTraiteePortabiliteTriggersHandler(): void
    {
        $this->makeSuperAdmin();
        $id = $this->insertRequest('portabilite', 'portabilite-handler@example.com');
        $_POST['csrf_token']    = self::CSRF_TOKEN;
        $_POST['status']        = 'traitee';
        $_POST['response_note'] = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->updateStatus(['id' => (string) $id]);
    }

    /**
     * POST updateStatus status=traitee + type=effacement + compte existant → compte supprimé (soft-delete).
     */
    public function testUpdateStatusTraiteeEffacementDeletesMatchingAccount(): void
    {
        $this->makeSuperAdmin();

        // Créer un compte utilisateur avec le même email que la demande RGPD
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            ['effacement-match@example.com', password_hash('Pass123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Test', 'Effacement', 'M')",
            [$accountId]
        );

        $id = $this->insertRequest('effacement', 'effacement-match@example.com');
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['status']     = 'traitee';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->updateStatus(['id' => (string) $id]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT deleted_at FROM accounts WHERE id = ?",
                [$accountId]
            );
            $this->assertNotFalse($row);
            $this->assertNotNull($row['deleted_at']);
            throw $e;
        }
    }
}
