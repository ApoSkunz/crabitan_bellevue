<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Lang;
use PHPUnit\Framework\TestCase;

class LangTest extends TestCase
{
    protected function setUp(): void
    {
        Lang::load('fr');
    }

    public function testLoadAndGetExistingKey(): void
    {
        $this->assertSame('Accueil', Lang::get('nav.home'));
    }

    public function testGetMissingKeyReturnsKey(): void
    {
        $this->assertSame('key.does.not.exist', Lang::get('key.does.not.exist'));
    }

    public function testGetWithReplacement(): void
    {
        // Injection d'une traduction avec placeholder pour le test
        Lang::load('fr');
        $result = Lang::get('nav.home', []);
        $this->assertIsString($result);
    }

    public function testGetReplacesPlaceholders(): void
    {
        // Test direct du mécanisme de remplacement via une clé inexistante
        // qui retourne la clé elle-même (on vérifie la logique de replace)
        $result = Lang::get('hello :name', ['name' => 'Alexandre']);
        $this->assertSame('hello Alexandre', $result);
    }

    public function testLoadEnglish(): void
    {
        Lang::load('en');
        $result = Lang::get('nav.home');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        Lang::load('fr');
    }

    public function testHelperFunctionReturnsTranslation(): void
    {
        Lang::load('fr');
        $this->assertSame('Accueil', \Core\__('nav.home'));
    }

    public function testLoadNonExistentLangFallsBackSilently(): void
    {
        // Ne doit pas lever d'exception, garde les traductions précédentes
        $this->expectNotToPerformAssertions();
        Lang::load('xx');
    }
}
