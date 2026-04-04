<?php
$pageTitle = __('checkout.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>> $addresses */
/** @var array<string, string>            $errors */
/** @var array<string, mixed>             $post */
/** @var array<int, array<string, mixed>> $removedItems */
/** @var float   $subtotal */
/** @var float   $deliveryDiscount */
/** @var float   $total */
/** @var int     $totalQty */
/** @var string  $csrfToken */
/** @var string  $lang */
/** @var string  $deliveryDelay */

$items            = $items            ?? [];
$addresses        = $addresses        ?? [];
$errors           = $errors           ?? [];
$post             = $post             ?? [];
$removedItems     = $removedItems     ?? [];
$subtotal         = $subtotal         ?? 0.0;
$deliveryDiscount = $deliveryDiscount ?? 0.0;
$total            = $total            ?? 0.0;
$totalQty         = $totalQty         ?? 0;
$csrfToken        = $csrfToken        ?? '';
$lang             = $lang             ?? 'fr';
$deliveryDelay    = $deliveryDelay    ?? '3 à 5 jours ouvrés';
$isEn             = ($lang === 'en');

$billingAddresses  = array_values(array_filter($addresses, fn($a) => $a['type'] === 'billing'));
$deliveryAddresses = array_values(array_filter($addresses, fn($a) => $a['type'] === 'delivery'));

$countries = [
    'France', 'Belgique', 'Luxembourg', 'Suisse', 'Allemagne', 'Pays-Bas',
    'Espagne', 'Italie', 'Royaume-Uni', 'Portugal', 'Autriche', 'Irlande',
    'Danemark', 'Suède', 'Norvège', 'Finlande', 'Pologne', 'République tchèque',
    'Hongrie', 'Roumanie', 'Grèce', 'Croatie', 'Slovénie', 'Slovaquie',
];

/**
 * @param string               $prefix  Préfixe des champs ('', 'del_'…)
 * @param array<string, mixed> $post    Valeurs postées précédentes
 * @param string               $idSuffix Suffixe pour les IDs HTML
 */
$renderAddressForm = function (string $prefix, array $post, string $idSuffix) use ($countries): void {
    $v   = fn(string $k): string => htmlspecialchars((string) ($post[$prefix . $k] ?? ''));
    $sel = fn(bool $c): string => $c ? ' selected' : '';
    $civ = (string) ($post[$prefix . 'civility'] ?? 'M');
    ?>
    <div class="form-group">
        <label for="addr-civility-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.civility')) ?></label>
        <select id="addr-civility-<?= $idSuffix ?>" name="<?= $prefix ?>civility" autocomplete="honorific-prefix">
            <option value="M"<?= $sel($civ === 'M') ?>><?= htmlspecialchars(__('account.civility_m')) ?></option>
            <option value="F"<?= $sel($civ === 'F') ?>><?= htmlspecialchars(__('account.civility_f')) ?></option>
            <option value="other"<?= $sel($civ === 'other') ?>><?= htmlspecialchars(__('account.civility_other')) ?></option>
        </select>
    </div>
    <div class="form-group">
        <label for="addr-firstname-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.firstname')) ?> *</label>
        <input type="text" id="addr-firstname-<?= $idSuffix ?>" name="<?= $prefix ?>firstname"
               value="<?= $v('firstname') ?>" required autocomplete="given-name">
    </div>
    <div class="form-group">
        <label for="addr-lastname-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.lastname')) ?> *</label>
        <input type="text" id="addr-lastname-<?= $idSuffix ?>" name="<?= $prefix ?>lastname"
               value="<?= $v('lastname') ?>" required autocomplete="family-name">
    </div>
    <div class="form-group">
        <label for="addr-country-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.address_country')) ?> *</label>
        <input type="text" id="addr-country-<?= $idSuffix ?>" name="<?= $prefix ?>country"
               value="<?= $v('country') ?: 'France' ?>"
               required autocomplete="country-name"
               list="addr-country-list-<?= $idSuffix ?>">
        <datalist id="addr-country-list-<?= $idSuffix ?>">
            <?php foreach ($countries as $c) : ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <div class="form-group form-group--row">
        <div>
            <label for="addr-zip-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.address_zip')) ?> *</label>
            <input type="text" id="addr-zip-<?= $idSuffix ?>" name="<?= $prefix ?>zip_code"
                   value="<?= $v('zip_code') ?>" required autocomplete="postal-code" maxlength="10">
        </div>
        <div>
            <label for="addr-city-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.address_city')) ?> *</label>
            <input type="text" id="addr-city-<?= $idSuffix ?>" name="<?= $prefix ?>city"
                   value="<?= $v('city') ?>" required autocomplete="address-level2">
        </div>
    </div>
    <div class="form-group">
        <label for="addr-street-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.address_street')) ?> *</label>
        <input type="text" id="addr-street-<?= $idSuffix ?>" name="<?= $prefix ?>street"
               value="<?= $v('street') ?>" required autocomplete="address-line1">
    </div>
    <div class="form-group">
        <label for="addr-phone-<?= $idSuffix ?>"><?= htmlspecialchars(__('account.address_phone')) ?> *</label>
        <input type="tel" id="addr-phone-<?= $idSuffix ?>" name="<?= $prefix ?>phone"
               value="<?= $v('phone') ?>" required autocomplete="tel" placeholder="+33 6 12 34 56 78">
    </div>
    <?php
};
?>

<main class="page-checkout" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('checkout.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="checkout-section container">

        <?php if (!empty($removedItems)) : ?>
        <div class="checkout-notice checkout-notice--warning" role="alert">
            <p><?= htmlspecialchars(__('checkout.removed_items_notice')) ?></p>
            <ul class="checkout-notice__list">
                <?php foreach ($removedItems as $r) : ?>
                <li><?= htmlspecialchars((string) ($r['name'] ?? __('checkout.unknown_product'))) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)) : ?>
        <div class="checkout-notice checkout-notice--error" role="alert">
            <ul class="checkout-notice__list">
                <?php foreach ($errors as $err) : ?>
                <li><?= htmlspecialchars((string) $err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST"
              action="/<?= htmlspecialchars($lang) ?>/commande/paiement"
              class="checkout-form"
              novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="checkout-layout">

                <!-- ================================================
                     Colonne gauche : adresses + paiement
                ================================================ -->
                <div class="checkout-main">

                    <!-- === Adresse de livraison === -->
                    <section class="checkout-card" aria-labelledby="checkout-delivery-title">
                        <h2 class="checkout-card__title" id="checkout-delivery-title">
                            <?= htmlspecialchars(__('checkout.delivery_address')) ?>
                        </h2>

                        <?php if (!empty($deliveryAddresses)) : ?>
                        <div class="checkout-address-list">
                            <?php foreach ($deliveryAddresses as $addr) :
                                $isChecked = empty($post)
                                    ? ($addr === reset($deliveryAddresses))
                                    : ((int)($post['delivery_address_id'] ?? 0) === (int)$addr['id']);
                            ?>
                            <label class="checkout-address-card <?= $isChecked ? 'checkout-address-card--selected' : '' ?>">
                                <input type="radio" name="delivery_address_id"
                                       value="<?= (int) $addr['id'] ?>"
                                       class="checkout-address-card__radio js-delivery-radio"
                                       <?= $isChecked ? 'checked' : '' ?>>
                                <span class="checkout-address-card__body">
                                    <strong><?= htmlspecialchars("{$addr['firstname']} {$addr['lastname']}") ?></strong><br>
                                    <?= htmlspecialchars($addr['street']) ?><br>
                                    <?= htmlspecialchars("{$addr['zip_code']} {$addr['city']}") ?><br>
                                    <?= htmlspecialchars($addr['country']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                            <label class="checkout-address-card checkout-address-card--new <?= ((int)($post['delivery_address_id'] ?? -1) === 0) ? 'checkout-address-card--selected' : '' ?>">
                                <input type="radio" name="delivery_address_id"
                                       value="0"
                                       class="checkout-address-card__radio js-delivery-radio"
                                       <?= ((int)($post['delivery_address_id'] ?? -1) === 0) ? 'checked' : '' ?>>
                                <span class="checkout-address-card__body checkout-address-card__body--new">
                                    + <?= htmlspecialchars(__('checkout.new_address')) ?>
                                </span>
                            </label>
                        </div>
                        <?php else : ?>
                        <input type="hidden" name="delivery_address_id" value="0">
                        <?php endif; ?>

                        <div class="checkout-new-address js-new-delivery-form" <?= (empty($deliveryAddresses) || (int)($post['delivery_address_id'] ?? -1) === 0) ? '' : 'hidden' ?>>
                            <h3 class="checkout-new-address__title"><?= htmlspecialchars(__('checkout.new_address_delivery')) ?></h3>
                            <?php $renderAddressForm('del_', $post, 'del'); ?>
                        </div>
                    </section>

                    <!-- === Adresse de facturation === -->
                    <section class="checkout-card" aria-labelledby="checkout-billing-title">
                        <h2 class="checkout-card__title" id="checkout-billing-title">
                            <?= htmlspecialchars(__('checkout.billing_address')) ?>
                        </h2>

                        <label class="checkout-same-address">
                            <input type="checkbox"
                                   name="same_address"
                                   value="1"
                                   id="checkout-same-address"
                                   class="js-same-address"
                                   <?= (empty($post) || array_key_exists('same_address', $post)) ? 'checked' : '' ?>>
                            <?= htmlspecialchars(__('checkout.same_as_delivery')) ?>
                        </label>

                        <div class="js-billing-fields" <?= (empty($post) || array_key_exists('same_address', $post)) ? 'hidden' : '' ?>>
                            <?php if (!empty($billingAddresses)) : ?>
                            <div class="checkout-address-list">
                                <?php foreach ($billingAddresses as $addr) :
                                    $isChecked = empty($post)
                                        ? ($addr === reset($billingAddresses))
                                        : ((int)($post['billing_address_id'] ?? 0) === (int)$addr['id']);
                                ?>
                                <label class="checkout-address-card <?= $isChecked ? 'checkout-address-card--selected' : '' ?>">
                                    <input type="radio" name="billing_address_id"
                                           value="<?= (int) $addr['id'] ?>"
                                           class="checkout-address-card__radio js-billing-radio"
                                           <?= $isChecked ? 'checked' : '' ?>>
                                    <span class="checkout-address-card__body">
                                        <strong><?= htmlspecialchars("{$addr['firstname']} {$addr['lastname']}") ?></strong><br>
                                        <?= htmlspecialchars($addr['street']) ?><br>
                                        <?= htmlspecialchars("{$addr['zip_code']} {$addr['city']}") ?><br>
                                        <?= htmlspecialchars($addr['country']) ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                                <label class="checkout-address-card checkout-address-card--new <?= ((int)($post['billing_address_id'] ?? -1) === 0) ? 'checkout-address-card--selected' : '' ?>">
                                    <input type="radio" name="billing_address_id"
                                           value="0"
                                           class="checkout-address-card__radio js-billing-radio"
                                           <?= ((int)($post['billing_address_id'] ?? -1) === 0) ? 'checked' : '' ?>>
                                    <span class="checkout-address-card__body checkout-address-card__body--new">
                                        + <?= htmlspecialchars(__('checkout.new_address')) ?>
                                    </span>
                                </label>
                            </div>
                            <?php else : ?>
                            <input type="hidden" name="billing_address_id" value="0">
                            <?php endif; ?>

                            <div class="checkout-new-address js-new-billing-form" <?= (empty($billingAddresses) || (int)($post['billing_address_id'] ?? -1) === 0) ? '' : 'hidden' ?>>
                                <h3 class="checkout-new-address__title"><?= htmlspecialchars(__('checkout.new_address_billing')) ?></h3>
                                <?php $renderAddressForm('', $post, 'bill'); ?>
                            </div>
                        </div>
                    </section>

                    <!-- === Mode de paiement === -->
                    <section class="checkout-card" aria-labelledby="checkout-payment-title">
                        <h2 class="checkout-card__title" id="checkout-payment-title">
                            <?= htmlspecialchars(__('checkout.payment_method')) ?>
                        </h2>

                        <?php
                        $selectedPayment = (string) ($post['payment_method'] ?? 'card');
                        $paymentMethods  = [
                            'card'     => __('checkout.payment_card'),
                            'virement' => __('checkout.payment_virement'),
                            'cheque'   => __('checkout.payment_cheque'),
                        ];
                        ?>
                        <div class="checkout-payment-tabs" role="tablist">
                            <?php foreach ($paymentMethods as $method => $label) : ?>
                            <button type="button"
                                    role="tab"
                                    class="checkout-payment-tab js-payment-tab <?= $selectedPayment === $method ? 'checkout-payment-tab--active' : '' ?>"
                                    data-method="<?= htmlspecialchars($method) ?>"
                                    aria-selected="<?= $selectedPayment === $method ? 'true' : 'false' ?>"
                                    aria-controls="checkout-panel-<?= htmlspecialchars($method) ?>">
                                <?= htmlspecialchars($label) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <input type="hidden" name="payment_method" id="checkout-payment-method" value="<?= htmlspecialchars($selectedPayment) ?>">

                        <!-- Carte bancaire -->
                        <div id="checkout-panel-card" role="tabpanel"
                             class="checkout-payment-panel <?= $selectedPayment !== 'card' ? 'checkout-payment-panel--hidden' : '' ?>">
                            <p class="checkout-payment-info"><?= htmlspecialchars(__('checkout.payment_card_info')) ?></p>
                            <p class="checkout-payment-info checkout-payment-info--sub"><?= htmlspecialchars(__('checkout.payment_card_redirect')) ?></p>
                        </div>

                        <!-- Virement bancaire -->
                        <div id="checkout-panel-virement" role="tabpanel"
                             class="checkout-payment-panel <?= $selectedPayment !== 'virement' ? 'checkout-payment-panel--hidden' : '' ?>">
                            <p class="checkout-payment-info checkout-payment-info--deferred">
                                <?= htmlspecialchars(__('checkout.payment_deferred_notice')) ?>
                            </p>
                            <div class="checkout-bank-details">
                                <p class="checkout-bank-details__beneficiary"><strong>G.F.A Bernard Solane &amp; Fils</strong></p>
                                <p class="checkout-bank-details__label"><?= htmlspecialchars(__('checkout.iban_label')) ?></p>
                                <p class="checkout-bank-details__value"><strong>FR76 3000 6000 0112 3456 7890 189</strong></p>
                                <p class="checkout-bank-details__hint"><?= htmlspecialchars(__('checkout.iban_reference_hint')) ?></p>
                            </div>
                        </div>

                        <!-- Chèque -->
                        <div id="checkout-panel-cheque" role="tabpanel"
                             class="checkout-payment-panel <?= $selectedPayment !== 'cheque' ? 'checkout-payment-panel--hidden' : '' ?>">
                            <p class="checkout-payment-info checkout-payment-info--deferred">
                                <?= htmlspecialchars(__('checkout.payment_deferred_notice')) ?>
                            </p>
                            <div class="checkout-bank-details">
                                <p class="checkout-bank-details__label"><?= htmlspecialchars(__('checkout.cheque_order')) ?></p>
                                <address class="checkout-bank-details__address">
                                    G.F.A Bernard Solane &amp; Fils<br>
                                    1 Château Crabitan Bellevue<br>
                                    33410 Sainte-Croix-du-Mont
                                </address>
                            </div>
                        </div>
                    </section>

                    <!-- === CGV + Newsletter === -->
                    <section class="checkout-card checkout-card--terms">
                        <div class="checkout-terms">
                            <label class="checkout-terms__label <?= isset($errors['cgv']) ? 'checkout-terms__label--error' : '' ?>">
                                <input type="checkbox" name="cgv" value="1" id="checkout-cgv"
                                       <?= ($post['cgv'] ?? '') ? 'checked' : '' ?> required>
                                <?php
                                $cgvUrl   = "/{$lang}/conditions-generales-de-vente";
                                $cgvLabel = '<a href="' . htmlspecialchars($cgvUrl) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars(__('checkout.cgv_link')) . '</a>';
                                echo sprintf(__('checkout.cgv_accept'), $cgvLabel);
                                ?>
                                <span class="checkout-terms__required" aria-hidden="true"> *</span>
                            </label>
                            <?php if (isset($errors['cgv'])) : ?>
                            <p class="checkout-terms__error" role="alert"><?= htmlspecialchars($errors['cgv']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="checkout-terms">
                            <label class="checkout-terms__label checkout-terms__label--optional">
                                <input type="checkbox" name="newsletter" value="1"
                                       <?= ($post['newsletter'] ?? '') ? 'checked' : '' ?>>
                                <?= htmlspecialchars(__('checkout.newsletter_optin')) ?>
                            </label>
                        </div>

                        <p class="checkout-terms__legal">
                            <?= htmlspecialchars(__('checkout.delivery_delay_notice')) ?>
                            <strong><?= htmlspecialchars($deliveryDelay) ?></strong>
                            <?= htmlspecialchars($isEn ? 'working days.' : 'jours ouvrés.') ?>
                        </p>
                    </section>

                </div><!-- /.checkout-main -->

                <!-- ================================================
                     Colonne droite : récapitulatif
                ================================================ -->
                <aside class="checkout-summary" aria-labelledby="checkout-summary-title">
                    <div class="checkout-card">
                        <h2 class="checkout-card__title" id="checkout-summary-title">
                            <?= htmlspecialchars(__('checkout.summary_title')) ?>
                        </h2>

                        <ul class="checkout-summary__items">
                            <?php foreach ($items as $item) :
                                $name  = (string) ($item['name']  ?? '');
                                $qty   = (int)   ($item['qty']   ?? 1);
                                $price = (float) ($item['price'] ?? 0.0);
                            ?>
                            <li class="checkout-summary__item">
                                <span class="checkout-summary__item-name">
                                    <?= htmlspecialchars($name) ?>
                                    <span class="checkout-summary__item-qty">× <?= $qty ?></span>
                                    <span class="checkout-summary__item-unit"><?= number_format($price, 2, ',', ' ') ?>&nbsp;€/btl</span>
                                </span>
                                <span class="checkout-summary__item-price">
                                    <?= number_format($price * $qty, 2, ',', ' ') ?>&nbsp;€
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="checkout-summary__totals">
                            <div class="checkout-summary__row">
                                <span><?= htmlspecialchars($isEn ? 'Subtotal' : 'Sous-total') ?></span>
                                <span><?= number_format($subtotal, 2, ',', ' ') ?>&nbsp;€</span>
                            </div>
                            <?php if ($deliveryDiscount > 0.0) : ?>
                            <div class="checkout-summary__row checkout-summary__row--discount">
                                <span><?= htmlspecialchars(__('cart.delivery_discount')) ?></span>
                                <span>−&nbsp;<?= number_format($deliveryDiscount, 2, ',', ' ') ?>&nbsp;€</span>
                            </div>
                            <?php endif; ?>
                            <div class="checkout-summary__row checkout-summary__row--total">
                                <strong><?= htmlspecialchars($isEn ? 'Total incl. VAT' : 'Total TTC') ?></strong>
                                <strong><?= number_format($total, 2, ',', ' ') ?>&nbsp;€</strong>
                            </div>
                        </div>

                        <p class="checkout-summary__qty">
                            <?= $totalQty ?> <?= htmlspecialchars($isEn ? ($totalQty > 1 ? 'bottles' : 'bottle') : ($totalQty > 1 ? 'bouteilles' : 'bouteille')) ?>
                        </p>

                        <?php if (isset($errors['multiple_12'])) : ?>
                        <p class="checkout-summary__error checkout-summary__error--multiple12" role="alert">
                            <?= htmlspecialchars($errors['multiple_12']) ?>
                        </p>
                        <?php endif; ?>
                        <button type="submit" class="btn btn--gold checkout-summary__cta">
                            <?= htmlspecialchars(__('checkout.submit')) ?>
                        </button>

                        <a href="/<?= htmlspecialchars($lang) ?>/panier" class="checkout-summary__back">
                            ← <?= htmlspecialchars(__('checkout.back_to_cart')) ?>
                        </a>
                    </div>
                </aside>

            </div><!-- /.checkout-layout -->
        </form>
    </section>
</main>

<script>
(function () {
    'use strict';

    // ---- Onglets paiement ----
    var tabs   = document.querySelectorAll('.js-payment-tab');
    var hidden = document.getElementById('checkout-payment-method');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var method = this.getAttribute('data-method');
            if (hidden) { hidden.value = method; }

            tabs.forEach(function (t) {
                var m      = t.getAttribute('data-method');
                var panel  = document.getElementById('checkout-panel-' + m);
                t.classList.remove('checkout-payment-tab--active');
                t.setAttribute('aria-selected', 'false');
                if (panel) { panel.classList.add('checkout-payment-panel--hidden'); }
            });

            this.classList.add('checkout-payment-tab--active');
            this.setAttribute('aria-selected', 'true');
            var active = document.getElementById('checkout-panel-' + method);
            if (active) { active.classList.remove('checkout-payment-panel--hidden'); }
        });
    });

    // ---- Adresse facturation identique à la livraison ----
    var sameChk    = document.getElementById('checkout-same-address');
    var billFields = document.querySelector('.js-billing-fields');

    function toggleBilling() {
        if (billFields) { billFields.hidden = sameChk ? sameChk.checked : true; }
    }
    if (sameChk) { sameChk.addEventListener('change', toggleBilling); }

    // ---- Validation code postal livraison (France métropolitaine) ----
    var zipDelInput  = document.getElementById('addr-zip-del');
    var zipErrMsg    = <?= json_encode($isEn
        ? 'Delivery is only available to mainland France (excluding Corsica and overseas territories).'
        : 'La livraison n\'est disponible qu\'en France métropolitaine (hors Corse et DOM-TOM).') ?>;

    function isMainlandFranceZip(zip) {
        if (!/^\d{5}$/.test(zip)) { return true; }
        var dept = parseInt(zip.substring(0, 2), 10);
        if (dept === 20) { return false; }
        if (dept >= 97)  { return false; }
        return true;
    }

    function validateDelZip() {
        if (!zipDelInput) { return; }
        var existing = zipDelInput.parentNode.querySelector('.js-zip-error');
        if (isMainlandFranceZip(zipDelInput.value)) {
            zipDelInput.setCustomValidity('');
            if (existing) { existing.remove(); }
        } else {
            zipDelInput.setCustomValidity(zipErrMsg);
            if (!existing) {
                var err = document.createElement('p');
                err.className = 'checkout-zip-error js-zip-error';
                err.style.color = '#e74c3c';
                err.style.fontSize = '0.82rem';
                err.style.marginTop = '0.3rem';
                err.textContent = zipErrMsg;
                zipDelInput.parentNode.appendChild(err);
            }
        }
    }

    if (zipDelInput) { zipDelInput.addEventListener('input', validateDelZip); }

    // ---- Nouvelle adresse facturation ----
    var billingRadios  = document.querySelectorAll('.js-billing-radio');
    var newBillForm    = document.querySelector('.js-new-billing-form');

    function toggleNewBill() {
        if (!newBillForm) { return; }
        var newSelected = document.querySelector('.js-billing-radio[value="0"]:checked');
        newBillForm.hidden = !newSelected;
    }

    billingRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            toggleNewBill();
            updateSelected(this, 'billing_address_id');
        });
    });
    toggleNewBill();

    // ---- Nouvelle adresse livraison ----
    var deliveryRadios = document.querySelectorAll('.js-delivery-radio');
    var newDelForm     = document.querySelector('.js-new-delivery-form');

    function toggleNewDel() {
        if (!newDelForm) { return; }
        var newSelected = document.querySelector('.js-delivery-radio[value="0"]:checked');
        newDelForm.hidden = !newSelected;
    }

    deliveryRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            toggleNewDel();
            updateSelected(this, 'delivery_address_id');
        });
    });
    toggleNewDel();

    // ---- Highlight carte sélectionnée ----
    function updateSelected(radio, name) {
        document.querySelectorAll('[name="' + name + '"]').forEach(function (r) {
            var card = r.closest('.checkout-address-card');
            if (card) { card.classList.toggle('checkout-address-card--selected', r.checked); }
        });
    }

    document.querySelectorAll('.checkout-address-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            var radio = this.querySelector('input[type="radio"]');
            if (radio && e.target !== radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });


})();
</script>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
