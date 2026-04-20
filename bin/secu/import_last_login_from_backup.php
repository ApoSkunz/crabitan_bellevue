<?php

/**
 * Import one-shot : met à jour accounts.last_login_at depuis un fichier exporté
 * d'une BDD de backup (ancienne version où les user_id ne correspondent plus).
 *
 * Formats acceptés (auto-détection par ligne) :
 *
 *   Format SQL INSERT — 3 colonnes :
 *     (11845, 'email@exemple.fr', '2026-04-01 16:20:58')
 *
 *   Format TSV mysql --batch (col1\tcol2\tcol3) :
 *     11845	email@exemple.fr	2026-04-01 16:20:58
 *
 *   Format CSV (col1,col2,col3 avec ou sans guillemets) :
 *     11845,email@exemple.fr,2026-04-01 16:20:58
 *     11845,"email@exemple.fr","2026-04-01 16:20:58"
 *
 * Ordre des colonnes dans tous les formats :
 *   col1 : id quelconque (ignoré)
 *   col2 : email
 *   col3 : datetime YYYY-MM-DD HH:MM:SS
 *
 * Génération du fichier depuis la BDD backup (Windows XAMPP) :
 *   "C:/xampp/mysql/bin/mysql.exe" -u root crabitan_backup --batch --skip-column-names -e ^
 *     "SELECT c.user_id, a.email, MAX(c.created_at) FROM connections c JOIN accounts a ON a.id = c.user_id GROUP BY c.user_id, a.email" ^
 *     > C:/Users/ApoSkunz/Desktop/backup_connections.tsv
 *
 * Exécution :
 *   php8.4-cli bin/secu/import_last_login_from_backup.php --file=/path/to/backup.tsv [--dry-run]
 *
 * ⚠️  Snapshot BDD recommandé avant exécution en production.
 * ⚠️  NE PAS planifier via cron — exécution manuelle unique.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
defined('SRC_PATH')  || define('SRC_PATH',  ROOT_PATH . '/src');
defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

// ── Arguments CLI ─────────────────────────────────────────────────────────────

$opts   = getopt('', ['file:', 'dry-run']);
$file   = $opts['file'] ?? null;
$dryRun = isset($opts['dry-run']);

if ($file === null) {
    fwrite(STDERR, "Usage : php8.4-cli bin/secu/import_last_login_from_backup.php --file=/path/to/backup.tsv [--dry-run]\n");
    exit(1);
}

if (!is_readable($file)) {
    fwrite(STDERR, "Fichier illisible : {$file}\n");
    exit(1);
}

$mode = $dryRun ? '[DRY-RUN] ' : '';

// ── Parseurs de ligne ─────────────────────────────────────────────────────────

/**
 * Tente d'extraire (email, datetime) depuis une ligne, quel que soit le format.
 * Retourne null si la ligne ne correspond à aucun format connu.
 *
 * @return array{string,string}|null  [email, datetime]
 */
function parseLine(string $line): ?array
{
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '--')) {
        return null;
    }

    // Format SQL INSERT : (..., 'email', 'datetime')
    if (preg_match(
        "/\(\s*\d+\s*,\s*'([^']+@[^']+)'\s*,\s*'(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})'\s*\)/",
        $line,
        $m
    )) {
        return [$m[1], $m[2]];
    }

    // Format TSV mysql --batch : id\temail\tdatetime
    if (preg_match(
        '/^[0-9]+\t([^\t]+@[^\t]+)\t(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/',
        $line,
        $m
    )) {
        return [$m[1], $m[2]];
    }

    // Format CSV : id,email,datetime (avec ou sans guillemets doubles)
    if (preg_match(
        '/^[0-9]+,"?([^,"]+@[^,"]+)"?,"?(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})"?/',
        $line,
        $m
    )) {
        return [$m[1], $m[2]];
    }

    return null;
}

// ── Parsing fichier → email → MAX(datetime) ───────────────────────────────────

$emailMap = []; // email => max datetime string

echo "{$mode}Lecture du fichier : {$file}\n";

$handle = fopen($file, 'r');
if ($handle === false) {
    fwrite(STDERR, "Impossible d'ouvrir le fichier.\n");
    exit(1);
}

$lineCount  = 0;
$matchCount = 0;

while (($line = fgets($handle)) !== false) {
    $lineCount++;
    $parsed = parseLine($line);
    if ($parsed === null) {
        continue;
    }
    [$email, $datetime] = $parsed;
    $email = strtolower(trim($email));
    $matchCount++;
    if (!isset($emailMap[$email]) || $datetime > $emailMap[$email]) {
        $emailMap[$email] = $datetime;
    }
}
fclose($handle);

echo "{$mode}Lignes lues : {$lineCount} — emails distincts extraits : " . count($emailMap) . " (sur {$matchCount} occurrences)\n\n";

if (empty($emailMap)) {
    echo "Aucune ligne correspondante trouvée. Vérifier le format du fichier.\n";
    echo "Formats acceptés :\n";
    echo "  SQL : (11845, 'email@exemple.fr', '2026-04-01 16:20:58')\n";
    echo "  TSV : 11845<TAB>email@exemple.fr<TAB>2026-04-01 16:20:58\n";
    echo "  CSV : 11845,email@exemple.fr,2026-04-01 16:20:58\n";
    echo "Vérifier les 3 premières lignes du fichier et s'assurer que col2=email, col3=datetime.\n";
    exit(0);
}

// ── Croisement BDD courante ───────────────────────────────────────────────────

$db = \Core\Database::getInstance();

$updated  = 0;
$skipped  = 0;
$notFound = 0;

foreach ($emailMap as $email => $backupDate) {
    $account = $db->fetchOne(
        'SELECT id, email, last_login_at FROM accounts WHERE email = ? AND deleted_at IS NULL LIMIT 1',
        [$email]
    );

    if ($account === null || $account === false) {
        $notFound++;
        echo "  NOT FOUND  {$email}\n";
        continue;
    }

    $currentDate = $account['last_login_at'];
    $accountId   = (int) $account['id'];

    // Mettre à jour seulement si la date backup est plus récente
    if ($currentDate !== null && $backupDate <= $currentDate) {
        $skipped++;
        echo "  SKIP       [{$accountId}] {$email} — actuel={$currentDate} >= backup={$backupDate}\n";
        continue;
    }

    $label = $currentDate === null ? 'NULL' : $currentDate;
    echo "  {$mode}UPDATE   [{$accountId}] {$email} — {$label} → {$backupDate}\n";

    if (!$dryRun) {
        $db->execute(
            'UPDATE accounts SET last_login_at = ? WHERE id = ?',
            [$backupDate, $accountId]
        );
    }
    $updated++;
}

// ── Bilan ─────────────────────────────────────────────────────────────────────

echo "\n{$mode}Bilan :\n";
echo "  Mis à jour  : {$updated}\n";
echo "  Ignorés     : {$skipped} (date actuelle déjà plus récente)\n";
echo "  Non trouvés : {$notFound} (email absent de accounts)\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Aucune modification appliquée. Relancer sans --dry-run pour exécuter.\n";
}
