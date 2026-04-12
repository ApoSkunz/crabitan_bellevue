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
        self::insertTestWine($slug, $color, 'Test Wine');
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

    private function makePdfWine(array $overrides = []): array
    {
        return array_merge([
            'label_name'          => 'Bordeaux',
            'vintage'             => 2021,
            'color'               => 'white',
            'is_cuvee_speciale'   => false,
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
        ], $overrides);
    }

    /**
     * renderPdfBody() avec imgPath='' (image absente) ne doit pas lever d'exception.
     */
    public function testRenderPdfBodyWithNullImagePath(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l         = fn(array $arr): string => $arr['fr'] ?? '';

        $pdf = $buildRef->invoke($ctrl, '', 'fr');
        $renderRef->invoke($ctrl, $pdf, $this->makePdfWine(['award' => '{"fr":"","en":""}']), [], '', 'fr', $l);

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    /**
     * buildPdf() avec logoPath non-null déclenche Header() sans erreur.
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

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, $srcPng, 'fr');

        $this->assertInstanceOf('TCPDF', $pdf);
    }

    /**
     * renderPdfBody() avec imgPath valide et lang='en' couvre la branche Image().
     */
    public function testRenderPdfBodyWithImagePathAndEnglishLang(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $srcPng = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        if (!is_file($srcPng)) {
            $this->markTestSkipped('Logo PNG introuvable.');
        }

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l         = fn(array $arr): string => $arr['en'] ?? '';

        $pdf = $buildRef->invoke($ctrl, '', 'en');
        $renderRef->invoke($ctrl, $pdf, $this->makePdfWine(['area' => null, 'age_of_vineyard' => null]), [], $srcPng, 'en', $l);

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    /**
     * buildPdf() + Output('S') déclenche Header() ET Footer() — vérifie la signature PDF.
     */
    public function testBuildPdfOutputTriggersHeaderAndFooter(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $ctrl     = $this->makeController();
        $buildRef = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf      = $buildRef->invoke($ctrl, '', 'fr');

        $pdfString = $pdf->Output('test.pdf', 'S');

        $this->assertStringStartsWith('%PDF', $pdfString);
    }

    /**
     * buildPdf() avec logo réel + Output('S') couvre la branche Image() dans Header().
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

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf       = $buildRef->invoke($ctrl, $srcPng, 'fr');
        $pdfString = $pdf->Output('test.pdf', 'S');

        $this->assertStringStartsWith('%PDF', $pdfString);
    }

    /**
     * renderPdfBody() avec tous les champs remplis couvre le chemin MultiCell complet.
     */
    public function testRenderPdfBodyWithAllFieldsPopulated(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l         = fn(array $arr): string => $arr['fr'] ?? '';

        $pdf = $buildRef->invoke($ctrl, '', 'fr');
        $renderRef->invoke($ctrl, $pdf, $this->makePdfWine(), [], '', 'fr', $l);

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    /**
     * renderPdfBody() avec tous les champs vides couvre le chemin champs optionnels absents.
     */
    public function testRenderPdfBodyWithAllFieldsEmpty(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l         = fn(array $arr): string => $arr['fr'] ?? '';

        $wine = $this->makePdfWine([
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
        ]);

        $pdf = $buildRef->invoke($ctrl, '', 'fr');
        $renderRef->invoke($ctrl, $pdf, $wine, [], '', 'fr', $l);

        $this->assertInstanceOf(\TCPDF::class, $pdf);
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

    // ----------------------------------------------------------------
    // PDF — branches non couvertes
    // ----------------------------------------------------------------

    /**
     * buildPdf() + Output('S') avec lang='en' couvre la branche Loi Evin EN dans Footer().
     */
    public function testBuildPdfWithEnglishLangOutputCoversFooterEvinEN(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $pdf       = $buildRef->invoke($ctrl, '', 'en');
        $pdfString = $pdf->Output('test_en.pdf', 'S');

        $this->assertStringStartsWith('%PDF', $pdfString);
    }

    /**
     * renderPdfBody() avec is_cuvee_speciale=1 couvre la branche subtitle Cuvée Spéciale.
     */
    public function testRenderPdfBodyWithCuveeSpecialeCoversSubtitleBranch(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $ctrl      = $this->makeController();
        $buildRef  = new \ReflectionMethod($ctrl, 'buildPdf');
        $renderRef = new \ReflectionMethod($ctrl, 'renderPdfBody');
        $l         = fn(array $arr): string => $arr['fr'] ?? '';

        $pdf = $buildRef->invoke($ctrl, '', 'fr');
        $renderRef->invoke($ctrl, $pdf, $this->makePdfWine(['is_cuvee_speciale' => 1]), [], '', 'fr', $l);

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    /**
     * renderPdfBottleImage() avec un fichier non-image couvre la branche getimagesize=false.
     */
    public function testRenderPdfBottleImageWithNonImageFileReturnsEarly(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.png';
        file_put_contents($tmpFile, 'not-an-image');

        try {
            $ctrl   = $this->makeController();
            $ref    = new \ReflectionMethod($ctrl, 'renderPdfBottleImage');
            $pdf    = (new \ReflectionMethod($ctrl, 'buildPdf'))->invoke($ctrl, '', 'fr');

            $ref->invoke($ctrl, $pdf, $tmpFile, 15.0, 35.0, 95.0, 20.0);

            $this->assertInstanceOf(\TCPDF::class, $pdf);
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * renderPdfBottleImage() avec image ratio > boxH/boxW couvre la branche iH > boxH.
     */
    public function testRenderPdfBottleImageWithTallImageCoversTallBranch(): void
    {
        if (!class_exists('TCPDF')) {
            require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
        if (!function_exists('imagepng')) {
            $this->markTestSkipped('Extension GD non disponible.');
        }

        // 10×30 px → ratio 3.0 > boxH/boxW (95/35 ≈ 2.71) → déclenche iH > boxH
        $tmpFile = tempnam(sys_get_temp_dir(), 'tall_') . '.png';
        $img = imagecreatetruecolor(10, 30);
        imagepng($img, $tmpFile);
        imagedestroy($img);

        try {
            $ctrl = $this->makeController();
            $ref  = new \ReflectionMethod($ctrl, 'renderPdfBottleImage');
            $pdf  = (new \ReflectionMethod($ctrl, 'buildPdf'))->invoke($ctrl, '', 'fr');

            $ref->invoke($ctrl, $pdf, $tmpFile, 15.0, 35.0, 95.0, 20.0);

            $this->assertInstanceOf(\TCPDF::class, $pdf);
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * buildNutritionFields() avec données complètes couvre calcul énergie + champs g.
     */
    public function testBuildNutritionFieldsWithFullDataReturnsLabels(): void
    {
        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod($ctrl, 'buildNutritionFields');

        $nutrition = [
            'energy_kj'       => 300,
            'fat_g'           => '0,5',
            'saturated_fat_g' => '0,1',
            'carbohydrate_g'  => '5,0',
            'sugars_g'        => '4,2',
            'protein_g'       => '0,3',
            'salt_g'          => '0,01',
            'ingredients'     => '{"fr":"Raisins, Sulfites","en":"Grapes, Sulphites"}',
        ];

        $resultFr = $ref->invoke($ctrl, $nutrition, false);
        $resultEn = $ref->invoke($ctrl, $nutrition, true);

        $this->assertStringContainsString('300 kJ', $resultFr['Énergie']);
        $this->assertStringContainsString('kcal', $resultFr['Énergie']);
        $this->assertSame('0,5 g', $resultFr['Matières grasses']);
        $this->assertSame('Raisins, Sulfites', $resultFr['Ingrédients']);
        $this->assertSame('Grapes, Sulphites', $resultEn['Ingredients']);
        $this->assertStringContainsString('kJ', $resultEn['Energy']);
    }

    /**
     * buildPdfSections() avec ENABLE_EXTRA_INFO=true couvre le bloc nutritionnel dans les sections.
     */
    public function testBuildPdfSectionsWithExtraInfoEnabledIncludesNutritionSection(): void
    {
        $_ENV['ENABLE_EXTRA_INFO'] = 'true';
        try {
            $ctrl = $this->makeController();
            $ref  = new \ReflectionMethod($ctrl, 'buildPdfSections');
            $l    = fn(array $arr): string => $arr['fr'] ?? '';

            $nutrition = [
                'energy_kj' => 280,
                'fat_g'     => '0,4',
                'ingredients' => '{"fr":"Raisins, Sulfites","en":"Grapes, Sulphites"}',
            ];
            $sections  = $ref->invoke($ctrl, $this->makePdfWine(), $nutrition, 'fr', $l);

            $this->assertArrayHasKey('INFORMATIONS NUTRITIONNELLES', $sections);
            $nutFields = $sections['INFORMATIONS NUTRITIONNELLES'];
            $this->assertArrayHasKey('Ingrédients', $nutFields);
            $this->assertSame('Raisins, Sulfites', $nutFields['Ingrédients']);
        } finally {
            $_ENV['ENABLE_EXTRA_INFO'] = 'false';
        }
    }

    // ----------------------------------------------------------------
    // technicalSheet() — rate limit
    // ----------------------------------------------------------------

    /**
     * technicalSheet() avec rate limit dépassé → 429 HttpException.
     * Couvre checkPdfRateLimit() : branche !checkLimit → abortWithData(429).
     * Lignes MISS 172-173 de technicalSheet + 141-148 de checkPdfRateLimit.
     */
    public function testTechnicalSheetRateLimitExceededAborts429(): void
    {
        $this->insertWine('test-pdf-ratelimit', 'sweet');

        $_SERVER['REMOTE_ADDR'] = '127.9.9.9';
        $slug    = 'test-pdf-ratelimit';
        $ip      = '127.9.9.9';
        $rlKey   = 'rl:pdf_wine:' . $ip . ':' . $slug;

        // Saturer le rate limit en session directement (APCu non dispo en TI)
        $_SESSION['_rl'][$rlKey . ':count'] = 10; // PDF_RATE_MAX = 10 → atteint
        $_SESSION['_rl_ttl'][$rlKey . ':count'] = time() + 3600;
        // Poser le lockout until dans le futur
        $_SESSION['_rl'][$rlKey . ':until'] = time() + 3600;
        $_SESSION['_rl_ttl'][$rlKey . ':until'] = time() + 3600;

        ob_start();
        try {
            $this->makeController('/fr/vins/' . $slug . '/fiche-technique')
                ->technicalSheet(['lang' => 'fr', 'slug' => $slug]);
            $this->fail('Expected HttpException 429');
        } catch (\Core\Exception\HttpException $e) {
            ob_end_clean();
            $this->assertSame(429, $e->status);
        }
    }
}
