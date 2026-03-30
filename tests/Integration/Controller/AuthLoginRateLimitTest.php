<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour les fonctionnalités de rate limiting du login.
 *
 * Couvre :
 *   - R1  : blocage IP après 5 tentatives (auth.too_many_attempts)
 *   - BT2 : lockout compte après 5 échecs consécutifs (auth.account_locked)
 *   - E1  : durée cookie/JWT remember_me=1 (30 j) vs remember_me=0 (24 h)
 *   - Reset des compteurs après connexion réussie
 *   - Compte inexistant : compteur IP incrémenté, pas de compteur compte
 */
class AuthLoginRateLimitTest extends IntegrationTestCase
{
    private const CSRF        = 'integration-csrf-token';
    private const IP_BLOCKED  = '10.0.0.1';
    private const IP_CLEAN    = '10.0.0.2';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF, '_rl' => []];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/RateLimitTest';
        $_SERVER['REMOTE_ADDR']     = self::IP_CLEAN;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST    = [];
        $_COOKIE  = [];
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Construit une instance d'AuthController prête pour un POST /fr/connexion.
     */
    private function makeController(): AuthController
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/connexion';
        $_GET = [];
        return new AuthController(new Request());
    }

    /**
     * Insère un compte vérifié dans la BDD de test.
     *
     * @return int ID du compte créé
     */
    private function insertVerifiedAccount(
        string $email = 'user@rl.test',
        string $password = 'Password123!'
    ): int {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash($password, PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'RL', 'Test', 'M')",
            [$id]
        );
        return $id;
    }

    /**
     * Pré-charge le compteur de session pour simuler N tentatives déjà enregistrées
     * sur la clé donnée (format clé interne RateLimiterService).
     *
     * @param string $bucketKey Clé de bucket telle que "login:10.0.0.1" ou "account_lockout:42"
     * @param int    $count     Nombre de tentatives à pré-charger
     */
    private function preloadRlCount(string $bucketKey, int $count): void
    {
        // RateLimiterService stocke sous "rl:{key}:count" et "rl:{key}:until"
        $_SESSION['_rl']['rl:' . $bucketKey . ':count'] = $count;
    }

    /**
     * Pré-pose un lockout actif sur un bucket (timestamp dans le futur).
     *
     * @param string $bucketKey Clé de bucket
     */
    private function preloadRlLockout(string $bucketKey): void
    {
        $_SESSION['_rl']['rl:' . $bucketKey . ':until'] = time() + 900;
        // Conserver le compteur au seuil pour que checkLimit lève bien le bloc
        $_SESSION['_rl']['rl:' . $bucketKey . ':count'] = 5;
    }

    /**
     * Retourne le compteur RateLimiter de la session pour la clé donnée.
     *
     * @param string $bucketKey Clé de bucket
     * @return int
     */
    private function getRlCount(string $bucketKey): int
    {
        return (int) ($_SESSION['_rl']['rl:' . $bucketKey . ':count'] ?? 0);
    }

    /**
     * Retourne le timestamp de lockout en session pour la clé donnée (0 si absent).
     *
     * @param string $bucketKey Clé de bucket
     * @return int
     */
    private function getRlUntil(string $bucketKey): int
    {
        return (int) ($_SESSION['_rl']['rl:' . $bucketKey . ':until'] ?? 0);
    }

    // ----------------------------------------------------------------
    // R1 — Rate limiting par IP
    // ----------------------------------------------------------------

    /**
     * R1 : après 5 tentatives, l'IP est bloquée et le message auth.too_many_attempts
     * est flashé ; la redirection doit renvoyer vers safeBack.
     */
    public function testIpBlockedAfterFiveAttempts(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_BLOCKED;
        // Pré-charger 5 tentatives (seuil MAX_LOGIN_IP atteint)
        $this->preloadRlCount('login:' . self::IP_BLOCKED, 5);

        $_POST = [
            'email'      => 'nobody@rl.test',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $flash = $_SESSION['flash']['modal_error'] ?? '';
        // Le message contient la traduction de auth.too_many_attempts
        $this->assertNotEmpty($flash, 'Un message flash doit être posé lors du blocage IP');
    }

    /**
     * R1 : une tentative supplémentaire après lockout IP ne doit pas
     * incrémenter le compteur IP (la requête est rejetée avant).
     */
    public function testIpBlockedDoesNotIncrementCounterFurtherAfterLockout(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_BLOCKED;
        $this->preloadRlLockout('login:' . self::IP_BLOCKED);

        $_POST = [
            'email'      => 'nobody@rl.test',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Le compteur ne doit pas avoir augmenté au-delà de 5
        $this->assertLessThanOrEqual(5, $this->getRlCount('login:' . self::IP_BLOCKED));
    }

    /**
     * R1 : un mauvais mot de passe avec IP propre incrémente le compteur IP.
     */
    public function testFailedLoginIncrementsIpCounter(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $this->insertVerifiedAccount('incr@rl.test', 'CorrectPass1!');

        $before = $this->getRlCount('login:' . self::IP_CLEAN);

        $_POST = [
            'email'      => 'incr@rl.test',
            'password'   => 'WrongPass99!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $this->assertSame($before + 1, $this->getRlCount('login:' . self::IP_CLEAN));
    }

    // ----------------------------------------------------------------
    // BT2 — Lockout compte
    // ----------------------------------------------------------------

    /**
     * BT2 : après 5 échecs sur un compte, le message auth.account_locked est flashé.
     */
    public function testAccountLockedAfterFiveFailures(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $id = $this->insertVerifiedAccount('locked@rl.test', 'Password123!');
        $this->preloadRlCount('account_lockout:' . $id, 5);

        $_POST = [
            'email'      => 'locked@rl.test',
            'password'   => 'WrongPass99!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $flash = $_SESSION['flash']['modal_error'] ?? '';
        $this->assertNotEmpty($flash, 'Un message flash doit être posé lors du lockout compte');
    }

    /**
     * BT2 : le lockout compte incrémente également le compteur IP.
     */
    public function testAccountLockoutAlsoIncrementsIpCounter(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $id = $this->insertVerifiedAccount('locked2@rl.test', 'Password123!');
        $this->preloadRlLockout('account_lockout:' . $id);

        $ipBefore = $this->getRlCount('login:' . self::IP_CLEAN);

        $_POST = [
            'email'      => 'locked2@rl.test',
            'password'   => 'WrongPass99!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Le contrôleur appelle recordAttempt($ipKey) lors du lockout BT2
        $this->assertGreaterThan($ipBefore, $this->getRlCount('login:' . self::IP_CLEAN));
    }

    /**
     * Compte inexistant : le compteur IP est incrémenté mais aucune clé
     * "account_lockout:*" ne doit apparaître dans la session.
     */
    public function testUnknownEmailIncrementsIpButNotAccountCounter(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $ipBefore = $this->getRlCount('login:' . self::IP_CLEAN);

        $_POST = [
            'email'      => 'ghost@rl.test',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Compteur IP incrémenté
        $this->assertGreaterThan($ipBefore, $this->getRlCount('login:' . self::IP_CLEAN));

        // Aucune clé account_lockout ne doit avoir été créée
        $rlKeys = array_keys($_SESSION['_rl'] ?? []);
        $accountLockoutKeys = array_filter($rlKeys, static fn(string $k) => str_contains($k, 'account_lockout'));
        $this->assertEmpty($accountLockoutKeys, 'Aucun compteur account_lockout ne doit être créé pour un email inconnu');
    }

    // ----------------------------------------------------------------
    // Reset des compteurs après connexion réussie
    // ----------------------------------------------------------------

    /**
     * Une connexion réussie réinitialise les compteurs IP et compte.
     */
    public function testSuccessfulLoginResetsRateLimitCounters(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $id = $this->insertVerifiedAccount('resetok@rl.test', 'Password123!');

        // Pré-charger 3 tentatives sur IP et 3 sur le compte
        $this->preloadRlCount('login:' . self::IP_CLEAN, 3);
        $this->preloadRlCount('account_lockout:' . $id, 3);

        $_POST = [
            'email'      => 'resetok@rl.test',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Après connexion réussie, les deux compteurs doivent être effacés
        $this->assertSame(0, $this->getRlCount('login:' . self::IP_CLEAN), 'Le compteur IP doit être remis à zéro après succès');
        $this->assertSame(0, $this->getRlCount('account_lockout:' . $id), 'Le compteur compte doit être remis à zéro après succès');
        $this->assertSame(0, $this->getRlUntil('login:' . self::IP_CLEAN), 'Le lockout IP doit être effacé après succès');
        $this->assertSame(0, $this->getRlUntil('account_lockout:' . $id), 'Le lockout compte doit être effacé après succès');
    }

    // ----------------------------------------------------------------
    // E1 — remember_me : durée cookie/JWT
    // ----------------------------------------------------------------

    /**
     * E1 : remember_me=1 → le cookie auth_token doit avoir une durée de 30 jours.
     */
    public function testRememberMeOneSetsCookieWithThirtyDays(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $this->insertVerifiedAccount('rememberme@rl.test', 'Password123!');

        $_POST = [
            'email'       => 'rememberme@rl.test',
            'password'    => 'Password123!',
            'csrf_token'  => self::CSRF,
            'remember_me' => '1',
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Vérifier via connections.expired_at (setcookie() ne peuple pas $_COOKIE en CLI)
        $connection = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE user_id = (SELECT id FROM accounts WHERE email = ?) ORDER BY created_at DESC LIMIT 1",
            ['rememberme@rl.test']
        );
        $this->assertNotFalse($connection);
        $this->assertGreaterThan(time() + (29 * 24 * 3600), strtotime((string) $connection['expired_at']));
    }

    /**
     * E1 : remember_me=0 → connexion courte durée (cookie 24 h, JWT_EXPIRY par défaut).
     */
    public function testRememberMeZeroSetsCookieWithTwentyFourHours(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::IP_CLEAN;
        $this->insertVerifiedAccount('norem@rl.test', 'Password123!');

        $_POST = [
            'email'       => 'norem@rl.test',
            'password'    => 'Password123!',
            'csrf_token'  => self::CSRF,
            'remember_me' => '0',
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // expired_at doit être < now + 29 jours (pas de remember_me long)
        $connection = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE user_id = (SELECT id FROM accounts WHERE email = ?) ORDER BY created_at DESC LIMIT 1",
            ['norem@rl.test']
        );
        $this->assertNotFalse($connection);
        $this->assertLessThan(time() + (29 * 24 * 3600), strtotime((string) $connection['expired_at']));
    }
}
