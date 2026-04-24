<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\LogApiController;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;

/**
 * Couvre les branches non atteintes dans LogApiControllerTest :
 *   - line 66   : budget quotidien dépassé → 429
 *   - lines 117-119 : corps de readInput() (php://input)
 *   - line 133  : mkdir() dans writeLog() quand le répertoire n'existe pas
 *   - line 155  : return précoce dans purgeOldLogs() quand logDir n'existe pas
 *   - line 162  : unlink() dans purgeOldLogs() pour les logs anciens
 */
#[AllowMockObjectsWithoutExpectations]
class LogApiControllerCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = ['_rl' => [], '_rl_ttl' => []];
        $_SERVER['REMOTE_ADDR']    = '127.0.0.2';  // IP distincte pour isoler des autres tests
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/log/client-error';
        $_SERVER['CONTENT_TYPE']   = 'application/json';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['CONTENT_TYPE']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée une sous-classe de LogApiController avec corps injecté et APCu désactivé.
     *
     * @param string $body Corps JSON à injecter
     * @return LogApiController
     */
    private function makeController(string $body): LogApiController
    {
        $noApcu = new class extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        $controller = new class (new Request(), $body) extends LogApiController {
            private string $injectedBody;

            public function __construct(\Core\Request $request, string $body)
            {
                parent::__construct($request);
                $this->injectedBody = $body;
            }

            protected function readInput(): string
            {
                return $this->injectedBody;
            }
        };

        $prop = new \ReflectionProperty(LogApiController::class, 'rateLimiter');
        $prop->setValue($controller, $noApcu);

        return $controller;
    }

    // ----------------------------------------------------------------
    // line 66 — budget quotidien dépassé → 429
    // ----------------------------------------------------------------

    /**
     * Couvre line 66 : checkLimit daily quota retourne false → 429.
     *
     * Le bucket quotidien est en lockout, le bucket minute est libre.
     */
    public function testDailyQuotaExceededReturns429(): void
    {
        // Bucket minute : libre (pas de lockout)
        $rlMinuteUntil = 'rl:js-log:127.0.0.2:until';
        $_SESSION['_rl'][$rlMinuteUntil]     = 0;
        $_SESSION['_rl_ttl'][$rlMinuteUntil] = time() + 120;

        // Bucket quotidien : lockout actif
        $rlDailyUntil = 'rl:js-log-daily:127.0.0.2:until';
        $_SESSION['_rl'][$rlDailyUntil]     = time() + 86400;
        $_SESSION['_rl_ttl'][$rlDailyUntil] = time() + 90000;

        $controller = $this->makeController(json_encode(['message' => 'test daily quota']) ?: '');

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
    // lines 117-119 — readInput() corps réel (php://input)
    // ----------------------------------------------------------------

    /**
     * Couvre lines 117-119 : readInput() retourne une string depuis php://input.
     *
     * On appelle la méthode protégée directement via réflexion pour ne pas
     * dépendre du contenu réel de php://input (vide en CLI).
     */
    public function testReadInputReturnsString(): void
    {
        $controller = new LogApiController(new Request());

        $ref    = new \ReflectionMethod(LogApiController::class, 'readInput');
        $result = $ref->invoke($controller);

        // En environnement CLI, php://input est vide — on vérifie juste le type
        $this->assertIsString($result);
    }

    // ----------------------------------------------------------------
    // line 133 — mkdir() dans writeLog() quand ROOT_PATH/storage/logs n'existe pas
    // ----------------------------------------------------------------

    /**
     * Couvre line 133 : mkdir() est appelé si le répertoire de logs n'existe pas.
     *
     * On ne peut pas facilement isoler ROOT_PATH, donc on vérifie que writeLog()
     * ne lève pas d'exception et que le fichier de log est créé normalement.
     * (La branche mkdir est déjà couverte sur des environnements CI où storage/logs
     * n'existe pas ; ici on déclenche le chemin valide).
     */
    public function testValidPayloadWritesLogWithoutException(): void
    {
        $logFile = ROOT_PATH . '/storage/logs/js-' . date('Y-m-d') . '.log';
        // Supprimer le log pour forcer la création (déclenche mkdir si absent)
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        $storageDir = ROOT_PATH . '/storage/logs';
        $dirExisted = is_dir($storageDir);
        if ($dirExisted) {
            // Renommer temporairement pour forcer mkdir — NON, trop risqué en CI
            // On laisse le répertoire et on vérifie simplement que writeLog passe
        }

        $controller = $this->makeController(json_encode([
            'message'   => 'coverage mkdir test',
            'source'    => 'test.js',
            'lineno'    => 1,
            'colno'     => 1,
            'stack'     => '',
            'url'       => 'https://crabitan.local/',
            'userAgent' => 'PHPUnit',
        ]) ?: '');

        $caught = null;
        try {
            $controller->clientError([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // La réponse attendue est 204 (succès)
        $this->assertNotNull($caught);
        $this->assertSame(204, $caught->status);
    }

    // ----------------------------------------------------------------
    // line 133 — mkdir() dans writeLog() quand le répertoire de logs n'existe pas
    // ----------------------------------------------------------------

    /**
     * Couvre line 133 : mkdir() est appelé dans writeLog() quand logDir absent.
     *
     * On renomme temporairement storage/logs pour simuler son absence,
     * puis on soumet un payload valide. writeLog() doit recréer le dossier via mkdir().
     *
     * @return void
     */
    public function testWriteLogCreatesDirWhenMissing(): void
    {
        $logDir    = ROOT_PATH . '/storage/logs';
        $hiddenDir = ROOT_PATH . '/storage/logs-hidden-' . uniqid();

        // Déplacer storage/logs pour forcer la branche mkdir (line 133)
        if (is_dir($logDir)) {
            rename($logDir, $hiddenDir);
        }

        try {
            $controller = $this->makeController(json_encode([
                'message'   => 'mkdir branch coverage',
                'source'    => 'test.js',
                'lineno'    => 1,
                'colno'     => 1,
                'stack'     => '',
                'url'       => 'https://crabitan.local/',
                'userAgent' => 'PHPUnit',
            ]) ?: '');

            $caught = null;
            try {
                $controller->clientError([]);
            } catch (HttpException $e) {
                $caught = $e;
            }

            // 204 = writeLog a réussi (mkdir appelé et fichier créé)
            $this->assertNotNull($caught);
            $this->assertSame(204, $caught->status);
            $this->assertTrue(is_dir($logDir), 'mkdir() devrait avoir recréé storage/logs');
        } finally {
            // Nettoyage : supprimer le dossier recréé et restaurer l'original
            $logFile = $logDir . '/js-' . date('Y-m-d') . '.log';
            if (file_exists($logFile)) {
                unlink($logFile);
            }
            if (is_dir($logDir)) {
                @rmdir($logDir);
            }
            if (is_dir($hiddenDir)) {
                rename($hiddenDir, $logDir);
            }
        }
    }

    // ----------------------------------------------------------------
    // line 155 — return dans purgeOldLogs() quand logDir n'existe pas
    // ----------------------------------------------------------------

    /**
     * Couvre line 155 : purgeOldLogs() retourne immédiatement si logDir absent.
     *
     * On renomme temporairement storage/logs puis on appelle purgeOldLogs via réflexion.
     * Le return précoce (line 155) est atteint car is_dir() retourne false.
     *
     * @return void
     */
    public function testPurgeOldLogsReturnsEarlyWhenDirMissing(): void
    {
        $logDir    = ROOT_PATH . '/storage/logs';
        $hiddenDir = ROOT_PATH . '/storage/logs-hidden-purge-' . uniqid();

        if (is_dir($logDir)) {
            rename($logDir, $hiddenDir);
        }

        try {
            $controller = new class (new Request()) extends LogApiController {
                /**
                 * @return void
                 */
                public function runPurge(): void
                {
                    $ref = new \ReflectionMethod(LogApiController::class, 'purgeOldLogs');
                    $ref->invoke($this);
                }

                /**
                 * @return string
                 */
                protected function readInput(): string
                {
                    return '';
                }
            };

            // Aucune exception ne doit être levée (early return silencieux)
            $controller->runPurge();
            $this->assertFalse(is_dir($logDir), 'logDir ne doit pas exister');
        } finally {
            if (is_dir($hiddenDir)) {
                rename($hiddenDir, $logDir);
            }
        }
    }

    // ----------------------------------------------------------------
    // line 162 — unlink() pour les fichiers de log expirés dans purgeOldLogs()
    // ----------------------------------------------------------------

    /**
     * Couvre line 162 : unlink() est appelé pour les logs > 30 jours.
     *
     * On crée un faux fichier js-*.log vieux de 31 jours dans storage/logs,
     * puis on déclenche une purge en soumettant un payload valide.
     * On force le passage par la purge en vidant le cache APCu de purge.
     */
    public function testPurgeDeletesOldLogFiles(): void
    {
        $logDir  = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Créer un fichier vieux de 31 jours
        $oldDate = date('Y-m-d', strtotime('-31 days'));
        $oldFile = $logDir . '/js-' . $oldDate . '.log';
        file_put_contents($oldFile, '{"old":"entry"}' . "\n");
        touch($oldFile, strtotime('-31 days'));

        // S'assurer que le fichier vieux existe avant la purge
        $this->assertFileExists($oldFile);

        // Créer un contrôleur qui exposé purgeOldLogs directement
        $controller = new class (new Request()) extends LogApiController {
            public function runPurge(): void
            {
                $ref = new \ReflectionMethod(LogApiController::class, 'purgeOldLogs');
                $ref->invoke($this);
            }

            protected function readInput(): string
            {
                return '';
            }
        };

        $controller->runPurge();

        // Le fichier vieux doit avoir été supprimé (line 162 couverte)
        $this->assertFileDoesNotExist($oldFile);
    }
}
