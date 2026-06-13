<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<div class="admin-page-header">
    <h1>Avis clients</h1>
    <p class="admin-page-header__sub"><?= number_format($total, 0, ',', ' ') ?> avis au total</p>
</div>

<div class="admin-card">
    <div class="admin-card__header u-flex u-items-center u-justify-between">
        <h2><?= number_format($total, 0, ',', ' ') ?> avis</h2>
        <form method="GET" action="/admin/avis" class="u-flex u-items-center u-gap-3">
            <label for="rv-per-page" class="admin-filters-sublabel u-mb-0">Par page</label>
            <select id="rv-per-page" name="per_page" class="admin-filters__select" data-auto-submit aria-label="Résultats par page">
                <?php foreach ([25, 50, 100] as $opt) : ?>
                    <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="admin-card__body admin-card__body--flush">
        <?php if (empty($reviews)) : ?>
            <p class="admin-empty-message">Aucun avis pour le moment.</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Note</th>
                        <th>Auteur</th>
                        <th>Localité</th>
                        <th>Commentaire</th>
                        <th>Email compte</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $rv) :
                        $stars  = (int) ($rv['rating'] ?? 0);
                        $starsHtml = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                        $comment = (string) ($rv['comment'] ?? '');
                        $preview = mb_strlen($comment) > 120
                            ? htmlspecialchars(mb_substr($comment, 0, 120)) . '…'
                            : htmlspecialchars($comment);
                    ?>
                    <tr>
                        <td data-label="Note">
                            <span class="admin-review-stars admin-review-stars--<?= $stars ?>"
                                  title="<?= $stars ?>/5">
                                <?= $starsHtml ?>
                            </span>
                            <span class="u-text-2xs u-text-muted-aa u-ml-1"><?= $stars ?>/5</span>
                        </td>
                        <td data-label="Auteur">
                            <?= htmlspecialchars((string) ($rv['display_name'] ?? '—')) ?>
                        </td>
                        <td data-label="Localité">
                            <?= htmlspecialchars((string) ($rv['location'] ?? '—')) ?>
                        </td>
                        <td data-label="Commentaire" class="u-text-sm">
                            <?= $preview ?>
                        </td>
                        <td data-label="Email compte">
                            <?php if (!empty($rv['email'])) : ?>
                                <a href="/admin/comptes?search=<?= rawurlencode((string) $rv['email']) ?>"
                                   class="u-text-sm">
                                    <?= htmlspecialchars((string) $rv['email']) ?>
                                </a>
                            <?php else : ?>
                                <span class="u-text-muted-aa">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Date" class="u-text-sm u-nowrap">
                            <?= htmlspecialchars(date('d/m/Y', strtotime((string) ($rv['created_at'] ?? '')))) ?>
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
