<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Account;

use Controller\Account\AccountExportController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountExportController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountExportControllerTest extends IntegrationTestCase
{
    private const CSRF = 'test-export-csrf';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte client vérifié.
     *
     * @param string $email       Adresse e-mail unique
     * @param string $accountType 'individual' ou 'company'
     * @return int Identifiant du compte créé
     */
    private function insertCustomer(string $email, string $accountType = 'individual'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT), $accountType]
        );

        if ($accountType === 'company') {
            self::$db->insert(
                "INSERT INTO account_companies (account_id, company_name, siret)
                 VALUES (?, 'Acme SARL', '12345678901234')",
                [$id]
            );
        } else {
            self::$db->insert(
                "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                 VALUES (?, 'Dupont', 'Jean', 'M')",
                [$id]
            );
        }

        return $id;
    }

    /**
     * Connecte un utilisateur via cookie JWT + connexion en base.
     *
     * @param int    $userId Identifiant du compte
     * @param string $role   Rôle JWT
     * @return string Token JWT généré
     */
    private function loginAs(int $userId, string $role = 'customer'): string
    {
        $token = Jwt::generate($userId, $role);
        $_COOKIE['auth_token'] = $token;
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        return $token;
    }

    /**
     * Crée un objet Request.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return Request
     */
    private function makeRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $uriQuery = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $uriQuery);
        $_GET = array_merge($_GET, $uriQuery);
        return new Request();
    }

    /**
     * Instancie AccountExportController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountExportController
     */
    private function makeController(string $method, string $uri): AccountExportController
    {
        return new AccountExportController($this->makeRequest($method, $uri));
    }

    // ----------------------------------------------------------------
    // exportPage()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la page export.
     */
    public function testExportPageRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('export.page.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('export', $output);
    }

    /**
     * Un compte company authentifié voit la page export.
     */
    public function testExportPageRendersForCompany(): void
    {
        $userId = $this->insertCustomer('export.page.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('export', $output);
    }

    /**
     * Les flashs export sont consommés à l'affichage.
     */
    public function testExportPageConsumesFlash(): void
    {
        $userId = $this->insertCustomer('export.flash@test.local');
        $this->loginAs($userId);
        $_SESSION['flash']['export_sent']  = 'email@test.local';
        $_SESSION['flash']['export_error'] = 'err';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('export_sent', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('export_error', $_SESSION['flash'] ?? []);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testExportPageRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/export')->exportPage(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // exportSend()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testExportSendCsrfFails(): void
    {
        $userId = $this->insertCustomer('export.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/export/envoyer')->exportSend(['lang' => 'fr']);
    }

    /**
     * Export valide → email envoyé, redirection.
     * newMailService(), buildExportData() et buildExportContent() surchargés.
     */
    public function testExportSendSuccess(): void
    {
        $userId = $this->insertCustomer('export.send.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $controller = new class ($this->makeRequest('POST', '/fr/mon-compte/export/envoyer')) extends AccountExportController {
            protected function newMailService(): \Service\MailService
            {
                return new class extends \Service\MailService {
                    public function sendRgpdExport(string $to, string $name, string $content, string $filename, string $lang): void
                    {
                        // no-op : évite l'envoi SMTP réel
                    }
                };
            }

            protected function buildExportData(int $userId): array
            {
                return [
                    'account'              => ['email' => 'test@test.local', 'role' => 'customer'],
                    'orders'               => [],
                    'addresses'            => [],
                    'adresses_commandes'   => [],
                    'favorites'            => [],
                    'trusted_devices'      => [],
                    'active_sessions'      => [],
                    'connection_logs'      => [],
                    'consentements'        => ['newsletter' => [], 'cgv_versions_acceptees' => []],
                ];
            }

            protected function buildExportContent(array $export, string $date, string $basename): array
            {
                return ['{}', false];
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $controller->exportSend(['lang' => 'fr']);
    }

    /**
     * Export avec MailService qui échoue → flash error, redirection.
     */
    public function testExportSendMailFails(): void
    {
        $userId = $this->insertCustomer('export.mailfail@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $controller = new class ($this->makeRequest('POST', '/fr/mon-compte/export/envoyer')) extends AccountExportController {
            protected function newMailService(): \Service\MailService
            {
                return new class extends \Service\MailService {
                    public function sendRgpdExport(string $to, string $name, string $content, string $filename, string $lang): void
                    {
                        throw new \RuntimeException('SMTP error');
                    }
                };
            }

            protected function buildExportData(int $userId): array
            {
                return [
                    'account'              => ['email' => 'test@test.local', 'role' => 'customer'],
                    'orders'               => [],
                    'addresses'            => [],
                    'adresses_commandes'   => [],
                    'favorites'            => [],
                    'trusted_devices'      => [],
                    'active_sessions'      => [],
                    'connection_logs'      => [],
                    'consentements'        => ['newsletter' => [], 'cgv_versions_acceptees' => []],
                ];
            }

            protected function buildExportContent(array $export, string $date, string $basename): array
            {
                return ['{}', false];
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $controller->exportSend(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testExportSendRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/export/envoyer')->exportSend(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // buildExportHeaders() — helper protégé
    // ----------------------------------------------------------------

    /**
     * buildExportHeaders retourne les bons headers pour ZIP.
     */
    public function testBuildExportHeadersZip(): void
    {
        $userId = $this->insertCustomer('export.headers.zip@test.local');
        $this->loginAs($userId);

        $controller = new AccountExportController($this->makeRequest('GET', '/fr/mon-compte/export'));
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildExportHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($controller, 'mes-donnees-2026-04-01', true);

        $this->assertContains('Content-Disposition: attachment; filename="mes-donnees-2026-04-01.zip"', $headers);
    }

    /**
     * buildExportHeaders retourne les bons headers pour JSON.
     */
    public function testBuildExportHeadersJson(): void
    {
        $userId = $this->insertCustomer('export.headers.json@test.local');
        $this->loginAs($userId);

        $controller = new AccountExportController($this->makeRequest('GET', '/fr/mon-compte/export'));
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildExportHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($controller, 'mes-donnees-2026-04-01', false);

        $this->assertContains('Content-Disposition: attachment; filename="mes-donnees-2026-04-01.json"', $headers);
    }

    // ----------------------------------------------------------------
    // buildExportContent() — helper protégé
    // ----------------------------------------------------------------

    /**
     * buildExportContent retourne le JSON brut quand ZipArchive n'est pas dispo.
     */
    public function testBuildExportContentFallbackToJson(): void
    {
        if (class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive disponible — test fallback JSON non applicable');
        }

        $userId = $this->insertCustomer('export.content.json@test.local');
        $this->loginAs($userId);

        $export = [
            'account'            => ['email' => 'test@test.local'],
            'orders'             => [],
            'addresses'          => [],
            'adresses_commandes' => [],
            'favorites'          => [],
            'trusted_devices'    => [],
            'active_sessions'    => [],
            'connection_logs'    => [],
            'consentements'      => [],
        ];

        $controller = new AccountExportController($this->makeRequest('GET', '/fr/mon-compte/export'));
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildExportContent');
        $method->setAccessible(true);

        [$content, $isZip] = $method->invoke($controller, $export, '2026-04-01', 'mes-donnees-2026-04-01');

        $this->assertFalse($isZip);
        $this->assertJson($content);
    }
}
