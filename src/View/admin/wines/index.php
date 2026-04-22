<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages  = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$colorLabels = ['red' => 'Rouge', 'white' => 'Blanc', 'rosé' => 'Rosé', 'sweet' => 'Liquoreux'];

if (!function_exists('wineListUrl')) {
function wineListUrl(int $page, ?string $color, ?string $avail, int $perPage, ?string $vintage = null, ?string $labelName = null, ?string $cuvee = null): string
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
    if ($vintage !== null && $vintage !== '') {
        $q['vintage'] = $vintage;
    }
    if ($labelName !== null && $labelName !== '') {
        $q['label_name'] = $labelName;
    }
    if ($cuvee !== null && $cuvee !== '') {
        $q['cuvee'] = $cuvee;
    }
    return '/admin/vins?' . http_build_query($q);
}
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
<form method="GET" action="/admin/vins" class="admin-filters" data-auto-submit>
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

    <select name="vintage" class="admin-filters__select" aria-label="Filtrer par millésime">
        <option value="">Tous les millésimes</option>
        <?php foreach ($vintages ?? [] as $v) : ?>
            <option value="<?= (int) $v ?>" <?= ($vintage ?? '') === (string)(int) $v ? 'selected' : '' ?>>
                <?= (int) $v ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="label_name" class="admin-filters__select" aria-label="Filtrer par appellation">
        <option value="">Toutes les appellations</option>
        <?php foreach ($labelNames ?? [] as $ln) : ?>
            <option value="<?= htmlspecialchars($ln) ?>" <?= ($labelName ?? '') === $ln ? 'selected' : '' ?>>
                <?= htmlspecialchars($ln) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="cuvee" class="admin-filters__select" aria-label="Filtrer cuvée spéciale">
        <option value="">Toutes les cuvées</option>
        <option value="1" <?= ($cuvee ?? '') === '1' ? 'selected' : '' ?>>Cuvée spéciale</option>
        <option value="0" <?= ($cuvee ?? '') === '0' ? 'selected' : '' ?>>Standard</option>
    </select>

    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ([10, 25, 50] as $n) : ?>
            <option value="<?= $n ?>" <?= (int)$perPage === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="admin-filters__btn js-noscript-only">Filtrer</button>
    <?php if ($color || $available || ($vintage ?? '') !== '' || ($labelName ?? '') !== '' || ($cuvee ?? '') !== '' || $perPage !== 10) : ?>
        <a href="/admin/vins" class="admin-btn admin-btn--outline admin-btn--sm">Réinitialiser</a>
    <?php endif; ?>
