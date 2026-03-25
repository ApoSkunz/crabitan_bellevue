<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour WineAdminController.
 * Couvre les helpers privés accessibles via Reflection.
 */
class WineAdminControllerTest extends TestCase
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

        // Mock Database to avoid real connection
        $dbMock = $this->createStub(\Core\Database::class);
        $this->instanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $this->generateSlug = new \ReflectionMethod(
            \Controller\Admin\WineAdminController::class,
            'generateSlug'
        );
        $this->generateSlug->setAccessible(true);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    private function makeController(): \Controller\Admin\WineAdminController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/vins';
        return new \Controller\Admin\WineAdminController(new \Core\Request());
    }

    // ----------------------------------------------------------------
    // generateSlug
    // ----------------------------------------------------------------

    public function testGenerateSlugBasicName(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, 'Bordeaux Rouge', 2022);
        $this->assertSame('bordeaux-rouge-2022', $result);
    }

    public function testGenerateSlugRemovesAccents(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, 'Sainte-Croix-du-Mont', 2021);
        $this->assertStringContainsString('sainte', $result);
        $this->assertStringContainsString('2021', $result);
    }

    public function testGenerateSlugReplacesSpecialChars(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, "Côtes de Bordeaux Rouge", 2020);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
    }

    public function testGenerateSlugNoLeadingOrTrailingDash(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, '  Bordeaux Blanc  ', 2019);
        $this->assertStringStartsNotWith('-', $result);
        $this->assertStringEndsNotWith('-', $result);
    }

    public function testGenerateSlugIncludesVintage(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->generateSlug->invoke($ctrl, 'Bordeaux Blanc', 2023);
        $this->assertStringEndsWith('2023', $result);
    }

    public function testGenerateSlugDifferentVintagesProduceDifferentSlugs(): void
    {
        $ctrl    = $this->makeController();
        $slug21  = $this->generateSlug->invoke($ctrl, 'Bordeaux Rouge', 2021);
        $slug22  = $this->generateSlug->invoke($ctrl, 'Bordeaux Rouge', 2022);
        $this->assertNotSame($slug21, $slug22);
    }
}
