<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$roleLabels = ['customer' => 'Client', 'admin' => 'Admin', 'super_admin' => 'Super Admin'];
$isSuperAdmin = $currentRole === 'super_admin';

if (!function_exists('accountUrl')) {
function accountUrl(int $p, ?string $role, ?string $type, string $search, int $perPage, ?string $sort = null, ?string $hasOrdered = null): string
{
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
    <h1>Comptes <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= $total ?>)</small></h1>
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
        <option value="">Toutes les commandes</option>
        <option value="1" <?= ($hasOrdered ?? '') === '1' ? 'selected' : '' ?>>A commandé</option>
        <option value="0" <?= ($hasOrdered ?? '') === '0' ? 'selected' : '' ?>>Jamais commandé</option>
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

    <button type="submit" class="admin-filters__btn">Filtrer</button>
    <?php if ($role || ($type ?? null) || $search) : ?>
        <a href="/admin/comptes<?= $perPage !== 10 ? '?per_page=' . $perPage : '' ?>"
           class="admin-btn admin-btn--outline admin-btn--sm">Réinitialiser</a>
    <?php endif; ?>
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
                    <?php if ($isSuperAdmin) : ?>
                        <th>Vérif.</th>
                        <th>Réinit. mdp</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($accounts)) : ?>
                <tr><td colspan="<?= $isSuperAdmin ? 13 : 11 ?>"
                        style="text-align:center;color:#8a7a60;padding:2rem;">Aucun compte trouvé</td></tr>
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
                        <td class="col-hide-tablet" data-label="ID" style="color:#8a7a60;"><?= (int) $account['id'] ?></td>
                        <td data-label="Email"><?= htmlspecialchars($account['email']) ?></td>
                        <td data-label="Nom / Société"><?= htmlspecialchars($displayName) ?></td>
                        <td class="col-hide-tablet" data-label="Type" style="font-size:0.8rem;">
                            <?= $account['account_type'] === 'company' ? 'Société' : 'Particulier' ?>
                        </td>
                        <td data-label="Rôle">
                            <span class="badge badge--<?= $roleBadge ?>">
                                <?= htmlspecialchars($roleLabels[$account['role']] ?? $account['role']) ?>
                            </span>
                        </td>
                        <td class="col-hide-tablet" data-label="Langue" style="text-transform:uppercase;font-size:0.8rem;">
                            <?= htmlspecialchars($account['lang']) ?>
                        </td>
                        <td class="col-hide-tablet" data-label="Vérifié">
                            <?php if ($verified) : ?>
                                <span style="color:#15803d;font-size:0.8rem;">✓</span>
                            <?php else : ?>
                                <span style="color:#b91c1c;font-size:0.8rem;">✗</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-hide-tablet" data-label="Connecté">
                            <?php if (!empty($account['has_connected'])) : ?>
                                <span style="color:#15803d;font-size:0.8rem;">✓</span>
                            <?php else : ?>
                                <span style="color:#b91c1c;font-size:0.8rem;">✗</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Inscrit le" style="white-space:nowrap;font-size:0.8rem;">
                            <?= date('d/m/Y', strtotime($account['created_at'])) ?>
                        </td>
                        <td class="col-hide-tablet" data-label="Dernière connexion" style="white-space:nowrap;font-size:0.8rem;">
                            <?php if (!empty($account['last_connection'])) : ?>
                                <?= date('d/m/Y H:i', strtotime($account['last_connection'])) ?>
                            <?php else : ?>
                                <span style="color:#8a7a60;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-hide-tablet" data-label="Cmdes" style="font-size:0.85rem;text-align:center;">
                            <?php $orderCount = (int) ($account['order_count'] ?? 0); ?>
                            <?php if ($orderCount > 0) : ?>
                                <?= $orderCount ?>
                            <?php else : ?>
                                <span style="color:#8a7a60;">—</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($isSuperAdmin) : ?>
                            <td style="white-space:nowrap;">
                                <?php if (!$verified) : ?>
                                    <form method="POST"
                                          action="/admin/comptes/<?= (int) $account['id'] ?>/verifier"
                                          style="display:inline;"
                                          data-admin-confirm="Vérifier ce compte manuellement ?">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <button type="submit"
                                                class="admin-btn admin-btn--outline admin-btn--sm"
                                                style="color:#15803d;border-color:#15803d;">
                                            Vérifier
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php if (!empty($account['email_verified_at']) && !empty($account['has_password'])) : ?>
                                    <form method="POST"
                                          action="/admin/comptes/<?= (int) $account['id'] ?>/reset-password"
                                          style="display:inline;"
                                          data-admin-confirm="Envoyer un lien de réinitialisation à <?= htmlspecialchars($account['email']) ?> ?">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <button type="submit"
                                                class="admin-btn admin-btn--outline admin-btn--sm"
                                                style="color:#92400e;border-color:#92400e;">
                                            Réinit. mdp
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-pagination">
            <a href="<?= htmlspecialchars(accountUrl(max(1, $page - 1), $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="<?= htmlspecialchars(accountUrl($i, $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(accountUrl(min($totalPages, $page + 1), $role, $type ?? null, $search, $perPage, $sort ?? null, $hasOrdered ?? null)) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
