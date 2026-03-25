<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsAdminController.
 * Couvre les helpers privés accessibles via Reflection.
 */
class NewsAdminControllerTest extends TestCase
{
    private \ReflectionMethod $generateSlug;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(\Core\Database::class);
        $this->instanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $this->generateSlug = new \ReflectionMethod(
            \Controller\Admin\NewsAdminController::class,
            'generateSlug'
        );
        $this->generateSlug->setAccessible(true);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    private function makeController(): \Controller\Admin\NewsAdminController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/actualites';
        return new \Controller\Admin\NewsAdminController(new \Core\Request());
    }

    // ----------------------------------------------------------------
    // generateSlug
    // ----------------------------------------------------------------

    public function testGenerateSlugBasicTitle(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, 'Bienvenue sur Crabitan');
        $this->assertSame('bienvenue-sur-crabitan', $result);
    }

    public function testGenerateSlugRemovesAccents(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, 'Dégustation des vins');
        // iconv TRANSLIT peut produire "degustation" ou "d-egustation" selon l'OS
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
        $this->assertStringContainsString('egustation', $result);
    }

    public function testGenerateSlugTruncatesAt80Chars(): void
    {
        $ctrl  = $this->makeController();
        $long  = str_repeat('a ', 50); // 100 chars
        $result = $this->generateSlug->invoke($ctrl, $long);
        $this->assertLessThanOrEqual(80, strlen($result));
    }

    public function testGenerateSlugNoLeadingOrTrailingDash(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, '---test---');
        $this->assertStringStartsNotWith('-', $result);
        $this->assertStringEndsNotWith('-', $result);
    }

    public function testGenerateSlugEmptyStringProducesEmpty(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, '');
        $this->assertSame('', $result);
    }

    public function testGenerateSlugCollapsesMultipleSpaces(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, 'Vin   rouge   2024');
        $this->assertStringNotContainsString('--', $result);
    }
}
