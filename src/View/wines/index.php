<?php
$isAdmin           = $isAdmin    ?? false;
$exitIntentVariant = 'boutique';
$activeCuvee = $activeCuvee ?? false;
$ratings    = $ratings    ?? [];
$pageTitle  = __('nav.wines');
$appUrl = APP_BASE_URL;
$metaDescription = $navLang === 'en'
    ? 'Discover all Château Crabitan Bellevue wines — Sainte-Croix-du-Mont AOC. Order online.'
    : 'Découvrez tous les vins du Château Crabitan Bellevue — AOC Sainte-Croix-du-Mont. Commandez en ligne.';
$canonical  = $appUrl . '/' . $navLang . '/vins';
$hreflangFr = $appUrl . '/fr/vins';
$hreflangEn = $appUrl . '/en/vins';
$ogTitle    = __('nav.wines') . ' — Château Crabitan Bellevue';
$ogDescription = $metaDescription;
$ogUrl      = $canonical;
$ogType     = 'website';
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$colorLabels = [
    'sweet' => __('wine.color.sweet'),
    'white' => __('wine.color.white'),
    'red'   => __('wine.color.red'),
    'rosé'  => __('wine.color.rosé'),
];
$cls = ' is-active'; // single definition — used for radio/checkbox filter active state
?>

