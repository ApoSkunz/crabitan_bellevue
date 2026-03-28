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
                                        src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
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
                                <span class="account-favorite-card__price">
                                    <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                                </span>
                                <button
                                    class="btn btn--ghost btn--sm js-account-fav-remove"
                                    data-wine-id="<?= (int) $wine['wine_id'] ?>"
                                    aria-label="<?= __('account.remove_favorite') ?>"
                                >
                                    <?= __('account.remove_favorite') ?>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
