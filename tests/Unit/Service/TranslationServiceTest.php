<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\TranslationService;

/**
 * Tests unitaires pour TranslationService.
 * Les appels HTTP sont injectés via le callable $httpFetch — aucune requête réelle.
 */
class TranslationServiceTest extends TestCase
{
    // ----------------------------------------------------------------
    // translate() — succès
    // ----------------------------------------------------------------

    /**
     * Une réponse DeepL valide retourne le texte traduit.
     */
    public function testTranslateReturnsTranslatedTextOnSuccess(): void
    {
        $service = new TranslationService('fake-key', static function (): string {
            return json_encode([
                'translations' => [['text' => 'Hello world']],
            ]) ?: '';
        });

        $this->assertSame('Hello world', $service->translate('Bonjour le monde'));
    }

    /**
     * translate() passe source_lang et target_lang corrects à l'API.
     */
    public function testTranslateSendsCorrectLangParams(): void
    {
        $capturedContent = '';
        $service = new TranslationService('fake-key', static function (string $url, mixed $ctx) use (&$capturedContent): string {
            $opts = stream_context_get_options($ctx);
            $capturedContent = $opts['http']['content'] ?? '';
            return json_encode(['translations' => [['text' => 'Hello']]]) ?: '';
        });

        $service->translate('Bonjour', 'fr', 'en');

        $this->assertStringContainsString('source_lang=FR', $capturedContent);
        $this->assertStringContainsString('target_lang=EN-US', $capturedContent);
    }

    // ----------------------------------------------------------------
    // translate() — fallback
    // ----------------------------------------------------------------

    /**
     * Si la clé API est vide, retourne le texte original sans appel HTTP.
     */
    public function testTranslateReturnsOriginalWhenApiKeyEmpty(): void
    {
        $called  = false;
        $service = new TranslationService('', static function () use (&$called): string {
            $called = true;
            return '';
        });

        $result = $service->translate('Bonjour');

        $this->assertSame('Bonjour', $result);
        $this->assertFalse($called, 'Aucun appel HTTP ne doit être effectué sans clé API');
    }

    /**
     * Si le texte est vide, retourne une chaîne vide sans appel HTTP.
     */
    public function testTranslateReturnsEmptyStringWhenTextEmpty(): void
    {
        $called  = false;
        $service = new TranslationService('fake-key', static function () use (&$called): string {
            $called = true;
            return '';
        });

        $result = $service->translate('');

        $this->assertSame('', $result);
        $this->assertFalse($called);
    }

    /**
     * Si l'appel HTTP échoue (false), retourne le texte original.
     */
    public function testTranslateFallsBackWhenHttpFails(): void
    {
        $service = new TranslationService('fake-key', static function (): false {
            return false;
        });

        $this->assertSame('Bonjour', $service->translate('Bonjour'));
    }

    /**
     * Si la réponse JSON est invalide, retourne le texte original.
     */
    public function testTranslateFallsBackOnInvalidJson(): void
    {
        $service = new TranslationService('fake-key', static function (): string {
            return 'not-json';
        });

        $this->assertSame('Bonjour', $service->translate('Bonjour'));
    }

    /**
     * Si la réponse ne contient pas de traduction, retourne le texte original.
     */
    public function testTranslateFallsBackOnMissingTranslation(): void
    {
        $service = new TranslationService('fake-key', static function (): string {
            return json_encode(['translations' => []]) ?: '';
        });

        $this->assertSame('Bonjour', $service->translate('Bonjour'));
    }

    /**
     * Si l'API lève une exception, retourne le texte original (pas de propagation).
     */
    public function testTranslateFallsBackOnException(): void
    {
        $service = new TranslationService('fake-key', static function (): never {
            throw new \RuntimeException('réseau indisponible');
        });

        $this->assertSame('Bonjour', $service->translate('Bonjour'));
    }

    // ----------------------------------------------------------------
    // mapTarget — EN-US spécifique à DeepL
    // ----------------------------------------------------------------

    /**
     * La cible 'en' est convertie en 'EN-US' (format DeepL obligatoire).
     */
    public function testTranslateSendsEnUsForEnglishTarget(): void
    {
        $capturedContent = '';
        $service = new TranslationService('fake-key', static function (string $url, mixed $ctx) use (&$capturedContent): string {
            $opts = stream_context_get_options($ctx);
            $capturedContent = $opts['http']['content'] ?? '';
            return json_encode(['translations' => [['text' => 'Hello']]]) ?: '';
        });

        $service->translate('Bonjour', 'fr', 'en');

        $this->assertStringContainsString('target_lang=EN-US', $capturedContent);
        $this->assertStringNotContainsString('target_lang=EN&', $capturedContent);
    }
}
