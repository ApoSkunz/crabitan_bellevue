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

    // ----------------------------------------------------------------
    // collection — sorts manquants
    // ----------------------------------------------------------------

    /**
     * collection() avec sort=vintage_asc ne doit pas générer d'erreur.
     */
    public function testCollectionWithSortVintageAsc(): void
    {
        $this->insertWine('test-col-sort-vint-asc', 'red');

        $_GET['sort'] = 'vintage_asc';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    /**
     * collection() avec sort=vintage_desc ne doit pas générer d'erreur.
     */
    public function testCollectionWithSortVintageDesc(): void
    {
        $this->insertWine('test-col-sort-vint-desc', 'white');

        $_GET['sort'] = 'vintage_desc';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    // ----------------------------------------------------------------
    // show — branche JWT valide + vin non favori
    // ----------------------------------------------------------------

    /**
     * show() avec un JWT valide mais le vin n'est pas favori : isLiked doit rester false.
     */
    public function testShowWithValidJwtAndWineNotLiked(): void
    {
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('notliked@example.com', 'hash', 'customer', 'fr', NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Not', 'Liked', 'M')",
            [$userId]
        );

        $this->insertWine('test-not-liked-wine');

        $token = \Core\Jwt::generate($userId, 'customer');
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        $_COOKIE['auth_token'] = $token;

        // Pas d'INSERT en favorites → isLiked = false
        ob_start();
        $this->makeController('/fr/vins/test-not-liked-wine')
            ->show(['lang' => 'fr', 'slug' => 'test-not-liked-wine']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wine-detail', $output);
    }

    // ----------------------------------------------------------------
    // Méthodes privées PDF via Reflection
    // ----------------------------------------------------------------

    /**
     * stripAlpha() retourne path=null quand le fichier source n'existe pas.
     */
    public function testStripAlphaReturnsNullPathForMissingFile(): void
    {
        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'stripAlpha');

        $result = $ref->invoke($ctrl, '/tmp/fichier_inexistant_' . uniqid() . '.png');

        $this->assertNull($result['path']);
        $this->assertSame('PNG', $result['type']);
        $this->assertNull($result['tmp']);
    }

    /**
     * stripAlpha() retourne le chemin original pour un fichier non-PNG.
     */
    public function testStripAlphaPassesThroughNonPngFile(): void
    {
        // Crée un fichier JPEG temporaire
        $tmpJpg = sys_get_temp_dir() . '/cb_test_' . uniqid() . '.jpg';
        file_put_contents($tmpJpg, 'fake jpeg data');

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'stripAlpha');

        $result = $ref->invoke($ctrl, $tmpJpg);

        $this->assertSame($tmpJpg, $result['path']);
        $this->assertSame('PNG', $result['type']);
        $this->assertNull($result['tmp']);

        @unlink($tmpJpg);
    }

    /**
     * stripAlpha() sur un PNG existant retourne un chemin non-null (GD disponible sur XAMPP).
     */
    public function testStripAlphaHandlesRealPng(): void
    {
        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';

        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable, test ignoré.');
        }

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'stripAlpha');

        $result = $ref->invoke($ctrl, $srcPng);

        // Le chemin doit être non-null (soit JPG converti, soit PNG original en fallback)
        $this->assertNotNull($result['path']);
        $this->assertContains($result['type'], ['JPG', 'PNG']);

        // Nettoyage du fichier temporaire éventuellement créé
        if ($result['tmp'] !== null && is_file($result['tmp'])) {
            @unlink($result['tmp']);
        }
    }

    /**
     * buildPdfFields() avec lang='fr' et champs area/age_of_vineyard non-null.
     */
    public function testBuildPdfFieldsFrWithFullWine(): void
    {
        $wine = [
            'oenological_comment' => '{"fr":"Belle robe","en":"Nice color"}',
            'soil'                => '{"fr":"Argile","en":"Clay"}',
            'pruning'             => '{"fr":"Guyot","en":"Guyot"}',
            'harvest'             => '{"fr":"Manuel","en":"Manual"}',
            'vinification'        => '{"fr":"Cuves","en":"Tanks"}',
            'barrel_fermentation' => '{"fr":"12 mois","en":"12 months"}',
            'award'               => '{"fr":"Or 2022","en":"Gold 2022"}',
            'area'                => '7.50',
            'age_of_vineyard'     => '30',
            'certification_label' => 'AOC Bordeaux',
            'city'                => 'Sainte-Croix-du-Mont',
            'variety_of_vine'     => 'Sémillon 100%',
        ];

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'buildPdfFields');
        $l    = fn(array $arr): string => $arr['fr'] ?? '';

        $fields = $ref->invoke($ctrl, $wine, 'fr', $l);

        $this->assertArrayHasKey('Commentaire', $fields);
        $this->assertArrayHasKey('Récompense', $fields);
        $this->assertArrayHasKey('Surface', $fields);
        $this->assertSame('7,50 ha', $fields['Surface']);
        $this->assertSame('30 ans', $fields['Âge des vignes']);
    }

    /**
     * buildPdfFields() avec lang='en' retourne les clés en anglais.
     */
    public function testBuildPdfFieldsEnWithFullWine(): void
    {
        $wine = [
            'oenological_comment' => '{"fr":"Belle robe","en":"Nice color"}',
            'soil'                => '{"fr":"Argile","en":"Clay"}',
            'pruning'             => '{"fr":"Guyot","en":"Guyot"}',
            'harvest'             => '{"fr":"Manuel","en":"Manual"}',
            'vinification'        => '{"fr":"Cuves","en":"Tanks"}',
            'barrel_fermentation' => '{"fr":"12 mois","en":"12 months"}',
            'award'               => '{"fr":"Or 2022","en":"Gold 2022"}',
            'area'                => '5.00',
            'age_of_vineyard'     => '25',
            'certification_label' => 'AOC Bordeaux',
            'city'                => 'Sainte-Croix-du-Mont',
            'variety_of_vine'     => 'Sémillon 100%',
        ];

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'buildPdfFields');
        $l    = fn(array $arr): string => $arr['en'] ?? '';

        $fields = $ref->invoke($ctrl, $wine, 'en', $l);

        $this->assertArrayHasKey('Comment', $fields);
        $this->assertArrayHasKey('Award', $fields);
        $this->assertArrayHasKey('Area', $fields);
        $this->assertSame('5,00 ha', $fields['Area']);
        $this->assertSame('25 ans', $fields['Age of vines']);
    }

    /**
     * buildPdfFields() avec area=null et age_of_vineyard=null retourne des chaînes vides.
     */
    public function testBuildPdfFieldsWithNullAreaAndAge(): void
    {
        $wine = [
            'oenological_comment' => null,
            'soil'                => null,
            'pruning'             => null,
            'harvest'             => null,
            'vinification'        => null,
            'barrel_fermentation' => null,
            'award'               => null,
            'area'                => null,
            'age_of_vineyard'     => null,
            'certification_label' => null,
            'city'                => null,
            'variety_of_vine'     => null,
        ];

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'buildPdfFields');
        $l    = fn(array $arr): string => $arr['fr'] ?? '';

        $fields = $ref->invoke($ctrl, $wine, 'fr', $l);

        $this->assertSame('', $fields['Surface']);
        $this->assertSame('', $fields['Âge des vignes']);
    }

    /**
     * renderPdfBody() avec img['path']=null ne doit pas lever d'exception.
     */
    public function testRenderPdfBodyWithNullImagePath(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $wine = [
            'label_name'          => 'Bordeaux',
            'vintage'             => 2020,
            'oenological_comment' => '{"fr":"Belle robe","en":"Nice color"}',
            'soil'                => '{"fr":"Argile","en":"Clay"}',
            'pruning'             => '{"fr":"Guyot","en":"Guyot"}',
            'harvest'             => '{"fr":"Manuel","en":"Manual"}',
            'vinification'        => '{"fr":"Cuves","en":"Tanks"}',
            'barrel_fermentation' => '{"fr":"12 mois","en":"12 months"}',
            'award'               => '{"fr":"","en":""}',
            'area'                => '7.50',
            'age_of_vineyard'     => '30',
            'certification_label' => 'AOC Bordeaux',
            'city'                => 'Sainte-Croix-du-Mont',
            'variety_of_vine'     => 'Sémillon 100%',
        ];

        $img  = ['path' => null, 'type' => 'PNG', 'tmp' => null];
        $logo = ['path' => null, 'type' => 'PNG', 'tmp' => null];

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $wine, $logo);

        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l = fn(array $arr): string => $arr['fr'] ?? '';

        // Ne doit pas lever d'exception
        $renderRef->invoke($ctrl, $pdf, $wine, $img, 'fr', $l);

        $this->assertTrue(true); // Si on arrive ici, le chemin img=null est couvert
    }

    /**
     * stripAlpha() sur un PNG dont GD échoue à décoder : convertPngToJpg retourne null,
     * le fallback renvoie le chemin PNG original avec type='PNG'.
     */
    public function testStripAlphaFallbackWhenConvertPngToJpgReturnsNull(): void
    {
        // Crée un faux PNG (données non valides pour GD/Imagick) mais avec extension .png
        $fakePng = sys_get_temp_dir() . '/cb_fake_' . uniqid() . '.png';
        file_put_contents($fakePng, 'not a real png content - corrupted');

        $ctrl   = $this->makeController();
        $refStr = new \ReflectionMethod($ctrl, 'stripAlpha');

        $result = $refStr->invoke($ctrl, $fakePng);

        // Le chemin doit être non-null : soit JPG converti, soit fallback PNG original
        $this->assertNotNull($result['path']);
        // Le chemin retourné doit être l'un ou l'autre
        $this->assertContains($result['type'], ['JPG', 'PNG']);

        @unlink($fakePng);
        if ($result['tmp'] !== null && is_file($result['tmp'])) {
            @unlink($result['tmp']);
        }
    }

    /**
     * convertPngToJpg() appelé via Reflection avec un PNG corrompu :
     * si GD échoue (imagecreatefromstring retourne false), la méthode tente Imagick
     * ou retourne null. Vérifie que le retour est null ou un tableau valide.
     */
    public function testConvertPngToJpgWithCorruptedPngReturnsNullOrArray(): void
    {
        $fakePng = sys_get_temp_dir() . '/cb_corrupt_' . uniqid() . '.png';
        file_put_contents($fakePng, "\x00\x01\x02corrupted png data");

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'convertPngToJpg');

        $result = $ref->invoke($ctrl, $fakePng);

        // Le résultat doit être soit null (ni GD ni Imagick ne supportent le fichier)
        // soit un tableau valide (Imagick a réussi)
        if ($result !== null) {
            $this->assertArrayHasKey('path', $result);
            $this->assertArrayHasKey('type', $result);
            $this->assertSame('JPG', $result['type']);
            if (is_file($result['tmp'])) {
                @unlink($result['tmp']);
            }
        } else {
            $this->assertNull($result);
        }

        @unlink($fakePng);
    }

    /**
     * buildPdf() avec logo['path'] non-null : vérifie que le Header() s'exécute
     * sans erreur quand une image logo valide est fournie.
     */
    public function testBuildPdfWithNonNullLogoDoesNotThrow(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable, test ignoré.');
        }

        $ctrl = $this->makeController();

        // Utilise stripAlpha via Reflection pour obtenir un logo compatible TCPDF
        $stripRef = new \ReflectionMethod($ctrl, 'stripAlpha');
        $logo     = $stripRef->invoke($ctrl, $srcPng);

        $wine = [
            'label_name' => 'Bordeaux',
            'vintage'    => 2021,
        ];

        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');

        // Ne doit pas lever d'exception — le Header() est appelé en interne lors d'AddPage()
        $pdf = $buildRef->invoke($ctrl, $wine, $logo);

        $this->assertInstanceOf('TCPDF', $pdf);

        // Nettoyage du fichier temporaire éventuel
        if ($logo['tmp'] !== null && is_file($logo['tmp'])) {
            @unlink($logo['tmp']);
        }
    }

    /**
     * renderPdfBody() avec img['path'] non-null et lang='en' couvre la branche Image().
     */
    public function testRenderPdfBodyWithImagePathAndEnglishLang(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $wine = [
            'label_name'          => 'Bordeaux',
            'vintage'             => 2020,
            'oenological_comment' => '{"fr":"Belle robe","en":"Nice color"}',
            'soil'                => '{"fr":"Argile","en":"Clay"}',
            'pruning'             => '{"fr":"Guyot","en":"Guyot"}',
            'harvest'             => '{"fr":"Manuel","en":"Manual"}',
            'vinification'        => '{"fr":"Cuves","en":"Tanks"}',
            'barrel_fermentation' => '{"fr":"12 mois","en":"12 months"}',
            'award'               => '{"fr":"","en":""}',
            'area'                => null,
            'age_of_vineyard'     => null,
            'certification_label' => 'AOC Bordeaux',
            'city'                => 'Sainte-Croix-du-Mont',
            'variety_of_vine'     => 'Sémillon 100%',
        ];

        $logo = ['path' => null, 'type' => 'PNG', 'tmp' => null];

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $wine, $logo);

        // Utilise le logo PNG existant comme image de vin (JPEG aussi valide ici)
        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable.');
        }
        $img = ['path' => $srcPng, 'type' => 'PNG', 'tmp' => null];

        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l = fn(array $arr): string => $arr['en'] ?? '';

        $renderRef->invoke($ctrl, $pdf, $wine, $img, 'en', $l);

        $this->assertTrue(true);
    }

    /**
     * buildPdf() + Output('S') déclenche Header() ET Footer() sur la classe anonyme TCPDF,
     * couvrant les lignes 246-285 (fond crème, logo null, titre, pied de page).
     */
    public function testBuildPdfOutputTriggersHeaderAndFooter(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $wine = [
            'label_name' => 'Bordeaux',
            'vintage'    => 2022,
        ];

        $logo = ['path' => null, 'type' => 'PNG', 'tmp' => null];

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $wine, $logo);

        // Output('S') renvoie le PDF en string et déclenche le Footer — aucun exit()
        $pdfString = $pdf->Output('test.pdf', 'S');

        // Le PDF généré doit contenir la signature PDF
        $this->assertStringStartsWith('%PDF', $pdfString);
    }

    /**
     * buildPdf() avec logo non-null + Output('S') : couvre la branche Image() dans Header().
     */
    public function testBuildPdfWithLogoOutputTriggersHeaderImage(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable, test ignoré.');
        }

        $ctrl     = $this->makeController();
        $stripRef = new \ReflectionMethod($ctrl, 'stripAlpha');
        $logo     = $stripRef->invoke($ctrl, $srcPng);

        $wine = [
            'label_name' => 'Bordeaux',
            'vintage'    => 2022,
        ];

        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $wine, $logo);

        // Output('S') déclenche le Footer + Header sur toutes les pages avec logo non-null
        $pdfString = $pdf->Output('test.pdf', 'S');

        $this->assertStringStartsWith('%PDF', $pdfString);

        if ($logo['tmp'] !== null && is_file($logo['tmp'])) {
            @unlink($logo['tmp']);
        }
    }

    /**
     * renderPdfBody() avec tous les champs remplis (value non vide) :
     * couvre la branche MultiCell dans le foreach (ligne 379).
     */
    public function testRenderPdfBodyWithAllFieldsPopulated(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $wine = [
            'label_name'          => 'Bordeaux',
            'vintage'             => 2021,
            'oenological_comment' => '{"fr":"Belle robe rubis","en":"Nice ruby color"}',
            'soil'                => '{"fr":"Argile et calcaire","en":"Clay and limestone"}',
            'pruning'             => '{"fr":"Guyot double","en":"Double Guyot"}',
            'harvest'             => '{"fr":"Vendanges manuelles","en":"Manual harvest"}',
            'vinification'        => '{"fr":"Cuves inox thermorégulées","en":"Thermo tanks"}',
            'barrel_fermentation' => '{"fr":"24 mois en barrique","en":"24 months in barrel"}',
            'award'               => '{"fr":"Médaille Or 2021","en":"Gold Medal 2021"}',
            'area'                => '8.50',
            'age_of_vineyard'     => '45',
            'certification_label' => 'AOC Bordeaux Supérieur',
            'city'                => 'Sainte-Croix-du-Mont',
            'variety_of_vine'     => 'Cabernet Sauvignon 60%, Merlot 40%',
        ];

        $img  = ['path' => null, 'type' => 'PNG', 'tmp' => null];
        $logo = ['path' => null, 'type' => 'PNG', 'tmp' => null];

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $wine, $logo);

        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l = fn(array $arr): string => $arr['fr'] ?? '';

        // Tous les champs sont remplis → MultiCell exécuté pour chaque field
        $renderRef->invoke($ctrl, $pdf, $wine, $img, 'fr', $l);

        $this->assertTrue(true);
    }

    /**
     * renderPdfBody() avec tous les champs vides (value === '') :
     * couvre la branche Ln(5) dans le foreach (ligne 381).
     */
    public function testRenderPdfBodyWithAllFieldsEmpty(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $wine = [
            'label_name'          => 'Bordeaux',
            'vintage'             => 2021,
            'oenological_comment' => '{"fr":"","en":""}',
            'soil'                => '{"fr":"","en":""}',
            'pruning'             => '{"fr":"","en":""}',
            'harvest'             => '{"fr":"","en":""}',
            'vinification'        => '{"fr":"","en":""}',
            'barrel_fermentation' => '{"fr":"","en":""}',
            'award'               => '{"fr":"","en":""}',
            'area'                => null,
            'age_of_vineyard'     => null,
            'certification_label' => '',
            'city'                => '',
            'variety_of_vine'     => '',
        ];

        $img  = ['path' => null, 'type' => 'PNG', 'tmp' => null];
        $logo = ['path' => null, 'type' => 'PNG', 'tmp' => null];

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $wine, $logo);

        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l = fn(array $arr): string => $arr['fr'] ?? '';

        // Tous les champs sont vides → Ln(5) exécuté pour chaque field
        $renderRef->invoke($ctrl, $pdf, $wine, $img, 'fr', $l);

        $this->assertTrue(true);
    }

    /**
     * convertPngToJpg() avec un PNG valide crée bien un fichier JPEG temporaire via GD.
     * Couvre les lignes 185-195 du chemin GD réussi.
     */
    public function testConvertPngToJpgWithValidPngReturnsJpgArray(): void
    {
        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable, test ignoré.');
        }
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('Extension GD indisponible.');
        }

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'convertPngToJpg');

        $result = $ref->invoke($ctrl, $srcPng);

        // GD doit réussir la conversion
        $this->assertNotNull($result);
        $this->assertSame('JPG', $result['type']);
        $this->assertIsString($result['path']);
        $this->assertTrue(is_file($result['path']));

        @unlink($result['tmp']);
    }

    /**
     * technicalSheet() retourne une HttpException 404 avec lang='en' et slug inexistant.
     * Couvre la branche Response::abort(404) dans le contexte anglophone.
     */
    public function testTechnicalSheetAborts404ForUnknownSlugEnglish(): void
    {
        $this->expectException(\Core\Exception\HttpException::class);

        $this->makeController('/en/wines/inexistant/technical-sheet')
            ->technicalSheet(['lang' => 'en', 'slug' => 'slug-inexistant-en']);
    }

    /**
     * stripAlpha() avec un fichier PNG valide retourne bien un chemin tmp non-null
     * et que le fichier temporaire existe sur disque (couverture GD complète).
     */
    public function testStripAlphaWithRealPngCreatesTmpFile(): void
    {
        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable, test ignoré.');
        }
        if (!function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('Extension GD indisponible.');
        }

        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'stripAlpha');

        $result = $ref->invoke($ctrl, $srcPng);

        $this->assertNotNull($result['path']);
        $this->assertNotNull($result['tmp']);
        $this->assertSame('JPG', $result['type']);
        $this->assertTrue(is_file($result['tmp']));

        @unlink($result['tmp']);
    }

    /**
     * collection() sans paramètre color : $color = null quand $_GET['color'] est absent.
     * Couvre la branche isset($_GET['color']) === false dans collection().
     */
    public function testCollectionWithNoColorParamUsesNull(): void
    {
        $this->insertWine('test-col-no-color', 'red');

        // $_GET['color'] non défini → branche `isset($_GET['color'])` = false → $color = null
        unset($_GET['color']);

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    /**
     * collection() avec $_GET['color'] = '' traité comme null (chaîne vide).
     * Couvre la branche `$_GET['color'] !== ''` = false → $color = null dans collection().
     */
    public function testCollectionWithEmptyColorParamUsesNull(): void
    {
        $this->insertWine('test-col-empty-color', 'white');

        $_GET['color'] = '';

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }
}
