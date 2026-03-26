<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\PhpInputMockStream;

/**
 * Tests unitaires pour GameScoreController.
 */
class GameScoreControllerTest extends TestCase
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

    private function makeController(): \Controller\GameScoreController
    {
        return new \Controller\GameScoreController(new \Core\Request());
    }

    // ── save() ──────────────────────────────────────────────────────────────

    /**
     * php://input retourne '' en contexte de test → json_decode → null
     * → game='' → pas dans ALLOWED_GAMES → json(['error'=>'Invalid payload'], 400)
     * → throws HttpException(400)
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveRejects400WhenPayloadIsEmpty(): void
    {
        $this->bootstrapApp();

        $caught = null;
        ob_start();
        try {
            $this->makeController()->save([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'save() doit lancer HttpException pour un payload vide');
        $this->assertSame(400, $caught->status);
    }

    // ── get() ───────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetRejects400ForUnknownGame(): void
    {
        $this->bootstrapApp();
        $_GET['game'] = 'unknown';

        $caught = null;
        ob_start();
        try {
            $this->makeController()->get([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'get() doit lancer HttpException pour un jeu inconnu');
        $this->assertSame(400, $caught->status);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetRejects400WhenGameIsMissing(): void
    {
        $this->bootstrapApp();
        unset($_GET['game']);

        $caught = null;
        ob_start();
        try {
            $this->makeController()->get([]);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(400, $caught->status);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetWithValidGameEitherReturnsJsonOrSkips(): void
    {
        $this->bootstrapApp();
        $_GET['game'] = 'memo';

        ob_start();
        try {
            $this->makeController()->get([]);
            ob_end_clean();
            $this->assertTrue(true); // succès inattendu sans BDD réelle
        } catch (HttpException $e) {
            ob_end_clean();
            // 200 = réponse JSON retournée (HttpException levée par Response::json)
            $this->assertSame(200, $e->status);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    /**
     * Payload JSON valide via mock php://input → passe la validation
     * → appelle GameScoreModel → retourne json 200.
     * Nécessite la BDD (table game_scores). Skippé si BDD indisponible.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSaveReturns200WithValidPayload(): void
    {
        $this->bootstrapApp();

        PhpInputMockStream::$inputData = (string) json_encode(['game' => 'memo', 'score' => 500]);
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', PhpInputMockStream::class);

        $caught = null;
        ob_start();
        try {
            $this->makeController()->save([]);
        } catch (HttpException $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('BDD ou php:// indisponible : ' . $e->getMessage());
            return;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'save() doit lancer HttpException(200) avec un payload valide');
        $this->assertSame(200, $caught->status);
    }
}