</form>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th class="col-hide-tablet">ID</th>
                    <th>Appellation</th>
                    <th>Couleur</th>
                    <th>Millésime</th>
                    <th>Prix</th>
                    <th class="col-hide-tablet">Qté produite</th>
                    <th>Stock</th>
                    <th>Statut</th>
                    <th>Mis en avant</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($wines)) : ?>
                <tr><td colspan="9" style="text-align:center;color:#8a7a60;padding:2rem;">Aucun vin trouvé</td></tr>
            <?php else : ?>
                <?php foreach ($wines as $wine) : ?>
                    <tr>
                        <td class="col-hide-tablet" data-label="ID" style="color:#8a7a60;"><?= (int) $wine['id'] ?></td>
                        <td data-label="Appellation">
                            <strong><?= htmlspecialchars($wine['label_name']) ?></strong>
                            <?php if ($wine['is_cuvee_speciale']) : ?>
                                <span class="badge badge--admin" style="margin-left:0.35rem;">Cuvée</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Couleur"><?= htmlspecialchars($colorLabels[$wine['wine_color']] ?? $wine['wine_color']) ?></td>
                        <td data-label="Millésime"><?= (int) $wine['vintage'] ?></td>
                        <td data-label="Prix"><?= number_format((float) $wine['price'], 2, ',', ' ') ?>&nbsp;€</td>
                        <td class="col-hide-tablet" data-label="Production"><?= number_format((int) $wine['production_quantity'], 0, ',', ' ') ?>&nbsp;bt</td>
                        <td data-label="Stock" <?= (int) $wine['stock_quantity'] === 0 ? 'style="color:#c0392b;font-weight:600;"' : '' ?>>
                            <?= number_format((int) $wine['stock_quantity'], 0, ',', ' ') ?>&nbsp;bt
                        </td>
                        <td data-label="Statut">
                            <?php if (!(bool) $wine['available'] && (int) $wine['stock_quantity'] === 0) : ?>
                                <span class="admin-btn admin-btn--sm admin-btn--out"
                                      style="cursor:default;opacity:0.7;"
                                      title="Stock épuisé — réapprovisionner avant de remettre en vente">
                                    Indisponible
                                </span>
                            <?php else : ?>
                            <button type="button"
                                    class="js-toggle-available admin-btn admin-btn--sm <?= $wine['available'] ? 'admin-btn--available' : 'admin-btn--out' ?>"
                                    data-id="<?= (int) $wine['id'] ?>"
                                    data-label="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage'], ENT_QUOTES) ?>"
                                    data-available="<?= $wine['available'] ? '1' : '0' ?>"
                                    data-csrf="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES) ?>"
                                    aria-label="<?= $wine['available'] ? 'Rendre indisponible' : 'Rendre disponible' ?>">
                                <?= $wine['available'] ? 'Disponible' : 'Indisponible' ?>
                            </button>
                            <?php endif; ?>
                        </td>
                        <td data-label="Mis en avant" style="text-align:center;">
                            <?php if ($wine['available']) : ?>
                            <button type="button"
                                    class="js-featured-btn admin-btn admin-btn--sm <?= $wine['featured'] ? 'admin-btn--gold' : 'admin-btn--outline' ?>"
                                    data-id="<?= (int) $wine['id'] ?>"
                                    data-label="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage'], ENT_QUOTES) ?>"
                                    data-featured="<?= $wine['featured'] ? '1' : '0' ?>"
                                    data-csrf="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES) ?>"
                                    aria-label="<?= $wine['featured'] ? 'Retirer de la homepage' : 'Mettre en avant sur la homepage' ?>">
                                <?= $wine['featured'] ? 'En avant' : 'Mettre en avant' ?>
                            </button>
                            <?php else : ?>
                            <span style="color:#8a7a60;font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="admin-actions">
                                <a href="/admin/vins/<?= (int) $wine['id'] ?>/modifier"
                                   class="admin-btn admin-btn--outline admin-btn--sm">Modifier</a>
                                <button type="button"
                                        class="admin-btn admin-btn--outline admin-btn--sm js-qr-btn"
                                        data-slug="<?= htmlspecialchars($wine['slug'], ENT_QUOTES) ?>"
                                        data-label="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage'], ENT_QUOTES) ?>"
                                        data-cuvee="<?= $wine['is_cuvee_speciale'] ? '1' : '0' ?>"
                                        aria-label="QR code <?= htmlspecialchars($wine['label_name'], ENT_QUOTES) ?>">QR Code</button>
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
            <a href="<?= htmlspecialchars(wineListUrl(max(1, $page - 1), $color, $available, $perPage, $vintage ?? null, $labelName ?? null, $cuvee ?? null)) ?>"
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="<?= htmlspecialchars(wineListUrl($i, $color, $available, $perPage, $vintage ?? null, $labelName ?? null, $cuvee ?? null)) ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(wineListUrl(min($totalPages, $page + 1), $color, $available, $perPage, $vintage ?? null, $labelName ?? null, $cuvee ?? null)) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal QR Code -->
<div id="admin-qr-modal" role="dialog" aria-modal="true" aria-labelledby="admin-qr-modal-title"
     style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.6);
            align-items:center;justify-content:center;">
    <div style="background:var(--color-surface,#fff);border-radius:6px;padding:2rem;
                max-width:360px;width:90%;text-align:center;position:relative;">
        <button type="button" id="admin-qr-modal-close"
                style="position:absolute;top:0.75rem;right:1rem;background:none;border:none;
                       font-size:1.25rem;cursor:pointer;color:var(--color-text-muted);" aria-label="Fermer">✕</button>
        <h3 id="admin-qr-modal-title" style="margin:0 0 0.5rem;font-size:0.9rem;"></h3>
        <div id="admin-qr-spinner" style="display:none;text-align:center;padding:2rem 0;">
            <span style="display:inline-block;width:32px;height:32px;border:3px solid #e0d5c0;
                         border-top-color:var(--color-gold,#a0844d);border-radius:50%;
                         animation:admin-spin 0.7s linear infinite;"></span>
            <p style="margin:0.5rem 0 0;font-size:0.78rem;color:#8a7a60;">Génération en cours…</p>
        </div>
        <style>@keyframes admin-spin { to { transform: rotate(360deg); } }</style>
        <canvas id="admin-qr-modal-canvas" width="300" height="300"
                style="border:1px solid #e0d5c0;border-radius:4px;display:block;margin:0 auto 0.75rem;"></canvas>
        <p id="admin-qr-modal-url" style="font-size:0.68rem;color:#8a7a60;word-break:break-all;margin-bottom:0.75rem;"></p>
        <a id="admin-qr-modal-download" href="#" download=""
           class="admin-btn admin-btn--gold admin-btn--sm"
           style="display:inline-block;">Télécharger</a>
    </div>
</div>

<!-- Modal générique confirmation -->
<div id="admin-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="admin-confirm-modal-title"
     style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.6);
            align-items:center;justify-content:center;">
    <div style="background:var(--color-surface,#fff);border-radius:6px;padding:2rem;
                max-width:440px;width:90%;position:relative;">
        <button type="button" id="admin-confirm-modal-close"
                style="position:absolute;top:0.75rem;right:1rem;background:none;border:none;
                       font-size:1.25rem;cursor:pointer;color:var(--color-text-muted);" aria-label="Fermer">✕</button>
        <h3 id="admin-confirm-modal-title" style="margin:0 0 1rem;font-size:1rem;color:#1a1208;"></h3>
        <p id="admin-confirm-modal-body" style="margin:0 0 1.5rem;font-size:0.9rem;color:#5a4e3a;"></p>
        <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button type="button" id="admin-confirm-modal-cancel"
                    class="admin-btn admin-btn--outline admin-btn--sm">Annuler</button>
            <button type="button" id="admin-confirm-modal-confirm"
                    class="admin-btn admin-btn--gold admin-btn--sm">Confirmer</button>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var pendingAction = null;

    // ── Helpers modal ──────────────────────────────────────────
    function openModal(title, body, btnLabel, btnClass, onConfirm) {
        document.getElementById('admin-confirm-modal-title').textContent = title;
        document.getElementById('admin-confirm-modal-body').textContent  = body;
        var confirmBtn = document.getElementById('admin-confirm-modal-confirm');
        confirmBtn.textContent = btnLabel;
        confirmBtn.className   = 'admin-btn admin-btn--sm ' + btnClass;
        pendingAction = onConfirm;
        document.getElementById('admin-confirm-modal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('admin-confirm-modal').style.display = 'none';
        pendingAction = null;
    }

    document.getElementById('admin-confirm-modal-confirm').addEventListener('click', function () {
        var action = pendingAction;
        closeModal();
        if (action) action();
    });
    document.getElementById('admin-confirm-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('admin-confirm-modal-close').addEventListener('click', closeModal);
    document.getElementById('admin-confirm-modal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    // ── AJAX helper ────────────────────────────────────────────
    function ajaxPost(url, csrf, extraParams, onSuccess) {
        var body = 'csrf_token=' + encodeURIComponent(csrf);
        if (extraParams) body += '&' + extraParams;
        fetch(url, {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
            body:    body,
        })
        .then(function (r) { return r.clone().json().catch(function() { return r.text().then(function(t){ console.error('[admin-wines] réponse non-JSON:', t.slice(0,300)); return {}; }); }); })
        .then(function (data) { if (data.ok) { onSuccess(data); } else { console.warn('[admin-wines] ok=false:', data); } })
        .catch(function (e) { console.error('[admin-wines] fetch error:', e); });
    }

    // ── Mise en avant ──────────────────────────────────────────
    function doSetFeatured(btn) {
        var id     = btn.dataset.id;
        var csrf   = btn.dataset.csrf;
        var action = btn.dataset.featured === '1' ? 'unset' : 'set';
        var body = 'csrf_token=' + encodeURIComponent(csrf) + '&action=' + action;
        fetch('/admin/vins/' + id + '/featured', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
            body: body,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                if (data.error === 'unavailable') {
                    alert('Ce vin est indisponible — il ne peut pas être mis en avant.');
                }
                return;
            }
            document.querySelectorAll('.js-featured-btn').forEach(function (b) {
                b.dataset.featured = '0';
                b.textContent = 'Mettre en avant';
                b.classList.remove('admin-btn--gold');
                b.classList.add('admin-btn--outline');
            });
            if (data.featured) {
                btn.dataset.featured = '1';
                btn.textContent = 'En avant';
                btn.classList.add('admin-btn--gold');
                btn.classList.remove('admin-btn--outline');
            }
        })
        .catch(function (e) { console.error('[admin-wines] featured error:', e); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-featured-btn');
        if (!btn) return;

        if (btn.dataset.featured === '1') {
            // Retirer — confirmation
            openModal(
                'Retirer ce vin de la homepage ?',
                '« ' + btn.dataset.label + ' » ne sera plus mis en avant. Le bloc homepage affichera le fallback automatique.',
                'Retirer',
                'admin-btn--outline',
                function () { doSetFeatured(btn); }
            );
            return;
        }

        var currentFeatured = document.querySelector('.js-featured-btn[data-featured="1"]');
        if (currentFeatured) {
            openModal(
                'Remplacer le vin en avant ?',
                '« ' + currentFeatured.dataset.label + ' » est actuellement mis en avant. Le remplacer par « ' + btn.dataset.label + ' » ?',
                'Remplacer',
                'admin-btn--gold',
                function () { doSetFeatured(btn); }
            );
            return;
        }

        openModal(
            'Mettre ce vin en avant ?',
            '« ' + btn.dataset.label + ' » sera affiché en section "Vin du moment" sur la homepage.',
            'Mettre en avant',
            'admin-btn--gold',
            function () { doSetFeatured(btn); }
        );
    });

    // ── Toggle disponibilité ────────────────────────────────────
    function doToggleAvailable(btn) {
        var id   = btn.dataset.id;
        var csrf = btn.dataset.csrf;
        ajaxPost('/admin/vins/' + id + '/disponibilite', csrf, null, function (data) {
            btn.dataset.available = data.available ? '1' : '0';
            btn.textContent       = data.available ? 'Disponible' : 'Indisponible';
            btn.classList.toggle('admin-btn--available', data.available);
            btn.classList.toggle('admin-btn--out', !data.available);
            btn.setAttribute('aria-label', data.available ? 'Rendre indisponible' : 'Rendre disponible');
            // Si le vin devient indisponible, masquer le bouton "Mettre en avant"
            var row = btn.closest('tr');
            if (row) {
                var featuredCell = row.querySelector('td:nth-child(9)');
                if (featuredCell) {
                    if (!data.available) {
                        featuredCell.innerHTML = '<span style="color:#8a7a60;font-size:0.8rem;">—</span>';
                    } else {
                        var csrf = btn.dataset.csrf;
                        var id   = btn.dataset.id;
                        var label = btn.dataset.label;
                        featuredCell.innerHTML =
                            '<button type="button"' +
                            ' class="js-featured-btn admin-btn admin-btn--sm admin-btn--outline"' +
                            ' data-id="' + id + '"' +
                            ' data-label="' + label.replace(/"/g, '&quot;') + '"' +
                            ' data-featured="0"' +
                            ' data-csrf="' + csrf.replace(/"/g, '&quot;') + '"' +
                            ' aria-label="Mettre en avant sur la homepage">' +
                            'Mettre en avant</button>';
                    }
                }
            }
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-toggle-available');
        if (!btn) return;

        if (btn.dataset.available === '1') {
            // Rendre indisponible — confirmation en rouge
            openModal(
                'Rendre ce vin indisponible ?',
                '« ' + btn.dataset.label + ' » sera retiré du catalogue et supprimé des paniers en cours.',
                'Rendre indisponible',
                'admin-btn--danger',
                function () { doToggleAvailable(btn); }
            );
            return;
        }

        openModal(
            'Remettre ce vin en vente ?',
            '« ' + btn.dataset.label + ' » sera de nouveau disponible dans le catalogue.',
            'Rendre disponible',
            'admin-btn--gold',
            function () { doToggleAvailable(btn); }
        );
    });
}());
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
