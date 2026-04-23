<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\AppLogsAdminController;
use Core\Database;
use Core\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable qui by-pass requireSuperAdmin() et neutralise view().
 * La logique de index() (chargement/filtre/pagination des logs) reste réelle.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableAppLogsAdminController extends AppLogsAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = [
        'id'   => 1,
        'role' => 'super_admin',
        'name' => 'Admin',
    ];

    /** @var array{template: string, data: array<string, mixed>}|null */
    public ?array $viewCall = null;

    /** @return array<string, mixed> */
    protected function requireSuperAdmin(): array
    {
        return $this->fakeAdmin;
    }

    /**
     * Capture le template et les données passés à la vue.
     *
     * @param string               $template
     * @param array<string, mixed> $data
     * @param int                  $httpStatus
     */
    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        $this->viewCall = ['template' => $template, 'data' => $data];
    }
}

/**
 * Tests unitaires pour AppLogsAdminController.
 *
 * Cible : 100% lignes (63 lignes) — couvre chargement JSON des logs (30 derniers jours),
 * filtres (level, search), pagination, whitelist per_page, clamp page.
 *
 * Les fichiers logs existants dans storage/logs sont déplacés dans un backup en setUp
 * puis restaurés en tearDown pour garantir l'isolation des fixtures.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class AppLogsAdminControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;
    private string $logsDir;
    private string $backupDir;

    /**
     * Prépare environnement : stub Database, backup des logs existants,
     * création du dossier logs si absent.
     */
    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';

        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $this->createStub(Database::class));

        $_SESSION = ['csrf' => 'valid'];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/logs';

        $this->logsDir   = ROOT_PATH . '/storage/logs';
        $this->backupDir = ROOT_PATH . '/storage/logs-backup-' . uniqid('', true);

        if (!is_dir($this->logsDir)) {
            mkdir($this->logsDir, 0775, true);
        }
        // Déplacer tous les app-*.log existants dans un backup pour isolation.
        mkdir($this->backupDir, 0775, true);
        foreach (glob($this->logsDir . '/app-*.log') ?: [] as $file) {
            rename($file, $this->backupDir . '/' . basename($file));
        }
    }

    /**
     * Restaure les fichiers logs d'origine et supprime les fixtures.
     */
    protected function tearDown(): void
    {
        // Supprime les fichiers fixtures posés par le test.
        foreach (glob($this->logsDir . '/app-*.log') ?: [] as $file) {
            @unlink($file);
        }
        // Restaure les logs d'origine depuis le backup.
        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir . '/app-*.log') ?: [] as $file) {
                rename($file, $this->logsDir . '/' . basename($file));
            }
            @rmdir($this->backupDir);
        }

        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Écrit un fichier log pour la date donnée avec les entrées JSON fournies.
     *
     * @param string                            $date    Date au format Y-m-d
     * @param array<int, array<string, mixed>>  $entries Entrées JSON à sérialiser
     * @param bool                              $addNoise Ajoute des lignes non-JSON en préfixe
     */
    private function writeLogFile(string $date, array $entries, bool $addNoise = false): void
    {
        $file  = $this->logsDir . '/app-' . $date . '.log';
        $lines = [];
        foreach ($entries as $entry) {
            if ($addNoise) {
                // Ligne texte (ignorée par loadRecentLogs car ne commence pas par '{')
                $lines[] = '[' . ($entry['timestamp'] ?? $date) . '] '
                    . ($entry['level'] ?? 'INFO') . ' — '
                    . ($entry['message'] ?? '');
            }
            $lines[] = json_encode($entry, JSON_UNESCAPED_UNICODE);
        }
        file_put_contents($file, implode("\n", $lines) . "\n");
    }

    /**
     * Happy path — aucun fichier log → pageSlice vide, totalPages = 1.
     */
    public function testIndexWithNoLogsReturnsEmpty(): void
    {
        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertNotNull($ctrl->viewCall);
        $this->assertSame('admin/app-logs/index', $ctrl->viewCall['template']);

        $data = $ctrl->viewCall['data'];
        $this->assertSame('app_logs', $data['adminSection']);
        $this->assertSame('Logs applicatifs', $data['pageTitle']);
        $this->assertSame([], $data['entries']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(50, $data['perPage']);
        $this->assertSame(1, $data['totalPages']);
        $this->assertSame(0, $data['totalEntries']);
        $this->assertSame([25, 50, 100], $data['perPageOpts']);
        $this->assertSame('', $data['level']);
        $this->assertSame('', $data['search']);
        $this->assertSame(30, $data['retentionDays']);
    }

    /**
     * Charge les entrées JSON et trie du plus récent au plus ancien.
     */
    public function testIndexLoadsAndSortsEntriesDesc(): void
    {
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', time() - 86400);

        $this->writeLogFile($today, [
            ['timestamp' => $today . ' 10:00:00', 'level' => 'INFO',    'message' => 'today'],
            ['timestamp' => $today . ' 11:00:00', 'level' => 'WARNING', 'message' => 'today-later'],
        ]);
        $this->writeLogFile($yesterday, [
            ['timestamp' => $yesterday . ' 09:00:00', 'level' => 'INFO', 'message' => 'yesterday'],
        ]);

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $data = $ctrl->viewCall['data'];
        $this->assertSame(3, $data['totalEntries']);
        $this->assertCount(3, $data['entries']);

        // Ordre décroissant : today-later > today > yesterday
        $this->assertSame('today-later', $data['entries'][0]['message']);
        $this->assertSame('today', $data['entries'][1]['message']);
        $this->assertSame('yesterday', $data['entries'][2]['message']);
    }

    /**
     * Filtre par level : seules les entrées ERROR doivent remonter.
     */
    public function testFilterByLevel(): void
    {
        $today = date('Y-m-d');
        $this->writeLogFile($today, [
            ['timestamp' => $today . ' 10:00:00', 'level' => 'INFO',  'message' => 'info-msg'],
            ['timestamp' => $today . ' 11:00:00', 'level' => 'ERROR', 'message' => 'error-msg'],
            ['timestamp' => $today . ' 12:00:00', 'level' => 'ERROR', 'message' => 'other-error'],
        ]);

        $_GET['level'] = 'ERROR';

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $data = $ctrl->viewCall['data'];
        $this->assertSame(2, $data['totalEntries']);
        $this->assertSame('ERROR', $data['level']);
        foreach ($data['entries'] as $entry) {
            $this->assertSame('ERROR', $entry['level']);
        }
    }

    /**
     * Filtre par texte de recherche (case-insensitive).
     */
    public function testFilterBySearch(): void
    {
        $today = date('Y-m-d');
        $this->writeLogFile($today, [
            ['timestamp' => $today . ' 10:00:00', 'level' => 'INFO', 'message' => 'Database connection established'],
            ['timestamp' => $today . ' 11:00:00', 'level' => 'INFO', 'message' => 'User login'],
            ['timestamp' => $today . ' 12:00:00', 'level' => 'ERROR', 'message' => 'DATABASE timeout'],
        ]);

        $_GET['search'] = 'database';

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $data = $ctrl->viewCall['data'];
        $this->assertSame(2, $data['totalEntries']);
        $this->assertSame('database', $data['search']);
    }

    /**
     * Filtres combinés (level + search) — intersection.
     */
    public function testFilterCombinedLevelAndSearch(): void
    {
        $today = date('Y-m-d');
        $this->writeLogFile($today, [
            ['timestamp' => $today . ' 10:00:00', 'level' => 'INFO',  'message' => 'database ok'],
            ['timestamp' => $today . ' 11:00:00', 'level' => 'ERROR', 'message' => 'database fail'],
            ['timestamp' => $today . ' 12:00:00', 'level' => 'ERROR', 'message' => 'unrelated'],
        ]);

        $_GET['level']  = 'ERROR';
        $_GET['search'] = 'database';

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->viewCall['data']['totalEntries']);
        $this->assertSame('database fail', $ctrl->viewCall['data']['entries'][0]['message']);
    }

    /**
     * per_page hors whitelist → clamp à 50.
     */
    public function testInvalidPerPageIsClamped(): void
    {
        $_GET['per_page'] = '999';
        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(50, $ctrl->viewCall['data']['perPage']);
    }

    /**
     * per_page=25 (whitelist) conservé.
     */
    public function testValidPerPageIsKept(): void
    {
        $_GET['per_page'] = '25';
        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->viewCall['data']['perPage']);
    }

    /**
     * Pagination : 60 entrées + per_page=25 → 3 pages ; page 2 contient les entrées 25..49.
     */
    public function testPaginationPageSlice(): void
    {
        $today = date('Y-m-d');
        $entries = [];
        // 60 entrées à timestamps décroissants pour ordre stable.
        for ($i = 0; $i < 60; $i++) {
            $entries[] = [
                // pad pour tri alphanumérique correct
                'timestamp' => sprintf('%s %02d:00:00', $today, (59 - $i) % 24),
                'level'     => 'INFO',
                'message'   => 'entry-' . $i,
                'idx'       => $i,
            ];
        }
        $this->writeLogFile($today, $entries);

        $_GET['per_page'] = '25';
        $_GET['page']     = '2';

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $data = $ctrl->viewCall['data'];
        $this->assertSame(60, $data['totalEntries']);
        $this->assertSame(3, $data['totalPages']); // ceil(60/25) = 3
        $this->assertSame(2, $data['page']);
        $this->assertCount(25, $data['entries']);
    }

    /**
     * Page demandée > totalPages → clamp à totalPages.
     */
    public function testPageClampedToTotalPages(): void
    {
        $today = date('Y-m-d');
        $this->writeLogFile($today, [
            ['timestamp' => $today . ' 10:00:00', 'level' => 'INFO', 'message' => 'single'],
        ]);

        $_GET['page'] = '99';

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->viewCall['data']['totalPages']);
        $this->assertSame(1, $ctrl->viewCall['data']['page']);
    }

    /**
     * Lignes non-JSON (préfixe texte du format "[date] LEVEL — msg") ignorées,
     * seules les entrées JSON sont chargées.
     */
    public function testSkipsNonJsonLines(): void
    {
        $today = date('Y-m-d');
        $this->writeLogFile($today, [
            ['timestamp' => $today . ' 10:00:00', 'level' => 'INFO', 'message' => 'good'],
        ], true); // addNoise = true

        // Ajouter manuellement une ligne vide et une ligne invalide
        file_put_contents(
            $this->logsDir . '/app-' . $today . '.log',
            "[2026-04-22] WARN — texte\n\n{\"timestamp\":\"" . $today . " 12:00:00\",\"level\":\"ERROR\",\"message\":\"err\"}\n",
            FILE_APPEND
        );

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $data = $ctrl->viewCall['data'];
        // 1 entrée JSON du writeLogFile + 1 ligne JSON ajoutée = 2 (lignes texte ignorées)
        $this->assertSame(2, $data['totalEntries']);
    }

    /**
     * Fichier log illisible : json_decode retourne non-array (ex: string "null"),
     * entrée ignorée.
     */
    public function testSkipsInvalidJsonEntries(): void
    {
        $today = date('Y-m-d');
        // Ligne '{}' = array vide valide (1 entrée)
        // Ligne '"string"' — commence par '"' donc ignorée (premier char != '{')
        // Ligne '{broken' — commence par '{' mais json_decode = null → ignoré
        // Ligne '{}' encore → 1 entrée
        file_put_contents(
            $this->logsDir . '/app-' . $today . '.log',
            "{}\n\"string\"\n{broken\n{\"ok\":true}\n"
        );

        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        // 2 entrées valides : {} et {"ok":true}
        $this->assertSame(2, $ctrl->viewCall['data']['totalEntries']);
    }

    /**
     * Page zéro demandée → clamp à 1 via max(1, …).
     */
    public function testPageZeroIsClampedToOne(): void
    {
        $_GET['page'] = '0';
        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->viewCall['data']['page']);
    }

    /**
     * search avec espaces est trim() avant d'être transmis/utilisé.
     */
    public function testSearchIsTrimmed(): void
    {
        $_GET['search'] = '  foo  ';
        $ctrl = new TestableAppLogsAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame('foo', $ctrl->viewCall['data']['search']);
    }

    /**
     * Si storage/logs n'existe pas, loadRecentLogs() retourne un array vide.
     * Couvre L70 (early return sur !is_dir).
     */
    public function testMissingLogsDirectoryReturnsEmpty(): void
    {
        // Déplacer temporairement le dossier logs pour simuler son absence.
        $hidden = $this->logsDir . '-hidden-' . uniqid();
        rename($this->logsDir, $hidden);

        try {
            $ctrl = new TestableAppLogsAdminController(new Request());
            $ctrl->index([]);

            $this->assertSame([], $ctrl->viewCall['data']['entries']);
            $this->assertSame(0, $ctrl->viewCall['data']['totalEntries']);
        } finally {
            rename($hidden, $this->logsDir);
        }
    }
}
