<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\CronRunner;
use PHPUnit\Framework\TestCase;

/**
 * Couvre les branches non atteintes dans CronRunnerTest :
 *   - lines 55-59 : branche lock non-acquis (flock LOCK_NB retourne false)
 *   - line 92     : mkdir() dans write() quand le répertoire n'existe pas encore
 *   - line 110    : return précoce dans rotate() quand logDir n'existe pas
 */
class CronRunnerCoverageTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/cron_cov_' . uniqid();
        // NE PAS créer le dossier ici — certains tests couvrent sa création automatique
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        if (is_dir($this->logDir)) {
            @rmdir($this->logDir);
        }
    }

    // ----------------------------------------------------------------
    // line 92 — mkdir() dans write() quand le répertoire n'existe pas
    // ----------------------------------------------------------------

    /**
     * write() crée le répertoire de logs s'il n'existe pas (line 92).
     */
    public function testWriteCreatesDirectoryWhenMissing(): void
    {
        // logDir n'existe pas encore
        $this->assertDirectoryDoesNotExist($this->logDir);

        $runner = new CronRunner('cov_mkdir', $this->logDir);

        ob_start();
        $runner->write('test mkdir branch');
        ob_end_clean();

        // Le répertoire a dû être créé (line 92 couverte)
        $this->assertDirectoryExists($this->logDir);

        $logFile = $this->logDir . '/cov_mkdir-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString('test mkdir branch', (string) file_get_contents($logFile));
    }

    // ----------------------------------------------------------------
    // line 110 — return dans rotate() quand logDir n'existe pas
    // ----------------------------------------------------------------

    /**
     * rotate() retourne immédiatement si logDir n'existe pas (line 110).
     * On vérifie que run() ne génère pas d'erreur dans ce cas.
     */
    public function testRunDoesNotFailWhenLogDirMissingAtRotation(): void
    {
        // logDir inexistant → rotate() doit retourner sans erreur (line 110)
        $runner = new CronRunner('cov_rotate', $this->logDir);

        ob_start();
        $result = $runner->run(function (callable $log): void {
            $log('after rotation attempt');
        });
        ob_end_clean();

        // run() doit réussir (0) même si logDir n'existait pas initialement
        $this->assertSame(0, $result);
        // Après run(), write() a créé le répertoire
        $this->assertDirectoryExists($this->logDir);
    }

    // ----------------------------------------------------------------
    // lines 55-59 — skip si le lock est déjà détenu (simulé via double run)
    // ----------------------------------------------------------------

    /**
     * Couvre lines 55-59 : CronRunner renvoie 0 et écrit "SKIP" si le lock est actif.
     *
     * Stratégie : on ouvre le lock file et on l'acquiert manuellement avant
     * que CronRunner tente de l'acquérir à son tour (LOCK_NB → false).
     */
    public function testRunSkipsWhenLockAlreadyHeld(): void
    {
        // Créer le répertoire pour que write() fonctionne lors du SKIP
        mkdir($this->logDir, 0755, true);

        $lockFile = sys_get_temp_dir() . '/cron_cov_skip_lock_test.lock';

        // Acquérir le lock en amont pour bloquer CronRunner
        $externalLock = fopen($lockFile, 'c');
        $this->assertNotFalse($externalLock, 'Impossible d\'ouvrir le fichier de lock pour le test');
        $locked = flock($externalLock, LOCK_EX | LOCK_NB);
        $this->assertTrue($locked, 'Impossible d\'acquérir le lock externe pour le test');

        $runner = new CronRunner('cov_skip_lock_test', $this->logDir);

        ob_start();
        $result = $runner->run(function (callable $log): void {
            $log('this should not run');
        });
        $output = ob_get_clean();

        // Libérer le lock externe
        flock($externalLock, LOCK_UN);
        fclose($externalLock);
        @unlink($lockFile);

        // CronRunner doit retourner 0 (skip)
        $this->assertSame(0, $result);
        // Le message SKIP doit avoir été écrit
        $this->assertStringContainsString('SKIP', (string) $output);
    }
}