<main class="page-wines" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('wines.h1_seo')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <?php require_once SRC_PATH . '/View/partials/wines-case-banner.php'; ?>

    <div class="container">
        <?php require_once SRC_PATH . '/View/partials/delivery-discount-flyer.php'; ?>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES                                                       -->
    <!-- ============================================================ -->
    <section class="wines-filters" aria-label="<?= htmlspecialchars(__('wine.filter_label')) ?>">
        <div class="container">
            <form class="wines-filters__form" method="GET" action="" data-auto-submit data-ajax-filter>
                <div class="wines-filters__left">
                    <fieldset class="wines-filters__colors">
                        <legend><?= htmlspecialchars(__('wine.filter_show')) ?></legend>

                        <label class="wines-filters__check<?= $activeColor === null ? $cls : '' ?>">
                            <input type="radio" name="color" value="" <?= $activeColor === null ? 'checked' : '' ?>>
                            <?= htmlspecialchars(__('wine.color.all')) ?>
                        </label>

                        <?php foreach ($colorLabels as $val => $label) : ?>
                            <label class="wines-filters__check<?= $activeColor === $val ? $cls : '' ?>">
                                <input
                                    type="radio"
                                    name="color"
                                    value="<?= htmlspecialchars($val) ?>"
                                    <?= $activeColor === $val ? 'checked' : '' ?>
                                >
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <?php require_once SRC_PATH . '/View/partials/wines-filter-cuvee.php'; ?>

                    <button type="submit" class="btn btn--gold wines-filters__submit js-noscript-only">
                        <?= htmlspecialchars(__('wine.filter_apply')) ?>
                    </button>
                </div>

                <div class="wines-filters__right">
                    <div class="wines-filters__sort">
                        <label for="wines-sort"><?= htmlspecialchars(__('wine.filter_sort')) ?></label>
                        <select id="wines-sort" name="sort">
                            <?php
                            $sortOptions = [
                                'default'      => __('wine.sort.default'),
                                'rating_desc'  => __('wine.sort.rating_desc'),
                                'price_asc'    => __('wine.sort.price_asc'),
                                'price_desc'   => __('wine.sort.price_desc'),
                                'vintage_asc'  => __('wine.sort.vintage_asc'),
                                'vintage_desc' => __('wine.sort.vintage_desc'),
                            ];
                            foreach ($sortOptions as $val => $label) : ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $activeSort === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wines-filters__bottom-row">
                        <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection" class="wines-filters__collection-link">
                            <?= htmlspecialchars(__('wine.view_collection')) ?>
                        </a>
                    </div>
                </div>
            </form>

            <p class="wines-filters__ttc-note"><?= htmlspecialchars(__('wine.ttc_note')) ?></p>
            <?php if (\Core\FeatureFlags::isClickCollectEnabled()) : ?>
            <p class="click-collect-badge">
                <?= $navLang === 'en'
                    ? 'Click &amp; Collect option available — pickup at the château within 48h'
                    : 'Option Click &amp; Collect disponible — retrait au château sous 48h' ?>
            </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- GRILLE                                                        -->
    <!-- ============================================================ -->
    <section class="wines-catalog" aria-label="<?= htmlspecialchars(__('nav.wines')) ?>" data-ajax-content>
        <div class="container">
            <?php if (empty($wines)) : ?>
                <p class="wines-catalog__empty"><?= htmlspecialchars(__('wine.empty')) ?></p>
            <?php else : ?>
                <div class="wines-grid">
                    <?php foreach ($wines as $wineIdx => $wine) :
                        $comment     = ['fr' => $wine['oenological_comment_fr'] ?? '', 'en' => $wine['oenological_comment_en'] ?? ''];
                        $description = $comment[$navLang] ?? ($comment['fr'] ?? '');
                        $awardData   = ['fr' => $wine['award_fr'] ?? '', 'en' => $wine['award_en'] ?? ''];
                        $award       = $awardData[$navLang] ?? ($awardData['fr'] ?? '');
                        $colorLabel  = $colorLabels[$wine['wine_color']] ?? $wine['wine_color'];
                        ?>
                        <article class="wine-card<?= !$wine['available'] ? ' wine-card--out-of-stock' : '' ?>">
                            <a
                                href="/<?= htmlspecialchars($navLang) ?>/vins/<?= htmlspecialchars($wine['slug']) ?>"
                                class="wine-card__inner"
                                aria-label="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                            >
                                <div class="wine-card__image-wrap">
                                    <?php require SRC_PATH . '/View/partials/wine-card-picture.php'; // NOSONAR php:S2003 — require intentionnel dans foreach : inclusion multiple par carte ?>
                                    <?php if (!$wine['available'] || (int) ($wine['stock_quantity'] ?? 0) === 0) : ?>
                                        <span class="wine-card__badge wine-card__badge--out">
                                            <?= htmlspecialchars(__('wine.out_of_stock')) ?>
                                        </span>
                                    <?php elseif ((int) ($wine['stock_quantity'] ?? 0) <= (int) ($_ENV['STOCK_ALERTE_LIMIT'] ?? 300)) : ?>
                                        <span class="wine-card__badge wine-card__badge--low-stock">
                                            <?= htmlspecialchars(__('wine.low_stock')) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($wine['certification_label']) : ?>
                                        <span class="wine-card__cert">
                                            <?= htmlspecialchars($wine['certification_label']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php $wineRating = $ratings[(int) $wine['id']] ?? null; ?>
                                    <?php if ($wineRating !== null) : ?>
                                        <span class="wine-card__rating-badge"><?= number_format($wineRating, 1) ?>/5</span>
                                    <?php endif; ?>
                                </div>

                                <div class="wine-card__hover">
                                    <p class="wine-card__description">
                                        <?= htmlspecialchars(mb_substr($description, 0, 160)) ?>…
                                    </p>
                                    <span class="wine-card__read-more">
                                        <?= htmlspecialchars(__('wine.read_more')) ?> &#8594;
                                    </span>
                                </div>
                            </a>

                            <div class="wine-card__body">
                                <span class="wine-card__color"><?= htmlspecialchars($colorLabel) ?></span>
                                <h2 class="wine-card__name">
                                    <?= htmlspecialchars($wine['label_name']) ?>
                                    <span class="wine-card__vintage"><?= (int) $wine['vintage'] ?></span>
                                </h2>
                                <?php if (!empty($wine['is_cuvee_speciale'])) : ?>
                                    <p class="wine-card__extra"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></p>
                                <?php endif; ?>
                                <?php if ($award !== '') : ?>
                                    <p class="wine-card__award">&#127942; <?= htmlspecialchars($award) ?></p>
                                <?php endif; ?>
                                <div class="wine-card__footer">
                                    <strong class="wine-card__price">
                                        <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                                    </strong>
                                    <div class="wine-card__actions">
                                        <?php if ($wine['available'] && !$isAdmin) : ?>
                                            <button
                                                type="button"
                                                class="wine-card__cart js-add-to-cart"
                                                data-wine-id="<?= (int) $wine['id'] ?>"
                                                data-wine-name="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                                                data-wine-price="<?= htmlspecialchars(number_format((float) $wine['price'], 2, ',', ' ') . ' €') ?>"
                                                data-wine-image="/assets/images/wines/<?= rawurlencode($wine['image_path']) ?>"
                                                data-wine-cuvee="<?= !empty($wine['is_cuvee_speciale']) ? htmlspecialchars(__('wine.cuvee_speciale')) : '' ?>"
                                                data-stock="<?= (int) $wine['stock_quantity'] ?>"
                                                aria-label="<?= htmlspecialchars(__('wine.add_to_cart') . ' : ' . $wine['label_name']) ?>"
                                            >&#128722;</button>
                                        <?php endif; ?>
                                        <?php $isCardLiked = isset($likedIds[(int) $wine['id']]); ?>
                                        <span class="wine-card__likes">
                                            <button
                                                type="button"
                                                class="wine-card__heart js-favorite<?= $isCardLiked ? ' is-liked' : '' ?>"
                                                data-wine-id="<?= (int) $wine['id'] ?>"
                                                data-liked="<?= $isCardLiked ? 'true' : 'false' ?>"
                                                data-login-msg="<?= htmlspecialchars(__('wine.like_login')) ?>"
                                                aria-label="<?= htmlspecialchars(__('wine.favorites') . ' : ' . $wine['label_name']) ?>"
                                                aria-pressed="<?= $isCardLiked ? 'true' : 'false' ?>"
                                            ><?= $isCardLiked ? '&#9829;' : '&#9825;' ?></button>
                                            <span class="wine-card__likes-count" data-wine-id="<?= (int) $wine['id'] ?>">
                                                <?= (int) ($wine['likes_count'] ?? 0) ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
