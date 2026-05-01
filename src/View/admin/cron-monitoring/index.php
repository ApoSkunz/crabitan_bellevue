<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$runs        = $runs        ?? [];
$summary     = $summary     ?? [];
$page        = (int)        ($page ?? 1);
$perPage     = (int)        ($perPage ?? 25);
$totalPages  = (int)        ($totalPages ?? 1);
$totalRuns   = (int)        ($totalRuns ?? 0);
$perPageOpts = $perPageOpts ?? [25, 50, 100];

if (!function_exists('cronMonUrl')) {
    function cronMonUrl(int $p, int $perPage): string
    {
        return '/admin/monitoring-crons?' . http_build_query(['page' => $p, 'per_page' => $perPage]);
    }
}

$statusBadge = function (string $status): string {
    $map = [
        'success' => ['bg' => '#e6f4ea', 'color' => '#1a6b1a', 'label' => 'OK'],
        'error'   => ['bg' => '#fdecea', 'color' => '#c0392b', 'label' => 'ERR'],
        'running' => ['bg' => '#e8eefd', 'color' => '#2a4ab0', 'label' => 'EN COURS'],
        'skipped' => ['bg' => '#f4f0e6', 'color' => '#8a7060', 'label' => 'SKIP'],
    ];
    $cfg = $map[$status] ?? $map['skipped'];
    return '<span style="background:' . $cfg['bg'] . ';color:' . $cfg['color']
        . ';font-size:0.72rem;padding:2px 8px;border-radius:12px;letter-spacing:0.04em;">'
        . htmlspecialchars($cfg['label']) . '</span>';
};
?>

<div class="admin-page-header">
    <h1>Monitoring crons <small style="font-size:0.75rem;color:#8a7060;">(<?= $totalRuns ?>)</small></h1>
    <p class="admin-page-header__sub">Journal d'exécution des jobs cron · rétention 30&nbsp;jours</p>
</div>

