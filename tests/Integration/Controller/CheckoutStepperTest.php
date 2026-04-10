<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\CartController;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration du stepper de progression du tunnel de commande.
 *
 * Vérifie que les vues panier, checkout et confirmation incluent
 * bien le partiel checkout-stepper avec la classe CSS attendue.
 */
class CheckoutStepperTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REQUEST_METHOD']  = 'GET';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Partiel — rendu direct step 1 (Panier)
    // ----------------------------------------------------------------

    public function testStepperRendersStep1Active(): void
    {
        $checkoutStep = 1;
        $lang         = 'fr';

        ob_start();
        require SRC_PATH . '/View/partials/checkout-stepper.php';
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('checkout-stepper', $output);
        $this->assertStringContainsString('checkout-stepper__step--active', $output);
        $this->assertStringContainsString('aria-current="step"', $output);
    }

    // ----------------------------------------------------------------
    // Partiel — step 2 (Livraison active, Panier done)
    // ----------------------------------------------------------------

    public function testStepperRendersStep2ActiveAndStep1Done(): void
    {
        $checkoutStep = 2;
        $lang         = 'fr';

        ob_start();
        require SRC_PATH . '/View/partials/checkout-stepper.php';
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('checkout-stepper__step--done', $output);
        $this->assertStringContainsString('checkout-stepper__step--active', $output);
        $this->assertStringContainsString('checkout-stepper__step--pending', $output);
        $this->assertStringContainsString('✓', $output);
    }

    // ----------------------------------------------------------------
    // Partiel — step 3 (Confirmation active)
    // ----------------------------------------------------------------

    public function testStepperRendersStep3Active(): void
    {
        $checkoutStep = 3;
        $lang         = 'en';

        ob_start();
        require SRC_PATH . '/View/partials/checkout-stepper.php';
        $output = (string) ob_get_clean();

        // Deux étapes done, aucune pending
        $this->assertSame(2, substr_count($output, 'checkout-stepper__step--done'));
        $this->assertStringContainsString('checkout-stepper__step--active', $output);
        $this->assertStringNotContainsString('checkout-stepper__step--pending', $output);
    }

    // ----------------------------------------------------------------
    // Vue panier (CartController::index, accessible invité)
    // ----------------------------------------------------------------

    public function testCartViewContainsCheckoutStepper(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/panier';

        ob_start();
        (new CartController(new Request()))->index(['lang' => 'fr']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('checkout-stepper', $output);
    }
}
