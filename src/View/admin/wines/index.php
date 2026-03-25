<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages  = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$colorLabels = ['red' => 'Rouge', 'white' => 'Blanc', 'rosé' => 'Rosé', 'sweet' => 'Liquoreux'];

function wineListUrl(int $page, ?string $color, ?string $avail, int $perPage): string
{
    $q = ['page' => $page];
    if ($color) {
        $q['color'] = $color;
    }
    if ($avail !== null && $avail !== '') {
        $q['available'] = $avail;
    }
    if ($perPage !== 10) {
        $q['per_page'] = $perPage;
    }
    return '/admin/vins?' . http_build_query($q);
}
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Vins <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= $total ?>)</small></h1>
    <a href="/admin/vins/ajouter" class="admin-btn admin-btn--gold">+ Ajouter un vin</a>
</div>

<!-- Filtres -->
<form method="GET" action="/admin/vins" class="admin-filters">
    <select name="color" class="admin-filters__select" aria-label="Filtrer par couleur">
        <option value="">Toutes les couleurs</option>
        <?php foreach ($colorLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= $color === $val ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="available" class="admin-filters__select" aria-label="Filtrer par statut">
        <option value="">Tous les statuts</option>
        <option value="available" <?= $available === 'available' ? 'selected' : '' ?>>Disponible</option>
        <option value="out"       <?= $available === 'out' ? 'selected' : '' ?>>Indisponible</option>
    </select>

    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ([10, 25, 50] as $n) : ?>
            <option value="<?= $n ?>" <?= (int)$perPage === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="admin-filters__btn">Filtrer</button>
    <?php if ($color || $available || $perPage !== 10) : ?>
        <a href="/admin/vins" class="admin-btn admin-btn--outline admin-btn--sm">Réinitialiser</a>
    <?php endif; ?>
</form>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Appellation</th>
                    <th>Couleur</th>
                    <th>Millésime</th>
                    <th>Prix</th>
                    <th>Qté produite</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($wines)) : ?>
                <tr><td colspan="8" style="text-align:center;color:#8a7a60;padding:2rem;">Aucun vin trouvé</td></tr>
            <?php else : ?>
                <?php foreach ($wines as $wine) : ?>
                    <tr>
                        <td style="color:#8a7a60;"><?= (int) $wine['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($wine['label_name']) ?></strong>
                            <?php if ($wine['is_cuvee_speciale']) : ?>
                                <span class="badge badge--admin" style="margin-left:0.35rem;">Cuvée</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($colorLabels[$wine['wine_color']] ?? $wine['wine_color']) ?></td>
                        <td><?= (int) $wine['vintage'] ?></td>
                        <td><?= number_format((float) $wine['price'], 2, ',', ' ') ?>&nbsp;€</td>
                        <td><?= number_format((int) $wine['quantity'], 0, ',', ' ') ?>&nbsp;bt</td>
                        <td>
                            <span class="badge badge--<?= $wine['available'] ? 'available' : 'out' ?>">
                                <?= $wine['available'] ? 'Disponible' : 'Indisponible' ?>
                            </span>
                        </td>
                        <td>
                            <div class="admin-actions">
                                <a href="/admin/vins/<?= (int) $wine['id'] ?>/modifier"
                                   class="admin-btn admin-btn--outline admin-btn--sm">Modifier</a>
                                <a href="/fr/vins/<?= htmlspecialchars($wine['slug']) ?>"
                                   class="admin-btn admin-btn--outline admin-btn--sm"
                                   target="_blank" rel="noopener">↗</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-pagination">
            <a href="<?= htmlspecialchars(wineListUrl(max(1, $page - 1), $color, $available, $perPage)) ?>"
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="<?= htmlspecialchars(wineListUrl($i, $color, $available, $perPage)) ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(wineListUrl(min($totalPages, $page + 1), $color, $available, $perPage)) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
