<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$statusLabels = [
    'pending'          => 'En attente',
    'paid'             => 'Payée',
    'processing'       => 'En préparation',
    'shipped'          => 'Expédiée',
    'delivered'        => 'Livrée',
    'cancelled'        => 'Annulée',
    'refunded'         => 'Remboursée',
    'return_requested' => 'Retour en cours',
    'refund_refused'   => 'Remboursement refusé',
    'refund_pending'   => 'Remboursement en attente',
];
$allStatuses   = array_keys($statusLabels);

// Rang du flux normal (permet de détecter un retour en arrière)
$statusRank = [
    'pending'          => 0,
    'paid'             => 1,
    'processing'       => 2,
    'shipped'          => 3,
    'delivered'        => 4,
    'return_requested' => 5,
    'refunded'         => 6,
    'refund_refused'   => 6,
    'refund_pending'   => 5,
    'cancelled'        => 99, // toujours terminal, jamais "en arrière"
];
$currentRank = $statusRank[$order['status']] ?? 0;
$paymentLabels = [
    'card'     => 'Carte bancaire',
    'virement' => 'Virement',
    'cheque'   => 'Chèque',
];

// Contenu du panier (snapshot JSON)
$cartItems = json_decode($order['content'] ?? '[]', true) ?? [];
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Commande <code style="font-size:1.1rem;font-variant:normal;"><?= htmlspecialchars($order['order_reference']) ?></code></h1>
    <a href="/admin/commandes" class="admin-btn admin-btn--outline">← Retour</a>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start;">

    <!-- Gauche : infos commande + articles -->
    <div>

        <!-- Articles -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card__header"><h2>Articles commandés</h2></div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Vin</th>
                            <th>Format</th>
                            <th>Qté</th>
                            <th>Prix unit.</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cartItems)) : ?>
                        <tr><td colspan="5" style="text-align:center;color:#8a7a60;padding:1.5rem;">Données indisponibles</td></tr>
                    <?php else : ?>
                        <?php foreach ($cartItems as $item) : ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($item['label_name'] ?? $item['name'] ?? '—') ?>
                                    <?php if (!empty($item['vintage'])) : ?>
                                        <span style="font-size:0.8rem;color:#8a7a60;margin-left:0.25rem;"><?= (int) $item['vintage'] ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['is_cuvee_speciale'])) : ?>
                                        <br><span style="font-size:0.72rem;letter-spacing:1.5px;text-transform:uppercase;color:#c9a84c;font-family:Georgia,serif;">Cuvée Spéciale</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['format'] ?? 'bottle') ?></td>
                                <td><?= (int) ($item['qty'] ?? 0) ?></td>
                                <td><?= number_format((float) ($item['price'] ?? 0), 2, ',', ' ') ?>&nbsp;€</td>
                                <td><?= number_format((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0), 2, ',', ' ') ?>&nbsp;€</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
                $itemsSubtotal    = array_reduce(
                    $cartItems,
                    fn ($c, $i) => $c + (float) ($i['price'] ?? 0) * (int) ($i['qty'] ?? 0),
                    0.0
                );
                $shippingDiscount = (float) ($order['shipping_discount'] ?? 0.0);
                $total            = (float) $order['price'];
            ?>
            <div style="padding:0.75rem 1.5rem;border-top:1px solid rgba(8,8,8,0.06);">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <tr>
                        <th scope="row" style="padding:3px 0;color:#8a7a60;font-weight:normal;text-align:left;">Sous-total articles</th>
                        <td style="padding:3px 0;text-align:right;color:#1a1208;"><?= number_format($itemsSubtotal, 2, ',', ' ') ?>&nbsp;€</td>
                    </tr>
                    <?php if ($shippingDiscount > 0.0) : ?>
                    <tr>
                        <th scope="row" style="padding:3px 0;color:#c9a84c;font-weight:normal;text-align:left;">Remise livraison</th>
                        <td style="padding:3px 0;text-align:right;color:#c9a84c;">− <?= number_format($shippingDiscount, 2, ',', ' ') ?>&nbsp;€</td>
                    </tr>
                    <?php endif; ?>
                    <tr style="border-top:1px solid rgba(8,8,8,0.08);">
                        <th scope="row" style="padding:6px 0 3px;font-weight:700;color:#1a1208;text-align:left;">Total TTC</th>
                        <td style="padding:6px 0 3px;text-align:right;font-weight:700;font-size:0.95rem;color:#1a1208;"><?= number_format($total, 2, ',', ' ') ?>&nbsp;€</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Adresses -->
        <div class="admin-card">
            <div class="admin-card__header"><h2>Adresses</h2></div>
            <div class="admin-card__body" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div>
                    <p style="font-family:var(--font-sans);font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#8a7a60;margin-bottom:0.5rem;">Facturation</p>
                    <p style="font-size:0.875rem;line-height:1.7;color:#1a1208;">
                        <?= htmlspecialchars($order['bill_firstname'] . ' ' . $order['bill_lastname']) ?><br>
                        <?= htmlspecialchars($order['bill_street'] ?? '') ?><br>
                        <?= htmlspecialchars($order['bill_zip'] ?? '') ?> <?= htmlspecialchars($order['bill_city'] ?? '') ?><br>
                        <?= htmlspecialchars($order['bill_country'] ?? '') ?>
                        <?php if ($order['bill_phone'] ?? '') :
                            ?><br><?= htmlspecialchars($order['bill_phone']) ?><?php
                        endif; ?>
                    </p>
                </div>
                <?php if ($order['del_street'] ?? null) : ?>
                <div>
                    <p style="font-family:var(--font-sans);font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#8a7a60;margin-bottom:0.5rem;">Livraison</p>
                    <p style="font-size:0.875rem;line-height:1.7;color:#1a1208;">
                        <?= htmlspecialchars($order['del_firstname'] . ' ' . $order['del_lastname']) ?><br>
                        <?= htmlspecialchars($order['del_street']) ?><br>
                        <?= htmlspecialchars($order['del_zip'] ?? '') ?> <?= htmlspecialchars($order['del_city'] ?? '') ?><br>
                        <?= htmlspecialchars($order['del_country'] ?? '') ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Droite : récap + changement de statut -->
    <div>

        <!-- Récap -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card__header"><h2>Résumé</h2></div>
            <div class="admin-card__body">
                <dl class="admin-dl">
                    <dt>Statut</dt>
                    <dd><span class="badge badge--<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span></dd>
                    <dt>Client</dt>
                    <dd>
                        <?php $isAnonymized = str_ends_with((string) ($order['email'] ?? ''), '@purged.invalid'); ?>
                        <?php if ($isAnonymized) : ?>
                            <em style="color:#8a7a60;">Compte anonymisé (RGPD)</em>
                        <?php else : ?>
                            <?= htmlspecialchars(trim($order['firstname'] . ' ' . $order['lastname'])) ?><br>
                            <span style="font-size:0.8rem;color:#8a7a60;"><?= htmlspecialchars($order['email']) ?></span>
                        <?php endif; ?>
                    </dd>
                    <dt>Paiement</dt>
                    <dd><?= htmlspecialchars($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd>
                    <dt>Montant</dt>
                    <dd><strong><?= number_format((float) $order['price'], 2, ',', ' ') ?>&nbsp;€</strong></dd>
                    <dt>Date</dt>
                    <dd><?= date('d/m/Y à H:i', strtotime($order['ordered_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Changement de statut -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card__header"><h2>Changer le statut</h2></div>
            <div class="admin-card__body">
                <?php if ($order['status'] === 'cancelled') : ?>
                    <p style="font-size:0.85rem;color:#8a7a60;">
                        Cette commande est annulée et ne peut plus être modifiée.
                    </p>
                <?php else : ?>
                    <form id="js-status-form"
                          method="POST"
                          action="/admin/commandes/<?= (int) $order['id'] ?>/statut">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="admin-field" style="margin-bottom:1rem;">
                            <label class="admin-field__label" for="status">Nouveau statut</label>
                            <select id="js-status-select" name="status" class="admin-field__select">
                                <?php foreach ($allStatuses as $s) :
                                    $rank     = $statusRank[$s] ?? 0;
                                    $backward = ($rank < $currentRank && $s !== $order['status'] && $s !== 'cancelled');
                                ?>
                                    <option value="<?= htmlspecialchars($s) ?>"
                                            <?= $order['status'] === $s ? 'selected' : '' ?>
                                            <?= $backward ? 'data-backward="1"' : '' ?>>
                                        <?= $backward ? '⚠ ' : '' ?><?= htmlspecialchars($statusLabels[$s] ?? $s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="js-status-submit" class="admin-btn admin-btn--primary" style="width:100%;">
                            Mettre à jour
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Remboursement en attente (Vision Air) -->
        <?php if ($order['status'] === 'refund_pending') : ?>
        <div class="admin-card" style="margin-bottom:1.5rem;border:1px solid #f59e0b;">
            <div class="admin-card__header" style="background:#fff8e1;">
                <h2 style="color:#92400e;">&#9432; Remboursement à traiter</h2>
            </div>
            <div class="admin-card__body">
                <p style="font-size:0.875rem;color:#78350f;margin-bottom:1rem;">
                    Le remboursement automatique GAE est désactivé. Traitez ce remboursement manuellement dans Vision Air, puis confirmez ci-dessous.
                </p>
                <p style="font-size:0.8rem;color:#92400e;background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.5rem 0.75rem;margin-bottom:1rem;">
                    Ouvrez <strong>Vision Air</strong> depuis votre poste de travail pour effectuer le remboursement, puis confirmez ci-dessous.
                </p>
                <form method="POST" action="/admin/commandes/<?= (int) $order['id'] ?>/statut"
                      data-admin-confirm="Confirmer que le remboursement a bien été effectué dans Vision Air ?">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="status" value="refunded">
                    <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;">
                        Confirmer le remboursement
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Facture -->
        <?php if ($order['status'] !== 'cancelled') : ?>
        <div class="admin-card">
            <div class="admin-card__header"><h2>Facture</h2></div>
            <div class="admin-card__body">
                <?php if (!empty($order['path_invoice'])) : ?>
                    <p style="font-size:0.85rem;color:#3d3425;margin-bottom:1rem;">
                        Facture disponible.
                    </p>
                    <a href="/admin/commandes/<?= (int) $order['id'] ?>/facture/telecharger"
                       target="_blank"
                       class="admin-btn admin-btn--outline"
                       style="display:block;text-align:center;margin-bottom:1rem;">
                        Télécharger / Ouvrir le PDF
                    </a>
                    <div style="background:#fff8e1;border:1px solid #f59e0b;border-radius:6px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.82rem;color:#78350f;">
                        ⚠ Une facture existe déjà pour cette commande. La remplacer ne supprime pas l'ancienne (archivage légal L441-9 C.com.).
                    </div>
                <?php else : ?>
                    <p style="font-size:0.85rem;color:#8a7a60;margin-bottom:1rem;">
                        Aucune facture uploadée.
                    </p>
                <?php endif; ?>
                <form method="POST"
                      action="/admin/commandes/<?= (int) $order['id'] ?>/facture"
                      enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="admin-field" style="margin-bottom:1rem;">
                        <label class="admin-field__label" for="invoice">
                            <?= !empty($order['path_invoice']) ? 'Remplacer la facture' : 'Uploader une facture' ?>
                            <span style="font-weight:400;font-size:0.72rem;">(PDF uniquement)</span>
                        </label>
                        <input type="file" id="invoice" name="invoice" accept="application/pdf"
                               class="admin-field__input" required>
                    </div>
                    <?php if (!empty($order['path_invoice'])) : ?>
                    <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;color:#78350f;margin-bottom:1rem;cursor:pointer;">
                        <input type="checkbox" name="confirm_replace" value="1" required
                               style="width:1rem;height:1rem;accent-color:#b45309;">
                        Je confirme vouloir remplacer la facture existante
                    </label>
                    <?php endif; ?>
                    <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;">
                        Enregistrer la facture
                    </button>
                </form>

                <?php if (!empty($invoiceFiles)) : ?>
                <div style="margin-top:1.25rem;border-top:1px solid rgba(8,8,8,0.06);padding-top:1rem;">
                    <p style="font-family:var(--font-sans);font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#8a7a60;margin-bottom:0.5rem;">
                        Historique des factures
                    </p>
                    <?php foreach ($invoiceFiles as $invFile) : ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;padding:0.35rem 0;border-bottom:1px solid rgba(8,8,8,0.04);font-size:0.78rem;color:#3d3425;">
                        <span>
                            <?= $invFile['source'] === 'auto' ? '⚙' : '⬆' ?>
                            <?= htmlspecialchars($invFile['invoice_number'] ?? basename($invFile['path'])) ?>
                            <span style="color:#8a7a60;margin-left:0.25rem;"><?= date('d/m/Y H:i', strtotime($invFile['uploaded_at'])) ?></span>
                        </span>
                        <a href="<?= htmlspecialchars('/admin/commandes/' . (int) $order['id'] . '/facture/historique/' . (int) $invFile['id']) ?>"
                           target="_blank"
                           style="color:#c9a84c;white-space:nowrap;">PDF</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<!-- Modal confirmation changement de statut -->
<div id="js-status-modal"
     role="dialog" aria-modal="true" aria-labelledby="js-status-modal-title"
     style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(10,8,4,.45);display:none;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:6px;padding:1.75rem 2rem;max-width:400px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18);">
        <h3 id="js-status-modal-title" style="font-size:1rem;font-weight:700;color:#1a1208;margin:0 0 0.75rem;">
            Confirmer le changement de statut
        </h3>
        <p id="js-status-modal-body" style="font-size:0.875rem;color:#3d3425;margin:0 0 1.25rem;line-height:1.6;"></p>
        <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem;">
            <button id="js-status-modal-confirm" class="admin-btn admin-btn--primary" style="width:100%;">Confirmer</button>
            <button id="js-status-modal-cancel" class="admin-btn admin-btn--outline" style="width:100%;">Annuler</button>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var form    = document.getElementById('js-status-form');
    var select  = document.getElementById('js-status-select');
    var trigger = document.getElementById('js-status-submit');
    var modal   = document.getElementById('js-status-modal');
    var body    = document.getElementById('js-status-modal-body');
    var btnOk   = document.getElementById('js-status-modal-confirm');
    var btnCancel = document.getElementById('js-status-modal-cancel');

    if (!form || !trigger || !modal) return;

    var labels = <?= json_encode($statusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var step = 0;

    function openModal(message, okLabel) {
        body.textContent = message;
        btnOk.textContent = okLabel || 'Confirmer';
        modal.style.display = 'flex';
        btnOk.focus();
    }

    function closeModal() {
        modal.style.display = 'none';
        step = 0;
        btnOk.classList.remove('admin-btn--danger');
        btnOk.classList.add('admin-btn--primary');
    }

    trigger.addEventListener('click', function () {
        var newStatus  = select.value;
        var selectedOpt = select.options[select.selectedIndex];
        var isBackward  = selectedOpt && selectedOpt.getAttribute('data-backward') === '1';
        step = 1;
        var label = labels[newStatus] || newStatus;

        if (isBackward) {
            btnOk.classList.remove('admin-btn--primary');
            btnOk.classList.add('admin-btn--danger');
            body.innerHTML = '<span style="color:#dc2626;font-weight:600;">⚠ Retour en arrière</span>'
                + '<br><span style="color:#dc2626;">Vous vous apprêtez à repasser la commande en &laquo;&nbsp;'
                + label + '&nbsp;&raquo;, un statut antérieur au statut actuel.</span>'
                + '<br><br>Cette action est inhabituelle. Confirmez uniquement s\'il s\'agit d\'une correction d\'erreur.';
            btnOk.textContent = 'Confirmer quand même';
            modal.style.display = 'flex';
            btnOk.focus();
        } else {
            openModal(
                'Vous êtes sur le point de passer cette commande en statut « ' + label + ' ». Confirmer ?',
                'Confirmer'
            );
        }
    });

    btnOk.addEventListener('click', function () {
        if (step === 1 && select.value === 'refunded') {
            step = 2;
            btnOk.textContent = 'Je confirme le remboursement';
            btnOk.classList.remove('admin-btn--primary');
            btnOk.classList.add('admin-btn--danger');
            body.textContent = 'Attention : passer en « Remboursé » déclenchera le remboursement automatique du client. Cette action est irréversible.';
            return;
        }
        closeModal();
        form.submit();
    });

    btnCancel.addEventListener('click', closeModal);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
    });
}());
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
