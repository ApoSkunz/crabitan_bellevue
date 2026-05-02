<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<div class="admin-page-header">
    <h1>Paniers</h1>
    <p class="admin-page-header__sub"><?= number_format($total, 0, ',', ' ') ?> panier<?= $total > 1 ? 's' : '' ?> enregistré<?= $total > 1 ? 's' : '' ?></p>
</div>

<div class="admin-card">
    <div class="admin-card__header u-flex u-items-center u-justify-between">
        <h2><?= number_format($total, 0, ',', ' ') ?> panier<?= $total > 1 ? 's' : '' ?></h2>
        <form method="GET" action="/admin/paniers" class="u-flex u-items-center u-gap-3">
            <label for="ct-per-page" class="admin-filters-sublabel u-mb-0">Par page</label>
            <select id="ct-per-page" name="per_page" class="admin-filters__select" data-auto-submit aria-label="Résultats par page">
                <?php foreach ([25, 50, 100] as $opt) : ?>
                    <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="admin-card__body admin-card__body--flush">
        <?php if (empty($carts)) : ?>
            <p class="admin-empty-message">Aucun panier enregistré.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th class="u-text-right">Qté</th>
                        <th>Contenu (aperçu)</th>
                        <th>Relance envoyée</th>
                        <th>Dernière activité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carts as $cart) :
                        $qty  = (int) ($cart['total_quantity'] ?? 0);
                        $items = json_decode((string) ($cart['content'] ?? '[]'), true);
                        $itemCount = is_array($items) ? count($items) : 0;
                        $reminder  = $cart['reminder_sent_at'] ?? null;
                        $updatedAt = (string) ($cart['updated_at'] ?? '');
                    ?>
                    <tr>
                        <td data-label="ID" class="u-text-sm u-text-muted-aa">#<?= (int) $cart['id'] ?></td>
                        <td data-label="Client">
                            <?php if (!empty($cart['email'])) : ?>
                                <a href="/admin/comptes?search=<?= rawurlencode((string) $cart['email']) ?>"
                                   class="u-text-sm">
                                    <?= htmlspecialchars((string) $cart['email']) ?>
                                </a>
                                <?php if (!empty($cart['owner_name'])) : ?>
                                    <span class="u-text-2xs u-text-muted-aa u-block">
                                        <?= htmlspecialchars((string) $cart['owner_name']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="u-text-muted-aa u-text-sm">Invité / supprimé</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Qté" class="u-text-right">
                            <strong><?= $qty ?></strong>
                            <span class="u-text-2xs u-text-muted-aa">
                                bouteille<?= $qty > 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td data-label="Contenu" class="u-text-sm">
                            <?= $itemCount ?> référence<?= $itemCount > 1 ? 's' : '' ?>
                        </td>
                        <td data-label="Relance">
                            <?php if ($reminder !== null) : ?>
                                <span class="u-text-success u-text-sm">
                                    ✓ <?= htmlspecialchars(date('d/m/Y', strtotime((string) $reminder))) ?>
                                </span>
                            <?php else : ?>
                                <span class="u-text-muted-aa u-text-sm">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Activité" class="u-text-sm u-nowrap">
                            <?= $updatedAt !== ''
                                ? htmlspecialchars(date('d/m/Y H:i', strtotime($updatedAt)))
                                : '—'
                            ?>
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
                <?php if ($page > 1) : ?>
                    <a href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?>"
                       class="admin-btn admin-btn--outline admin-btn--sm">&laquo; Précédent</a>
                <?php endif; ?>
                <span class="admin-pagination__info">Page <?= $page ?> / <?= $pages ?></span>
                <?php if ($page < $pages) : ?>
                    <a href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?>"
                       class="admin-btn admin-btn--outline admin-btn--sm">Suivant &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
