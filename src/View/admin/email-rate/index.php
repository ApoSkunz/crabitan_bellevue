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
?>

<div class="admin-page-header">
    <h1>Monitoring emails — dernières 25h <small style="font-size:0.75rem;color:#8a7060;">(<?= $totalLogs ?>)</small></h1>
    <p class="admin-page-header__sub">
        Journal des envois par canal SMTP · purge automatique après 25&nbsp;heures
    </p>
</div>

<!-- Agrégat par canal -->
<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__header">
        <h2 style="font-size:0.85rem;letter-spacing:0.08em;text-transform:uppercase;color:#8a7060;margin:0;">
            Agrégat par canal (25&nbsp;h)
        </h2>
    </div>
    <div class="admin-card__body" style="padding:0;">
        <?php if (empty($summary)) : ?>
            <p style="padding:1.5rem;color:#8a7060;font-size:0.875rem;">Aucun email envoyé dans les 25 dernières heures.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Adresse expéditrice</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Succès</th>
                        <th style="text-align:right;">Erreurs</th>
                        <th>Premier envoi</th>
                        <th>Dernier envoi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $row) : ?>
                        <tr>
                            <td><?= htmlspecialchars($row['from_address']) ?></td>
                            <td style="text-align:right;font-weight:600;"><?= (int) $row['total_sent'] ?></td>
                            <td style="text-align:right;color:#2d7a2d;"><?= (int) $row['success_count'] ?></td>
                            <td style="text-align:right;<?= (int) $row['error_count'] > 0 ? 'color:#c0392b;font-weight:600;' : '' ?>">
                                <?= (int) $row['error_count'] ?>
                            </td>
                            <td style="font-size:0.8rem;color:#5a4a3a;"><?= htmlspecialchars($row['first_at'] ?? '—') ?></td>
                            <td style="font-size:0.8rem;color:#5a4a3a;"><?= htmlspecialchars($row['last_at'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Filtre pagination -->
<form method="GET" action="/admin/emails-rate" class="admin-filters" data-auto-submit style="margin-bottom:0.75rem;">
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
        <h2 style="font-size:0.85rem;letter-spacing:0.08em;text-transform:uppercase;color:#8a7060;margin:0;">
            Journal des envois
        </h2>
    </div>
    <div class="admin-card__body" style="padding:0;">
        <?php if (empty($logs)) : ?>
            <p style="padding:1.5rem;color:#8a7060;font-size:0.875rem;">Aucun log disponible.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>De</th>
                        <th>À</th>
                        <th>Sujet</th>
                        <th style="text-align:center;">Statut</th>
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
                            <td style="font-size:0.8rem;white-space:nowrap;"><?= htmlspecialchars($log['sent_at']) ?></td>
                            <td style="font-size:0.72rem;"><span style="background:#f3ede0;color:#5a4a3a;padding:2px 8px;border-radius:12px;letter-spacing:0.04em;"><?= htmlspecialchars($type) ?></span></td>
                            <td style="font-size:0.8rem;"><?= htmlspecialchars($log['from_address']) ?></td>
                            <td style="font-size:0.8rem;"><?= htmlspecialchars($log['to_address']) ?></td>
                            <td style="font-size:0.8rem;">
                                <?= $subject ?><?= $truncated ? '&hellip;' : '' ?>
                                <?php if (!$isOk && !empty($log['error_message'])) : ?>
                                    <br>
                                    <span style="color:#c0392b;font-size:0.72rem;">
                                        <?= htmlspecialchars(mb_substr($log['error_message'], 0, 100)) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($isOk) : ?>
                                    <span style="background:#e6f4ea;color:#1a6b1a;font-size:0.72rem;padding:2px 8px;border-radius:12px;letter-spacing:0.04em;">OK</span>
                                <?php else : ?>
                                    <span style="background:#fdecea;color:#c0392b;font-size:0.72rem;padding:2px 8px;border-radius:12px;letter-spacing:0.04em;">ERR</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1) : ?>
    <div class="admin-pagination">
        <a href="<?= htmlspecialchars(emailRateUrl(1, $perPage)) ?>"
           class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>" aria-label="Première page">«</a>
        <a href="<?= htmlspecialchars(emailRateUrl(max(1, $page - 1), $perPage)) ?>"
           class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
            <a href="<?= htmlspecialchars(emailRateUrl($i, $perPage)) ?>"
               class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= htmlspecialchars(emailRateUrl(min($totalPages, $page + 1), $perPage)) ?>"
           class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        <a href="<?= htmlspecialchars(emailRateUrl($totalPages, $perPage)) ?>"
           class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>" aria-label="Dernière page">»</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
