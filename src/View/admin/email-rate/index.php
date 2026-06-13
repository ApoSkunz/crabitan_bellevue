<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$logs        = $logs        ?? [];
$summary     = $summary     ?? [];
$page        = (int)        ($page ?? 1);
$perPage     = (int)        ($perPage ?? 25);
$totalPages  = (int)        ($totalPages ?? 1);
$totalLogs   = (int)        ($totalLogs ?? 0);
$perPageOpts = $perPageOpts ?? [25, 50, 100];

if (!function_exists('emailRateUrl')) {
    function emailRateUrl(int $p, int $perPage): string
    {
        return '/admin/emails-rate?' . http_build_query(['page' => $p, 'per_page' => $perPage]);
    }
}

$typeLabelsFr = [
    'contact'            => 'Contact',
    'register'           => 'Inscription',
    'reset_password'     => 'Réinit. mot de passe',
    'email_change'       => 'Changement email',
    'order'              => 'Commande',
    'invoice'            => 'Facture',
    'rgpd_export'        => 'Export RGPD',
    'rgpd_deletion'      => 'Suppression RGPD',
    'admin_action'       => 'Action admin',
    'cron_newsletter'    => 'Newsletter (cron)',
    'cron_inactivity'    => 'Inactivité (cron)',
    'cron_reminder'      => 'Relance (cron)',
    'cron_review'        => 'Avis (cron)',
    'cron_abandoned_cart' => 'Panier abandonné (cron)',
    'cron_post_purchase' => 'Post-achat (cron)',
    'other'              => 'Autre',
];
?>

<div class="admin-page-header">
    <h1>Monitoring emails — dernières 25h <small class="u-text-2xs admin-subcount">(<?= $totalLogs ?>)</small></h1>
    <p class="admin-page-header__sub">
        Journal des envois par canal SMTP · purge automatique après 25&nbsp;heures
    </p>
</div>

