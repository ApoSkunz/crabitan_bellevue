<?php
$pageTitle     = __('account.ratings_title');
$activeSection = 'ratings';
$noindex       = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $wines */
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.ratings_title') ?></h1>
                <p class="account-header__sub"><?= htmlspecialchars(__('account.ratings_intro')) ?></p>
            </header>

            <?php if (empty($wines)) : ?>
                <p class="account-empty"><?= __('account.ratings_empty') ?></p>
                <a class="btn btn--primary" href="/<?= htmlspecialchars($lang) ?>/vins">
                    <?= __('account.discover_wines') ?>
                </a>
            <?php else : ?>
                <ul class="account-ratings-grid">
                    <?php foreach ($wines as $wine) : ?>
                    <?php
                    $imgFile  = (string) ($wine['image_path'] ?? '');
                    $imgNoExt = (string) preg_replace('/\.[^.]+$/', '', $imgFile);
                    $imgBase  = '/assets/images/wines/';
                    $current       = (int) ($wine['rating'] ?? 0);
                    $hasRating     = $current >= 1;
                    $isCuveeSpec   = (bool) ($wine['is_cuvee_speciale'] ?? false);
                    ?>
                    <li class="account-rating-card<?= $hasRating ? '' : ' account-rating-card--unrated' ?>">
                        <a class="account-rating-card__img-wrap"
                           href="/<?= htmlspecialchars($lang) ?>/vins/<?= htmlspecialchars((string) $wine['slug']) ?>">
                            <?php if ($imgFile !== '') : ?>
                            <picture>
                                <source srcset="<?= $imgBase . rawurlencode($imgNoExt . '-400w.webp') ?>" type="image/webp">
                                <img class="account-rating-card__img"
                                     src="<?= $imgBase . rawurlencode($imgFile) ?>"
                                     alt="<?= htmlspecialchars((string) $wine['label_name']) ?>"
                                     width="120" height="120" loading="lazy">
                            </picture>
                            <?php endif; ?>
                        </a>
                        <div class="account-rating-card__body">
                            <p class="account-rating-card__name">
                                <?= htmlspecialchars((string) $wine['label_name']) ?>
                            </p>
                            <span class="account-rating-card__vintage"><?= (int) $wine['vintage'] ?></span>
                            <?php if ($isCuveeSpec) : ?>
                            <span class="account-rating-card__badge"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></span>
                            <?php endif; ?>

                            <p class="account-rating-card__unrated-label<?= $hasRating ? ' account-rating-card__unrated-label--hidden' : '' ?>">
                                <?= htmlspecialchars(__('account.ratings_not_yet')) ?>
                            </p>

                            <form method="post"
                                  action="/<?= htmlspecialchars($lang) ?>/mon-compte/mes-notes"
                                  class="account-rating-card__form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                <input type="hidden" name="wine_id" value="<?= (int) $wine['wine_id'] ?>">
                                <fieldset class="review-stars account-rating-stars">
                                    <legend class="sr-only"><?= htmlspecialchars(__('review.rating_label')) ?></legend>
                                    <div class="review-stars__inputs">
                                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                                        <input type="radio" class="review-star-input"
                                               id="star-<?= (int) $wine['wine_id'] ?>-<?= $i ?>"
                                               name="rating" value="<?= $i ?>"
                                               <?= $current === $i ? 'checked' : '' ?>>
                                        <label class="review-star-label"
                                               for="star-<?= (int) $wine['wine_id'] ?>-<?= $i ?>">★</label>
                                        <?php endfor; ?>
                                    </div>
                                </fieldset>
                                <button type="submit" class="btn btn--gold btn--sm account-rating-card__save">
                                    <?= htmlspecialchars(__('account.ratings_save')) ?>
                                </button>
                            </form>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
