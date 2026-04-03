<?php
$pageTitle = __('cart.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<int, array<string, mixed>> $cartItems */
/** @var bool $isAuth */
$cartItems = $cartItems ?? [];
$isAuth    = $isAuth    ?? false;
?>

<main class="page-cart" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('cart.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="cart-section container">

        <?php if ($isAuth && !empty($cartItems)) : ?>
        <!-- ======================================================
             Connecté — panier non vide
        ====================================================== -->
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
                            $wineId    = (int)   ($item['wine_id'] ?? 0);
                            $qty       = (int)   ($item['qty']     ?? 1);
                            $name      = (string)($item['name']    ?? '');
                            $image     = (string)($item['image']   ?? '');
                            $price     = (float) ($item['price']   ?? 0.0);
                            $subtotal  = $price * $qty;
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
                                <input
                                    type="number"
                                    id="qty-<?= $wineId ?>"
                                    name="qty"
                                    class="js-cart-qty"
                                    value="<?= $qty ?>"
                                    min="1"
                                    max="96"
                                    data-wine-id="<?= $wineId ?>"
                                    aria-label="<?= htmlspecialchars(__('cart.update_qty')) ?>"
                                >
                            </td>
                            <td class="cart-table__col-price cart-table__unit-price" data-label="Prix unitaire">
                                <?= number_format($price, 2, ',', ' ') ?>&nbsp;€
                            </td>
                            <td class="cart-table__col-subtotal cart-table__subtotal js-cart-subtotal" data-label="<?= htmlspecialchars(__('cart.item_total')) ?>">
                                <?= number_format($subtotal, 2, ',', ' ') ?>&nbsp;€
                            </td>
                            <td class="cart-table__col-action">
                                <button
                                    type="button"
                                    class="js-cart-remove btn-icon btn-icon--danger"
                                    data-wine-id="<?= $wineId ?>"
                                    aria-label="<?= htmlspecialchars(__('cart.remove')) ?> — <?= htmlspecialchars($name) ?>"
                                >
                                    &times;
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside class="cart-summary">
                <p class="cart-summary__label"><?= htmlspecialchars(__('cart.order_total')) ?></p>
                <p class="cart-summary__total" id="cart-total">
                    <?php
                    $grandTotal = array_sum(array_map(
                        fn(array $i): float => (float)($i['price'] ?? 0.0) * (int)($i['qty'] ?? 1),
                        $cartItems
                    ));
                    echo number_format($grandTotal, 2, ',', ' ') . '&nbsp;€';
                    ?>
                </p>
                <a href="/<?= htmlspecialchars($lang ?? 'fr') ?>/commande" class="btn btn--gold cart-summary__cta">
                    <?= htmlspecialchars(__('cart.checkout')) ?>
                </a>
            </aside>
        </div>

        <?php elseif ($isAuth && empty($cartItems)) : ?>
        <!-- ======================================================
             Connecté — panier vide
        ====================================================== -->
        <div class="cart-empty-state">
            <p class="cart-empty"><?= htmlspecialchars(__('cart.empty')) ?></p>
            <a href="/<?= htmlspecialchars($lang ?? 'fr') ?>/vins" class="btn btn--outline">
                <?= htmlspecialchars(__('cart.browse')) ?>
            </a>
        </div>

        <?php else : ?>
        <!-- ======================================================
             Invité — rendu JS depuis cookie cb-cart
        ====================================================== -->
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
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
