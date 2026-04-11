<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\LogApiController;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;

/**
 * Tests unitaires pour LogApiController::clientError().
 *
 * Couvre : payload vide → 400, rate limit dépassé → 429, payload valide → 204 + log.
 * APCu est désactivé via sous-classe pour garantir des tests déterministes.
 */
class LogApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = ['_rl' => [], '_rl_ttl' => []];
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/log/client-error';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REMOTE_ADDR']);

        // Nettoyer les fichiers de log créés pendant le test
        $logFile = ROOT_PATH . '/storage/logs/js-error-' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    // ----------------------------------------------------------------
    // Test : payload vide → 400
    // ----------------------------------------------------------------

    /**
     * Un corps de requête vide doit retourner 400 Bad Request.
     */
    public function testEmptyPayloadReturns400(): void
    {
        $controller = $this->makeControllerWithBody('');

        $caught = null;
        try {
            $controller->clientError([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'Une HttpException doit être levée');
        $this->assertSame(400, $caught->status);
    }

    /**
     * Un payload JSON sans champ "message" doit retourner 400.
     */
    public function testPayloadWithoutMessageReturns400(): void
    {
        $controller = $this->makeControllerWithBody(json_encode(['source' => 'test.js', 'lineno' => 1]) ?: '');

        $caught = null;
        try {
            $controller->clientError([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(400, $caught->status);
    }

    // ----------------------------------------------------------------
    // Test : rate limit → 429
    // ----------------------------------------------------------------

    /**
     * Après 10 tentatives (MAX_ATTEMPTS), la 11e doit retourner 429.
     */
    public function testRateLimitReturns429(): void
    {
        // Pré-remplir le bucket rate limit en session au-delà du seuil
        $rlKey = 'rl:js-log:127.0.0.1';
        $_SESSION['_rl'][$rlKey . ':count']    = 10;
        $_SESSION['_rl_ttl'][$rlKey . ':count'] = time() + 60;
        // Poser le lockout
        $_SESSION['_rl'][$rlKey . ':until']    = time() + 60;
        $_SESSION['_rl_ttl'][$rlKey . ':until'] = time() + 120;

        $controller = $this->makeControllerWithBody(
            json_encode(['message' => 'JS Error', 'source' => 'https://crabitan.local/assets/main.js']) ?: ''
        );

        $caught = null;
        try {
            $controller->clientError([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(429, $caught->status);
    }

    // ----------------------------------------------------------------
    // Test : payload valide → 204 + fichier log créé
    // ----------------------------------------------------------------

    /**
     * Un payload valide doit retourner 204 et créer une entrée dans le log.
     */
    public function testValidPayloadReturns204AndWritesLog(): void
    {
        $payload = [
            'message'   => 'Uncaught ReferenceError: foo is not defined',
            'source'    => 'https://crabitan.local/assets/main.js',
            'lineno'    => 42,
            'colno'     => 7,
            'stack'     => "ReferenceError: foo\n    at main.js:42:7",
            'url'       => 'https://crabitan.local/fr/vins',
            'userAgent' => 'Mozilla/5.0 PHPUnit',
        ];

        $controller = $this->makeControllerWithBody(json_encode($payload) ?: '');

        $caught = null;
        try {
            $controller->clientError([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(204, $caught->status);

        // Vérifier que le fichier log a été créé
        $logFile = ROOT_PATH . '/storage/logs/js-error-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $line = trim(file_get_contents($logFile) ?: '');
        $entry = json_decode($line, true);
        $this->assertIsArray($entry);
        $this->assertSame('Uncaught ReferenceError: foo is not defined', $entry['message']);
        $this->assertSame(42, $entry['lineno']);
    }

    // ----------------------------------------------------------------
    // Helper : sous-classe avec corps de requête injecté
    // ----------------------------------------------------------------

    /**
     * Crée une sous-classe de LogApiController dont readBody() retourne $body.
     *
     * @param string $body Corps JSON à injecter
     * @return LogApiController
     */
    private function makeControllerWithBody(string $body): LogApiController
    {
        $noApcu = new class extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        // On crée une sous-classe anonyme pour contourner php://input non-mockable
        $controller = new class (new Request(), $body) extends LogApiController {
            private string $injectedBody;

            public function __construct(\Core\Request $request, string $body)
            {
                parent::__construct($request);
                $this->injectedBody = $body;
            }

            /**
             * Surcharge la lecture du corps pour les tests.
             *
             * @return string
             */
            protected function readInput(): string
            {
                return $this->injectedBody;
            }
        };

        $prop = new \ReflectionProperty(LogApiController::class, 'rateLimiter');
        $prop->setValue($controller, $noApcu);

        return $controller;
    }
}
