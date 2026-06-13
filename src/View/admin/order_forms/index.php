<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($error) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Bons de commande</h1>
</div>

<!-- Upload form -->
<div class="admin-card u-mb-6">
    <div class="admin-card__header"><h2>Ajouter un bon de commande</h2></div>
    <div class="admin-card__body">
        <form method="POST" action="/admin/bons-de-commande/ajouter"
              enctype="multipart/form-data" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="admin-form__grid">
                <div class="admin-field">
                    <label class="admin-field__label" for="year">Année *</label>
                    <input type="number" id="year" name="year" required
                           min="2000" max="2100"
                           class="admin-field__input"
                           value="<?= date('Y') ?>">
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="label">
                        Version <span class="u-font-normal u-text-xxs">(optionnel — ex : V2, Mise à jour)</span>
                    </label>
                    <input type="text" id="label" name="label"
                           class="admin-field__input"
                           placeholder="V2">
                </div>
                <div class="admin-field admin-field--full">
                    <label class="admin-field__label" for="pdf">Fichier PDF *</label>
                    <input type="file" id="pdf" name="pdf" required
                           accept="application/pdf"
                           class="admin-field__input">
                </div>
            </div>
            <div class="admin-form__actions">
                <button type="submit" class="admin-btn admin-btn--primary">Téléverser</button>
            </div>
        </form>
    </div>
</div>

<!-- Filtres pagination -->
<?php
if (!function_exists('buildPageUrl')) {
function buildPageUrl(int $page, int $perPage): string
{
    $q = http_build_query(array_filter(['page' => $page > 1 ? $page : null, 'per_page' => $perPage !== 10 ? $perPage : null]));
    return '/admin/bons-de-commande' . ($q !== '' ? '?' . $q : '');
}
}
?>
<div class="u-flex u-items-center u-gap-4 u-mb-4 u-flex-wrap">
    <form method="GET" action="/admin/bons-de-commande" class="u-flex u-items-center u-gap-2">
        <label for="per-page-select" class="u-text-sm u-text-soft">Par page :</label>
        <select id="per-page-select" name="per_page" class="admin-field__select admin-perpage-select"
                data-auto-submit="1">
            <?php foreach ([10, 25, 50] as $opt) : ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <span class="u-text-sm u-text-soft u-ml-auto">
        <?= $total ?> bon<?= $total > 1 ? 's' : '' ?> de commande
    </span>
</div>

<!-- Liste -->
<div class="admin-card">
    <div class="admin-card__header"><h2>Historique</h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Année</th>
                    <th>Version</th>
                    <th>Fichier</th>
                    <th>Ajouté le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($forms)) : ?>
                <tr><td colspan="5" class="text-center u-text-soft u-p-8">Aucun bon de commande</td></tr>
            <?php else : ?>
                <?php foreach ($forms as $form) : ?>
                    <tr>
                        <td><strong><?= (int) $form['year'] ?></strong></td>
                        <td><?= htmlspecialchars($form['label'] ?? '—') ?></td>
                        <td class="u-text-sm u-text-soft"><?= htmlspecialchars($form['filename']) ?></td>
                        <td class="u-nowrap"><?= date('d/m/Y', strtotime($form['uploaded_at'])) ?></td>
                        <td class="u-flex u-gap-2 u-items-center">
                            <a href="/admin/bons-de-commande/<?= (int) $form['id'] ?>/telecharger"
                               class="admin-btn admin-btn--outline admin-btn--sm"
                               target="_blank" rel="noopener">Aperçu</a>
                            <form method="POST"
                                  action="/admin/bons-de-commande/<?= (int) $form['id'] ?>/supprimer"
                                  data-admin-confirm="Supprimer ce bon de commande ?"
                                  class="u-m-0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1) : ?>
    <div class="admin-pagination admin-pagination--forms">
        <?php if ($page > 1) : ?>
            <a href="<?= buildPageUrl($page - 1, $perPage) ?>" class="admin-btn admin-btn--outline admin-btn--sm">←</a>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $pages; $p++) : ?>
            <a href="<?= buildPageUrl($p, $perPage) ?>"
               class="admin-btn admin-btn--sm<?= $p === $page ? ' admin-btn--primary' : ' admin-btn--outline' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
        <?php if ($page < $pages) : ?>
            <a href="<?= buildPageUrl($page + 1, $perPage) ?>" class="admin-btn admin-btn--outline admin-btn--sm">→</a>
        <?php endif; ?>
        <span class="u-ml-auto u-text-78 u-text-soft">
            Page <?= $page ?> / <?= $pages ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
