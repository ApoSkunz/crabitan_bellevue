<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour les actions GET (rendu de formulaires) d'AuthController.
 * Chaque méthode rend une vue PHP — on bufferise la sortie pour vérifier le rendu.
 */
class AuthControllerFormTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf'];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
    }

    private function makeController(string $uri = '/fr/connexion'): AuthController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new AuthController(new Request());
    }

    // ----------------------------------------------------------------
    // registerForm
    // ----------------------------------------------------------------

    public function testRegisterFormRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/inscription')->registerForm(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('inscription', $output);
    }

    // ----------------------------------------------------------------
    // forgotForm
    // ----------------------------------------------------------------

    public function testForgotFormRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/mot-de-passe-oublie')->forgotForm(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // resetForm — token valide
    // ----------------------------------------------------------------

    public function testResetFormWithValidTokenRendersForm(): void
    {
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('resetform@example.com', 'hash', 'customer', 'fr', NOW())",
            []
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'R', 'User', 'M')",
            [$userId]
        );
        $token = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        ob_start();
        $this->makeController('/fr/reinitialisation/' . $token)
            ->resetForm(['lang' => 'fr', 'token' => $token]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // resetForm — token invalide (affiche message d'erreur, pas de form)
    // ----------------------------------------------------------------

    public function testResetFormWithInvalidTokenRendersError(): void
    {
        ob_start();
        $this->makeController('/fr/reinitialisation/badtoken')
            ->resetForm(['lang' => 'fr', 'token' => 'badtoken']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringNotContainsString('<form', $output);
    }
}
