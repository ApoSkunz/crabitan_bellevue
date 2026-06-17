<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$roleLabels = ['customer' => 'Client', 'admin' => 'Admin', 'super_admin' => 'Super Admin'];
$isSuperAdmin = $currentRole === 'super_admin';

if (!function_exists('accountUrl')) {
function accountUrl( // NOSONAR — php:S107 : params nécessaires pour reconstruire l'URL de pagination avec tous les filtres admin
    int $p,
    ?string $role,
    ?string $type,
    string $search,
    int $perPage,
    ?string $sort = null,
    ?string $hasOrdered = null,
    ?string $activity = null
): string {
    $q = ['page' => $p];
    if ($role) {
        $q['role'] = $role;
    }
    if ($type) {
        $q['type'] = $type;
    }
    if ($search !== '') {
        $q['search'] = $search;
    }
    if ($perPage !== 10) {
        $q['per_page'] = $perPage;
    }
    if ($sort !== null) {
        $q['sort'] = $sort;
    }
    if ($hasOrdered !== null) {
        $q['has_ordered'] = $hasOrdered;
    }
    if ($activity !== null) {
        $q['activity'] = $activity;
    }
    return '/admin/comptes?' . http_build_query($q);
}
}
?>

<?php if ($flash ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Comptes <small class="admin-section-count">(<?= $total ?>)</small></h1>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/comptes" class="admin-filters" data-auto-submit>
    <select name="role" class="admin-filters__select" aria-label="Filtrer par rôle">
        <option value="">Tous les rôles</option>
        <?php foreach ($roleLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= $role === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="type" class="admin-filters__select" aria-label="Filtrer par type">
        <option value="">Tous les types</option>
        <option value="individual" <?= ($type ?? '') === 'individual' ? 'selected' : '' ?>>Particulier</option>
        <option value="company"    <?= ($type ?? '') === 'company'    ? 'selected' : '' ?>>Société</option>
    </select>

    <select name="has_ordered" class="admin-filters__select" aria-label="Filtrer par commandes">
        <option value="">Tous les clients</option>
        <option value="1" <?= ($hasOrdered ?? '') === '1' ? 'selected' : '' ?>>A commandé</option>
        <option value="0" <?= ($hasOrdered ?? '') === '0' ? 'selected' : '' ?>>Jamais commandé</option>
    </select>

    <select name="activity" class="admin-filters__select" aria-label="Filtrer par activité">
        <option value="">Toute activité</option>
        <option value="active"   <?= ($activity ?? '') === 'active'   ? 'selected' : '' ?>>Actif</option>
        <option value="inactive" <?= ($activity ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif (+3 ans)</option>
        <option value="warning"  <?= ($activity ?? '') === 'warning'  ? 'selected' : '' ?>>⚠ Alerte suppression</option>
        <option value="deleted"  <?= ($activity ?? '') === 'deleted'  ? 'selected' : '' ?>>Supprimé</option>
    </select>

    <input type="text" name="search" class="admin-filters__input"
           placeholder="Email, nom, prénom…"
           value="<?= htmlspecialchars($search) ?>">

    <select name="sort" class="admin-filters__select" aria-label="Trier par">
        <option value="created_at_desc" <?= ($sort ?? null) === 'created_at_desc' || ($sort ?? null) === null ? 'selected' : '' ?>>Inscription — décroissant</option>
        <option value="created_at_asc"  <?= ($sort ?? null) === 'created_at_asc'  ? 'selected' : '' ?>>Inscription — croissant</option>
        <option value="last_connection_desc" <?= ($sort ?? null) === 'last_connection_desc' ? 'selected' : '' ?>>Connexion — décroissant</option>
        <option value="last_connection_asc"  <?= ($sort ?? null) === 'last_connection_asc'  ? 'selected' : '' ?>>Connexion — croissant</option>
        <option value="order_count_desc"     <?= ($sort ?? null) === 'order_count_desc'     ? 'selected' : '' ?>>Commandes — décroissant</option>
        <option value="order_count_asc"      <?= ($sort ?? null) === 'order_count_asc'      ? 'selected' : '' ?>>Commandes — croissant</option>
    </select>

    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ([10, 25, 50] as $n) : ?>
            <option value="<?= $n ?>" <?= (int)$perPage === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>

    <noscript><button type="submit" class="admin-filters__btn">Filtrer</button></noscript>
    <a href="/admin/comptes<?= $perPage !== 10 ? '?per_page=' . $perPage : '' ?>"
       class="admin-btn admin-btn--dark admin-btn--sm">Réinitialiser</a>
</form>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="col-hide-tablet">ID</th>
                    <th>Email</th>
                    <th>Nom / Société</th>
                    <th class="col-hide-tablet">Type</th>
                    <th>Rôle</th>
                    <th class="col-hide-tablet">Langue</th>
                    <th class="col-hide-tablet">Vérifié</th>
                    <th class="col-hide-tablet">Connecté</th>
                    <th>Inscrit le</th>
                    <th class="col-hide-tablet">Dernière connexion</th>
                    <th class="col-hide-tablet">Cmdes</th>
                    <th class="col-hide-tablet">Activité</th>
                    <th>Vérif.</th>
                    <?php if ($isSuperAdmin) : ?>
                        <th>Réinit. mdp</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($accounts)) : ?>
                <tr><td colspan="<?= $isSuperAdmin ? 14 : 13 ?>"
                        class="admin-table-empty-cell">Aucun compte trouvé</td></tr>
            <?php else : ?>
                <?php foreach ($accounts as $account) : ?>
                    <?php
                    if ($account['account_type'] === 'company') {
                        $displayName = $account['company_name'] ?? '—';
                    } else {
                        $displayName = trim($account['firstname'] . ' ' . $account['lastname']) ?: '—';
                    }
                    $roleBadge = match ($account['role']) {
                        'super_admin' => 'super',
                        'admin'       => 'admin',
                        default       => 'customer',
                    };
                    $verified = !empty($account['email_verified_at']);
    ?>
                    <tr>
                        <td class="col-hide-tablet text-muted" data-label="ID"><?= (int) $account['id'] ?></td>
                        <td data-label="Email"><?= htmlspecialchars($account['email']) ?></td>
                        <td data-label="Nom / Société"><?= htmlspecialchars($displayName) ?></td>
                        <td class="col-hide-tablet u-text-sm" data-label="Type">
                            <?= $account['account_type'] === 'company' ? 'Société' : 'Particulier' ?>
                        </td>
                        <td data-label="Rôle">
                            <span class="badge badge--<?= $roleBadge ?>">
                                <?= htmlspecialchars($roleLabels[$account['role']] ?? $account['role']) ?>
                            </span>
                        </td>
                        <td class="col-hide-tablet u-uppercase u-text-sm" data-label="Langue">
                            <?= htmlspecialchars($account['lang']) ?>
                        </td>
                        <td class="col-hide-tablet" data-label="Vérifié">
                            <?php if ($verified) : ?>
                                <span class="u-text-sm u-text-green">✓</span>
                            <?php else : ?>
                                <span class="u-text-sm u-text-red">✗</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-hide-tablet" data-label="Connecté">
                            <?php if (!empty($account['has_connected'])) : ?>
                                <span class="u-text-sm u-text-green">✓</span>
                            <?php else : ?>
                                <span class="u-text-sm u-text-red">✗</span>
                            <?php endif; ?>
                        </td>
                        <td class="u-nowrap u-text-sm" data-label="Inscrit le">
                            <?= date('d/m/Y', strtotime($account['created_at'])) ?>
                        </td>
                        <td class="col-hide-tablet u-nowrap u-text-sm" data-label="Dernière connexion">
                            <?php if (!empty($account['last_connection'])) : ?>
                                <?= date('d/m/Y H:i', strtotime($account['last_connection'])) ?>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-hide-tablet u-text-85 text-center" data-label="Cmdes">
                            <?php $orderCount = (int) ($account['order_count'] ?? 0); ?>
                            <?php if ($orderCount > 0) : ?>
                                <?= $orderCount ?>
                            <?php else : ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-hide-tablet u-text-9" data-label="Activité">
                            <?php
                            $scheduledAt  = $account['scheduled_deletion_at'] ?? null;
                            $isDeleted    = !empty($account['deleted_at']);
                            $isWarning    = !$isDeleted && !empty($account['inactivity_warning_sent_at']);
                            $warningDate  = $account['inactivity_warning_sent_at'] ?? null;
                            // canCancel : alerte envoyée, pas encore soft-deleted (scheduled_deletion_at absent à ce stade)
                            $canCancel    = $isWarning;
                            $daysLeft     = ($canCancel && $warningDate !== null)
                                ? max(0, (int) ceil((strtotime((string) $warningDate . ' +30 days') - time()) / 86400))
                                : 0;
                            // canRestore : soft-deleted dans la fenêtre J+30 avant anonymisation
                            $canRestore   = $isDeleted && $scheduledAt !== null && strtotime((string) $scheduledAt) > time();
                            $daysRestore  = $canRestore
                                ? max(0, (int) ceil((strtotime((string) $scheduledAt) - time()) / 86400))
                                : 0;
                            ?>
                            <div class="u-flex-col u-items-start u-gap-2">
                                <?php
                                if ($isDeleted) {
                                    echo '<span class="u-text-error">Supprimé</span>';
                                } elseif ($isWarning) {
                                    echo '<span class="u-text-warning" title="Alerte envoyée le ' . htmlspecialchars(date('d/m/Y', strtotime((string) $account['inactivity_warning_sent_at']))) . '">⚠ Alerte</span>';
                                } else {
                                    $lastConn = $account['last_connection'] ?? null;
                                    $isActive = $lastConn !== null && strtotime((string) $lastConn) >= strtotime('-3 years');
                                    echo $isActive
                                        ? '<span class="u-text-green">Actif</span>'
                                        : '<span class="u-text-soft">Inactif</span>';
                                }
                                ?>
                                <?php if ($canCancel) : ?>
                                    <form method="POST"
                                          action="/admin/comptes/<?= (int) $account['id'] ?>/annuler-suppression"
                                          data-admin-confirm="Annuler la suppression du compte <?= htmlspecialchars($account['email']) ?> ? (<?= $daysLeft ?> j restants avant anonymisation)">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <button type="submit" class="admin-btn admin-btn--verify admin-btn--sm">
                                            Annuler suppression
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canRestore) : ?>
                                    <form method="POST"
                                          action="/admin/comptes/<?= (int) $account['id'] ?>/restaurer"
                                          data-admin-confirm="Restaurer le compte <?= htmlspecialchars($account['email']) ?> ? (<?= $daysRestore ?> j avant anonymisation définitive)">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <button type="submit" class="admin-btn admin-btn--reset admin-btn--sm">
                                            Restaurer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="u-nowrap">
                            <?php
                            if (!$verified) : ?>
                                <form method="POST"
                                      action="/admin/comptes/<?= (int) $account['id'] ?>/verifier"
                                      class="u-inline-block"
                                      data-admin-confirm="Vérifier ce compte manuellement ?">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                    <button type="submit" class="admin-btn admin-btn--verify admin-btn--sm">
                                        Vérifier
                                    </button>
                                </form>
                            <?php else : ?>
                                <span class="u-text-gray-light">—</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($isSuperAdmin) : ?>
                            <td class="u-nowrap">
                                <?php if (!empty($account['email_verified_at']) && !empty($account['has_password'])) : ?>
                                    <form method="POST"
                                          action="/admin/comptes/<?= (int) $account['id'] ?>/reset-password"
                                          class="u-inline-block"
                                          data-admin-confirm="Envoyer un lien de réinitialisation à <?= htmlspecialchars($account['email']) ?> ?">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <button type="submit" class="admin-btn admin-btn--reset admin-btn--sm">
                                            Réinit. mdp
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <span class="u-text-gray-light">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) :
        $disabledSuffix = ' disabled';
        $prevDisabled   = $page <= 1 ? $disabledSuffix : '';
        $nextDisabled   = $page >= $totalPages ? $disabledSuffix : '';
        ?>
        <div class="admin-pagination">
            <a href="<?= htmlspecialchars(accountUrl(1, $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
               class="admin-pagination__item<?= $prevDisabled ?>" aria-label="Première page">«</a>
            <a href="<?= htmlspecialchars(accountUrl(max(1, $page - 1), $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
               class="admin-pagination__item<?= $prevDisabled ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="<?= htmlspecialchars(accountUrl($i, $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(accountUrl(min($totalPages, $page + 1), $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
               class="admin-pagination__item<?= $nextDisabled ?>">›</a>
            <a href="<?= htmlspecialchars(accountUrl($totalPages, $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
               class="admin-pagination__item<?= $nextDisabled ?>" aria-label="Dernière page">»</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
