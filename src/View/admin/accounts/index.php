<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$roleLabels = ['customer' => 'Client', 'admin' => 'Admin', 'super_admin' => 'Super Admin'];

function accountPaginationUrl(int $p, ?string $role, string $search): string
{
    $q = ['page' => $p];
    if ($role) {
        $q['role'] = $role;
    }
    if ($search !== '') {
        $q['search'] = $search;
    }
    return '/admin/comptes?' . http_build_query($q);
}
?>

<div class="admin-page-header">
    <h1>Comptes <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= $total ?>)</small></h1>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/comptes" class="admin-filters">
    <select name="role" class="admin-filters__select" aria-label="Filtrer par rôle">
        <option value="">Tous les rôles</option>
        <?php foreach ($roleLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= $role === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="search" class="admin-filters__input"
           placeholder="Email, nom, prénom…"
           value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="admin-filters__btn">Filtrer</button>
    <?php if ($role || $search) : ?>
        <a href="/admin/comptes" class="admin-btn admin-btn--outline admin-btn--sm">Réinitialiser</a>
    <?php endif; ?>
</form>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Nom / Société</th>
                    <th>Type</th>
                    <th>Rôle</th>
                    <th>Langue</th>
                    <th>Vérifié</th>
                    <th>Inscrit le</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($accounts)) : ?>
                <tr><td colspan="8" style="text-align:center;color:#8a7a60;padding:2rem;">Aucun compte trouvé</td></tr>
            <?php else : ?>
                <?php foreach ($accounts as $account) : ?>
                    <?php
                    $displayName = $account['account_type'] === 'company'
                        ? ($account['company_name'] ?? '—')
                        : (trim($account['firstname'] . ' ' . $account['lastname']) ?: '—');
                    $roleBadge = match ($account['role']) {
                        'super_admin' => 'super',
                        'admin'       => 'admin',
                        default       => 'customer',
                    };
                    ?>
                    <tr>
                        <td style="color:#8a7a60;"><?= (int) $account['id'] ?></td>
                        <td><?= htmlspecialchars($account['email']) ?></td>
                        <td><?= htmlspecialchars($displayName) ?></td>
                        <td style="font-size:0.8rem;"><?= $account['account_type'] === 'company' ? 'Société' : 'Particulier' ?></td>
                        <td><span class="badge badge--<?= $roleBadge ?>"><?= htmlspecialchars($roleLabels[$account['role']] ?? $account['role']) ?></span></td>
                        <td style="text-transform:uppercase;font-size:0.8rem;"><?= htmlspecialchars($account['lang']) ?></td>
                        <td>
                            <?php if ($account['email_verified_at']) : ?>
                                <span style="color:#15803d;font-size:0.8rem;">✓</span>
                            <?php else : ?>
                                <span style="color:#b91c1c;font-size:0.8rem;">✗</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;font-size:0.8rem;"><?= date('d/m/Y', strtotime($account['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-pagination">
            <a href="<?= htmlspecialchars(accountPaginationUrl(max(1, $page - 1), $role, $search)) ?>"
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="<?= htmlspecialchars(accountPaginationUrl($i, $role, $search)) ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(accountPaginationUrl(min($totalPages, $page + 1), $role, $search)) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
