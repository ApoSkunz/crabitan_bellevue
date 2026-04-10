<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\CronRunner;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CronRunner.
 */
class CronRunnerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/cron_test_' . uniqid();
        mkdir($this->logDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->logDir);
    }

    public function testRunReturnZeroOnSuccess(): void
    {
        $runner = new CronRunner('test_ok', $this->logDir);
        $result = $runner->run(function (callable $log): void {
            $log('step 1');
            $log('step 2');
        });

        $this->assertSame(0, $result);
    }

    public function testRunReturnOneOnException(): void
    {
        $runner = new CronRunner('test_fail', $this->logDir);
        $result = $runner->run(function (callable $log): void {
            $log('before error');
            throw new \RuntimeException('Simulated failure');
        });

        $this->assertSame(1, $result);
    }

    public function testWriteCreatesLogFile(): void
    {
        $runner = new CronRunner('test_write', $this->logDir);
        ob_start();
        $runner->write('hello log');
        ob_end_clean();

        $logFile = $this->logDir . '/test_write-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString('hello log', file_get_contents($logFile));
    }

    public function testLogContainsStartAndEnd(): void
    {
        $runner = new CronRunner('test_start_end', $this->logDir);
        ob_start();
        $runner->run(function (callable $log): void {
            $log('doing work');
        });
        ob_end_clean();

        $logFile = $this->logDir . '/test_start_end-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('START', $content);
        $this->assertStringContainsString('END', $content);
        $this->assertStringContainsString('doing work', $content);
    }

    public function testRotateDeletesOldLogs(): void
    {
        $runner = new CronRunner('test_rotate', $this->logDir);

        // Créer un fichier de log vieux de 31 jours
        $oldFile = $this->logDir . '/test_rotate-' . date('Y-m-d', strtotime('-31 days')) . '.log';
        file_put_contents($oldFile, 'old log');
        touch($oldFile, strtotime('-31 days'));

        // Créer un fichier récent
        $recentFile = $this->logDir . '/test_rotate-' . date('Y-m-d') . '.log';
        file_put_contents($recentFile, 'recent log');

        ob_start();
        $runner->run(function (callable $log): void {
            $log('trigger rotation');
        });
        ob_end_clean();

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);
    }

    public function testFatalLogWrittenOnException(): void
    {
        $runner = new CronRunner('test_fatal', $this->logDir);
        ob_start();
        $runner->run(function (callable $log): void {
            throw new \LogicException('boom');
        });
        ob_end_clean();

        $logFile = $this->logDir . '/test_fatal-' . date('Y-m-d') . '.log';
        $this->assertStringContainsString('FATAL', file_get_contents($logFile));
        $this->assertStringContainsString('boom', file_get_contents($logFile));
    }
}
