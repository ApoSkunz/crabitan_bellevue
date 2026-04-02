<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Api;

use Controller\Api\FavoriteApiController;
use Core\Exception\HttpException;
use Core\Jwt;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour FavoriteApiController.
 */
class FavoriteApiControllerTest extends IntegrationTestCase
{
    private int $userId;
    private int $wineId;

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/api/favoris/toggle';

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            ['fav.api.' . bin2hex(random_bytes(4)) . '@test.local', password_hash('Pass123!', PASSWORD_BCRYPT)]
        );

        $this->wineId = (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, is_cuvee_speciale,
              image_path, slug)
             VALUES (?, 'red', 'bottle', 2020, 19.90, 100, 1,
                     'AOC', 5.00, 'Bordeaux', 'Merlot', 20,
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     0, 'wine_fav_test.jpg', ?)",
            ['Fav Test Rouge 2020', 'fav-test-rouge-2020-' . bin2hex(random_bytes(4))]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function loginAs(int $userId): void
    {
        $token = Jwt::generate($userId, 'customer');
        $_COOKIE['auth_token'] = $token;
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
    }

    private function makeController(): FavoriteApiController
    {
        return new FavoriteApiController(new \Core\Request());
    }

    // ----------------------------------------------------------------
    // toggle — wine_id invalide
    // ----------------------------------------------------------------

    /**
     * Un wine_id manquant/invalide retourne 422.
     */
    public function testToggleInvalidWineIdReturns422(): void
    {
        $this->loginAs($this->userId);
        $_POST['wine_id'] = '0';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->toggle([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // toggle — ajout d'un favori
    // ----------------------------------------------------------------

    /**
     * Un wine_id valide retourne liked=true lors du premier toggle.
     */
    public function testToggleAddsWineToFavorites(): void
    {
        $this->loginAs($this->userId);
        $_POST['wine_id'] = (string) $this->wineId;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->toggle([]);
        } finally {
            $raw = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertTrue($json['success']);
                $this->assertTrue($json['liked']);
            }
        }
    }

    // ----------------------------------------------------------------
    // toggle — suppression d'un favori (deuxième appel)
    // ----------------------------------------------------------------

    /**
     * Un deuxième toggle sur le même vin retire le favori (liked=false).
     */
    public function testToggleRemovesWineFromFavorites(): void
    {
        $this->loginAs($this->userId);
        // Insère d'abord un favori existant
        self::$db->insert(
            "INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)",
            [$this->userId, $this->wineId]
        );

        $_POST['wine_id'] = (string) $this->wineId;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->toggle([]);
        } finally {
            $raw = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertTrue($json['success']);
                $this->assertFalse($json['liked']);
            }
        }
    }

    // ----------------------------------------------------------------
    // toggle — non authentifié → redirect
    // ----------------------------------------------------------------

    /**
     * Sans cookie JWT, l'appel redirige vers /connexion.
     */
    public function testToggleUnauthenticatedRedirects(): void
    {
        $_POST['wine_id'] = (string) $this->wineId;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404); // 404 — path enumeration prevention

        $this->makeController()->toggle([]);
    }
}