<!-- Agrégat par canal -->
<div class="admin-card u-mb-6">
    <div class="admin-card__header">
        <h2 class="admin-card-section-title">
            Agrégat par canal (25&nbsp;h)
        </h2>
    </div>
    <div class="admin-card__body u-p-0">
        <?php if (empty($summary)) : ?>
            <p class="admin-empty-state">Aucun email envoyé dans les 25 dernières heures.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Adresse expéditrice</th>
                        <th class="u-text-right">Total (25h)</th>
                        <th class="u-text-right">Dernière heure / 500</th>
                        <th class="u-text-right">Succès</th>
                        <th class="u-text-right">Erreurs</th>
                        <th>Premier envoi</th>
                        <th>Dernier envoi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $row) :
                        $lastHour = (int) ($row['sent_last_hour'] ?? 0);
                        $pct      = (int) min(100, round(($lastHour / 500) * 100));
                        $warn     = $lastHour >= 400;
                        $danger   = $lastHour >= 475;
                        if ($danger) {
                            $textColorClass = 'u-text-error';
                            $barColor       = '#c0392b';
                        } elseif ($warn) {
                            $textColorClass = 'u-text-amber';
                            $barColor       = '#d97706';
                        } else {
                            $textColorClass = '';
                            $barColor       = '#2d7a2d';
                        }
                    ?>
                        <tr>
                            <td data-label="Adresse"><?= htmlspecialchars($row['from_address']) ?></td>
                            <td data-label="Total" class="u-text-right u-font-semibold"><?= (int) $row['total_sent'] ?></td>
                            <td data-label="Dernière heure" class="u-text-right admin-rate-cell">
                                <span class="u-font-semibold u-nowrap <?= $textColorClass ?>"><?= $lastHour ?> / 500</span>
                                <div class="admin-rate-bar-track">
                                    <div class="admin-rate-bar-fill admin-rate-bar-fill--<?= $danger ? 'danger' : ($warn ? 'warn' : 'ok') ?>" data-pct="<?= $pct ?>"></div>
                                </div>
                            </td>
                            <td data-label="Succès" class="u-text-right u-text-green"><?= (int) $row['success_count'] ?></td>
                            <td data-label="Erreurs" class="u-text-right<?= (int) $row['error_count'] > 0 ? ' u-text-error u-font-semibold' : '' ?>">
                                <?= (int) $row['error_count'] ?>
                            </td>
                            <td data-label="Premier" class="u-text-sm u-text-brown"><?= htmlspecialchars($row['first_at'] ?? '—') ?></td>
                            <td data-label="Dernier" class="u-text-sm u-text-brown"><?= htmlspecialchars($row['last_at'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Filtre pagination -->
<form method="GET" action="/admin/emails-rate" class="admin-filters u-mb-3" data-auto-submit>
    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ($perPageOpts as $n) : ?>
            <option value="<?= (int) $n ?>" <?= $perPage === (int) $n ? 'selected' : '' ?>><?= (int) $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="admin-filters__btn">Appliquer</button></noscript>
</form>

<!-- Logs paginés -->
<div class="admin-card">
    <div class="admin-card__header">
        <h2 class="admin-card-section-title">
            Journal des envois
        </h2>
    </div>
    <div class="admin-card__body u-p-0">
        <?php if (empty($logs)) : ?>
            <p class="admin-empty-state">Aucun log disponible.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>De</th>
                        <th>À</th>
                        <th>Sujet</th>
                        <th class="text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <?php
                            $isOk      = (bool) $log['success'];
                            $subject   = htmlspecialchars(mb_substr($log['subject'] ?? '', 0, 60));
                            $truncated = mb_strlen($log['subject'] ?? '') > 60;
                            $type      = (string) ($log['type'] ?? 'other');
                        ?>
                        <tr>
                            <td class="u-text-sm u-nowrap"><?= htmlspecialchars($log['sent_at']) ?></td>
                            <td class="u-text-xxs"><span class="admin-type-badge"><?= htmlspecialchars($typeLabelsFr[$type] ?? $type) ?></span></td>
                            <td class="u-text-sm"><?= htmlspecialchars($log['from_address']) ?></td>
                            <td class="u-text-sm"><?= htmlspecialchars($log['to_address']) ?></td>
                            <td class="u-text-sm">
                                <?= $subject ?><?= $truncated ? '&hellip;' : '' ?>
                                <?php if (!$isOk && !empty($log['error_message'])) : ?>
                                    <br>
                                    <span class="u-text-xxs u-text-error">
                                        <?= htmlspecialchars(mb_substr($log['error_message'], 0, 100)) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isOk) : ?>
                                    <span class="admin-status-badge admin-status-badge--ok">OK</span>
                                <?php else : ?>
                                    <span class="admin-status-badge admin-status-badge--err">ERR</span>
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
        <a href="<?= htmlspecialchars(emailRateUrl(1, $perPage)) ?>"
           class="admin-pagination__item<?= $prevDisabled ?>" aria-label="Première page">«</a>
        <a href="<?= htmlspecialchars(emailRateUrl(max(1, $page - 1), $perPage)) ?>"
           class="admin-pagination__item<?= $prevDisabled ?>">‹</a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
            <a href="<?= htmlspecialchars(emailRateUrl($i, $perPage)) ?>"
               class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= htmlspecialchars(emailRateUrl(min($totalPages, $page + 1), $perPage)) ?>"
           class="admin-pagination__item<?= $nextDisabled ?>">›</a>
        <a href="<?= htmlspecialchars(emailRateUrl($totalPages, $perPage)) ?>"
           class="admin-pagination__item<?= $nextDisabled ?>" aria-label="Dernière page">»</a>
    </div>
    <?php endif; ?>
</div>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
document.querySelectorAll('[data-pct]').forEach(function (el) {
    el.style.width = el.dataset.pct + '%';
});
</script>
<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
