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
    <div class="admin-flash admin-flash--warning">
        &#9888; <?= (int) $overdue ?> demande<?= $overdue > 1 ? 's' : '' ?> en attente depuis plus de <?= \Model\RgpdRequestModel::ALERT_DAYS ?> jours (J-5 du délai légal d'1 mois).
    </div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Demandes RGPD <small class="admin-section-count">(<?= (int) $total ?>)</small></h1>
    <a href="/admin/rgpd/demandes/ajouter" class="admin-btn admin-btn--primary admin-btn--sm">+ Nouvelle demande</a>
</div>

<form method="GET" action="/admin/rgpd/demandes" class="admin-filters" data-auto-submit>
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

    <button type="submit" class="admin-btn admin-btn--outline admin-btn--sm js-noscript-only">Filtrer</button>
    <a href="/admin/rgpd/demandes" class="admin-btn admin-btn--ghost admin-btn--sm">Réinitialiser</a>
</form>

<?php if (empty($requests)) : ?>
    <p class="admin-muted">Aucune demande RGPD trouvée.</p>
<?php else : ?>
    <div class="admin-table-wrapper">
        <table class="admin-table">
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
                    $rowClass     = $isOverdue ? 'admin-row--overdue' : '';
                    $delayClass   = $isOverdue ? 'admin-text--overdue' : '';
                    $treatedName  = $req['treated_by_firstname'] ?? ($req['treated_by_company'] ?? '—');
                    if (($req['treated_by_lastname'] ?? '') !== '') {
                        $treatedName = $req['treated_by_firstname'] . ' ' . $req['treated_by_lastname'];
                    }
                    $receivedFmt  = $req['received_at'] ? date('d/m/Y', strtotime($req['received_at'])) : '—';
                    $days         = (int) ($req['days_since_received'] ?? 0);
                    $delayLabel   = "{$days} j";
                ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= (int) $req['id'] ?></td>
                        <td><?= htmlspecialchars($typeLabels[$req['type']] ?? $req['type']) ?></td>
                        <td><?= htmlspecialchars($req['requester_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($req['requester_email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($receivedFmt) ?></td>
                        <td class="<?= $delayClass ?>">
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
                            <details class="rgpd-actions">
                                <summary class="admin-btn admin-btn--outline admin-btn--xs">Actions &#9660;</summary>
                                <div class="rgpd-actions__dropdown">
                                    <form method="POST" action="/admin/rgpd/demandes/<?= (int) $req['id'] ?>/statut" class="u-mb-1">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <select name="status" aria-label="Statut de la demande" class="admin-filters__select u-w-full u-mb-1">
                                            <?php foreach ($statusLabels as $val => $label) : ?>
                                                <option value="<?= htmlspecialchars($val) ?>" <?= $req['status'] === $val ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="response_note" aria-label="Note de réponse"
                                            placeholder="Note de réponse (optionnel)"
                                            class="rgpd-note-textarea"
                                            rows="2"><?= htmlspecialchars($req['response_note'] ?? '') ?></textarea>
                                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--xs u-w-full u-mt-1">Enregistrer statut</button>
                                    </form>

                                    <?php if ($req['type'] === 'portabilite') : ?>
                                        <a href="/admin/rgpd/demandes/<?= (int) $req['id'] ?>/export-portabilite"
                                           class="admin-btn admin-btn--outline admin-btn--xs u-block text-center u-mb-1"
                                           data-admin-confirm="Envoyer l'export RGPD ?">
                                            &#128228; Envoyer export
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!in_array($req['response_note'] ?? '', ['', null], true)) : ?>
                                        <details class="rgpd-note-details">
                                            <summary>Voir la note</summary>
                                            <p class="u-mt-1"><?= nl2br(htmlspecialchars($req['response_note'])) ?></p>
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
        <nav class="admin-pagination u-mt-4 u-flex u-flex-wrap" aria-label="Pagination des demandes RGPD">
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
    document.querySelectorAll('.rgpd-actions[open]').forEach(function (d) {
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

<hr class="admin-divider">

<h2 class="u-text-lg u-mb-4">
    Journal des exports automatiques — Art. 20
    <small class="admin-section-count">(<?= (int) ($logTotal ?? 0) ?>)</small>
</h2>

<form method="GET" action="/admin/rgpd/demandes" class="admin-filters">
    <?php foreach (['status' => $statusFilter ?? null, 'type' => $typeFilter ?? null, 'page' => $page ?? 1, 'per_page' => $perPage ?? 20] as $k => $v) : ?>
        <?php if ($v) : ?><input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string) $v) ?>"><?php endif; ?>
    <?php endforeach; ?>
    <input type="text" name="log_email" aria-label="Filtrer par email"
           placeholder="Filtrer par email"
           value="<?= htmlspecialchars($logEmail ?? '') ?>"
           class="admin-filters__select admin-filters__input--wide">
    <input type="date" name="log_date_from" aria-label="Date début"
           value="<?= htmlspecialchars($logDateFrom ?? '') ?>" class="admin-filters__select">
    <input type="date" name="log_date_to" aria-label="Date fin"
           value="<?= htmlspecialchars($logDateTo   ?? '') ?>" class="admin-filters__select">
    <button type="submit" class="admin-btn admin-btn--outline admin-btn--sm">Filtrer</button>
    <a href="/admin/rgpd/demandes" class="admin-btn admin-btn--ghost admin-btn--sm">Réinitialiser</a>
</form>

<?php if (empty($exportLogs)) : ?>
    <p class="admin-muted">Aucun export enregistré.</p>
<?php else : ?>
    <div class="admin-table-wrapper">
        <table class="admin-table">
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
                        <td class="admin-td--mono"><?= htmlspecialchars($log['ip'] ?? '—') ?></td>
                        <td class="u-text-85"><?= htmlspecialchars($log['filename']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($log['requested_at']))) ?></td>
                        <td class="admin-muted u-text-sm"><?= htmlspecialchars(date('d/m/Y', strtotime($log['expires_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($logTotalPages ?? 1) > 1) : ?>
        <nav aria-label="Pagination des exports RGPD" class="u-mt-3 u-flex u-flex-wrap">
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
