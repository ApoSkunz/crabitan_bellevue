<?php
$pageTitle = __('cart.title');
defined('WINE_IMG_BASE') || define('WINE_IMG_BASE', '/assets/images/wines/');
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
$crossSellWines   = $crossSellWines   ?? [];
$featuredWine     = $featuredWine     ?? null;
$removedCount     = (int) ($removedCount ?? 0);
/** @var callable(string): string $toWebp */
$toWebp = static fn(string $p): string => (string) preg_replace('/\.[^.]+$/', '.webp', $p);
?>

<?php $checkoutStep = 1; require SRC_PATH . '/View/partials/checkout-stepper.php'; // NOSONAR — php:S2003 : require intentionnel, le partiel est chargé plusieurs fois (vues + tests isolation) ?>

<main class="page-cart" id="main-content">
    <?php if ($removedCount > 0) : ?>
    <div class="alert alert--warning container" role="alert">
        <?= htmlspecialchars(__('cart.wines_removed_notice')) ?>
    </div>
    <?php endif; ?>

    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('cart.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="cart-section container" data-random-wine="<?= htmlspecialchars(json_encode($randomWine ?? null)) ?>"
             data-lang="<?= htmlspecialchars($lang) ?>"
             data-is-en="<?= $isEn ? '1' : '0' ?>">

        <?php if ($isAuth && $isB2B) : ?>
        <!-- ======================================================
             Connecté B2B — message de contact
        ====================================================== -->
        <div class="cart-b2b">
            <h2 class="cart-b2b__title"><?= htmlspecialchars(__('cart.b2b_title')) ?></h2>
            <p class="cart-b2b__message"><?= htmlspecialchars(__('cart.b2b_message')) ?></p>
            <?php
            $b2bSubject = rawurlencode(__('cart.b2b_subject'));
            $b2bEmail   = $_ENV['CONTACT_OWNER_EMAIL'];
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
        $over600Email   = $_ENV['CONTACT_OWNER_EMAIL'];
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
                    <?= htmlspecialchars(__('cart.delivery_discounts')) ?>
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
                <?php $tierLabel = $hasDiscount ? __('cart.tier_next') : __('cart.tier_first'); ?>
                <p class="cart-progress-bar__label">

                    <!-- État normal : progression vers le palier suivant -->
                    <span id="cart-progress-normal" <?= $isMaxTier ? 'hidden' : '' ?>>
                        <span id="cart-progress-discount" <?= ($hasDiscount && !$isMaxTier) ? '' : 'hidden' ?>>
                            <?= htmlspecialchars(__('cart.delivery_discount_label')) ?> : <strong id="cart-progress-discount-value">−&nbsp;<?= number_format($deliveryDiscount, 2, ',', ' ') ?>&nbsp;€</strong><span aria-hidden="true"> · </span>
                        </span><?= htmlspecialchars(__('cart.progress_only')) ?>
                        <strong id="cart-progress-remaining-cases"><?= $progressRemainCases ?></strong>
                        <?= htmlspecialchars(__('cart.progress_cases')) ?>
                        (<span id="cart-progress-remaining"><?= $progressRemain ?></span>&nbsp;<?= htmlspecialchars(__('cart.progress_bottles')) ?>)
                        <span id="cart-progress-tier-label"><?= $tierLabel ?></span>
                    </span>

                    <!-- État palier max : remise maximale confirmée -->
                    <span id="cart-progress-max" <?= $isMaxTier ? '' : 'hidden' ?>>
                        <?= htmlspecialchars(__('cart.progress_max_tier')) ?> : <strong id="cart-progress-max-value">−&nbsp;<?= number_format($deliveryDiscount, 2, ',', ' ') ?>&nbsp;€</strong>
                    </span>

                </p>
                <div class="cart-progress-bar__track"
                     role="progressbar"
                     aria-valuemin="0"
                     aria-valuemax="<?= (int) ($nextTier['min_quantity'] ?? $totalQty) ?>"
                     aria-valuenow="<?= $totalQty ?>"
                     aria-label="<?= htmlspecialchars(__('cart.progress_aria')) ?>">
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
                            <th scope="col" class="cart-table__col-product"><?= htmlspecialchars(__('cart.product')) ?></th>
                            <th scope="col" class="cart-table__col-qty"><?= htmlspecialchars(__('cart.qty')) ?></th>
                            <th scope="col" class="cart-table__col-price"><?= htmlspecialchars(__('cart.unit_price')) ?></th>
                            <th scope="col" class="cart-table__col-subtotal"><?= htmlspecialchars(__('cart.item_total')) ?></th>
                            <th scope="col" class="cart-table__col-action" aria-label="Actions"></th>
                        </tr>
                    </thead>
                    <tbody id="cart-tbody">
                        <?php foreach ($cartItems as $item) : ?>
                        <?php
                            $wineId      = (int)   ($item['wine_id']       ?? 0);
                            $qty         = (int)   ($item['qty']           ?? 1);
                            $name        = (string)($item['name']          ?? '');
                            $image       = (string)($item['image']         ?? '');
                            $price       = (float) ($item['price']         ?? 0.0);
                            $stock       = (int)   ($item['stock_quantity'] ?? 0);
                            $itemTotal   = $price * $qty;
                        ?>
                        <tr class="cart-table__row" data-wine-id="<?= $wineId ?>" data-price="<?= number_format($price, 2, '.', '') ?>" data-stock="<?= $stock ?>">
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
                                <?php if ((int) ($item['vintage'] ?? 0) > 0) : ?>
                                    <span class="cart-table__vintage"><?= (int) $item['vintage'] ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['is_cuvee_speciale'])) : ?>
                                    <span class="cart-table__cuvee"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></span>
                                <?php endif; ?>
                                <?php
                                $stockLimit = (int) ($_ENV['STOCK_ALERTE_LIMIT'] ?? 300);
                                if ($stock > 0 && $stock <= $stockLimit) : // NOSONAR — php:S3973 — syntaxe alternative PHP standard dans les vues
                                ?>
                                    <span class="cart-table__stock-notice">
                                        <?= $stock ?> <?= htmlspecialchars(__('wine.low_stock_qty')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="cart-table__col-qty">
                                <label for="qty-<?= $wineId ?>" class="sr-only">
                                    <?= htmlspecialchars(__('cart.update_qty')) ?> — <?= htmlspecialchars($name) ?>
                                </label>
                                <div class="cart-qty-control">
                                    <button type="button"
                                            class="cart-qty-btn cart-qty-btn--minus js-qty-minus"
                                            data-wine-id="<?= $wineId ?>"
                                            aria-label="<?= htmlspecialchars(__('cart.qty_decrease')) ?>">−</button>
                                    <input type="number"
                                           class="js-cart-qty cart-qty-input"
                                           id="qty-<?= $wineId ?>"
                                           value="<?= $qty ?>"
                                           min="1"
                                           max="<?= $stock > 0 ? $stock : '' ?>"
                                           data-wine-id="<?= $wineId ?>"
                                           aria-label="<?= htmlspecialchars(__('cart.update_qty')) ?>">
                                    <button type="button"
                                            class="cart-qty-btn cart-qty-btn--plus js-qty-plus"
                                            data-wine-id="<?= $wineId ?>"
                                            <?= ($stock > 0 && $qty >= $stock) ? 'disabled' : '' ?>
                                            aria-label="<?= htmlspecialchars(__('cart.qty_increase')) ?>">+</button>
                                </div>
                            </td>
                            <td class="cart-table__col-price cart-table__unit-price" data-label="<?= htmlspecialchars(__('cart.unit_price')) ?>">
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
                                    data-wine-name="<?= htmlspecialchars($name) ?>"
                                    aria-label="<?= htmlspecialchars(__('cart.remove')) ?> — <?= htmlspecialchars($name) ?>"
                                >
                                    <svg class="btn-retirer__icon" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                    </svg>
                                    <span class="btn-retirer__text"><?= htmlspecialchars(__('cart.remove')) ?></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside class="cart-summary">

                <?php /* Sous-total + remise + total */ ?>
                <?php
                $bottleLabel = $totalQty > 1 ? __('cart.progress_bottles') : __('cart.progress_bottle');
                ?>
                <div class="cart-summary__row cart-summary__subtotal-row">
                    <span class="cart-summary__row-label">
                        <?= htmlspecialchars(__('cart.subtotal_label')) ?>
                        (<span id="cart-article-count"><?= $totalQty ?></span>&nbsp;<?= htmlspecialchars($bottleLabel) ?>)
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
                    <p class="cart-summary__label"><?= htmlspecialchars(__('cart.total_ttc')) ?></p>
                    <p class="cart-summary__total" id="cart-total">
                        <?php
                        $finalTotal = $subtotal - $deliveryDiscount;
                        echo number_format(max(0.0, $finalTotal), 2, ',', ' ') . '&nbsp;€';
                        ?>
                    </p>
                </div>

                <a href="/<?= htmlspecialchars($lang) ?>/commande"
                   id="js-cart-checkout-btn"
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
        <?php $randomWine = $randomWine ?? null; ?>
        <div class="cart-empty-state">
            <p class="cart-empty"><?= htmlspecialchars(__('cart.empty')) ?></p>
            <?php if ($featuredWine !== null) : ?>
            <div class="cart-empty-wine">
                <p class="cart-empty-wine__label"><?= htmlspecialchars(__('cart.you_might_enjoy')) ?></p>
                <a href="/<?= htmlspecialchars($lang) ?>/vins/<?= htmlspecialchars((string)($featuredWine['slug'] ?? '')) ?>"
                   class="cart-empty-wine__card">
                    <?php
                        $featuredImagePath = (string)($featuredWine['image_path'] ?? '');
                        $featuredImage     = WINE_IMG_BASE . rawurlencode($featuredImagePath);
                        $featuredImageWebp = WINE_IMG_BASE . rawurlencode($toWebp($featuredImagePath));
                    ?>
                    <picture>
                        <source srcset="<?= $featuredImageWebp ?>" type="image/webp">
                        <img src="<?= $featuredImage ?>"
                             alt="<?= htmlspecialchars((string)($featuredWine['label_name'] ?? '')) ?>"
                             class="cart-empty-wine__img"
                             width="120" height="160" loading="lazy">
                    </picture>
                    <span class="cart-empty-wine__name"><?= htmlspecialchars((string)($featuredWine['label_name'] ?? '')) ?></span>
                    <span class="cart-empty-wine__price"><?= number_format((float)($featuredWine['price'] ?? 0), 2, ',', ' ') ?>&nbsp;€</span>
                </a>
            </div>
            <?php elseif ($randomWine !== null) : ?>
            <div class="cart-empty-wine">
                <p class="cart-empty-wine__label"><?= htmlspecialchars(__('cart.you_might_enjoy')) ?></p>
                <a href="/<?= htmlspecialchars($lang) ?>/vins/<?= htmlspecialchars((string)($randomWine['slug'] ?? '')) ?>"
                   class="cart-empty-wine__card">
                    <?php
                        $randomImagePath = (string)($randomWine['image_path'] ?? '');
                        $wineImage       = WINE_IMG_BASE . rawurlencode($randomImagePath);
                        $wineImageWebp   = WINE_IMG_BASE . rawurlencode($toWebp($randomImagePath));
                    ?>
                    <picture>
                        <source srcset="<?= $wineImageWebp ?>" type="image/webp">
                        <img src="<?= $wineImage ?>"
                             alt="<?= htmlspecialchars((string)($randomWine['label_name'] ?? '')) ?>"
                             class="cart-empty-wine__img"
                             width="120" height="160" loading="lazy">
                    </picture>
                    <span class="cart-empty-wine__name"><?= htmlspecialchars((string)($randomWine['label_name'] ?? '')) ?></span>
                    <span class="cart-empty-wine__price"><?= number_format((float)($randomWine['price'] ?? 0), 2, ',', ' ') ?>&nbsp;€</span>
                </a>
            </div>
            <?php endif; ?>
            <a href="/<?= htmlspecialchars($lang) ?>/vins" class="btn btn--gold">
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
                    <?= htmlspecialchars(__('cart.delivery_discounts')) ?>
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
        <?php if (isset($randomWine) && $randomWine !== null) : ?>
        <div id="cart-guest-recommendation" class="cart-empty-wine" hidden>
            <p class="cart-empty-wine__label"><?= htmlspecialchars(__('cart.you_might_enjoy')) ?></p>
            <a href="/<?= htmlspecialchars($lang) ?>/vins/<?= htmlspecialchars((string)($randomWine['slug'] ?? '')) ?>"
               class="cart-empty-wine__card">
                <?php
                    $guestImagePath = (string)($randomWine['image_path'] ?? '');
                    $wineImage      = WINE_IMG_BASE . rawurlencode($guestImagePath);
                    $wineImageWebp  = WINE_IMG_BASE . rawurlencode($toWebp($guestImagePath));
                ?>
                <picture>
                    <source srcset="<?= $wineImageWebp ?>" type="image/webp">
                    <img src="<?= $wineImage ?>"
                         alt="<?= htmlspecialchars((string)($randomWine['label_name'] ?? '')) ?>"
                         class="cart-empty-wine__img"
                         width="120" height="160" loading="lazy">
                </picture>
                <span class="cart-empty-wine__name"><?= htmlspecialchars((string)($randomWine['label_name'] ?? '')) ?></span>
                <span class="cart-empty-wine__price"><?= number_format((float)($randomWine['price'] ?? 0), 2, ',', ' ') ?>&nbsp;€</span>
            </a>
        </div>
        <?php endif; ?>
        <div id="cart-guest-browse-cta" class="cart-guest-cta" hidden>
            <a href="/<?= htmlspecialchars($lang) ?>/vins" class="btn btn--gold">
                <?= htmlspecialchars(__('cart.browse')) ?>
            </a>
        </div>
        <?php endif; ?>

    <?php if (!empty($crossSellWines)) : ?>
    <section class="cart-cross-sell container" aria-label="<?= htmlspecialchars(__('cart.cross_sell.title')) ?>">
        <h2 class="cart-cross-sell__title"><?= htmlspecialchars(__('cart.cross_sell.title')) ?></h2>
        <div class="cart-cross-sell__grid">
            <?php foreach ($crossSellWines as $cw) :
                $cwSlug    = htmlspecialchars((string) ($cw['slug']       ?? ''));
                $cwName    = htmlspecialchars((string) ($cw['label_name'] ?? ''));
                $cwVintage = (int) ($cw['vintage'] ?? 0);
                $cwPrice   = (float) ($cw['price'] ?? 0.0);
                $cwImage   = rawurlencode((string) ($cw['image_path'] ?? ''));
                $cwId      = (int) ($cw['id'] ?? 0);
                $cwCuvee   = !empty($cw['is_cuvee_speciale']);
            ?>
            <article class="wine-card wine-cross-sell__card">
                <a href="/<?= htmlspecialchars($lang) ?>/vins/<?= $cwSlug ?>"
                   class="wine-card__inner"
                   aria-label="<?= $cwName ?>">
                    <div class="wine-card__image-wrap">
                        <?php $cwWebp = rawurlencode($toWebp((string) ($cw['image_path'] ?? ''))); ?>
                        <picture>
                            <source srcset="/assets/images/wines/<?= $cwWebp ?>" type="image/webp">
                            <img src="/assets/images/wines/<?= $cwImage ?>"
                                 alt="<?= $cwName ?>"
                                 class="wine-card__image"
                                 width="200" height="280"
                                 loading="lazy">
                        </picture>
                    </div>
                </a>
                <div class="wine-card__body">
                    <h3 class="wine-card__name">
                        <?= $cwName ?>
                        <?php if ($cwVintage > 0) : ?>
                        <span class="wine-card__vintage"><?= $cwVintage ?></span>
                        <?php endif; ?>
                    </h3>
                    <?php if ($cwCuvee) : ?>
                    <p class="wine-card__extra"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></p>
                    <?php endif; ?>
                    <div class="wine-card__footer">
                        <strong class="wine-card__price">
                            <?= number_format($cwPrice, 2, ',', ' ') ?>&nbsp;€
                        </strong>
                        <button type="button"
                                class="wine-card__cart js-add-to-cart"
                                data-wine-id="<?= $cwId ?>"
                                data-wine-name="<?= $cwName ?>"
                                data-wine-price="<?= htmlspecialchars(number_format($cwPrice, 2, ',', ' ') . ' €') ?>"
                                data-wine-image="/assets/images/wines/<?= $cwImage ?>"
                                data-wine-cuvee="<?= $cwCuvee ? htmlspecialchars(__('wine.cuvee_speciale')) : '' ?>"
                                aria-label="<?= htmlspecialchars(__('wine.add_to_cart')) ?> — <?= $cwName ?>">
                            &#128722;
                        </button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    </section>

    <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
    window.__pricingRules = <?= json_encode(array_map(function (array $r): array {
        return [
            'min_quantity'   => (int)   $r['min_quantity'],
            'max_quantity'   => $r['max_quantity'] !== null ? (int) $r['max_quantity'] : null,
            'delivery_price' => (float) $r['delivery_price'],
            'price_type'     => (string) $r['price_type'],
        ];
    }, $pricingRules), JSON_UNESCAPED_UNICODE) ?>;
    window.__totalQty = <?= (int) $totalQty ?>;
    window.__isMultiple12Error = <?= ($totalQty > 0 && $totalQty % 12 !== 0) ? 'true' : 'false' ?>;
    window.__multiple12Msg = <?= json_encode(
        $isEn
            ? 'The quantity must be a multiple of 12 bottles.'
            : 'La quantité doit être un multiple de 12 bouteilles.',
        JSON_UNESCAPED_UNICODE
    ) ?>;

    (function () {
        var btn    = document.getElementById('js-cart-checkout-btn');
        var notice = document.querySelector('.cart-notice');
        if (!btn) { return; }

        function getTotalQty() {
            var inputs = document.querySelectorAll('.js-cart-qty');
            var total = 0;
            inputs.forEach(function (input) {
                total += parseInt(input.value, 10) || 0;
            });
            return total > 0 ? total : window.__totalQty;
        }

        function shakeNotice() {
            if (!notice) { return; }
            notice.classList.remove('cart-notice--shake');
            // Force reflow pour relancer l'animation si déjà active
            void notice.offsetWidth;
            notice.classList.add('cart-notice--shake');
        }

        // Intercept le clic sur le bouton commander : shake si invalide
        btn.addEventListener('click', function (e) {
            var qty = getTotalQty();
            if (qty > 0 && qty % 12 !== 0) {
                e.preventDefault();
                if (notice) { notice.classList.add('cart-notice--error'); }
                shakeNotice();
                if (notice) {
                    notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        // Écoute les changements de quantité pour retirer le rouge si corrigé
        function onQtyChange() {
            var qty = getTotalQty();
            if (notice) { notice.classList.toggle('cart-notice--error', qty > 0 && qty % 12 !== 0); }
        }

        document.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('js-cart-qty')) {
                onQtyChange();
            }
        });
        document.addEventListener('click', function (e) {
            if (e.target && (
                e.target.classList.contains('js-qty-minus') ||
                e.target.classList.contains('js-qty-plus') ||
                e.target.classList.contains('js-cart-remove')
            )) {
                setTimeout(onQtyChange, 50);
            }
        });

        // Initialisation (rouge si déjà invalide au chargement)
        if (window.__isMultiple12Error) {
            if (notice) { notice.classList.add('cart-notice--error'); }
        }
    })();
    </script>

    <dialog id="cart-remove-dialog" class="cart-remove-dialog" aria-labelledby="cart-remove-dialog-title">
        <div class="cart-remove-dialog__inner">
            <h2 class="cart-remove-dialog__title" id="cart-remove-dialog-title">
                <?= htmlspecialchars(__('cart.remove_confirm_title')) ?>
            </h2>
            <p class="cart-remove-dialog__body" id="cart-remove-dialog-body"></p>
            <div class="cart-remove-dialog__actions">
                <button type="button" class="btn btn--ghost" id="cart-remove-cancel">
                    <?= htmlspecialchars(__('cart.remove_confirm_cancel')) ?>
                </button>
                <button type="button" class="btn btn--gold" id="cart-remove-confirm">
                    <?= htmlspecialchars(__('cart.remove_confirm_ok')) ?>
                </button>
            </div>
        </div>
    </dialog>
    <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
    window.__cartRemoveConfirmBody = <?= json_encode(htmlspecialchars(__('cart.remove_confirm_body'))) ?>;
    </script>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
