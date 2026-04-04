<?php
$pageTitle = __('cart.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<int, array<string, mixed>> $cartItems */
/** @var bool $isAuth */
/** @var bool $isB2B */
/** @var array<string, mixed>|null $pricingRule */
/** @var array<string, mixed>|null $nextTier */
/** @var int $totalQty */
/** @var float $subtotal */
/** @var float $deliveryDiscount */
/** @var array<int, array<string, mixed>> $pricingRules */
$cartItems        = $cartItems        ?? [];
$isAuth           = $isAuth           ?? false;
$isB2B            = $isB2B            ?? false;
$pricingRule      = $pricingRule      ?? null;
$nextTier         = $nextTier         ?? null;
$totalQty         = $totalQty         ?? 0;
$lang             = $lang             ?? 'fr';
$isEn             = ($lang === 'en');
$subtotal         = $subtotal         ?? 0.0;
$deliveryDiscount = $deliveryDiscount ?? 0.0;
$pricingRules     = $pricingRules     ?? [];
?>

<main class="page-cart" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('cart.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="cart-section container">

        <?php if ($isAuth && $isB2B) : ?>
        <!-- ======================================================
             Connecté B2B — message de contact
        ====================================================== -->
        <div class="cart-b2b">
            <h2 class="cart-b2b__title"><?= htmlspecialchars(__('cart.b2b_title')) ?></h2>
            <p class="cart-b2b__message"><?= htmlspecialchars(__('cart.b2b_message')) ?></p>
            <?php
            $b2bSubject = rawurlencode(__('cart.b2b_subject'));
            $b2bEmail   = 'contact@crabitanbellevue.fr';
            ?>
            <a
                href="mailto:<?= htmlspecialchars($b2bEmail) ?>?subject=<?= $b2bSubject ?>"
                class="cart-b2b__mailto"
            ><?= htmlspecialchars($b2bEmail) ?></a>
        </div>

        <?php elseif ($isAuth && !empty($cartItems)) : ?>
        <!-- ======================================================
             Connecté — panier non vide
        ====================================================== -->

        <!-- Notice multiple de 12 (statique — toujours visible) -->
        <div class="cart-notice" role="note">
            <p><?= htmlspecialchars(__('cart.min_12_notice')) ?></p>
        </div>

        <!-- Notice > 600 bouteilles -->
        <?php
        $over600Subject = rawurlencode(__('cart.over_600_subject'));
        $over600Email   = 'contact@crabitanbellevue.fr';
        ?>
        <div class="cart-notice cart-notice--contact js-notice-600"
             id="cart-notice-600"
             role="note"
             <?= $totalQty <= 600 ? 'hidden' : '' ?>>
            <p><?= htmlspecialchars(__('cart.over_600_notice')) ?></p>
            <a href="mailto:<?= htmlspecialchars($over600Email) ?>?subject=<?= $over600Subject ?>"
               class="cart-notice__mailto"><?= htmlspecialchars($over600Email) ?></a>
        </div>

        <!-- Accordéon remises livraison + barre de progression palier -->
        <?php if (!empty($pricingRules)) : ?>
        <div class="cart-delivery-section">
            <details class="cart-delivery-accordion">
                <summary class="cart-delivery-accordion__trigger">
                    <?= htmlspecialchars($isEn ? 'Delivery discounts' : 'Remises livraison') ?>
                    <span class="cart-delivery-accordion__arrow" aria-hidden="true">▾</span>
                </summary>
                <ul class="cart-delivery-accordion__list">
                    <?php foreach ($pricingRules as $rule) :
                        $labelData = json_decode((string) ($rule['label'] ?? '{}'), true) ?? [];
                        $ruleLabel = (string) ($labelData[$lang] ?? $labelData['fr'] ?? '');
                    ?>
                    <li class="cart-delivery-accordion__item <?= ($pricingRule !== null && (int)$rule['id'] === (int)($pricingRule['id'] ?? 0)) ? 'cart-delivery-accordion__item--active' : '' ?>">
                        <?= htmlspecialchars($ruleLabel) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <?php
            $progressHidden      = true;
            $progressRemain      = 0;
            $progressRemainCases = 0;
            $progressPct         = 0;
            $isMaxTier           = false;
            $hasDiscount         = $deliveryDiscount > 0.0;

            if ($totalQty > 0) {
                if ($nextTier !== null) {
                    $progressHidden      = false;
                    $fromQty             = $pricingRule !== null ? (int) ($pricingRule['min_quantity'] ?? 0) : 0;
                    $toQty               = (int) $nextTier['min_quantity'];
                    $progressRemain      = $toQty - $totalQty;
                    $progressRemainCases = (int) ceil($progressRemain / 12);
                    $range               = $toQty - $fromQty;
                    $progressPct         = $range > 0 ? min(100, (int) round(($totalQty - $fromQty) / $range * 100)) : 0;
                } elseif ($pricingRule !== null) {
                    // Palier maximum atteint
                    $progressHidden = false;
                    $progressPct    = 100;
                    $isMaxTier      = true;
                }
            }
            ?>
            <div class="cart-progress-bar"
                 id="cart-progress-bar"
                 <?= $progressHidden ? 'hidden' : '' ?>>
                <p class="cart-progress-bar__label">

                    <!-- État normal : progression vers le palier suivant -->
                    <span id="cart-progress-normal" <?= $isMaxTier ? 'hidden' : '' ?>>
                        <span id="cart-progress-discount" <?= ($hasDiscount && !$isMaxTier) ? '' : 'hidden' ?>>
                            <?= $isEn ? 'Delivery discount' : 'Remise livraison' ?> : <strong id="cart-progress-discount-value">−&nbsp;<?= number_format($deliveryDiscount, 2, ',', ' ') ?>&nbsp;€</strong><span aria-hidden="true"> · </span>
                        </span><?= $isEn ? 'Only' : 'Plus que' ?>
                        <strong id="cart-progress-remaining-cases"><?= $progressRemainCases ?></strong>
                        <?= $isEn ? 'case(s) of 12' : 'caisse(s) de 12' ?>
                        (<span id="cart-progress-remaining"><?= $progressRemain ?></span>&nbsp;<?= $isEn ? 'bottles' : 'bouteilles' ?>)
                        <span id="cart-progress-tier-label"><?= $isEn ? ($hasDiscount ? 'for the next tier' : 'for your delivery discount') : ($hasDiscount ? 'pour le palier suivant' : 'pour votre remise livraison') ?></span>
                    </span>

                    <!-- État palier max : remise maximale confirmée -->
                    <span id="cart-progress-max" <?= $isMaxTier ? '' : 'hidden' ?>>
                        <?= $isEn ? 'Maximum delivery discount tier reached' : 'Palier remise livraison maximum atteint' ?> : <strong id="cart-progress-max-value">−&nbsp;<?= number_format($deliveryDiscount, 2, ',', ' ') ?>&nbsp;€</strong>
                    </span>

                </p>
                <div class="cart-progress-bar__track"
                     role="progressbar"
                     aria-valuemin="0"
                     aria-valuemax="<?= (int) ($nextTier['min_quantity'] ?? $totalQty) ?>"
                     aria-valuenow="<?= $totalQty ?>"
                     aria-label="<?= htmlspecialchars($isEn ? 'Delivery discount progress' : 'Progression remise livraison') ?>">
                    <div class="cart-progress-bar__fill"
                         id="cart-progress-fill"
                         style="width:<?= $progressPct ?>%"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="cart-layout">
            <div class="cart-table-wrap">
                <table class="cart-table" aria-label="<?= htmlspecialchars(__('cart.title')) ?>">
                    <thead>
                        <tr>
                            <th scope="col" class="cart-table__col-image" aria-label="Image"></th>
                            <th scope="col" class="cart-table__col-product">Produit</th>
                            <th scope="col" class="cart-table__col-qty"><?= htmlspecialchars(__('cart.qty')) ?></th>
                            <th scope="col" class="cart-table__col-price">Prix unitaire</th>
                            <th scope="col" class="cart-table__col-subtotal"><?= htmlspecialchars(__('cart.item_total')) ?></th>
                            <th scope="col" class="cart-table__col-action" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody id="cart-tbody">
                        <?php foreach ($cartItems as $item) : ?>
                        <?php
                            $wineId      = (int)   ($item['wine_id'] ?? 0);
                            $qty         = (int)   ($item['qty']     ?? 1);
                            $name        = (string)($item['name']    ?? '');
                            $image       = (string)($item['image']   ?? '');
                            $price       = (float) ($item['price']   ?? 0.0);
                            $itemTotal   = $price * $qty;
                        ?>
                        <tr class="cart-table__row" data-wine-id="<?= $wineId ?>" data-price="<?= number_format($price, 2, '.', '') ?>">
                            <td class="cart-table__col-image">
                                <?php if ($image !== '') : ?>
                                <img
                                    src="<?= htmlspecialchars($image) ?>"
                                    alt="<?= htmlspecialchars($name) ?>"
                                    class="cart-table__img"
                                    width="64"
                                    height="64"
                                    loading="lazy"
                                >
                                <?php endif; ?>
                            </td>
                            <td class="cart-table__col-product cart-table__name">
                                <?= htmlspecialchars($name) ?>
                            </td>
                            <td class="cart-table__col-qty">
                                <label for="qty-<?= $wineId ?>" class="sr-only">
                                    <?= htmlspecialchars(__('cart.update_qty')) ?> — <?= htmlspecialchars($name) ?>
                                </label>
                                <div class="cart-qty-control">
                                    <button type="button"
                                            class="cart-qty-btn cart-qty-btn--minus js-qty-minus"
                                            data-wine-id="<?= $wineId ?>"
                                            aria-label="<?= htmlspecialchars($isEn ? 'Decrease quantity' : 'Diminuer la quantité') ?>">−</button>
                                    <input type="number"
                                           class="js-cart-qty cart-qty-input"
                                           id="qty-<?= $wineId ?>"
                                           value="<?= $qty ?>"
                                           min="1"
                                           data-wine-id="<?= $wineId ?>"
                                           aria-label="<?= htmlspecialchars(__('cart.update_qty')) ?>">
                                    <button type="button"
                                            class="cart-qty-btn cart-qty-btn--plus js-qty-plus"
                                            data-wine-id="<?= $wineId ?>"
                                            aria-label="<?= htmlspecialchars($isEn ? 'Increase quantity' : 'Augmenter la quantité') ?>">+</button>
                                </div>
                            </td>
                            <td class="cart-table__col-price cart-table__unit-price" data-label="Prix unitaire">
                                <?= number_format($price, 2, ',', ' ') ?>&nbsp;€
                            </td>
                            <td class="cart-table__col-subtotal cart-table__subtotal js-cart-subtotal" data-label="<?= htmlspecialchars(__('cart.item_total')) ?>">
                                <?= number_format($itemTotal, 2, ',', ' ') ?>&nbsp;€
                            </td>
                            <td class="cart-table__col-action">
                                <button
                                    type="button"
                                    class="js-cart-remove btn-retirer"
                                    data-wine-id="<?= $wineId ?>"
                                    aria-label="<?= htmlspecialchars(__('cart.remove')) ?> — <?= htmlspecialchars($name) ?>"
                                >
                                    <svg class="btn-retirer__icon" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                    </svg>
                                    <span class="btn-retirer__text"><?= htmlspecialchars($isEn ? 'Remove' : 'Retirer') ?></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside class="cart-summary">

                <?php /* Sous-total + remise + total */ ?>
                <div class="cart-summary__row cart-summary__subtotal-row">
                    <span class="cart-summary__row-label">
                        <?= $isEn ? 'Subtotal' : 'Sous-total' ?>
                        (<span id="cart-article-count"><?= $totalQty ?></span>&nbsp;<?= $isEn ? 'bottle' . ($totalQty > 1 ? 's' : '') : 'bouteille' . ($totalQty > 1 ? 's' : '') ?>)
                    </span>
                    <span class="cart-summary__row-value js-cart-subtotal-display"
                          id="cart-subtotal"
                          <?= ($deliveryDiscount > 0.0) ? 'style="text-decoration:line-through;opacity:.55;font-size:.9em"' : '' ?>>
                        <?= number_format($subtotal, 2, ',', ' ') ?>&nbsp;€
                    </span>
                </div>

                <?php if ($deliveryDiscount > 0.0) : ?>
                <div class="cart-summary__row cart-summary__discount-row js-discount-row">
                    <span class="cart-summary__row-label cart-summary__row-label--discount">
                        <?= htmlspecialchars(__('cart.delivery_discount')) ?>
                    </span>
                    <span class="cart-summary__row-value cart-summary__row-value--discount js-discount-value">
                        &minus;&nbsp;<?= number_format($deliveryDiscount, 2, ',', ' ') ?>&nbsp;€
                    </span>
                </div>
                <?php else : ?>
                <div class="cart-summary__row cart-summary__discount-row js-discount-row" hidden>
                    <span class="cart-summary__row-label cart-summary__row-label--discount">
                        <?= htmlspecialchars(__('cart.delivery_discount')) ?>
                    </span>
                    <span class="cart-summary__row-value cart-summary__row-value--discount js-discount-value">
                        &minus;&nbsp;0,00&nbsp;€
                    </span>
                </div>
                <?php endif; ?>

                <div class="cart-summary__row cart-summary__final-row">
                    <p class="cart-summary__label"><?= htmlspecialchars($isEn ? 'Total incl. VAT' : 'Total TTC') ?></p>
                    <p class="cart-summary__total" id="cart-total">
                        <?php
                        $finalTotal = $subtotal - $deliveryDiscount;
                        echo number_format(max(0.0, $finalTotal), 2, ',', ' ') . '&nbsp;€';
                        ?>
                    </p>
                </div>

                <a href="/<?= htmlspecialchars($lang) ?>/commande"
                   id="cart-checkout-btn"
                   class="btn btn--gold cart-summary__cta">
                    <?= htmlspecialchars(__('cart.checkout')) ?>
                </a>
                <p id="cart-checkout-over600-error" class="cart-checkout-error" hidden>
                    <?= htmlspecialchars(__('cart.over_600_checkout_error')) ?>
                </p>
            </aside>
        </div>

        <?php elseif ($isAuth && empty($cartItems)) : ?>
        <!-- ======================================================
             Connecté — panier vide
        ====================================================== -->
        <div class="cart-empty-state">
            <p class="cart-empty"><?= htmlspecialchars(__('cart.empty')) ?></p>
            <a href="/<?= htmlspecialchars($lang) ?>/vins" class="btn btn--outline">
                <?= htmlspecialchars(__('cart.browse')) ?>
            </a>
        </div>

        <?php else : ?>
        <!-- ======================================================
             Invité — rendu JS depuis cookie cb-cart
        ====================================================== -->

        <!-- Notice multiple de 12 (invité — statique) -->
        <div class="cart-notice" role="note">
            <p><?= htmlspecialchars(__('cart.min_12_notice')) ?></p>
        </div>

        <?php if (!empty($pricingRules)) : ?>
        <div class="cart-guest-delivery">
            <details class="cart-delivery-accordion">
                <summary class="cart-delivery-accordion__trigger">
                    <?= htmlspecialchars($isEn ? 'Delivery discounts' : 'Remises livraison') ?>
                    <span class="cart-delivery-accordion__arrow" aria-hidden="true">▾</span>
                </summary>
                <ul class="cart-delivery-accordion__list">
                    <?php foreach ($pricingRules as $rule) :
                        $labelData = json_decode((string) ($rule['label'] ?? '{}'), true) ?? [];
                        $ruleLabel = (string) ($labelData[$lang] ?? $labelData['fr'] ?? '');
                    ?>
                    <li class="cart-delivery-accordion__item"><?= htmlspecialchars($ruleLabel) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
        <?php endif; ?>

        <div id="cart-guest" class="cart-guest" aria-live="polite" aria-label="Contenu du panier">
            <p class="cart-guest__loading"><?= htmlspecialchars(__('cart.empty')) ?></p>
        </div>

        <div class="cart-guest-cta">
            <p class="cart-guest-cta__text"><?= htmlspecialchars(__('cart.login_to_order')) ?></p>
            <button type="button" class="btn btn--gold js-open-login-from-cart">
                <?= htmlspecialchars(__('cart.login_cta')) ?>
            </button>
        </div>
        <?php endif; ?>

    </section>

    <script>
    window.__pricingRules = <?= json_encode(array_map(function (array $r): array {
        return [
            'min_quantity'   => (int)   $r['min_quantity'],
            'max_quantity'   => $r['max_quantity'] !== null ? (int) $r['max_quantity'] : null,
            'delivery_price' => (float) $r['delivery_price'],
            'price_type'     => (string) $r['price_type'],
        ];
    }, $pricingRules), JSON_UNESCAPED_UNICODE) ?>;
    </script>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
