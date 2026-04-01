<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AccountController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour le flux de changement d'email.
 *
 * Couvre :
 *  - Formulaire POST → génération token + insertion en BDD
 *  - GET lien confirmation → email mis à jour, sessions révoquées, audit loggué
 *  - Token expiré → exception
 *  - Token déjà utilisé → exception
 *  - Rate limit (>= 3 demandes / 24h) → exception
 *  - Mauvais mot de passe → exception
 *
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountEmailChangeTest extends IntegrationTestCase
{
    private const CSRF       = 'test-email-change-csrf';
    private const PASSWORD   = 'Password123!';
    private const OLD_EMAIL  = 'ancien@example.com';
    private const NEW_EMAIL  = 'nouveau@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
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
     * Insère un compte client vérifié et retourne son ID.
     *
     * @param string $email    Adresse email du compte
     * @param string $password Mot de passe en clair
     * @return int Identifiant du compte créé
     */
    private function insertCustomer(
        string $email = self::OLD_EMAIL,
        string $password = self::PASSWORD
    ): int {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            [$email, password_hash($password, PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Dupont', 'Jean', 'M')",
            [$id]
        );
        return $id;
    }

    /**
     * Connecte un utilisateur via cookie JWT + connexion en base.
     *
     * @param int    $userId Identifiant du compte
     * @param string $role   Rôle JWT
     * @return string Token JWT
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
     * Crée un objet Request et un AccountController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountController
     */
    private function makeController(string $method, string $uri): AccountController
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $uriQuery = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $uriQuery);
        $_GET = array_merge($_GET, $uriQuery);
        return new AccountController(new Request());
    }

    /**
     * Insère directement un token de changement d'email en BDD.
     *
     * @param int    $userId     Identifiant du compte
     * @param string $rawToken   Token brut (haché en SHA-256 avant stockage)
     * @param string $newEmail   Nouvelle adresse email
     * @param int    $ttlSeconds TTL en secondes (positif = valide, négatif = expiré)
     * @param bool   $used       Si true, marque le token comme déjà utilisé
     * @return void
     */
    private function insertEmailChangeToken(
        int $userId,
        string $rawToken,
        string $newEmail,
        int $ttlSeconds = 86400,
        bool $used = false
    ): void {
        $hashed    = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $usedAt    = $used ? date('Y-m-d H:i:s', time() - 60) : null;

        self::$db->execute(
            "UPDATE accounts
             SET email_change_token    = ?,
                 email_change_new_email = ?,
                 email_change_expires_at = ?,
                 email_change_used_at   = ?
             WHERE id = ?",
            [$hashed, $newEmail, $expiresAt, $usedAt, $userId]
        );
    }

    // ----------------------------------------------------------------
    // Flux complet requête → confirmation
    // ----------------------------------------------------------------

    /**
     * Flux nominal : POST demande → token enregistré en BDD,
     * GET confirmation → email mis à jour, sessions révoquées, audit loggué.
     */
    public function testFullEmailChangeFlow(): void
    {
        $userId = $this->insertCustomer();
        $this->loginAs($userId);

        // ── ÉTAPE 1 : POST demande de changement ──────────────────────
        $_POST = [
            'csrf_token'       => self::CSRF,
            'new_email'        => self::NEW_EMAIL,
            'current_password' => self::PASSWORD,
        ];

        $ctrl = $this->makeController('POST', '/fr/mon-compte/profil/changer-email');

        $this->expectOutputRegex('/./'); // absorbe l'éventuel echo de redirect
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->fail('requestEmailChange a levé une HttpException inattendue : ' . $e->getMessage());
        } catch (\Throwable) {
            // redirect() peut appeler header() → exception ignorée en CLI
        }

        // Vérifier que le token a bien été enregistré en BDD
        $row = self::$db->fetchOne(
            "SELECT email_change_token, email_change_new_email, email_change_expires_at
             FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertNotNull($row['email_change_token'], 'Le token doit être stocké en BDD');
        $this->assertSame(self::NEW_EMAIL, $row['email_change_new_email']);
        $this->assertGreaterThan(
            date('Y-m-d H:i:s'),
            $row['email_change_expires_at'],
            'Le token doit être dans le futur'
        );

        // ── ÉTAPE 2 : GET confirmation via le lien email ───────────────
        // Récupérer le token haché stocké (pour reconstruire le raw token n'est
        // pas possible, donc on insère directement un raw token connu)
        $rawToken = bin2hex(random_bytes(32));
        $this->insertEmailChangeToken($userId, $rawToken, self::NEW_EMAIL);

        $_GET    = ['token' => $rawToken];
        $_COOKIE = []; // déconnecté lors de la confirmation (autre navigateur)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/email/confirmer?token=' . $rawToken;
        $ctrl2 = new AccountController(new Request());

        try {
            $ctrl2->confirmEmailChange(['lang' => 'fr']);
        } catch (\Throwable) {
            // redirect() → header() → exception ignorée en CLI
        }

        // L'email doit être mis à jour
        $updated = self::$db->fetchOne(
            "SELECT email, email_change_used_at FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertSame(self::NEW_EMAIL, $updated['email'], 'L\'email doit être mis à jour');
        $this->assertNotNull($updated['email_change_used_at'], 'Le token doit être marqué utilisé');

        // Les sessions doivent être révoquées
        $activeSessions = self::$db->fetchOne(
            "SELECT COUNT(*) AS total FROM connections WHERE user_id = ? AND status = 'active'",
            [$userId]
        );
        $this->assertSame(0, (int) $activeSessions['total'], 'Toutes les sessions doivent être révoquées');

        // L'audit doit être loggué
        $audit = self::$db->fetchOne(
            "SELECT * FROM audit_log WHERE user_id = ? AND event_type = 'email_changed' ORDER BY id DESC LIMIT 1",
            [$userId]
        );
        $this->assertNotFalse($audit, 'Un événement d\'audit doit être enregistré');
    }

    // ----------------------------------------------------------------
    // Token expiré
    // ----------------------------------------------------------------

    /**
     * Confirmation avec token expiré → HttpException attendue.
     */
    public function testConfirmWithExpiredTokenThrows(): void
    {
        $userId   = $this->insertCustomer();
        $rawToken = bin2hex(random_bytes(32));
        $this->insertEmailChangeToken($userId, $rawToken, self::NEW_EMAIL, -3600); // expiré

        $_GET    = ['token' => $rawToken];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/email/confirmer?token=' . $rawToken;
        $ctrl = new AccountController(new Request());

        $this->expectException(HttpException::class);
        $ctrl->confirmEmailChange(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // Token déjà utilisé
    // ----------------------------------------------------------------

    /**
     * Confirmation avec token déjà utilisé → HttpException attendue.
     */
    public function testConfirmWithUsedTokenThrows(): void
    {
        $userId   = $this->insertCustomer();
        $rawToken = bin2hex(random_bytes(32));
        $this->insertEmailChangeToken($userId, $rawToken, self::NEW_EMAIL, 86400, true); // used

        $_GET    = ['token' => $rawToken];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/email/confirmer?token=' . $rawToken;
        $ctrl = new AccountController(new Request());

        $this->expectException(HttpException::class);
        $ctrl->confirmEmailChange(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // Rate limit
    // ----------------------------------------------------------------

    /**
     * Si 3 demandes déjà faites dans les 24h, la 4e doit lever une HttpException.
     */
    public function testRequestEmailChangeRateLimitThrows(): void
    {
        $userId = $this->insertCustomer();
        $this->loginAs($userId);

        // Simuler 3 entrées d'audit email_change_request dans les 24h
        for ($i = 0; $i < 3; $i++) {
            self::$db->insert(
                "INSERT INTO audit_log (user_id, event_type, ip, created_at)
                 VALUES (?, 'email_change_request', '127.0.0.1', NOW())",
                [$userId]
            );
        }

        $_POST = [
            'csrf_token'       => self::CSRF,
            'new_email'        => self::NEW_EMAIL,
            'current_password' => self::PASSWORD,
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/profil/changer-email';
        $ctrl = new AccountController(new Request());

        $this->expectException(HttpException::class);
        $ctrl->requestEmailChange(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // Mauvais mot de passe
    // ----------------------------------------------------------------

    /**
     * POST avec mauvais mot de passe → HttpException attendue.
     */
    public function testRequestEmailChangeWrongPasswordThrows(): void
    {
        $userId = $this->insertCustomer();
        $this->loginAs($userId);

        $_POST = [
            'csrf_token'       => self::CSRF,
            'new_email'        => self::NEW_EMAIL,
            'current_password' => 'mauvais_mot_de_passe',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/profil/changer-email';
        $ctrl = new AccountController(new Request());

        $this->expectException(HttpException::class);
        $ctrl->requestEmailChange(['lang' => 'fr']);
    }
}