<!-- Agrégat 24h -->
<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__header">
        <h2 style="font-size:0.85rem;letter-spacing:0.08em;text-transform:uppercase;color:#8a7060;margin:0;">
            Agrégat 24&nbsp;h par job
        </h2>
    </div>
    <div class="admin-card__body" style="padding:0;overflow-x:auto;">
        <?php if (empty($summary)) : ?>
            <p style="padding:1.5rem;color:#8a7060;font-size:0.875rem;">Aucun run cron dans les 24 dernières heures.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th style="text-align:right;">Runs</th>
                        <th style="text-align:right;">Succès</th>
                        <th style="text-align:right;">Erreurs</th>
                        <th style="text-align:right;">Actions</th>
                        <th style="text-align:right;">Durée moy.</th>
                        <th>Dernier run</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $row) : ?>
                        <tr>
                            <td><code style="font-size:0.8rem;"><?= htmlspecialchars($row['job_name']) ?></code></td>
                            <td style="text-align:right;"><?= (int) $row['runs'] ?></td>
                            <td style="text-align:right;color:#2d7a2d;"><?= (int) $row['success'] ?></td>
                            <td style="text-align:right;<?= (int) $row['errors'] > 0 ? 'color:#c0392b;font-weight:600;' : '' ?>"><?= (int) $row['errors'] ?></td>
                            <td style="text-align:right;"><?= (int) $row['actions_total'] ?></td>
                            <td style="text-align:right;font-size:0.8rem;color:#5a4a3a;"><?= number_format((float) $row['avg_duration_ms'], 0, ',', ' ') ?>&nbsp;ms</td>
                            <td style="font-size:0.8rem;color:#5a4a3a;"><?= htmlspecialchars($row['last_run'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<form method="GET" action="/admin/monitoring-crons" class="admin-filters" data-auto-submit style="margin-bottom:0.75rem;">
    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ($perPageOpts as $n) : ?>
            <option value="<?= (int) $n ?>" <?= $perPage === (int) $n ? 'selected' : '' ?>><?= (int) $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="admin-filters__btn">Appliquer</button></noscript>
</form>

<div class="admin-card">
    <div class="admin-card__body" style="padding:0;overflow-x:auto;">
        <?php if (empty($runs)) : ?>
            <p style="padding:1.5rem;color:#8a7060;font-size:0.875rem;">Aucun run disponible.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Démarré</th>
                        <th>Job</th>
                        <th style="text-align:center;">Statut</th>
                        <th style="text-align:right;">Durée</th>
                        <th style="text-align:right;">Actions</th>
                        <th>Dry-run</th>
                        <th>Extrait log</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($runs as $run) :
                        $duration = $run['duration_ms'] !== null ? number_format((float) $run['duration_ms'], 0, ',', ' ') . '&nbsp;ms' : '—';
                        $excerpt  = $run['log_output'] !== null ? mb_substr((string) $run['log_output'], 0, 160) : '';
                        $logTrunc = $run['log_output'] !== null && mb_strlen((string) $run['log_output']) > 160;
                    ?>
                        <tr>
                            <td style="font-size:0.8rem;white-space:nowrap;"><?= htmlspecialchars($run['started_at']) ?></td>
                            <td><code style="font-size:0.8rem;"><?= htmlspecialchars($run['job_name']) ?></code></td>
                            <td style="text-align:center;"><?= $statusBadge((string) $run['status']) ?></td>
                            <td style="text-align:right;font-size:0.8rem;"><?= $duration ?></td>
                            <td style="text-align:right;"><?= (int) $run['actions_count'] ?></td>
                            <td><?= $run['dry_run'] ? '<span style="color:#8a7060;font-size:0.75rem;">DRY</span>' : '' ?></td>
                            <td class="cron-log-cell" data-label="Log" style="font-size:0.75rem;color:#5a4a3a;max-width:320px;">
                                <div class="cron-log-excerpt"><?= htmlspecialchars($excerpt) ?><?= $logTrunc ? '&hellip;' : '' ?></div>
                                <?php if (!empty($run['error_message'])) : ?>
                                    <div class="cron-log-error"><?= htmlspecialchars(mb_substr((string) $run['error_message'], 0, 200)) ?></div>
                                <?php endif; ?>
                                <?php if ($logTrunc) : ?>
                                    <div><button type="button"
                                        class="cron-log-btn"
                                        data-log="<?= htmlspecialchars((string) $run['log_output'], ENT_QUOTES) ?>"
                                        data-job="<?= htmlspecialchars($run['job_name'], ENT_QUOTES) ?>"
                                        data-date="<?= htmlspecialchars($run['started_at'], ENT_QUOTES) ?>">
                                        Voir tout
                                    </button></div>
                                <?php endif; ?>
                            </td>
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
        <a href="<?= htmlspecialchars(cronMonUrl(1, $perPage)) ?>"
           class="admin-pagination__item<?= $prevDisabled ?>" aria-label="Première page">«</a>
        <a href="<?= htmlspecialchars(cronMonUrl(max(1, $page - 1), $perPage)) ?>"
           class="admin-pagination__item<?= $prevDisabled ?>" aria-label="Page précédente">‹</a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
            <a href="<?= htmlspecialchars(cronMonUrl($i, $perPage)) ?>"
               class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"
               aria-label="Page <?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= htmlspecialchars(cronMonUrl(min($totalPages, $page + 1), $perPage)) ?>"
           class="admin-pagination__item<?= $nextDisabled ?>" aria-label="Page suivante">›</a>
        <a href="<?= htmlspecialchars(cronMonUrl($totalPages, $perPage)) ?>"
           class="admin-pagination__item<?= $nextDisabled ?>" aria-label="Dernière page">»</a>
    </div>
    <?php endif; ?>
</div>

<!-- Modale log complet -->
<dialog id="cron-log-modal" aria-labelledby="cron-log-modal-title">
    <div class="cron-log-modal__panel">
        <div class="cron-log-modal__header">
            <div>
                <span id="cron-log-modal-title" class="cron-log-modal__job"></span>
                <span class="cron-log-modal__date"></span>
            </div>
            <button type="button" class="cron-log-modal__close" aria-label="Fermer">✕</button>
        </div>
        <pre class="cron-log-modal__body"></pre>
    </div>
</dialog>


<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    const modal   = document.getElementById('cron-log-modal');
    const body    = modal.querySelector('.cron-log-modal__body');
    const jobEl   = modal.querySelector('.cron-log-modal__job');
    const dateEl  = modal.querySelector('.cron-log-modal__date');
    const closeBtn = modal.querySelector('.cron-log-modal__close');

    function open(log, job, date) {
        body.textContent   = log;
        jobEl.textContent  = job;
        dateEl.textContent = date;
        modal.showModal();
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function close() {
        modal.close();
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.cron-log-btn');
        if (btn) open(btn.dataset.log, btn.dataset.job, btn.dataset.date);
    });

    closeBtn.addEventListener('click', close);
    /* clic sur le backdrop natif <dialog> */
    modal.addEventListener('click', function (e) {
        if (e.target === modal) close();
    });
    /* Escape natif du <dialog> remet aussi l'overflow */
    modal.addEventListener('close', function () {
        document.body.style.overflow = '';
    });
}());
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
