<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages   = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

$typeLabels   = \Model\RgpdRequestModel::TYPE_LABELS;
$statusLabels = \Model\RgpdRequestModel::STATUS_LABELS;

$statusClasses = [
    'nouvelle'  => 'admin-badge--warning',
    'en_cours'  => 'admin-badge--info',
    'traitee'   => 'admin-badge--success',
    'refusee'   => 'admin-badge--danger',
];

if (!function_exists('rgpdUrl')) {
    function rgpdUrl(int $p, ?string $status, ?string $type, int $perPage): string
    {
        $q = ['page' => $p];
        if ($status) {
            $q['status'] = $status;
        }
        if ($type) {
            $q['type'] = $type;
        }
        if ($perPage !== 20) {
            $q['per_page'] = $perPage;
        }
        return '/admin/rgpd/demandes?' . http_build_query($q);
    }
}
?>

<?php if ($flash ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<?php if (($overdue ?? 0) > 0) : ?>
    <div class="admin-flash admin-flash--warning" style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:10px 14px;border-radius:4px;margin-bottom:16px;">
        &#9888; <?= (int) $overdue ?> demande<?= $overdue > 1 ? 's' : '' ?> en attente depuis plus de <?= \Model\RgpdRequestModel::ALERT_DAYS ?> jours (J-5 du délai légal d'1 mois).
    </div>
<?php endif; ?>

<div class="admin-page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
    <h1>Demandes RGPD <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= (int) $total ?>)</small></h1>
    <a href="/admin/rgpd/demandes/ajouter" class="admin-btn admin-btn--primary admin-btn--sm">+ Nouvelle demande</a>
</div>

