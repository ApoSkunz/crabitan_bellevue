<?php
$pageTitle = __('checkout.confirmation_title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<string, mixed> $order */
/** @var array<int, array<string, mixed>> $items */
/** @var string $lang */

$order  = $order  ?? [];
$items  = $items  ?? [];
$lang   = $lang   ?? 'fr';
$isEn   = ($lang === 'en');

$ref             = (string)  ($order['order_reference'] ?? '');
$paymentMethod   = (string)  ($order['payment_method']  ?? '');
$total           = (float)   ($order['price']           ?? 0.0);
$discount        = (float)   ($order['shipping_discount'] ?? 0.0);
$isDeferred      = in_array($paymentMethod, ['virement', 'cheque'], true);
$newsletterOptIn = (bool) ($newsletterOptIn ?? false);
?>

<main class="page-confirmation" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('checkout.confirmation_title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="confirmation-section container">

        <div class="confirmation-card">
            <div class="confirmation-card__header">
                <svg class="confirmation-card__icon" aria-hidden="true" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <p class="confirmation-card__thanks"><?= htmlspecialchars(__('checkout.thanks_message')) ?></p>
                <p class="confirmation-card__ref">
                    <?= htmlspecialchars(__('checkout.order_reference')) ?>
                    <strong><?= htmlspecialchars($ref) ?></strong>
                </p>
                <?php if ($newsletterOptIn) : ?>
                <p class="confirmation-card__newsletter">
                    <?= htmlspecialchars(__('checkout.newsletter_subscribed')) ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- Mode de paiement -->
            <div class="confirmation-payment-method">
                <?php
                $paymentLabels = [
                    'card'     => $isEn ? 'Credit card' : 'Carte bancaire',
                    'virement' => $isEn ? 'Bank transfer' : 'Virement bancaire',
                    'cheque'   => $isEn ? 'Cheque' : 'Chèque',
                ];
                $paymentLabel = $paymentLabels[$paymentMethod] ?? htmlspecialchars($paymentMethod);
                ?>
                <p class="confirmation-payment-method__label">
                    <strong><?= htmlspecialchars($isEn ? 'Payment method' : 'Mode de paiement') ?></strong> :
                    <?= htmlspecialchars($paymentLabel) ?>
                    <?php if ($paymentMethod === 'card') : ?>
                    — <em><?= htmlspecialchars($isEn ? 'Payment being processed' : 'Paiement en cours de traitement') ?></em>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($isDeferred) : ?>
            <!-- Paiement différé : virement ou chèque -->
            <div class="confirmation-deferred">
                <p class="confirmation-deferred__notice">
                    <?= htmlspecialchars(__('checkout.deferred_order_notice')) ?>
                </p>

                <?php if ($paymentMethod === 'virement') : ?>
                <div class="confirmation-bank-details">
                    <h2 class="confirmation-bank-details__title"><?= htmlspecialchars(__('checkout.iban_label')) ?></h2>
                    <p class="confirmation-bank-details__value"><strong>FR76 3000 6000 0112 3456 7890 189</strong></p>
                    <p class="confirmation-bank-details__hint">
                        <?= htmlspecialchars(__('checkout.iban_reference_hint')) ?> :
                        <strong><?= htmlspecialchars($ref) ?></strong>
                    </p>
                </div>
                <?php else : ?>
                <div class="confirmation-bank-details">
                    <h2 class="confirmation-bank-details__title"><?= htmlspecialchars(__('checkout.cheque_send_to')) ?></h2>
                    <address class="confirmation-bank-details__address">
                        G.F.A Bernard Solane &amp; Fils<br>
                        1 Château Crabitan Bellevue<br>
                        33410 Sainte-Croix-du-Mont
                    </address>
                    <p class="confirmation-bank-details__hint">
                        <?= htmlspecialchars(__('checkout.cheque_ref_mention')) ?> :
                        <strong><?= htmlspecialchars($ref) ?></strong>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Récapitulatif livraison -->
            <?php
            $billingName    = trim("{$order['bill_firstname']} {$order['bill_lastname']}");
            $deliveryName   = trim("{$order['del_firstname']} {$order['del_lastname']}");
            $hasDelivery    = ($deliveryName !== '');
            $showAddr       = $billingName !== '' || $hasDelivery;
            ?>
            <?php if ($showAddr) : ?>
            <div class="confirmation-addresses">
                <?php if ($billingName !== '') : ?>
                <div class="confirmation-address-block">
                    <h2 class="confirmation-address-block__title"><?= htmlspecialchars(__('checkout.billing_address')) ?></h2>
                    <address class="confirmation-address-block__body">
                        <?= htmlspecialchars($billingName) ?><br>
                        <?= htmlspecialchars((string) ($order['bill_street'] ?? '')) ?><br>
                        <?= htmlspecialchars(trim("{$order['bill_zip']} {$order['bill_city']}")) ?><br>
                        <?= htmlspecialchars((string) ($order['bill_country'] ?? '')) ?>
                    </address>
                </div>
                <?php endif; ?>

                <?php if ($hasDelivery) : ?>
                <div class="confirmation-address-block">
                    <h2 class="confirmation-address-block__title"><?= htmlspecialchars(__('checkout.delivery_address')) ?></h2>
                    <address class="confirmation-address-block__body">
                        <?= htmlspecialchars($deliveryName) ?><br>
                        <?= htmlspecialchars((string) ($order['del_street'] ?? '')) ?><br>
                        <?= htmlspecialchars(trim("{$order['del_zip']} {$order['del_city']}")) ?><br>
                        <?= htmlspecialchars((string) ($order['del_country'] ?? '')) ?>
                    </address>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Récapitulatif commande -->
            <?php if (!empty($items)) : ?>
            <div class="confirmation-items">
                <h2 class="confirmation-items__title"><?= htmlspecialchars(__('checkout.summary_title')) ?></h2>
                <ul class="confirmation-items__list">
                    <?php foreach ($items as $item) :
                        $name    = (string) ($item['name']  ?? '');
                        $qty     = (int)   ($item['qty']   ?? 1);
                        $price   = (float) ($item['price'] ?? 0.0);
                        $isCuvee = (bool)  ($item['is_cuvee_speciale'] ?? false);
                    ?>
                    <li class="confirmation-items__item">
                        <span>
                            <?= htmlspecialchars($name) ?>
                            <?php if ($isCuvee) : ?><span class="checkout-summary__item-cuvee"><?= htmlspecialchars($isEn ? 'Special' : 'Cuvée Spéciale') ?></span><?php endif; ?>
                            × <?= $qty ?>
                        </span>
                        <span><?= number_format($price * $qty, 2, ',', ' ') ?>&nbsp;€</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($discount > 0.0) : ?>
                <div class="confirmation-items__discount">
                    <span><?= htmlspecialchars(__('cart.delivery_discount')) ?></span>
                    <span>−&nbsp;<?= number_format($discount, 2, ',', ' ') ?>&nbsp;€</span>
                </div>
                <?php endif; ?>
                <div class="confirmation-items__total">
                    <strong><?= htmlspecialchars($isEn ? 'Total incl. VAT' : 'Total TTC') ?></strong>
                    <strong><?= number_format($total, 2, ',', ' ') ?>&nbsp;€</strong>
                </div>
            </div>
            <?php endif; ?>

            <div class="confirmation-card__actions">
                <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes" class="btn btn--gold" id="js-orders-btn">
                    <?= htmlspecialchars(__('checkout.view_orders')) ?>
                </a>
                <a href="/<?= htmlspecialchars($lang) ?>/vins" class="btn btn--gold">
                    <?= htmlspecialchars(__('checkout.continue_shopping')) ?>
                </a>
            </div>
            <p class="confirmation-card__redirect" id="js-redirect-notice">
                <?= htmlspecialchars($isEn ? 'Redirecting to your orders in ' : 'Redirection vers vos commandes dans ') ?>
                <strong id="js-countdown">15</strong>
                <?= htmlspecialchars($isEn ? ' seconds.' : ' secondes.') ?>
            </p>
        </div>

    </section>
</main>

<script>
(function () {
    var seconds  = 15;
    var counter  = document.getElementById('js-countdown');
    var url      = '/<?= htmlspecialchars($lang) ?>/mon-compte/commandes';
    var timer    = setInterval(function () {
        seconds--;
        if (counter) { counter.textContent = seconds; }
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = url;
        }
    }, 1000);
})();
</script>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
