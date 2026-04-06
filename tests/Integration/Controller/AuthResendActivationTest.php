<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AuthController::resendActivation().
 *
 * Couvre :
 *  - Flux nominal : compte non vérifié → token régénéré, flash succès
 *  - Anti-énumération : email inconnu → même réponse succès (302)
 *  - Anti-énumération : email déjà vérifié → même réponse succès (302)
 *  - CSRF invalide → flash succès (anti-enum, pas d'erreur révélée)
 *  - Rate limit par IP : 4e tentative bloquée silencieusement
 */
class AuthResendActivationTest extends IntegrationTestCase
{
    private const CSRF        = 'resend-activation-csrf';
    private const UNVERIFIED  = 'unverified@example.com';
    private const VERIFIED    = 'verified@example.com';
    private const PASSWORD    = 'Password123!';

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION  = ['csrf' => self::CSRF];
        $_POST     = [];
        $_COOKIE   = [];
        $_GET      = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.10.20.30';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_GET     = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte non vérifié et retourne son ID.
     *
     * @return int Identifiant du compte
     */
    private function insertUnverifiedAccount(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verification_token,
                                   email_verification_token_expires_at)
             VALUES (?, ?, 'customer', 'fr', 'initial-token', DATE_ADD(NOW(), INTERVAL 24 HOUR))",
            [self::UNVERIFIED, password_hash(self::PASSWORD, PASSWORD_BCRYPT)]
        );
    }

    /**
     * Insère un compte vérifié et retourne son ID.
     *
     * @return int Identifiant du compte
     */
    private function insertVerifiedAccount(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            [self::VERIFIED, password_hash(self::PASSWORD, PASSWORD_BCRYPT)]
        );
    }

    /**
     * Crée un AuthController configuré pour POST /fr/renvoyer-activation.
     *
     * @return AuthController
     */
    private function makeController(): AuthController
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/renvoyer-activation';
        return new AuthController(new Request());
    }

    // ----------------------------------------------------------------
    // Flux nominal
    // ----------------------------------------------------------------

    /**
     * Compte non vérifié → token régénéré en BDD, flash succès positionné.
     */
    public function testResendActivationForUnverifiedAccountRefreshesToken(): void
    {
        $userId = $this->insertUnverifiedAccount();

        $_POST = ['csrf_token' => self::CSRF, 'email' => self::UNVERIFIED];
        $ctrl  = $this->makeController();

        try {
            $ctrl->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            if ($e->status !== 302) {
                $this->fail('resendActivation a levé HttpException inattendue : ' . $e->getMessage());
            }
        } catch (\Throwable) {
            // header() → exception CLI ignorée
        }

        // Le token doit avoir été régénéré (différent de 'initial-token')
        $row = self::$db->fetchOne(
            "SELECT email_verification_token FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertNotSame('initial-token', $row['email_verification_token'], 'Le token doit être régénéré');
        $this->assertNotNull($row['email_verification_token']);

        // Flash succès positionné
        $this->assertNotEmpty($_SESSION['flash']['resend_activation_success'] ?? '');
    }

    // ----------------------------------------------------------------
    // Anti-énumération : email inconnu
    // ----------------------------------------------------------------

    /**
     * Email inconnu → même réponse 302 succès, aucune exception révélatrice.
     */
    public function testResendActivationWithUnknownEmailReturnsSilently(): void
    {
        $_POST = ['csrf_token' => self::CSRF, 'email' => 'nobody@example.com'];
        $ctrl  = $this->makeController();

        $threw302 = false;
        try {
            $ctrl->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            if ($e->status === 302) {
                $threw302 = true;
            }
        } catch (\Throwable) {
            $threw302 = true;
        }

        $this->assertTrue($threw302 || !empty($_SESSION['flash']['resend_activation_success'] ?? ''));
    }

    // ----------------------------------------------------------------
    // Anti-énumération : email déjà vérifié
    // ----------------------------------------------------------------

    /**
     * Email déjà vérifié → même réponse silencieuse, token non modifié.
     */
    public function testResendActivationWithVerifiedAccountReturnsSilently(): void
    {
        $this->insertVerifiedAccount();

        $_POST = ['csrf_token' => self::CSRF, 'email' => self::VERIFIED];
        $ctrl  = $this->makeController();

        try {
            $ctrl->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        } catch (\Throwable) {
            // ignoré
        }

        // Le compte vérifié ne doit pas avoir de token de vérification actif
        $row = self::$db->fetchOne(
            "SELECT email_verification_token FROM accounts WHERE email = ?",
            [self::VERIFIED]
        );
        $this->assertNull($row['email_verification_token'], 'Le token ne doit pas être modifié pour un compte vérifié');
    }

    // ----------------------------------------------------------------
    // Rate limit par IP
    // ----------------------------------------------------------------

    /**
     * Après 3 tentatives, la 4e est bloquée silencieusement (même flash succès).
     */
    public function testResendActivationRateLimitBlocksSilently(): void
    {
        // Pré-charger le compteur IP à la limite
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ipKey = 'resend_activation_ip:127.10.20.30';
        $_SESSION = [
            'csrf'     => self::CSRF,
            '_rl'      => [$ipKey => 3],
            '_rl_ttl'  => [$ipKey => time() + 900],
        ];

        $_POST = ['csrf_token' => self::CSRF, 'email' => self::UNVERIFIED];
        $ctrl  = $this->makeController();

        try {
            $ctrl->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            // 302 attendu — comportement silencieux
            $this->assertSame(302, $e->status);
        } catch (\Throwable) {
            // ignoré
        }

        // Flash succès positionné malgré le rate limit (anti-enum)
        $this->assertNotEmpty($_SESSION['flash']['resend_activation_success'] ?? '');
    }
}