<form method="GET" action="/admin/rgpd/demandes" class="admin-filters" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
    <select name="status" class="admin-filters__select" aria-label="Filtrer par statut">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= ($statusFilter ?? '') === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="type" class="admin-filters__select" aria-label="Filtrer par type de droit">
        <option value="">Tous les types</option>
        <?php foreach ($typeLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= ($typeFilter ?? '') === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="per_page" class="admin-filters__select" aria-label="Résultats par page">
        <?php foreach ([10, 20, 50] as $n) : ?>
            <option value="<?= $n ?>" <?= (int) $perPage === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="admin-btn admin-btn--outline admin-btn--sm">Filtrer</button>
    <a href="/admin/rgpd/demandes" class="admin-btn admin-btn--ghost admin-btn--sm">Réinitialiser</a>
</form>

<?php if (empty($requests)) : ?>
    <p style="color:#8a7a60;">Aucune demande RGPD trouvée.</p>
<?php else : ?>
    <div class="admin-table-wrapper">
        <table class="admin-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Demandeur</th>
                    <th>Email</th>
                    <th>Reçue le</th>
                    <th>Délai</th>
                    <th>Statut</th>
                    <th>Traité par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req) :
                    $isOverdue    = in_array($req['status'], ['nouvelle', 'en_cours'], true)
                                    && (int) ($req['days_since_received'] ?? 0) >= \Model\RgpdRequestModel::ALERT_DAYS;
                    $rowStyle     = $isOverdue ? 'background:#fff8e1;' : '';
                    $treatedName  = $req['treated_by_firstname'] ?? ($req['treated_by_company'] ?? '—');
                    if (($req['treated_by_lastname'] ?? '') !== '') {
                        $treatedName = $req['treated_by_firstname'] . ' ' . $req['treated_by_lastname'];
                    }
                    $receivedFmt  = $req['received_at'] ? date('d/m/Y', strtotime($req['received_at'])) : '—';
                    $days         = (int) ($req['days_since_received'] ?? 0);
                    $delayLabel   = "{$days} j";
                    $delayStyle   = $isOverdue ? 'color:#c62828;font-weight:bold;' : '';
                ?>
                    <tr style="<?= $rowStyle ?>">
                        <td><?= (int) $req['id'] ?></td>
                        <td><?= htmlspecialchars($typeLabels[$req['type']] ?? $req['type']) ?></td>
                        <td><?= htmlspecialchars($req['requester_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($req['requester_email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($receivedFmt) ?></td>
                        <td style="<?= $delayStyle ?>">
                            <?= htmlspecialchars($delayLabel) ?>
                            <?php if ($isOverdue) : ?>
                                <span title="Délai légal dépassé de plus de 5 jours" aria-label="Alerte délai">&#9888;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="admin-badge <?= htmlspecialchars($statusClasses[$req['status']] ?? '') ?>">
                                <?= htmlspecialchars($statusLabels[$req['status']] ?? $req['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string) $treatedName) ?></td>
                        <td>
                            <details class="rgpd-actions-details" style="position:relative;">
                                <summary class="admin-btn admin-btn--outline admin-btn--xs" style="cursor:pointer;list-style:none;">Actions &#9660;</summary>
                                <div style="position:absolute;z-index:10;background:#fff;border:1px solid #ddd;border-radius:4px;padding:8px;min-width:220px;right:0;top:100%;box-shadow:0 2px 8px rgba(0,0,0,.1);">
                                    <form method="POST" action="/admin/rgpd/demandes/<?= (int) $req['id'] ?>/statut" style="margin-bottom:6px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <select name="status" class="admin-filters__select" style="width:100%;margin-bottom:4px;">
                                            <?php foreach ($statusLabels as $val => $label) : ?>
                                                <option value="<?= htmlspecialchars($val) ?>" <?= $req['status'] === $val ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="response_note" placeholder="Note de réponse (optionnel)"
                                            style="width:100%;font-size:0.8rem;padding:4px;border:1px solid #ddd;border-radius:3px;resize:vertical;"
                                            rows="2"><?= htmlspecialchars($req['response_note'] ?? '') ?></textarea>
                                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--xs" style="width:100%;margin-top:4px;">Enregistrer statut</button>
                                    </form>

                                    <?php if ($req['type'] === 'portabilite') : ?>
                                        <a href="/admin/rgpd/demandes/<?= (int) $req['id'] ?>/export-portabilite"
                                           class="admin-btn admin-btn--outline admin-btn--xs"
                                           style="display:block;text-align:center;margin-bottom:6px;"
                                           data-confirm="Envoyer l'export RGPD ?">
                                            &#128228; Envoyer export
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!in_array($req['response_note'] ?? '', ['', null], true)) : ?>
                                        <details style="font-size:0.8rem;color:#5a4030;background:#fafaf8;border:1px solid #e8e0d0;border-radius:3px;padding:4px 6px;">
                                            <summary>Voir la note</summary>
                                            <p style="margin:4px 0 0;"><?= nl2br(htmlspecialchars($req['response_note'])) ?></p>
                                        </details>
                                    <?php endif; ?>

                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <nav class="admin-pagination" aria-label="Pagination des demandes RGPD" style="margin-top:16px;display:flex;gap:6px;flex-wrap:wrap;">
            <?php if ($page > 1) : ?>
                <a href="<?= htmlspecialchars(rgpdUrl($page - 1, $statusFilter, $typeFilter, $perPage)) ?>"
                   class="admin-btn admin-btn--outline admin-btn--sm">&#8592; Précédent</a>
            <?php endif; ?>

            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++) : ?>
                <a href="<?= htmlspecialchars(rgpdUrl($p, $statusFilter, $typeFilter, $perPage)) ?>"
                   class="admin-btn admin-btn--outline admin-btn--sm<?= $p === $page ? ' active' : '' ?>"
                   <?= $p === $page ? 'aria-current="page"' : '' ?>>
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages) : ?>
                <a href="<?= htmlspecialchars(rgpdUrl($page + 1, $statusFilter, $typeFilter, $perPage)) ?>"
                   class="admin-btn admin-btn--outline admin-btn--sm">Suivant &#8594;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
document.addEventListener('click', function (e) {
    document.querySelectorAll('.rgpd-actions-details[open]').forEach(function (d) {
        if (!d.contains(e.target)) { d.removeAttribute('open'); }
    });
});
</script>

<?php
// ----------------------------------------------------------------
// Section : Journal des exports RGPD automatiques (Art. 20)
// ----------------------------------------------------------------
$logTotalPages = \Controller\Admin\RgpdAdminController::DEFAULT_PER_PAGE > 0 ? (int) ceil(($logTotal ?? 0) / \Controller\Admin\RgpdAdminController::DEFAULT_PER_PAGE) : 1;
?>

<hr style="margin:32px 0;border:none;border-top:1px solid #e8e0d0;">

<h2 style="font-size:1.1rem;margin-bottom:16px;">
    Journal des exports automatiques — Art. 20
    <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= (int) ($logTotal ?? 0) ?>)</small>
