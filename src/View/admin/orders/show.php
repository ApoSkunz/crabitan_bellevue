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
    'ready_for_pickup' => 'Prêt au retrait',
    'collected'        => 'Retiré',
];
// Workflow statuts selon mode de livraison
$isPickup       = ($order['delivery_method'] ?? 'shipping') === 'pickup';
$shippingFlow   = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'return_requested', 'refund_pending', 'refund_refused', 'refunded', 'cancelled'];
$pickupFlow     = ['pending', 'paid', 'processing', 'ready_for_pickup', 'collected', 'cancelled', 'refunded'];
$allStatuses    = $isPickup ? $pickupFlow : $shippingFlow;

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
    'ready_for_pickup' => 3, // Click & Collect : équivalent "expédiée" dans le flux
    'collected'        => 4, // Click & Collect : équivalent "livrée" dans le flux
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
    <h1>Commande <code class="u-text-lg u-font-normal-style"><?= htmlspecialchars($order['order_reference']) ?></code></h1>
    <a href="/admin/commandes" class="admin-btn admin-btn--outline">← Retour</a>
</div>

<div class="admin-order-layout admin-order-grid">

    <!-- Gauche : infos commande + articles -->
    <div>

        <!-- Articles -->
        <div class="admin-card u-mb-6">
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
                        <tr><td colspan="5" class="text-center u-text-soft admin-td-pad">Données indisponibles</td></tr>
                    <?php else : ?>
                        <?php foreach ($cartItems as $item) : ?>
                            <tr>
                                <td data-label="Vin">
                                    <?= htmlspecialchars($item['label_name'] ?? $item['name'] ?? '—') ?>
                                    <?php if (!empty($item['vintage'])) : ?>
                                        <span class="u-text-sm u-text-soft u-ml-1"><?= (int) $item['vintage'] ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['is_cuvee_speciale'])) : ?>
                                        <br><span class="admin-cuvee-badge">Cuvée Spéciale</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Format"><?= htmlspecialchars($item['format'] ?? 'bottle') ?></td>
                                <td data-label="Qté"><?= (int) ($item['qty'] ?? 0) ?></td>
                                <td data-label="Prix unit."><?= number_format((float) ($item['price'] ?? 0), 2, ',', ' ') ?>&nbsp;€</td>
                                <td data-label="Sous-total"><?= number_format((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0), 2, ',', ' ') ?>&nbsp;€</td>
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
            <?php $priceMismatch = abs(($itemsSubtotal - $shippingDiscount) - $total) > 0.01 && $itemsSubtotal > 0.0; ?>
            <?php if ($priceMismatch) : ?>
            <div class="u-info-box admin-order-price-note">
                <strong>Prix indicatifs actuels.</strong> Les prix unitaires affichés sont les prix actuels du catalogue, pas les prix historiques de la commande. Seul le <strong>Total TTC</strong> fait foi pour cette commande.
            </div>
            <?php endif; ?>
            <div class="admin-order-totals">
                <table class="u-table-full">
                    <tr>
                        <th scope="row" class="admin-order-totals__label"><?= 'Sous-total articles' . ($priceMismatch ? ' <span class="u-text-warning">(indicatif)</span>' : '') ?></th>
                        <td class="u-text-right <?= $priceMismatch ? 'u-text-soft' : 'u-text-primary' ?>"><?= number_format($itemsSubtotal, 2, ',', ' ') ?>&nbsp;€</td>
                    </tr>
                    <?php if ($shippingDiscount > 0.0) : ?>
                    <tr>
                        <th scope="row" class="admin-order-totals__label u-text-gold">
                            <?= $isPickup ? 'Remise Click &amp; Collect' : 'Remise livraison' ?>
                        </th>
                        <td class="u-text-right u-text-gold">− <?= number_format($shippingDiscount, 2, ',', ' ') ?>&nbsp;€</td>
                    </tr>
                    <?php endif; ?>
                    <tr class="u-border-top-md">
                        <th scope="row" class="admin-order-totals__total-label">Total TTC</th>
                        <td class="u-text-right u-font-bold u-text-95 u-text-primary"><?= number_format($total, 2, ',', ' ') ?>&nbsp;€</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Adresses -->
        <div class="admin-card">
            <div class="admin-card__header"><h2>Adresses</h2></div>
            <div class="admin-card__body admin-addresses-grid u-grid-2">
                <div>
                    <p class="admin-addr-label">Facturation</p>
                    <p class="u-text-85 u-leading-loose u-text-primary">
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
                    <p class="admin-addr-label">Livraison</p>
                    <p class="u-text-85 u-leading-loose u-text-primary">
                        <?= htmlspecialchars($order['del_firstname'] . ' ' . $order['del_lastname']) ?><br>
                        <?= htmlspecialchars($order['del_street']) ?><br>
                        <?= htmlspecialchars($order['del_zip'] ?? '') ?> <?= htmlspecialchars($order['del_city'] ?? '') ?><br>
                        <?= htmlspecialchars($order['del_country'] ?? '') ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (trim((string) ($order['customer_note'] ?? '')) !== '') : ?>
        <!-- Commentaire client -->
        <div class="admin-card">
            <div class="admin-card__header"><h2>Commentaire client</h2></div>
            <div class="admin-card__body">
                <p class="u-text-85 u-leading-loose u-text-primary"><?= nl2br(htmlspecialchars($order['customer_note'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Droite : récap + changement de statut -->
    <div>

        <!-- Récap -->
        <div class="admin-card u-mb-6">
            <div class="admin-card__header"><h2>Résumé</h2></div>
            <div class="admin-card__body">
                <dl class="admin-dl">
                    <dt>Statut</dt>
                    <dd><span class="badge badge--<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span></dd>
                    <dt>Mode de livraison</dt>
                    <dd>
                        <?php $isPickup = ($order['delivery_method'] ?? 'shipping') === 'pickup'; ?>
                        <?php if ($isPickup) : ?>
                            <span class="badge-pickup">Retrait au château</span>
                        <?php else : ?>
                            <span class="badge-delivery">Livraison à domicile</span>
                        <?php endif; ?>
                    </dd>
                    <dt>Client</dt>
                    <dd>
                        <?php $isAnonymized = str_ends_with((string) ($order['email'] ?? ''), '@purged.invalid'); ?>
                        <?php if ($isAnonymized) : ?>
                            <em class="u-text-soft">Compte anonymisé (RGPD)</em>
                        <?php else : ?>
                            <?= htmlspecialchars(trim($order['firstname'] . ' ' . $order['lastname'])) ?><br>
                            <span class="u-text-sm u-text-soft"><?= htmlspecialchars($order['email']) ?></span>
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
        <div class="admin-card u-mb-6">
            <div class="admin-card__header"><h2>Changer le statut</h2></div>
            <div class="admin-card__body">
                <?php if ($order['status'] === 'cancelled') : ?>
                    <p class="u-text-85 u-text-soft">
                        Cette commande est annulée et ne peut plus être modifiée.
                    </p>
                <?php else : ?>
                    <form id="js-status-form"
                          method="POST"
                          action="/admin/commandes/<?= (int) $order['id'] ?>/statut">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div class="admin-field u-mb-4">
                            <label class="admin-field__label" for="js-status-select">Nouveau statut</label>
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
                        <button type="button" id="js-status-submit" class="admin-btn admin-btn--primary u-w-full">
                            Mettre à jour
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Remboursement en attente (Vision Air) -->
        <?php if ($order['status'] === 'refund_pending') : ?>
        <div class="admin-card u-mb-6 admin-card--warning-border">
            <div class="admin-card__header admin-card-header--warning">
                <h2 class="u-text-warning-dk">&#9432; Remboursement à traiter</h2>
            </div>
            <div class="admin-card__body">
                <p class="u-text-85 u-text-warning-dkr u-mb-4">
                    Le remboursement automatique GAE est désactivé. Traitez ce remboursement manuellement dans Vision Air, puis confirmez ci-dessous.
                </p>
                <p class="u-text-sm u-text-warning-dkr u-alert-warning u-mb-4">
                    Ouvrez <strong>Vision Air</strong> depuis votre poste de travail pour effectuer le remboursement, puis confirmez ci-dessous.
                </p>
                <form method="POST" action="/admin/commandes/<?= (int) $order['id'] ?>/statut"
                      data-admin-confirm="Confirmer que le remboursement a bien été effectué dans Vision Air ?">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="status" value="refunded">
                    <button type="submit" class="admin-btn admin-btn--primary u-w-full">
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
                    <p class="u-text-85 u-text-gray u-mb-4">
                        Facture disponible.
                    </p>
                    <a href="/admin/commandes/<?= (int) $order['id'] ?>/facture/telecharger"
                       target="_blank"
                       class="admin-btn admin-btn--outline u-block text-center u-mb-4">
                        Télécharger / Ouvrir le PDF
                    </a>
                    <div class="u-alert-warning u-mb-4">
                        ⚠ Une facture existe déjà pour cette commande. La remplacer ne supprime pas l'ancienne (archivage légal L441-9 C.com.).
                    </div>
                <?php else : ?>
                    <p class="u-text-85 u-text-soft u-mb-4">
                        Aucune facture uploadée.
                    </p>
                <?php endif; ?>
                <form method="POST"
                      action="/admin/commandes/<?= (int) $order['id'] ?>/facture"
                      enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="admin-field u-mb-4">
                        <label class="admin-field__label" for="invoice">
                            <?= !empty($order['path_invoice']) ? 'Remplacer la facture' : 'Uploader une facture' ?>
                            <span class="u-font-normal u-text-xxs">(PDF uniquement)</span>
                        </label>
                        <input type="file" id="invoice" name="invoice" accept="application/pdf"
                               class="admin-field__input" required>
                    </div>
                    <?php if (!empty($order['path_invoice'])) : ?>
                    <label class="u-flex u-items-center u-gap-2 u-text-sm-plus u-text-warning-dkr u-mb-4 u-cursor-pointer">
                        <input type="checkbox" name="confirm_replace" value="1" required
                               class="admin-replace-checkbox">
                        Je confirme vouloir remplacer la facture existante
                    </label>
                    <?php endif; ?>
                    <button type="submit" class="admin-btn admin-btn--primary u-w-full">
                        Enregistrer la facture
                    </button>
                </form>

                <?php if (!empty($invoiceFiles)) : ?>
                <div class="admin-invoice-history">
                    <p class="admin-addr-label">
                        Historique des factures
                    </p>
                    <?php foreach ($invoiceFiles as $invFile) : ?>
                    <div class="admin-invoice-row">
                        <span>
                            <?= $invFile['source'] === 'auto' ? '⚙' : '⬆' ?>
                            <?= htmlspecialchars($invFile['invoice_number'] ?? basename($invFile['path'])) ?>
                            <span class="u-text-soft u-ml-1"><?= date('d/m/Y H:i', strtotime($invFile['uploaded_at'])) ?></span>
                        </span>
                        <a href="<?= htmlspecialchars('/admin/commandes/' . (int) $order['id'] . '/facture/historique/' . (int) $invFile['id']) ?>"
                           target="_blank"
                           class="u-text-gold u-nowrap">PDF</a>
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
     class="u-hidden admin-status-modal">
    <div class="admin-status-modal__box">
        <h3 id="js-status-modal-title" class="admin-status-modal__title">
            Confirmer le changement de statut
        </h3>
        <p id="js-status-modal-body" class="u-text-85 u-text-gray u-mb-5 u-leading-relaxed"></p>
        <div class="u-flex-col u-flex u-gap-2 u-mt-1">
            <button id="js-status-modal-confirm" class="admin-btn admin-btn--primary u-w-full">Confirmer</button>
            <button id="js-status-modal-cancel" class="admin-btn admin-btn--outline u-w-full">Annuler</button>
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
        modal.classList.remove('u-hidden');
        btnOk.focus();
    }

    function closeModal() {
        modal.classList.add('u-hidden');
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
            body.innerHTML = '<span class="u-text-error-alt u-font-semibold">⚠ Retour en arrière</span>'
                + '<br><span class="u-text-error-alt">Vous vous apprêtez à repasser la commande en &laquo;&nbsp;'
                + label + '&nbsp;&raquo;, un statut antérieur au statut actuel.</span>'
                + '<br><br>Cette action est inhabituelle. Confirmez uniquement s\'il s\'agit d\'une correction d\'erreur.';
            btnOk.textContent = 'Confirmer quand même';
            modal.classList.remove('u-hidden');
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
        if (e.key === 'Escape' && !modal.classList.contains('u-hidden')) closeModal();
    });
}());
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
