<?php
$pageTitle     = __('account.favorites_title');
$activeSection = 'favorites';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $favorites */
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('panel.favorites') ?></h1>
            </header>

            <?php if ($favorites === []) : ?>
                <p class="account-empty"><?= __('account.favorites_empty') ?></p>
                <a class="btn btn--primary" href="/<?= htmlspecialchars($lang) ?>/vins">
                    <?= __('account.discover_wines') ?>
                </a>
            <?php else : ?>
                <ul class="account-favorites-grid">
                    <?php foreach ($favorites as $wine) : ?>
                        <li class="account-favorite-card">
                            <?php if ($wine['image_path']) : ?>
                                <a href="/<?= htmlspecialchars($lang) ?>/vins/<?= htmlspecialchars($wine['slug']) ?>">
                                    <img
                                        class="account-favorite-card__img"
                                        src="/assets/images/wines/<?= rawurlencode($wine['image_path']) ?>"
                                        alt="<?= htmlspecialchars($wine['name']) ?>"
                                        loading="lazy"
                                        width="120"
                                    >
                                </a>
                            <?php endif; ?>
                            <div class="account-favorite-card__body">
                                <a class="account-favorite-card__name"
                                   href="/<?= htmlspecialchars($lang) ?>/vins/<?= htmlspecialchars($wine['slug']) ?>">
                                    <?= htmlspecialchars($wine['name']) ?>
                                </a>
                                <?php if ($wine['vintage']) : ?>
                                    <span class="account-favorite-card__vintage">
                                        <?= (int) $wine['vintage'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php $isAvailable = (bool) ($wine['available'] ?? false); ?>
                                <?php if ($isAvailable) : ?>
                                    <span class="account-favorite-card__price">
                                        <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                                    </span>
                                <?php else : ?>
                                    <span class="account-favorite-card__price account-favorite-card__price--unavailable">
                                        <em><?= htmlspecialchars(__('wine.not_for_sale')) ?></em>
                                    </span>
                                <?php endif; ?>
                                <div class="account-favorite-card__actions">
                                    <?php if ($isAvailable) : ?>
                                        <button
                                            type="button"
                                            class="btn btn--gold btn--sm js-add-to-cart"
                                            data-wine-id="<?= (int) $wine['wine_id'] ?>"
                                            data-wine-name="<?= htmlspecialchars($wine['name'] . ' ' . (int) $wine['vintage']) ?>"
                                            data-wine-price="<?= htmlspecialchars(number_format((float) $wine['price'], 2, ',', ' ') . ' €') ?>"
                                            data-wine-image="/assets/images/wines/<?= rawurlencode((string) $wine['image_path']) ?>"
                                            data-wine-cuvee="<?= !empty($wine['is_cuvee_speciale']) ? htmlspecialchars(__('wine.cuvee_speciale')) : '' ?>"
                                            data-stock="<?= (int) ($wine['stock_quantity'] ?? 0) ?>"
                                            aria-label="<?= htmlspecialchars(__('wine.add_to_cart') . ' : ' . $wine['name']) ?>"
                                        >&#128722;</button>
                                    <?php endif; ?>
                                    <button
                                        class="btn btn--ghost btn--sm js-account-fav-remove"
                                        data-wine-id="<?= (int) $wine['wine_id'] ?>"
                                        aria-label="<?= __('account.remove_favorite') ?>"
                                    >
                                        <?= __('account.remove_favorite') ?>
                                    </button>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
