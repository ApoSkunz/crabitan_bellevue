<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$entries      = $entries      ?? [];
$page         = (int)         ($page ?? 1);
$perPage      = (int)         ($perPage ?? 50);
$totalPages   = (int)         ($totalPages ?? 1);
$totalEntries = (int)         ($totalEntries ?? 0);
$perPageOpts  = $perPageOpts  ?? [25, 50, 100];
$level        = (string)      ($level ?? '');
$search       = (string)      ($search ?? '');
$retention    = (int)         ($retentionDays ?? 30);

if (!function_exists('appLogsUrl')) {
    function appLogsUrl(int $p, int $perPage, string $level, string $search): string
    {
        $q = ['page' => $p, 'per_page' => $perPage];
        if ($level !== '') {
            $q['level'] = $level;
        }
        if ($search !== '') {
            $q['search'] = $search;
        }
        return '/admin/logs-app?' . http_build_query($q);
    }
}

$levels = ['EXCEPTION', 'E_ERROR', 'E_WARNING', 'E_NOTICE', 'INFO'];
$levelColors = [
    'EXCEPTION' => ['#fdecea', '#c0392b'],
    'E_ERROR'   => ['#fdecea', '#c0392b'],
    'E_WARNING' => ['#fff4e5', '#9a6a07'],
    'E_NOTICE'  => ['#eaf2fd', '#1d5db5'],
    'INFO'      => ['#eaf7ea', '#2d7a2d'],
];
?>

<div class="admin-page-header">
    <h1>Logs applicatifs <small class="u-text-2xs admin-subcount">(<?= $totalEntries ?> entrées / <?= $retention ?>&nbsp;j)</small></h1>
    <p class="admin-page-header__sub">
        Rétention <?= $retention ?>&nbsp;jours · JSON consolidé depuis <code>storage/logs/app-YYYY-MM-DD.log</code>
    </p>
</div>

<form method="GET" action="/admin/logs-app" class="admin-filters" data-auto-submit>
    <select name="level" class="admin-filters__select" aria-label="Filtrer par niveau">
        <option value="">Tous niveaux</option>
        <?php foreach ($levels as $lv) : ?>
            <option value="<?= htmlspecialchars($lv) ?>" <?= $level === $lv ? 'selected' : '' ?>><?= htmlspecialchars($lv) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="search" class="admin-filters__input"
           aria-label="Recherche texte"
           placeholder="Recherche texte…"
           value="<?= htmlspecialchars($search) ?>">
    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ($perPageOpts as $n) : ?>
            <option value="<?= (int) $n ?>" <?= $perPage === (int) $n ? 'selected' : '' ?>><?= (int) $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="admin-filters__btn">Filtrer</button></noscript>
    <a href="/admin/logs-app" class="admin-btn admin-btn--dark admin-btn--sm">Réinitialiser</a>
</form>

<div class="admin-card">
    <div class="admin-card__body u-p-0">
        <?php if (empty($entries)) : ?>
            <p class="admin-empty-state">Aucun log disponible.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Horodatage</th>
                        <th class="text-center">Niveau</th>
                        <th>Message</th>
                        <th>Fichier</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) :
                        $lvl   = (string) ($entry['level']   ?? '—');
                        $col   = $levelColors[$lvl] ?? ['#f3ede0', '#5a4a3a'];
                        $msg   = (string) ($entry['message'] ?? '');
                        $file  = (string) ($entry['file']    ?? '');
                        $line  = (int)    ($entry['line']    ?? 0);
                        $url   = (string) ($entry['url']     ?? '');
                        $ts    = (string) ($entry['timestamp'] ?? '');
                        $short = mb_strlen($msg) > 200 ? mb_substr($msg, 0, 200) . '…' : $msg;
                        if ($file === '') {
                            $fileShort = '—';
                        } else {
                            $fileShort = basename($file);
                            if ($line > 0) {
                                $fileShort .= ':' . $line;
                            }
                        }
                    ?>
                    <tr>
                        <td class="u-text-78 u-nowrap u-text-brown"><?= htmlspecialchars($ts) ?></td>
                        <td class="text-center">
                            <span class="admin-log-level-badge admin-log-level-badge--<?= strtolower(str_replace('_', '-', $lvl)) ?>"><?= htmlspecialchars($lvl) ?></span>
                        </td>
                        <td class="u-text-sm-plus"><?= htmlspecialchars($short) ?></td>
                        <td class="u-text-2xs u-text-brown" title="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($fileShort) ?></td>
                        <td class="u-text-2xs u-text-brown"><?= htmlspecialchars(mb_substr($url, 0, 60)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1) :
        $disabledSuffix = ' disabled';
        $prevDisabled   = $page <= 1 ? $disabledSuffix : '';
        $nextDisabled   = $page >= $totalPages ? $disabledSuffix : '';
        ?>
    <div class="admin-pagination">
        <a href="<?= htmlspecialchars(appLogsUrl(1, $perPage, $level, $search)) ?>"
           class="admin-pagination__item<?= $prevDisabled ?>" aria-label="Première page">«</a>
        <a href="<?= htmlspecialchars(appLogsUrl(max(1, $page - 1), $perPage, $level, $search)) ?>"
           class="admin-pagination__item<?= $prevDisabled ?>">‹</a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
            <a href="<?= htmlspecialchars(appLogsUrl($i, $perPage, $level, $search)) ?>"
               class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= htmlspecialchars(appLogsUrl(min($totalPages, $page + 1), $perPage, $level, $search)) ?>"
           class="admin-pagination__item<?= $nextDisabled ?>">›</a>
        <a href="<?= htmlspecialchars(appLogsUrl($totalPages, $perPage, $level, $search)) ?>"
           class="admin-pagination__item<?= $nextDisabled ?>" aria-label="Dernière page">»</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
