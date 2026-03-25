<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\PricingAdminController;
use Core\Exception\HttpException;

class PricingAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): PricingAdminController
    {
        return new PricingAdminController($this->makeRequest($method, '/admin/tarifs'));
    }

    private function insertPricingRule(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO pricing_rules (format, min_quantity, max_quantity, delivery_price, withdrawal_price, label, active)
             VALUES ('bottle', 1, 5, 5.00, 0.00, '{\"fr\":\"Petite commande\",\"en\":\"Small order\"}', 1)"
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersPricingView(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-pricing-form', $output);
        $this->assertStringContainsString('Tarifs', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnInvalidCsrf(): void
    {
        $_SESSION['csrf'] = 'wrong-token';
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, ids vide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnSuccess(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['id'] = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }

    // ----------------------------------------------------------------
    // update — id invalide ignoré
    // ----------------------------------------------------------------

    public function testUpdateIgnoresInvalidId(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['id'] = [0, -1];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }

    // ----------------------------------------------------------------
    // update — id valide → mise à jour réelle + redirect 302
    // ----------------------------------------------------------------

    public function testUpdateWithRealIdUpdatesAndRedirects(): void
    {
        $id = $this->insertPricingRule();

        $_POST['csrf_token']              = self::CSRF_TOKEN;
        $_POST['id']                      = [$id];
        $_POST["delivery_{$id}"]          = '6.00';
        $_POST["withdrawal_{$id}"]        = '0.00';
        $_POST["label_fr_{$id}"]          = 'Petite commande';
        $_POST["label_en_{$id}"]          = 'Small order';
        $_POST["active_{$id}"]            = '1';
        $_POST["min_qty_{$id}"]           = '1';
        $_POST["max_qty_{$id}"]           = '5';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }
}
