<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Database;
use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests de couverture pour PageController — sitemap, sitemapMedia, sitemapIndex,
 * securityTxt, galerie et accessibilite.
 *
 * Couvre les lignes non atteintes par PageControllerTest.php :
 *   L226-543 : sitemapXml(), sitemapMedia() (toutes branches)
 *   L551-571 : sitemapIndex()
 *   L578-591 : securityTxt()
 *   L249-274 : galerie() (avec fichiers dans le répertoire)
 *   L282-286 : accessibilite()
 */
class PageControllerSitemapTest extends TestCase
{
    private ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->dbInstanceProp->setValue(null, $stub);
        return $stub;
    }

    private function bootstrapApp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    // ================================================================
    // sitemapXml() — génère le sitemap XML avec pages statiques + vins + news
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapXmlGeneratesXmlAndThrowsHttpException0(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        // WineModel::getAllSlugs → 1 vin
        // NewsModel::getAll → 1 article
        $stub->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['slug' => 'bordeaux-2020']],  // getAllSlugs
            [['slug' => 'actualite-test']]   // getAll (news)
        );

        $_SERVER['REQUEST_URI'] = '/sitemap.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapXml([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('<?xml', $output);
        $this->assertStringContainsString('urlset', $output);
        $this->assertStringContainsString('bordeaux-2020', $output);
        $this->assertStringContainsString('actualite-test', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // sitemapXml() — APP_URL absent → fallback DEFAULT_APP_URL
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapXmlUsesDefaultAppUrlWhenEnvAbsent(): void
    {
        $this->bootstrapApp();
        unset($_ENV['APP_URL']);

        $stub = $this->setDbMock();
        $stub->method('fetchAll')->willReturn([]);

        $_SERVER['REQUEST_URI'] = '/sitemap.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapXml([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('crabitanbellevue.fr', $output);
    }

    // ================================================================
    // sitemapXml() — aucun vin ni news → sortie valide sans entrées dynamiques
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapXmlWithNoWinesOrNewsIsValid(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        $stub->method('fetchAll')->willReturn([]);

        $_SERVER['REQUEST_URI'] = '/sitemap.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapXml([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('</urlset>', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // sitemapMedia() — génère le sitemap media XML
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapMediaGeneratesXmlAndThrowsHttpException0(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        // WineModel::getAllForMediaSitemap → 1 vin avec image
        // NewsModel::getAll → 1 article avec image
        $stub->method('fetchAll')->willReturnOnConsecutiveCalls(
            [['slug' => 'bordeaux-2020', 'label_name' => 'Bordeaux', 'vintage' => 2020, 'image_path' => 'bordeaux.jpg']],
            [['slug' => 'news-test', 'title' => 'Actualité', 'image_path' => 'news.jpg']]
        );

        $_SERVER['REQUEST_URI'] = '/sitemap-media.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapMedia([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('<?xml', $output);
        $this->assertStringContainsString('image:image', $output);
        $this->assertStringContainsString('bordeaux.jpg', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // sitemapMedia() — article sans image_path → skip (L437-438)
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapMediaSkipsNewsWithoutImagePath(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        $stub->method('fetchAll')->willReturnOnConsecutiveCalls(
            [], // pas de vins
            [
                ['slug' => 'no-image', 'title' => 'Sans image', 'image_path' => ''], // skip
                ['slug' => 'with-image', 'title' => 'Avec image', 'image_path' => 'img.jpg'], // inclus
            ]
        );

        $_SERVER['REQUEST_URI'] = '/sitemap-media.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapMedia([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        // L'article sans image ne doit pas apparaître
        $this->assertStringNotContainsString('no-image', $output);
        // L'article avec image doit apparaître
        $this->assertStringContainsString('with-image', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // sitemapMedia() — aucune donnée → sortie XML valide
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapMediaWithNoDataIsValid(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        $stub->method('fetchAll')->willReturn([]);

        $_SERVER['REQUEST_URI'] = '/sitemap-media.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapMedia([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        // Les images carousel et galerie doivent toujours être présentes
        $this->assertStringContainsString('carousel', $output);
        $this->assertStringContainsString('gallery', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // sitemapIndex() — génère le sitemapindex XML
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapIndexGeneratesXmlAndThrowsHttpException0(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $_SERVER['REQUEST_URI'] = '/sitemap-index.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapIndex([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('sitemapindex', $output);
        $this->assertStringContainsString('sitemap.xml', $output);
        $this->assertStringContainsString('sitemap-media.xml', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // securityTxt() — génère le fichier security.txt
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSecurityTxtGeneratesCorrectContent(): void
    {
        $this->bootstrapApp();
        $_ENV['SECURITY_EMAIL'] = 'security@example.com';
        $_ENV['APP_URL']        = 'https://test.example.com';

        $_SERVER['REQUEST_URI'] = '/.well-known/security.txt';

        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->securityTxt();
        } catch (\Throwable) {
            // securityTxt() ne redirige pas, elle echo directement
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('Contact: mailto:security@example.com', $output);
        $this->assertStringContainsString('Expires:', $output);
        $this->assertStringContainsString('Preferred-Languages: fr, en', $output);
        $this->assertStringContainsString('Policy:', $output);
        $this->assertStringContainsString('Canonical:', $output);

        unset($_ENV['SECURITY_EMAIL'], $_ENV['APP_URL']);
    }

    // ================================================================
    // galerie() — répertoire vide → sortie photos vide
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGalerieResolvesLangAndBuildsPhotosList(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/galerie';

        $controller = new class (new \Core\Request()) extends \Controller\PageController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->galerie(['lang' => 'fr']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertSame('fr', $controller->lastViewData['lang']);
        $this->assertArrayHasKey('photos', $controller->lastViewData);
        $this->assertIsArray($controller->lastViewData['photos']);
    }

    // ================================================================
    // accessibilite() — rend la vue avec lang
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAccessibiliteResolvesLang(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/accessibilite';

        $controller = new class (new \Core\Request()) extends \Controller\PageController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->accessibilite(['lang' => 'fr']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertSame('fr', $controller->lastViewData['lang']);
    }

    // ================================================================
    // galerie() — exclut les variantes -600w, -800w, etc. (L258-260)
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGalerieExcludesWidthVariants(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/galerie';

        // La méthode galerie() utilise ROOT_PATH/public/assets/images/gallery/
        // En test unitaire, ce répertoire peut être vide ou absent.
        // On vérifie juste que la méthode retourne une liste de photos valide.
        $controller = new class (new \Core\Request()) extends \Controller\PageController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->galerie(['lang' => 'en']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertSame('en', $controller->lastViewData['lang']);

        // Aucune photo avec le pattern -600w/-800w ne doit être dans la liste
        foreach ($controller->lastViewData['photos'] as $photo) {
            $this->assertDoesNotMatchRegularExpression(
                '/-\d+w\.(jpg|jpeg|png|webp)$/i',
                (string) ($photo['src'] ?? '')
            );
        }
    }

    // ================================================================
    // sitemapXml() — hreflang x-default présent dans la sortie
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapXmlContainsHreflangXDefault(): void
    {
        $this->bootstrapApp();
        $_ENV['APP_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        $stub->method('fetchAll')->willReturn([]);

        $_SERVER['REQUEST_URI'] = '/sitemap.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapXml([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertStringContainsString('x-default', $output);
        $this->assertStringContainsString('alternate', $output);

        unset($_ENV['APP_URL']);
    }

    // ================================================================
    // sitemapMedia() — APP_URL absent → fallback DEFAULT_APP_URL
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapMediaUsesDefaultAppUrlWhenEnvAbsent(): void
    {
        $this->bootstrapApp();
        unset($_ENV['APP_URL']);

        $stub = $this->setDbMock();
        $stub->method('fetchAll')->willReturn([]);

        $_SERVER['REQUEST_URI'] = '/sitemap-media.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapMedia([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('crabitanbellevue.fr', $output);
    }

    // ================================================================
    // sitemapIndex() — APP_URL absent → fallback DEFAULT_APP_URL
    // ================================================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSitemapIndexUsesDefaultAppUrlWhenEnvAbsent(): void
    {
        $this->bootstrapApp();
        unset($_ENV['APP_URL']);

        $_SERVER['REQUEST_URI'] = '/sitemap-index.xml';

        $caught = null;
        ob_start();
        try {
            (new \Controller\PageController(new \Core\Request()))->sitemapIndex([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught);
        $this->assertSame(0, $caught->status);
        $this->assertStringContainsString('crabitanbellevue.fr', $output);
    }
}
