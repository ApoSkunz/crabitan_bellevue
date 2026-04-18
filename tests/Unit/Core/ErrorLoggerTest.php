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
        $this->logDir = sys_get_temp_dir() . '/test-app-logs-' . uniqid();
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
    // register()
    // ----------------------------------------------------------------

    /**
     * register() installe les handlers sans lever d'exception.
     */
    public function testRegisterInstallsHandlers(): void
    {
        $this->expectNotToPerformAssertions();
        ErrorLogger::register();
        restore_error_handler();
        restore_exception_handler();
    }

    // ----------------------------------------------------------------
    // write() — création du répertoire
    // ----------------------------------------------------------------

    /**
     * write() crée le répertoire storage/logs/ s'il n'existe pas.
     */
    public function testWriteCreatesLogDirIfMissing(): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        foreach (glob($logDir . '/*.log') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($logDir);
        @rmdir(ROOT_PATH . '/storage');

        ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'INFO',
            'message'   => 'dir creation test',
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/test',
            'method'    => 'GET',
        ]);

        $this->assertDirectoryExists($logDir);
    }

    // ----------------------------------------------------------------
    // purgeOldLogs()
    // ----------------------------------------------------------------

    /**
     * write() déclenche la purge et supprime les fichiers de plus de 90 jours.
     */
    public function testPurgeOldLogsDeletesExpiredFile(): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $oldFile = $logDir . '/app-' . date('Y-m-d', strtotime('-91 days')) . '.log';
        file_put_contents($oldFile, 'old entry');
        touch($oldFile, time() - (91 * 86400));

        ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'INFO',
            'message'   => 'purge trigger',
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/test',
            'method'    => 'GET',
        ]);

        $this->assertFileDoesNotExist($oldFile);
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

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
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
    // warning()
    // ----------------------------------------------------------------

    /**
     * warning() logue avec le level WARNING et inclut le contexte + message.
     */
    public function testWarningLogsWithWarningLevelAndContext(): void
    {
        ErrorLogger::warning(new \RuntimeException('SMTP down'), '[MailService]');

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('WARNING', $decoded['level']);
        $this->assertStringContainsString('[MailService]', $decoded['message']);
        $this->assertStringContainsString('SMTP down', $decoded['message']);
    }

    /**
     * warning() sans contexte : message contient juste la classe + message.
     */
    public function testWarningWithoutContextLogsClassAndMessage(): void
    {
        ErrorLogger::warning(new \InvalidArgumentException('bad arg'));

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('WARNING', $decoded['level']);
        $this->assertStringContainsString('InvalidArgumentException', $decoded['message']);
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

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('EXCEPTION', $decoded['level']);
        $this->assertStringContainsString('RuntimeException', $decoded['message']);
        $this->assertStringContainsString('Something broke', $decoded['message']);
    }

    // ----------------------------------------------------------------
    // info()
    // ----------------------------------------------------------------

    /**
     * info() logue avec le level INFO et le message fourni.
     */
    public function testInfoLogsWithInfoLevel(): void
    {
        ErrorLogger::info('Vérification âge validée ip=1.2.3.0');

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertStringContainsString('âge', $decoded['message']);
    }

    // ----------------------------------------------------------------
    // handleException() — silencieux 404/405
    // ----------------------------------------------------------------

    /**
     * handleException() ne logue PAS les HttpException 404 (bruit crawlers).
     */
    public function testHandleExceptionSilentFor404(): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        foreach (glob($logDir . '/*.log') ?: [] as $f) {
            unlink($f);
        }

        ErrorLogger::handleException(new \Core\Exception\HttpException(404));

        $files = glob($logDir . '/app-*.log') ?: [];
        $this->assertEmpty($files, 'HttpException 404 ne doit pas créer de fichier log');
    }

    /**
     * handleException() ne logue PAS les HttpException 405.
     */
    public function testHandleExceptionSilentFor405(): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        foreach (glob($logDir . '/*.log') ?: [] as $f) {
            unlink($f);
        }

        ErrorLogger::handleException(new \Core\Exception\HttpException(405));

        $files = glob($logDir . '/app-*.log') ?: [];
        $this->assertEmpty($files, 'HttpException 405 ne doit pas créer de fichier log');
    }
}
