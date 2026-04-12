<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Account;

use Controller\Account\AccountPreferenceController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountPreferenceController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountPreferenceControllerTest extends IntegrationTestCase
{
    private const CSRF = 'test-pref-csrf';

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
     * Instancie AccountPreferenceController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountPreferenceController
     */
    private function makeController(string $method, string $uri): AccountPreferenceController
    {
        return new AccountPreferenceController($this->makeRequest($method, $uri));
    }

    /**
     * Insère un token de désabonnement pour un compte donné.
     *
     * @param int    $userId Identifiant du compte
     * @param string $token  Token de désabonnement en clair
     * @return void
     */
    private function insertUnsubscribeToken(int $userId, string $token): void
    {
        self::$db->insert(
            "UPDATE accounts SET newsletter_unsubscribe_token = ? WHERE id = ?",
            [$token, $userId]
        );
    }

    // ----------------------------------------------------------------
    // favorites()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la page des favoris.
     */
    public function testFavoritesRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('fav.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/favoris')->favorites(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un compte company authentifié peut aussi voir ses favoris.
     */
    public function testFavoritesRendersForCompany(): void
    {
        $userId = $this->insertCustomer('fav.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/favoris')->favorites(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testFavoritesRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/favoris')->favorites(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // unsubscribePage()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue unsubscribe avec success=false et confirm=false.
     */
    public function testUnsubscribePageEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/newsletter/desabonnement')
             ->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable → vue unsubscribe avec confirm=false.
     */
    public function testUnsubscribePageTokenNotFound(): void
    {
        $_GET = ['token' => 'non-existent-token'];

        ob_start();
        $this->makeController('GET', '/fr/newsletter/desabonnement?token=non-existent-token')
             ->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide → vue unsubscribe avec confirm=true.
     */
    public function testUnsubscribePageValidToken(): void
    {
        $userId = $this->insertCustomer('unsub.page.ok@test.local');
        $token  = bin2hex(random_bytes(16));
        $this->insertUnsubscribeToken($userId, $token);
        $_GET = ['token' => $token];

        ob_start();
        $this->makeController('GET', '/fr/newsletter/desabonnement?token=' . $token)
             ->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide avec campaignId → confirm=true, campaignId transmis.
     */
    public function testUnsubscribePageValidTokenWithCampaignId(): void
    {
        $userId = $this->insertCustomer('unsub.page.cid@test.local');
        $token  = bin2hex(random_bytes(16));
        $this->insertUnsubscribeToken($userId, $token);
        $_GET = ['token' => $token, 'cid' => '42'];

        ob_start();
        $this->makeController('GET', '/fr/newsletter/desabonnement?token=' . $token . '&cid=42')
             ->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * cid=0 (invalide) → campaignId=null transmis.
     */
    public function testUnsubscribePageInvalidCampaignId(): void
    {
        $userId = $this->insertCustomer('unsub.page.cid0@test.local');
        $token  = bin2hex(random_bytes(16));
        $this->insertUnsubscribeToken($userId, $token);
        $_GET = ['token' => $token, 'cid' => '0'];

        ob_start();
        $this->makeController('GET', '/fr/newsletter/desabonnement?token=' . $token . '&cid=0')
             ->unsubscribePage(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // unsubscribe()
    // ----------------------------------------------------------------

    /**
     * Token vide en POST → success=false.
     */
    public function testUnsubscribeEmptyToken(): void
    {
        $_POST = ['unsub_token' => '', 'campaign_id' => '0'];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')
             ->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable → success=false.
     */
    public function testUnsubscribeTokenNotFound(): void
    {
        $_POST = ['unsub_token' => 'invalid-token', 'campaign_id' => '0'];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')
             ->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide → désabonné avec success=true.
     */
    public function testUnsubscribeSuccess(): void
    {
        $userId = $this->insertCustomer('unsub.ok@test.local');
        // Activer la newsletter sur ce compte
        self::$db->insert(
            "UPDATE accounts SET newsletter = 1 WHERE id = ?",
            [$userId]
        );
        $token = bin2hex(random_bytes(16));
        $this->insertUnsubscribeToken($userId, $token);

        $_POST = ['unsub_token' => $token, 'campaign_id' => '0'];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')
             ->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide avec campaignId → désabonné + tracking enregistré.
     */
    public function testUnsubscribeSuccessWithCampaignId(): void
    {
        $userId = $this->insertCustomer('unsub.cid.ok@test.local');
        self::$db->insert(
            "UPDATE accounts SET newsletter = 1 WHERE id = ?",
            [$userId]
        );
        $token = bin2hex(random_bytes(16));
        $this->insertUnsubscribeToken($userId, $token);

        // Insérer une newsletter pour le tracking
        $campaignId = (int) self::$db->insert(
            "INSERT INTO newsletters (subject, title, body) VALUES ('Sujet Test', 'Titre Test', 'Corps test')"
        );

        $_POST = ['unsub_token' => $token, 'campaign_id' => (string) $campaignId];

        ob_start();
        $this->makeController('POST', '/fr/newsletter/desabonnement')
             ->unsubscribe(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }
}
