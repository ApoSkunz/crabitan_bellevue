<?php

declare(strict_types=1);

namespace Core;

/**
 * Orchestrateur des scripts cron.
 *
 * Fournit à chaque job : verrouillage (évite double exécution), journalisation
 * horodatée dans storage/logs/, rotation des logs > 30 jours, et code de
 * sortie correct (0 = succès, 1 = exception non gérée).
 */
class CronRunner
{
    private string $name;
    private string $logDir;

    private ?bool $forcedDryRun;

    /**
     * @param string    $name        Nom du script (utilisé pour le fichier de log et le lock)
     * @param string    $logDir      Répertoire de logs (vide = ROOT_PATH/storage/logs)
     * @param bool|null $forcedDryRun true/false force le mode dry-run (null = lire depuis argv)
     */
    public function __construct(string $name, string $logDir = '', ?bool $forcedDryRun = null)
    {
        $this->name         = $name;
        $this->forcedDryRun = $forcedDryRun;
        if ($logDir !== '') {
            $this->logDir = $logDir;
        } else {
            $this->logDir = defined('ROOT_PATH') ? ROOT_PATH . '/storage/logs' : sys_get_temp_dir();
        }
    }

    /**
     * Exécute le job en gérant lock, logs, rotation et code de sortie.
     *
     * Passe `bool $dryRun` en 2e argument au callable. Lancer le script avec
     * `--dry-run` (CLI) ou forcer via le constructeur (HTTP).
     *
     * @param callable(callable(string):void, bool):void $job Callable recevant $log et $dryRun
     * @return int 0 = succès ou skip, 1 = erreur fatale
     */
    public function run(callable $job): int
    {
        $dryRun = $this->forcedDryRun ?? in_array('--dry-run', $GLOBALS['argv'] ?? [], true);
        $prefix   = $dryRun ? '[DRY-RUN] ' : '';

        $lockFile = sys_get_temp_dir() . '/cron_' . $this->name . '.lock';
        $lock     = fopen($lockFile, 'c');

        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->write('SKIP — déjà en cours d\'exécution (lock actif)');
            if ($lock !== false) {
                fclose($lock);
            }
            return 0;
        }

        $this->rotate();
        $this->write($prefix . 'START');

        try {
            $job(fn(string $msg) => $this->write($msg), $dryRun);
            $this->write($prefix . 'END');
        } catch (\Throwable $e) {
            $this->write(
                'FATAL — ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine()
            );
            flock($lock, LOCK_UN);
            fclose($lock);
            return 1;
        }

        flock($lock, LOCK_UN);
        fclose($lock);
        return 0;
    }

    /**
     * Écrit une ligne horodatée dans le fichier de log du jour et sur stdout.
     *
     * @param string $msg Message à journaliser
     * @return void
     */
    public function write(string $msg): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $logFile = $this->logDir . '/' . $this->name . '-' . date('Y-m-d') . '.log';
        $line    = date('Y-m-d H:i:s') . ' [' . $this->name . '] ' . $msg . PHP_EOL;

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    }

    /**
     * Supprime les fichiers de log de ce script datant de plus de 30 jours.
     *
     * @return void
     */
    private function rotate(): void
    {
        if (!is_dir($this->logDir)) {
            return;
        }

        $cutoff = time() - 30 * 86400;
        $files  = glob($this->logDir . '/' . $this->name . '-*.log') ?: [];

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}
