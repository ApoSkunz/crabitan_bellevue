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
        'success' => 'cron-badge--success',
        'error'   => 'cron-badge--error',
        'running' => 'cron-badge--running',
        'skipped' => 'cron-badge--skipped',
    ];
    $labels = [
        'success' => 'OK',
        'error'   => 'ERR',
        'running' => 'EN COURS',
        'skipped' => 'SKIP',
    ];
    $cls   = $map[$status]   ?? $map['skipped'];
    $label = $labels[$status] ?? $labels['skipped'];
    return '<span class="cron-badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
};
?>

<div class="admin-page-header">
    <h1>Monitoring crons <small class="u-text-2xs u-text-soft">(<?= $totalRuns ?>)</small></h1>
    <p class="admin-page-header__sub">Journal d'exécution des jobs cron · rétention 30&nbsp;jours</p>
</div>

<!-- Agrégat 24h -->
<div class="admin-card u-mb-6">
    <div class="admin-card__header">
        <h2 class="u-text-85 u-tracking-wider u-uppercase u-text-soft u-m-0">
            Agrégat 24&nbsp;h par job
        </h2>
    </div>
    <div class="admin-card__body u-p-0 u-overflow-x-auto">
        <?php if (empty($summary)) : ?>
            <p class="u-p-6 u-text-soft u-text-87">Aucun run cron dans les 24 dernières heures.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th class="u-text-right">Runs</th>
                        <th class="u-text-right">Succès</th>
                        <th class="u-text-right">Erreurs</th>
                        <th class="u-text-right">Actions</th>
                        <th class="u-text-right">Durée moy.</th>
                        <th>Dernier run</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $row) : ?>
                        <tr>
                            <td><code class="u-text-sm"><?= htmlspecialchars($row['job_name']) ?></code></td>
                            <td class="u-text-right"><?= (int) $row['runs'] ?></td>
                            <td class="u-text-right u-text-success"><?= (int) $row['success'] ?></td>
                            <td class="u-text-right<?= (int) $row['errors'] > 0 ? ' u-text-error u-font-semibold' : '' ?>"><?= (int) $row['errors'] ?></td>
                            <td class="u-text-right"><?= (int) $row['actions_total'] ?></td>
                            <td class="u-text-right u-text-sm u-text-brown-warm"><?= number_format((float) $row['avg_duration_ms'], 0, ',', ' ') ?>&nbsp;ms</td>
                            <td class="u-text-sm u-text-brown-warm"><?= htmlspecialchars($row['last_run'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<form method="GET" action="/admin/monitoring-crons" class="admin-filters u-mb-3" data-auto-submit>
    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ($perPageOpts as $n) : ?>
            <option value="<?= (int) $n ?>" <?= $perPage === (int) $n ? 'selected' : '' ?>><?= (int) $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="admin-filters__btn">Appliquer</button></noscript>
</form>

<div class="admin-card">
    <div class="admin-card__body u-p-0 u-overflow-x-auto">
        <?php if (empty($runs)) : ?>
            <p class="u-p-6 u-text-soft u-text-87">Aucun run disponible.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Démarré</th>
                        <th>Job</th>
                        <th class="text-center">Statut</th>
                        <th class="u-text-right">Durée</th>
                        <th class="u-text-right">Actions</th>
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
                            <td class="u-text-sm u-nowrap"><?= htmlspecialchars($run['started_at']) ?></td>
                            <td><code class="u-text-sm"><?= htmlspecialchars($run['job_name']) ?></code></td>
                            <td class="text-center"><?= $statusBadge((string) $run['status']) ?></td>
                            <td class="u-text-right u-text-sm"><?= $duration ?></td>
                            <td class="u-text-right"><?= (int) $run['actions_count'] ?></td>
                            <td><?= $run['dry_run'] ? '<span class="u-text-soft u-text-2xs">DRY</span>' : '' ?></td>
                            <td class="cron-log-cell u-text-2xs u-text-brown-warm cron-log-cell--maxw" data-label="Log">
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
