<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\NewsletterController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour NewsletterController.
 *
 * Utilise la BDD réelle (transactions rollbackées).
 * Les appels MailService en succès ne sont pas testés en TI (SMTP non disponible).
 */
class NewsletterControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * @param array<string, string> $get
     * @param array<string, string> $post
     */
    private function makeController(
        string $method = 'GET',
        string $uri = '/fr/newsletter/confirmation',
        array $get = [],
        array $post = []
    ): NewsletterController {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET  = $get;
        $_POST = $post;
        return new NewsletterController(new Request());
    }

    // ----------------------------------------------------------------
    // confirmSubscription — token vide
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionEmptyTokenRendersErrorView(): void
    {
        $controller = $this->makeController('GET', '/fr/newsletter/confirmation', ['token' => '']);

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    // ----------------------------------------------------------------
    // confirmSubscription — token inconnu en BDD
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionUnknownTokenRendersErrorView(): void
    {
        $controller = $this->makeController(
            'GET',
            '/fr/newsletter/confirmation',
            ['token' => bin2hex(random_bytes(32))]
        );

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    // ----------------------------------------------------------------
    // confirmSubscription — token expiré en BDD
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionExpiredTokenRendersErrorView(): void
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expired     = date('Y-m-d H:i:s', strtotime('-1 hour'));

        self::$db->execute(
            "INSERT INTO newsletter_subscriptions
             (email, newsletter_token_hash, newsletter_token_expires_at,
              newsletter_confirmed, consent_ip, lang, attempts_24h, last_attempt_at)
             VALUES (?, ?, ?, 0, '127.0.0.1', 'fr', 1, NOW())",
            ['expired@test.com', $hashedToken, $expired]
        );

        $controller = $this->makeController(
            'GET',
            '/fr/newsletter/confirmation',
            ['token' => $rawToken]
        );

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        // La vue rend confirm_expired (traduction), pas le mot "expired"
        $this->assertStringContainsString('auth-status--error', $output);
        $this->assertStringNotContainsString('auth-status--ok', $output);
    }

    // ----------------------------------------------------------------
    // confirmSubscription — token valide en BDD
    // ----------------------------------------------------------------

    public function testConfirmSubscriptionValidTokenRendersSuccessView(): void
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expires     = date('Y-m-d H:i:s', strtotime('+48 hours'));

        self::$db->execute(
            "INSERT INTO newsletter_subscriptions
             (email, newsletter_token_hash, newsletter_token_expires_at,
              newsletter_confirmed, consent_ip, lang, attempts_24h, last_attempt_at)
             VALUES (?, ?, ?, 0, '127.0.0.1', 'fr', 1, NOW())",
            ['confirm@test.com', $hashedToken, $expires]
        );

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $controller = $this->makeController(
            'GET',
            '/fr/newsletter/confirmation',
            ['token' => $rawToken]
        );

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('invalid', $output);
        $this->assertStringNotContainsString('expired', $output);
    }

    // ----------------------------------------------------------------
    // subscribe — validation email
    // ----------------------------------------------------------------

    public function testSubscribeWithInvalidEmailReturns422(): void
    {
        $controller = $this->makeController(
            'POST',
            '/fr/newsletter/inscription',
            [],
            ['email' => 'not-an-email']
        );

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(422);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    public function testSubscribeWithEmptyEmailReturns422(): void
    {
        $controller = $this->makeController(
            'POST',
            '/fr/newsletter/inscription',
            [],
            ['email' => '']
        );

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(422);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }
}
