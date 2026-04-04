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

$ref           = (string)  ($order['order_reference'] ?? '');
$paymentMethod = (string)  ($order['payment_method']  ?? '');
$total         = (float)   ($order['price']           ?? 0.0);
$discount      = (float)   ($order['shipping_discount'] ?? 0.0);
$isDeferred    = in_array($paymentMethod, ['virement', 'cheque'], true);
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
                        Château Crabitan Bellevue<br>
                        2 Crabitan, 33410 Sainte-Croix-du-Mont
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
                        $name  = (string) ($item['name']  ?? '');
                        $qty   = (int)   ($item['qty']   ?? 1);
                        $price = (float) ($item['price'] ?? 0.0);
                    ?>
                    <li class="confirmation-items__item">
                        <span><?= htmlspecialchars($name) ?> × <?= $qty ?></span>
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
                <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes" class="btn btn--gold">
                    <?= htmlspecialchars(__('checkout.view_orders')) ?>
                </a>
                <a href="/<?= htmlspecialchars($lang) ?>/vins" class="btn btn--outline">
                    <?= htmlspecialchars(__('checkout.continue_shopping')) ?>
                </a>
            </div>
        </div>

    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
