<?php

declare(strict_types=1);

namespace Controller\Admin;

/**
 * Consultation des logs applicatifs (storage/logs/app-YYYY-MM-DD.log) sur 30 jours.
 * Filtre niveaux, pagination, recherche texte.
 * Accès super_admin uniquement.
 */
class AppLogsAdminController extends AdminController
{
    private const ALLOWED_PER_PAGE = [25, 50, 100];
    private const RETENTION_DAYS   = 30;

    /**
     * Affiche la page des logs applicatifs avec pagination + filtre niveau.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        $perPage = (int) ($_GET['per_page'] ?? 50);
        if (!in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = 50;
        }
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $level     = (string) ($_GET['level'] ?? '');
        $search    = trim((string) ($_GET['search'] ?? ''));

        $entries = $this->loadRecentLogs();
        $entries = $this->filterEntries($entries, $level, $search);

        $total      = count($entries);
        $pages      = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = min($page, $pages);
        $offset     = ($page - 1) * $perPage;
        $pageSlice  = array_slice($entries, $offset, $perPage);

        $this->view('admin/app-logs/index', [
            'adminUser'     => $adminUser,
            'adminSection'  => 'app_logs',
            'pageTitle'     => 'Logs applicatifs',
            'breadcrumbs'   => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Logs applicatifs']],
            'entries'       => $pageSlice,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => $pages,
            'totalEntries'  => $total,
            'perPageOpts'   => self::ALLOWED_PER_PAGE,
            'level'         => $level,
            'search'        => $search,
            'retentionDays' => self::RETENTION_DAYS,
        ]);
    }

    /**
     * Charge les entrées JSON des logs sur les N derniers jours.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadRecentLogs(): array
    {
        $dir     = ROOT_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            return [];
        }
        $entries = [];
        for ($i = 0; $i < self::RETENTION_DAYS; $i++) {
            $date = date('Y-m-d', time() - ($i * 86400));
            $file = $dir . '/app-' . $date . '.log';
            if (!is_file($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                // Chaque entrée = bloc "[date] LEVEL — msg\n{json}". On cible les JSON uniquement.
                if ($line === '' || $line[0] !== '{') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $entries[] = $decoded;
                }
            }
        }
        // Trier du plus récent au plus ancien
        usort($entries, static function (array $a, array $b): int {
            return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
        });
        return $entries;
    }

    /**
     * Filtre les entrées par niveau et recherche texte.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function filterEntries(array $entries, string $level, string $search): array
    {
        return array_values(array_filter($entries, static function (array $e) use ($level, $search): bool {
            if ($level !== '' && (string) ($e['level'] ?? '') !== $level) {
                return false;
            }
            if ($search !== '') {
                $haystack = mb_strtolower(json_encode($e) ?: '');
                if (!str_contains($haystack, mb_strtolower($search))) {
                    return false;
                }
            }
            return true;
        }));
    }
}
