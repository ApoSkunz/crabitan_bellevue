<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\HttpsFailStream;
use Tests\Unit\Stubs\HttpsMockStream;

/**
 * Tests unitaires pour WeatherController.
 */
class WeatherControllerTest extends TestCase
{
    // ── Helpers ────────────────────────────────────────────────────────────

    private function bootstrapApp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    private function makeController(): \Controller\WeatherController
    {
        return new \Controller\WeatherController(new \Core\Request());
    }

    // ── current() ───────────────────────────────────────────────────────────

    /**
     * WEATHER_API_KEY vide → json(['error'=>'no_key'], 503) → HttpException(503).
     */
    #[BackupGlobals(true)]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurrentReturns503WhenNoApiKey(): void
    {
        $this->bootstrapApp();
        $_ENV['WEATHER_API_KEY'] = '';

        $caught = null;
        ob_start();
        try {
            $this->makeController()->current([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'current() doit lancer HttpException quand WEATHER_API_KEY est vide');
        $this->assertSame(503, $caught->status);
    }

    /**
     * WEATHER_API_KEY absent → même comportement 503.
     */
    #[BackupGlobals(true)]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurrentReturns503WhenApiKeyNotSet(): void
    {
        $this->bootstrapApp();
        unset($_ENV['WEATHER_API_KEY']);

        $caught = null;
        ob_start();
        try {
            $this->makeController()->current([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(503, $caught->status);
    }

    /**
     * Avec une clé présente mais une API injoignable → 502 ou markTestSkipped.
     */
    #[BackupGlobals(true)]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurrentWithLangParamEnDoesNotThrow503(): void
    {
        $this->bootstrapApp();
        $_ENV['WEATHER_API_KEY'] = 'dummy-key-for-test';
        $_GET['lang'] = 'en';

        $caught = null;
        ob_start();
        try {
            $this->makeController()->current([]);
        } catch (HttpException $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Réseau indisponible : ' . $e->getMessage());
        }
        ob_end_clean();

        // Si une exception HTTP est levée, ce ne doit pas être un 503 (clé valide)
        if ($caught !== null) {
            $this->assertNotSame(503, $caught->status);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Clé présente + https:// intercepté par HttpsFailStream → fetch retourne false → 502.
     */
    #[BackupGlobals(true)]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurrentReturns502WhenFetchFails(): void
    {
        $this->bootstrapApp();
        $_ENV['WEATHER_API_KEY'] = 'test-key';

        stream_wrapper_unregister('https');
        stream_wrapper_register('https', HttpsFailStream::class);

        $caught = null;
        ob_start();
        try {
            $this->makeController()->current([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'current() doit retourner 502 quand file_get_contents échoue');
        $this->assertSame(502, $caught->status);
    }

    /**
     * Clé présente + https:// retourne du JSON invalide (clés manquantes) → 502 invalid_response.
     */
    #[BackupGlobals(true)]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurrentReturns502WhenResponseIsInvalid(): void
    {
        $this->bootstrapApp();
        $_ENV['WEATHER_API_KEY'] = 'test-key';

        HttpsMockStream::$response = '{"error":{"code":1002,"message":"API key is invalid."}}';
        stream_wrapper_unregister('https');
        stream_wrapper_register('https', HttpsMockStream::class);

        $caught = null;
        ob_start();
        try {
            $this->makeController()->current([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'current() doit retourner 502 quand la réponse est invalide');
        $this->assertSame(502, $caught->status);
    }

    /**
     * Clé présente + https:// retourne une réponse WeatherAPI valide → 200 avec données météo.
     */
    #[BackupGlobals(true)]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCurrentReturns200WithValidWeatherResponse(): void
    {
        $this->bootstrapApp();
        $_ENV['WEATHER_API_KEY'] = 'test-key';
        $_GET['lang'] = 'fr';

        HttpsMockStream::$response = (string) json_encode([
            'current'  => [
                'wind_kph'  => 15.4,
                'condition' => ['text' => 'Partiellement nuageux'],
            ],
            'forecast' => [
                'forecastday' => [
                    [
                        'day' => [
                            'mintemp_c' => 12.3,
                            'maxtemp_c' => 22.7,
                        ],
                    ],
                ],
            ],
        ]);
        stream_wrapper_unregister('https');
        stream_wrapper_register('https', HttpsMockStream::class);

        $caught = null;
        ob_start();
        try {
            $this->makeController()->current([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'current() doit lancer HttpException(200) avec une réponse valide');
        $this->assertSame(200, $caught->status);
    }
}
