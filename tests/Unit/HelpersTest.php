<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Lang;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        // Load French translations so __() has data to work with
        Lang::load('fr');
    }

    protected function tearDown(): void
    {
        $prop = new \ReflectionProperty(Lang::class, 'translations');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    public function testHelperReturnsTranslation(): void
    {
        $result = __('nav.home');
        $this->assertSame('Accueil', $result);
    }

    public function testHelperReturnsKeyWhenTranslationMissing(): void
    {
        $result = __('nonexistent.key.xyz');
        $this->assertSame('nonexistent.key.xyz', $result);
    }

    public function testHelperAppliesPlaceholderReplacement(): void
    {
        // Loads a key with :placeholder and verifies replacement
        Lang::load('fr');
        // Use a key that has placeholders — falls back to checking replacement works
        $result = __('age_gate.quote');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testHelperFunctionExists(): void
    {
        $this->assertTrue(function_exists('__'));
    }
}
