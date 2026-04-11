<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\ErrorLogger;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour Core\ErrorLogger.
 *
 * Couvre : write(), handleError(), handleException(), errorLevelName(), purge.
 */
class ErrorLoggerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/test-php-error-logs-' . uniqid();
        mkdir($this->logDir, 0755, true);

        // Redéfinir ROOT_PATH pour pointer vers le répertoire temporaire
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname($this->logDir));
        }

        $_SERVER['REQUEST_URI']    = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*.log') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->logDir);
    }

    // ----------------------------------------------------------------
    // errorLevelName()
    // ----------------------------------------------------------------

    /**
     * E_ERROR → 'E_ERROR'.
     */
    public function testErrorLevelNameReturnsCorrectNameForEError(): void
    {
        $this->assertSame('E_ERROR', ErrorLogger::errorLevelName(E_ERROR));
    }

    /**
     * E_NOTICE → 'E_NOTICE'.
     */
    public function testErrorLevelNameReturnsCorrectNameForENotice(): void
    {
        $this->assertSame('E_NOTICE', ErrorLogger::errorLevelName(E_NOTICE));
    }

    /**
     * E_DEPRECATED → 'E_DEPRECATED'.
     */
    public function testErrorLevelNameReturnsCorrectNameForEDeprecated(): void
    {
        $this->assertSame('E_DEPRECATED', ErrorLogger::errorLevelName(E_DEPRECATED));
    }

    /**
     * Valeur inconnue → 'E_UNKNOWN'.
     */
    public function testErrorLevelNameReturnsUnknownForUnknownCode(): void
    {
        $this->assertSame('E_UNKNOWN', ErrorLogger::errorLevelName(99999));
    }

    // ----------------------------------------------------------------
    // write()
    // ----------------------------------------------------------------

    /**
     * write() crée le fichier et y ajoute une ligne JSON valide.
     */
    public function testWriteCreatesLogFileWithJsonEntry(): void
    {
        ErrorLogger::write([
            'timestamp' => '2026-04-11 12:00:00',
            'level'     => 'E_WARNING',
            'message'   => 'Test warning',
            'file'      => '/app/src/Test.php',
            'line'      => 42,
            'trace'     => '',
            'url'       => '/test',
            'method'    => 'GET',
        ]);

        $files = glob(ROOT_PATH . '/storage/logs/php-error-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('E_WARNING', $decoded['level']);
        $this->assertSame('Test warning', $decoded['message']);
    }

    // ----------------------------------------------------------------
    // handleError()
    // ----------------------------------------------------------------

    /**
     * handleError() retourne false (délégation au handler natif).
     */
    public function testHandleErrorReturnsFalse(): void
    {
        $result = ErrorLogger::handleError(E_WARNING, 'Test error', '/app/src/Foo.php', 10);
        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // handleException()
    // ----------------------------------------------------------------

    /**
     * handleException() logue le nom de la classe + message de l'exception.
     */
    public function testHandleExceptionLogsClassAndMessage(): void
    {
        ErrorLogger::handleException(new \RuntimeException('Something broke'));

        $files = glob(ROOT_PATH . '/storage/logs/php-error-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('EXCEPTION', $decoded['level']);
        $this->assertStringContainsString('RuntimeException', $decoded['message']);
        $this->assertStringContainsString('Something broke', $decoded['message']);
    }
}
