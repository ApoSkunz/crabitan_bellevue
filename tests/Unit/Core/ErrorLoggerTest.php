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

        // Purge des logs avant chaque test — évite les faux positifs en ordre aléatoire (Infection)
        foreach (glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [] as $f) {
            unlink($f);
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

    // ----------------------------------------------------------------
    // errorLevelName() — niveaux non couverts
    // ----------------------------------------------------------------

    /**
     * E_WARNING → 'E_WARNING'.
     */
    public function testErrorLevelNameEWarning(): void
    {
        $this->assertSame('E_WARNING', ErrorLogger::errorLevelName(E_WARNING));
    }

    /**
     * E_PARSE → 'E_PARSE'.
     */
    public function testErrorLevelNameEParse(): void
    {
        $this->assertSame('E_PARSE', ErrorLogger::errorLevelName(E_PARSE));
    }

    /**
     * E_CORE_ERROR → 'E_CORE_ERROR'.
     */
    public function testErrorLevelNameECoreError(): void
    {
        $this->assertSame('E_CORE_ERROR', ErrorLogger::errorLevelName(E_CORE_ERROR));
    }

    /**
     * E_CORE_WARNING → 'E_CORE_WARNING'.
     */
    public function testErrorLevelNameECoreWarning(): void
    {
        $this->assertSame('E_CORE_WARNING', ErrorLogger::errorLevelName(E_CORE_WARNING));
    }

    /**
     * E_COMPILE_ERROR → 'E_COMPILE_ERROR'.
     */
    public function testErrorLevelNameECompileError(): void
    {
        $this->assertSame('E_COMPILE_ERROR', ErrorLogger::errorLevelName(E_COMPILE_ERROR));
    }

    /**
     * E_COMPILE_WARNING → 'E_COMPILE_WARNING'.
     */
    public function testErrorLevelNameECompileWarning(): void
    {
        $this->assertSame('E_COMPILE_WARNING', ErrorLogger::errorLevelName(E_COMPILE_WARNING));
    }

    /**
     * E_USER_ERROR → 'E_USER_ERROR'.
     */
    public function testErrorLevelNameEUserError(): void
    {
        $this->assertSame('E_USER_ERROR', ErrorLogger::errorLevelName(E_USER_ERROR));
    }

    /**
     * E_USER_WARNING → 'E_USER_WARNING'.
     */
    public function testErrorLevelNameEUserWarning(): void
    {
        $this->assertSame('E_USER_WARNING', ErrorLogger::errorLevelName(E_USER_WARNING));
    }

    /**
     * E_USER_NOTICE → 'E_USER_NOTICE'.
     */
    public function testErrorLevelNameEUserNotice(): void
    {
        $this->assertSame('E_USER_NOTICE', ErrorLogger::errorLevelName(E_USER_NOTICE));
    }

    /**
     * E_RECOVERABLE_ERROR → 'E_RECOVERABLE_ERROR'.
     */
    public function testErrorLevelNameERecoverableError(): void
    {
        $this->assertSame('E_RECOVERABLE_ERROR', ErrorLogger::errorLevelName(E_RECOVERABLE_ERROR));
    }

    /**
     * E_USER_DEPRECATED → 'E_USER_DEPRECATED'.
     */
    public function testErrorLevelNameEUserDeprecated(): void
    {
        $this->assertSame('E_USER_DEPRECATED', ErrorLogger::errorLevelName(E_USER_DEPRECATED));
    }

    // ----------------------------------------------------------------
    // write() — branche channel déjà défini
    // ----------------------------------------------------------------

    /**
     * write() avec channel fourni → channel conservé dans le JSON.
     */
    public function testWritePreservesProvidedChannel(): void
    {
        ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'INFO',
            'message'   => 'cron test',
            'channel'   => 'cron',
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/cron',
            'method'    => 'GET',
        ]);

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        $lines   = array_filter(explode("\n", trim((string) $content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('cron', $decoded['channel']);
    }

    /**
     * write() sans channel → channel = 'php' ajouté par défaut.
     */
    public function testWriteDefaultChannelIsPhp(): void
    {
        ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'INFO',
            'message'   => 'no channel',
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/',
            'method'    => 'GET',
        ]);

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = (string) file_get_contents($files[0]);
        $lines   = array_filter(explode("\n", trim($content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('php', $decoded['channel']);
    }

    /**
     * write() avec file non vide et line = 0 → "(Foo.php)" sans numéro de ligne dans la ligne humaine.
     */
    public function testWriteHumanLineWithFileButNoLineNumber(): void
    {
        ErrorLogger::write([
            'timestamp' => '2026-01-01 00:00:00',
            'level'     => 'E_NOTICE',
            'message'   => 'no line number',
            'file'      => '/app/src/Foo.php',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/',
            'method'    => 'GET',
        ]);

        $files   = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = (string) file_get_contents($files[0]);

        // Extraire la ligne humaine (commence par '[')
        $lines = explode("\n", $content);
        $humanLine = '';
        foreach ($lines as $line) {
            if (str_contains($line, 'no line number')) {
                $humanLine = $line;
                break;
            }
        }

        // La ligne lisible doit contenir (Foo.php) mais pas (Foo.php:0)
        $this->assertStringContainsString('(Foo.php)', $humanLine);
        $this->assertStringNotContainsString('(Foo.php:0)', $humanLine);
    }

    /**
     * write() avec message contenant des sauts de ligne → remplacé par espace.
     */
    public function testWriteReplacesNewlinesInMessage(): void
    {
        ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'E_WARNING',
            'message'   => "line1\nline2\rline3",
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/',
            'method'    => 'GET',
        ]);

        $files   = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = (string) file_get_contents($files[0]);
        // La ligne humaine ne doit pas contenir de saut de ligne dans le message
        $this->assertStringContainsString('line1 line2 line3', $content);
    }

    // ----------------------------------------------------------------
    // handleError() — avec REMOTE_ADDR
    // ----------------------------------------------------------------

    /**
     * handleError() anonymise l'IP depuis REMOTE_ADDR.
     */
    public function testHandleErrorAnonymizesRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.42';
        ErrorLogger::handleError(E_NOTICE, 'addr test', '/app/foo.php', 1);

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $this->assertNotEmpty($files);

        $content = (string) file_get_contents($files[0]);
        $lines   = array_filter(explode("\n", trim($content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('192.168.1.0', $decoded['ip']);
    }

    // ----------------------------------------------------------------
    // anonymizeIp — via handleError() — cas limites
    // ----------------------------------------------------------------

    /**
     * IP vide → ip vide dans le log.
     */
    public function testHandleErrorWithEmptyRemoteAddrLogsEmptyIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '';
        ErrorLogger::handleError(E_NOTICE, 'empty addr', '', 0);

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = (string) file_get_contents($files[0]);
        $lines   = array_filter(explode("\n", trim($content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('', $decoded['ip']);
    }

    /**
     * IP non-IP (chaîne invalide) → retournée telle quelle (pas d'IPv4 ni IPv6).
     */
    public function testHandleErrorWithInvalidIpLogsItUnchanged(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        ErrorLogger::handleError(E_NOTICE, 'bad ip', '', 0);

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = (string) file_get_contents($files[0]);
        $lines   = array_filter(explode("\n", trim($content)));
        $decoded = json_decode((string) end($lines), true);
        $this->assertSame('not-an-ip', $decoded['ip']);
    }

    /**
     * IPv6 valide → dernier groupe anonymisé à 0000.
     */
    public function testHandleErrorAnonymizesIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        ErrorLogger::handleError(E_NOTICE, 'ipv6 test', '', 0);

        $files = glob(ROOT_PATH . '/storage/logs/app-*.log') ?: [];
        $content = (string) file_get_contents($files[0]);
        $lines   = array_filter(explode("\n", trim($content)));
        $decoded = json_decode((string) end($lines), true);
        // Le dernier groupe doit être 0000
        $this->assertStringEndsWith(':0000', $decoded['ip']);
    }

    // ----------------------------------------------------------------
    // purgeOldLogs() — fichier récent non purgé
    // ----------------------------------------------------------------

    /**
     * write() ne supprime pas les fichiers de moins de 90 jours.
     */
    public function testPurgeOldLogsKeepsRecentFile(): void
    {
        $logDir = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $recentFile = $logDir . '/app-' . date('Y-m-d', strtotime('-1 day')) . '.log';
        file_put_contents($recentFile, 'recent entry');

        ErrorLogger::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'INFO',
            'message'   => 'purge recent trigger',
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => '/test',
            'method'    => 'GET',
        ]);

        $this->assertFileExists($recentFile);
    }

    // ----------------------------------------------------------------
    // write() — création du répertoire (process isolé)
    // ----------------------------------------------------------------

    /**
     * write() appelle mkdir() quand storage/logs n'existe pas encore.
     * Renomme temporairement le dossier réel pour simuler son absence.
     * Couvre ErrorLogger.php:161.
     */
    public function testWriteCreatesLogDirWhenMissing(): void
    {
        $logDir  = ROOT_PATH . '/storage/logs';
        $backDir = ROOT_PATH . '/storage/logs_errlog_bak';

        $existed = is_dir($logDir);
        if ($existed) {
            rename($logDir, $backDir);
        }

        try {
            $this->assertDirectoryDoesNotExist($logDir);

            \Core\ErrorLogger::write([
                'timestamp' => date('Y-m-d H:i:s'),
                'level'     => 'INFO',
                'message'   => 'mkdir branch test',
                'file'      => '',
                'line'      => 0,
                'trace'     => '',
                'url'       => '/test',
                'method'    => 'GET',
            ]);

            $this->assertDirectoryExists($logDir);
        } finally {
            foreach (glob($logDir . '/*.log') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($logDir);
            if ($existed && is_dir($backDir)) {
                rename($backDir, $logDir);
            }
        }
    }

    // ----------------------------------------------------------------
    // anonymizeIpv6() — branche inet_ntop retourne false (processus isolé)
    // ----------------------------------------------------------------

    /**
     * anonymizeIpv6() retourne l'IP originale quand inet_ntop retourne false.
     *
     * On surcharge inet_ntop dans le namespace Core avant le chargement de la classe.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAnonymizeIpv6ReturnOriginalWhenInetNtopReturnsFalse(): void
    {
        // Déclarer inet_ntop dans le namespace Core pour shadower la built-in
        // → simule le cas où inet_ntop retourne false (branche ligne 219 : return $ip)
        eval('namespace Core; function inet_ntop(string $in_addr): string|false { return false; }');

        $method = new \ReflectionMethod(\Core\ErrorLogger::class, 'anonymizeIpv6');
        $result = $method->invoke(null, '2001:db8::1');

        // La branche "return $ip" (ligne 219) doit retourner l'IP sans modification
        $this->assertSame('2001:db8::1', $result);
    }
}