</h2>

<form method="GET" action="/admin/rgpd/demandes" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
    <?php foreach (['status' => $statusFilter ?? null, 'type' => $typeFilter ?? null, 'page' => $page ?? 1, 'per_page' => $perPage ?? 20] as $k => $v) : ?>
        <?php if ($v) : ?><input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string) $v) ?>"><?php endif; ?>
    <?php endforeach; ?>
    <input type="text" name="log_email" placeholder="Filtrer par email"
           value="<?= htmlspecialchars($logEmail ?? '') ?>"
           class="admin-filters__select" style="min-width:200px;">
    <input type="date" name="log_date_from" value="<?= htmlspecialchars($logDateFrom ?? '') ?>" class="admin-filters__select">
    <input type="date" name="log_date_to"   value="<?= htmlspecialchars($logDateTo   ?? '') ?>" class="admin-filters__select">
    <button type="submit" class="admin-btn admin-btn--outline admin-btn--sm">Filtrer</button>
    <a href="/admin/rgpd/demandes" class="admin-btn admin-btn--ghost admin-btn--sm">Réinitialiser</a>
</form>

<?php if (empty($exportLogs)) : ?>
    <p style="color:#8a7a60;">Aucun export enregistré.</p>
<?php else : ?>
    <div class="admin-table-wrapper">
        <table class="admin-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Email</th>
                    <th>IP</th>
                    <th>Fichier</th>
                    <th>Date</th>
                    <th>Expiration</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exportLogs as $log) : ?>
                    <tr>
                        <td><?= (int) $log['id'] ?></td>
                        <td><?= htmlspecialchars($log['email']) ?></td>
                        <td style="font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($log['ip'] ?? '—') ?></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($log['filename']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($log['requested_at']))) ?></td>
                        <td style="color:#8a7a60;font-size:0.8rem;"><?= htmlspecialchars(date('d/m/Y', strtotime($log['expires_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($logTotalPages ?? 1) > 1) : ?>
        <nav aria-label="Pagination des exports RGPD" style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;">
            <?php
            $logPerPage = \Controller\Admin\RgpdAdminController::DEFAULT_PER_PAGE;
            $buildLogUrl = static function (int $p) use ($statusFilter, $typeFilter, $perPage, $page, $logEmail, $logDateFrom, $logDateTo, $logPerPage): string {
                $q = ['log_page' => $p];
                if ($statusFilter) { $q['status'] = $statusFilter; }
                if ($typeFilter)   { $q['type']   = $typeFilter; }
                if ($page > 1)     { $q['page']   = $page; }
                if ($perPage !== $logPerPage) { $q['per_page'] = $perPage; }
                if ($logEmail)     { $q['log_email'] = $logEmail; }
                if ($logDateFrom)  { $q['log_date_from'] = $logDateFrom; }
                if ($logDateTo)    { $q['log_date_to'] = $logDateTo; }
                return '/admin/rgpd/demandes?' . http_build_query($q);
            };
            ?>
            <?php if (($logPage ?? 1) > 1) : ?>
                <a href="<?= htmlspecialchars($buildLogUrl(($logPage ?? 1) - 1)) ?>" class="admin-btn admin-btn--outline admin-btn--sm">&#8592; Précédent</a>
            <?php endif; ?>
            <?php for ($p = max(1, ($logPage ?? 1) - 2); $p <= min($logTotalPages, ($logPage ?? 1) + 2); $p++) : ?>
                <a href="<?= htmlspecialchars($buildLogUrl($p)) ?>"
                   class="admin-btn admin-btn--outline admin-btn--sm<?= $p === ($logPage ?? 1) ? ' active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if (($logPage ?? 1) < $logTotalPages) : ?>
                <a href="<?= htmlspecialchars($buildLogUrl(($logPage ?? 1) + 1)) ?>" class="admin-btn admin-btn--outline admin-btn--sm">Suivant &#8594;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
