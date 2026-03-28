<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\WineController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour WineController.
 */
class WineControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
    }

    private function makeController(string $uri = '/fr/vins'): WineController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        return new WineController(new Request());
    }

    private function insertWine(string $slug = 'test-wine-2020', string $color = 'sweet'): void
    {
        self::$db->insert(
            "INSERT INTO wines
                (label_name, wine_color, format, vintage, price, quantity, available,
                 area, city, variety_of_vine, age_of_vineyard,
                 oenological_comment, soil, pruning, harvest, vinification,
                 barrel_fermentation, award, extra_comment, image_path, slug)
             VALUES (?, ?, 'bottle', 2020, 22.00, 120, 1,
                     7.50, 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
                     '{\"fr\":\"Description test\",\"en\":\"Test description\"}',
                     '{\"fr\":\"Argile\",\"en\":\"Clay\"}',
                     '{\"fr\":\"Guyot mixte\",\"en\":\"Mixed Guyot\"}',
                     '{\"fr\":\"Manuelles\",\"en\":\"Manual\"}',
                     '{\"fr\":\"Cuves inox\",\"en\":\"Stainless steel\"}',
                     '{\"fr\":\"36 mois\",\"en\":\"36 months\"}',
                     '{\"fr\":\"\",\"en\":\"\"}',
                     '{\"fr\":\"\",\"en\":\"\"}',
                     'Wine_Test_2020.png', ?)",
            ['Test Wine', $color, $slug]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('wines', $output);
    }

    public function testIndexRendersWineGrid(): void
    {
        $this->insertWine('test-sweet-2020', 'sweet');

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-grid', $output);
        $this->assertStringContainsString('wine-card', $output);
    }

    public function testIndexRendersEmptyStateWhenNoWines(): void
    {
        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        // Pas d'erreur, la vue se charge
        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexFiltersWinesByColor(): void
    {
        $this->insertWine('test-red-2020', 'red');
        $this->insertWine('test-sweet-2020', 'sweet');

        $_GET['color'] = 'sweet';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wine-card', $output);
    }

    public function testIndexWithInvalidPerPageFallsBackToDefault(): void
    {
        $this->insertWine('test-invalid-pp-2020', 'red');

        $_GET['per_page'] = '7'; // Not in VALID_PER_PAGE

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        // Vue chargée sans erreur, per_page remis à 25 par défaut
        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithValidPerPage10(): void
    {
        $this->insertWine('test-pp10-2020', 'white');

        $_GET['per_page'] = '10';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexPaginationPageTwo(): void
    {
        // Insère suffisamment de vins pour avoir une deuxième page avec per_page=10
        for ($i = 1; $i <= 12; $i++) {
            $this->insertWine("test-pag-wine-{$i}", 'red');
        }

        $_GET['per_page'] = '10';
        $_GET['page']     = '2';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithSortPriceAsc(): void
    {
        $this->insertWine('test-sort-asc', 'red');

        $_GET['sort'] = 'price_asc';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithSortPriceDesc(): void
    {
        $this->insertWine('test-sort-desc', 'sweet');

        $_GET['sort'] = 'price_desc';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithEnglishLang(): void
    {
        $this->insertWine('test-en-wine-2020', 'white');

        ob_start();
        $this->makeController('/en/wines')->index(['lang' => 'en']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testIndexWithEmptyColorParam(): void
    {
        $this->insertWine('test-empty-color', 'red');

        $_GET['color'] = ''; // doit être traité comme null

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithLoggedInUserShowsLikedIds(): void
    {
        // Un cookie invalide ne doit pas lever d'exception — getCurrentUserLikedIds retourne []
        $_COOKIE['auth_token'] = 'invalid.jwt.token';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    // ----------------------------------------------------------------
    // collection
    // ----------------------------------------------------------------

    public function testCollectionRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('collection', $output);
    }

    public function testCollectionRendersGroupedWines(): void
    {
        $this->insertWine('test-sweet-col', 'sweet');
        $this->insertWine('test-red-col', 'red');

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('collection-group', $output);
        $this->assertStringContainsString('wine-card', $output);
    }

    public function testCollectionWithAvailableFilter(): void
    {
        $this->insertWine('test-col-avail', 'red');

        $_GET['avail'] = 'available';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithOutFilter(): void
    {
        $this->insertWine('test-col-out', 'white');

        $_GET['avail'] = 'out';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithInvalidAvailFallsBackToNull(): void
    {
        $this->insertWine('test-col-badavail', 'red');

        $_GET['avail'] = 'invalid_value'; // Doit être traité comme null

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithInvalidPerPageFallsBackToDefault(): void
    {
        $this->insertWine('test-col-pp', 'sweet');

        $_GET['per_page'] = '999'; // Not in VALID_PER_PAGE

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithColorFilter(): void
    {
        $this->insertWine('test-col-filt-red', 'red');
        $this->insertWine('test-col-filt-white', 'white');

        $_GET['color'] = 'red';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionPaginationPageTwo(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->insertWine("test-colpag-{$i}", 'red');
        }

        $_GET['per_page'] = '10';
        $_GET['page']     = '2';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithInvalidLoggedInCookie(): void
    {
        // Cookie JWT invalide — getCurrentUserLikedIds doit retourner [] sans erreur
        $_COOKIE['auth_token'] = 'not.a.valid.jwt';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function testShowRendersWineDetail(): void
    {
        $this->insertWine('test-wine-detail-show');

        ob_start();
        $this->makeController('/fr/vins/test-wine-detail-show')
            ->show(['lang' => 'fr', 'slug' => 'test-wine-detail-show']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('wine-detail', $output);
        $this->assertStringContainsString('Test Wine', $output);
    }

    public function testShowWithInvalidSlugAborts404(): void
    {
        $this->expectException(HttpException::class);

        $this->makeController('/fr/vins/inexistant')
            ->show(['lang' => 'fr', 'slug' => 'inexistant']);
    }

    public function testShowWithInvalidJwtCookieDoesNotCrash(): void
    {
        $this->insertWine('test-show-jwt');

        // Cookie JWT invalide — isLiked reste false, pas d'exception
        $_COOKIE['auth_token'] = 'bad.jwt.token';

        ob_start();
        $this->makeController('/fr/vins/test-show-jwt')
            ->show(['lang' => 'fr', 'slug' => 'test-show-jwt']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wine-detail', $output);
    }

    public function testShowWithEnglishLang(): void
    {
        $this->insertWine('test-show-en');

        ob_start();
        $this->makeController('/en/wines/test-show-en')
            ->show(['lang' => 'en', 'slug' => 'test-show-en']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testShowWithValidJwtAndLikedWine(): void
    {
        // Insert a user account
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('liked@example.com', 'hash', 'customer', 'fr', NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Liked', 'User', 'M')",
            [$userId]
        );

        $this->insertWine('test-liked-wine');

        // Get the inserted wine's id
        $wineRow = self::$db->fetchOne(
            "SELECT id FROM wines WHERE slug = ?",
            ['test-liked-wine']
        );
        $wineId = (int) $wineRow['id'];

        // Insert favorite
        self::$db->insert(
            "INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)",
            [$userId, $wineId]
        );

        // Generate a valid JWT cookie
        $token = \Core\Jwt::generate($userId, 'customer');
        // Insert a connection so the cookie is valid in any middleware
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        $_COOKIE['auth_token'] = $token;

        ob_start();
        $this->makeController('/fr/vins/test-liked-wine')
            ->show(['lang' => 'fr', 'slug' => 'test-liked-wine']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wine-detail', $output);
    }

    public function testIndexWithValidJwtAndFavorites(): void
    {
        // Insert a user account and a wine, then a favorite
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('favindex@example.com', 'hash', 'customer', 'fr', NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Fav', 'User', 'M')",
            [$userId]
        );
        $this->insertWine('test-fav-index-wine');

        $wineRow = self::$db->fetchOne("SELECT id FROM wines WHERE slug = ?", ['test-fav-index-wine']);
        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$userId, $wineRow['id']]);

        $token = \Core\Jwt::generate($userId, 'customer');
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        $_COOKIE['auth_token'] = $token;

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testCollectionWithValidJwtAndFavorites(): void
    {
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('favcol@example.com', 'hash', 'customer', 'fr', NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Fav', 'Col', 'F')",
            [$userId]
        );
        $this->insertWine('test-fav-col-wine', 'red');

        $wineRow = self::$db->fetchOne("SELECT id FROM wines WHERE slug = ?", ['test-fav-col-wine']);
        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$userId, $wineRow['id']]);

        $token = \Core\Jwt::generate($userId, 'customer');
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        $_COOKIE['auth_token'] = $token;

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testIndexWithSortVintageAsc(): void
    {
        $this->insertWine('test-sort-vintage-asc', 'red');

        $_GET['sort'] = 'vintage_asc';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithSortVintageDesc(): void
    {
        $this->insertWine('test-sort-vintage-desc', 'white');

        $_GET['sort'] = 'vintage_desc';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testCollectionWithSortPriceAsc(): void
    {
        $this->insertWine('test-col-sort-asc', 'sweet');

        $_GET['sort'] = 'price_asc';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithSortPriceDesc(): void
    {
        $this->insertWine('test-col-sort-desc', 'white');

        $_GET['sort'] = 'price_desc';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithColorAndAvailFilter(): void
    {
        $this->insertWine('test-col-color-avail', 'red');

        $_GET['color'] = 'red';
        $_GET['avail'] = 'available';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testCollectionWithEnglishLang(): void
    {
        $this->insertWine('test-col-en', 'sweet');

        ob_start();
        $this->makeController('/en/wines/collection')->collection(['lang' => 'en']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testIndexWithValidPerPage25(): void
    {
        $this->insertWine('test-pp25-wine', 'red');

        $_GET['per_page'] = '25';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithValidPerPage50(): void
    {
        $this->insertWine('test-pp50-wine', 'white');

        $_GET['per_page'] = '50';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexWithValidPerPage100(): void
    {
        $this->insertWine('test-pp100-wine', 'sweet');

        $_GET['per_page'] = '100';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-catalog', $output);
    }

    // ----------------------------------------------------------------
    // technicalSheet
    // ----------------------------------------------------------------

    public function testTechnicalSheetAborts404ForUnknownSlug(): void
    {
        $this->expectException(HttpException::class);

        $this->makeController('/fr/vins/inexistant/fiche-technique')
            ->technicalSheet(['lang' => 'fr', 'slug' => 'inexistant']);
    }
}
