<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Journal d'audit</h1>
    <p class="admin-page-header__sub">
        Journal immuable — rétention 3 ans (Art. 5.2 RGPD accountability)
    </p>
</div>

<!-- ================================================================
     Filtres + export CSV
================================================================ -->
<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__body">
        <form method="GET" action="/admin/logs" class="admin-filters">
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Admin (ID)</div>
                <input
                    type="number"
                    name="admin_id"
                    value="<?= (int) ($filters['admin_id'] ?? 0) ?: '' ?>"
                    min="1"
                    class="admin-filters__select"
                    style="width:90px;"
                    placeholder="Tous"
                    aria-label="ID admin"
                >
            </div>
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Action</div>
                <input
                    type="text"
                    name="action"
                    value="<?= htmlspecialchars((string) ($filters['action'] ?? '')) ?>"
                    class="admin-filters__select"
                    style="width:200px;"
                    placeholder="ex: order.status_updated"
                    aria-label="Type d'action"
                >
            </div>
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Du</div>
                <input
                    type="date"
                    name="date_from"
                    value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>"
                    class="admin-filters__select"
                    aria-label="Date de début"
                >
            </div>
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Au</div>
                <input
                    type="date"
                    name="date_to"
                    value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>"
                    class="admin-filters__select"
                    aria-label="Date de fin"
                >
            </div>
            <div>
                <div style="font-size:0.72rem;color:#8a7060;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;">Par page</div>
                <select name="per_page" class="admin-filters__select" aria-label="Résultats par page">
                    <?php foreach ([25, 50, 100] as $n) : ?>
                        <option value="<?= $n ?>" <?= (int) $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <div style="font-size:0.72rem;color:transparent;margin-bottom:3px;">.</div>
                <button type="submit" class="admin-filters__btn">Filtrer</button>
            </div>
            <div>
                <div style="font-size:0.72rem;color:transparent;margin-bottom:3px;">.</div>
                <a href="/admin/logs"
                   style="display:inline-block;padding:0.42rem 1rem;font-size:0.82rem;letter-spacing:0.06em;border:1px solid rgba(8,8,8,0.2);color:#1a1208;text-decoration:none;">
                    Réinitialiser
                </a>
            </div>
        </form>

        <?php
        $exportParams = array_filter([
            'admin_id'  => $filters['admin_id']  ?? null,
            'action'    => $filters['action']    ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to'   => $filters['date_to']   ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $exportUrl = '/admin/logs/export' . ($exportParams !== [] ? '?' . http_build_query($exportParams) : '');
        ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(8,8,8,0.06);">
            <a href="<?= htmlspecialchars($exportUrl) ?>"
               class="admin-btn admin-btn--primary"
               title="Télécharger le journal au format CSV">
                ↓ Exporter CSV
            </a>
            <span style="font-size:0.75rem;color:var(--color-text-muted);margin-left:0.75rem;">
                <?= number_format($total, 0, ',', ' ') ?> entrée<?= $total > 1 ? 's' : '' ?>
            </span>
        </div>
    </div>
</div>

<!-- ================================================================
     Tableau du journal
================================================================ -->
<div class="admin-card">
    <div class="admin-card__header">
        <h2>
            <?= number_format($total, 0, ',', ' ') ?> entrée<?= $total > 1 ? 's' : '' ?>
        </h2>
    </div>
    <div class="admin-card__body" style="padding:0;">
        <?php if (empty($logs)) : ?>
            <p style="padding:1.5rem;color:var(--color-text-muted);text-align:center;">
                Aucune entrée dans le journal pour ces critères.
            </p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Cible</th>
                        <th>ID cible</th>
                        <th>Détails</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td style="font-size:0.78rem;white-space:nowrap;color:var(--color-text-muted);">
                                <?= date('d/m/Y H:i:s', strtotime((string) $log['created_at'])) ?>
                            </td>
                            <td style="font-size:0.8rem;">
                                <?= htmlspecialchars((string) ($log['admin_name'] ?? '')) ?><br>
                                <span style="font-size:0.72rem;color:var(--color-text-muted);">
                                    #<?= (int) $log['admin_id'] ?>
                                    <?php if (!empty($log['admin_email'])) : ?>
                                        &middot; <?= htmlspecialchars((string) $log['admin_email']) ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <code style="font-size:0.75rem;background:rgba(8,8,8,0.04);padding:2px 5px;border-radius:3px;">
                                    <?= htmlspecialchars((string) $log['action']) ?>
                                </code>
                            </td>
                            <td style="font-size:0.8rem;">
                                <?= htmlspecialchars((string) $log['target_type']) ?>
                            </td>
                            <td style="font-size:0.8rem;text-align:center;">
                                <?= $log['target_id'] !== null ? (int) $log['target_id'] : '<span style="color:var(--color-text-muted);">—</span>' ?>
                            </td>
                            <td style="font-size:0.75rem;max-width:260px;word-break:break-word;">
                                <?php if (!empty($log['details'])) : ?>
                                    <?php
                                    $decoded = json_decode((string) $log['details'], true);
                                    if (is_array($decoded)) {
                                        echo '<ul style="margin:0;padding-left:1rem;list-style:disc;">';
                                        foreach ($decoded as $k => $v) {
                                            echo '<li><strong>' . htmlspecialchars((string) $k) . '</strong>: '
                                                . htmlspecialchars((string) (is_array($v) ? json_encode($v) : $v)) . '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<span style="color:var(--color-text-muted);">' . htmlspecialchars((string) $log['details']) . '</span>';
                                    }
                                    ?>
                                <?php else : ?>
                                    <span style="color:var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem;font-family:monospace;">
                                <?= htmlspecialchars((string) $log['ip_address']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $pages = max(1, (int) ceil($total / $perPage));
            if ($pages > 1) : // NOSONAR — php:S3973 : syntaxe alternative PHP template intentionnelle
            ?>
            <div class="admin-pagination">
                <?php
                $baseParams = array_filter([
                    'admin_id'  => $filters['admin_id']  ?? null,
                    'action'    => $filters['action']    ?? null,
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to'   => $filters['date_to']   ?? null,
                    'per_page'  => $perPage !== 50 ? $perPage : null,
                ], fn ($v) => $v !== null && $v !== '');
                ?>
                <?php if ($page > 1) : ?>
                    <?php $prevParams = array_merge($baseParams, ['page' => $page - 1]); ?>
                    <a href="/admin/logs?<?= http_build_query($prevParams) ?>"
                       class="admin-btn admin-btn--outline admin-btn--sm">&laquo; Précédent</a>
                <?php endif; ?>
                <span class="admin-pagination__info">Page <?= $page ?> / <?= $pages ?></span>
                <?php if ($page < $pages) : ?>
                    <?php $nextParams = array_merge($baseParams, ['page' => $page + 1]); ?>
                    <a href="/admin/logs?<?= http_build_query($nextParams) ?>"
                       class="admin-btn admin-btn--outline admin-btn--sm">Suivant &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
