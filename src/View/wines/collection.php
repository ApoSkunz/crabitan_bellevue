<?php
$isAdmin           = $isAdmin ?? false;
$exitIntentVariant = 'collection';
$ratings   = $ratings ?? [];
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('nav.collection');
$metaDescription = $navLang === 'en'
    ? 'Explore the full range of Château Crabitan Bellevue wines — sweet, white, red and rosé Bordeaux wines.'
    : 'Découvrez la gamme complète des vins du Château Crabitan Bellevue — vins liquoreux, blancs, rouges et rosés de Bordeaux.';
$appUrl = APP_BASE_URL;
$breadcrumbLd = json_encode([
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('nav.home'),       'item' => $appUrl . '/' . $navLang],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('nav.wines'),      'item' => $appUrl . '/' . $navLang . '/vins'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => __('nav.collection'), 'item' => $appUrl . '/' . $navLang . '/vins/collection'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$colorLabels = [
    'sweet' => __('wine.color.sweet'),
    'white' => __('wine.color.white'),
    'red'   => __('wine.color.red'),
    'rosé'  => __('wine.color.rosé'),
];

// Ordre d'affichage des groupes
$colorOrder  = ['sweet', 'white', 'red', 'rosé'];
$activeColor  = $activeColor  ?? null;
$activeSort   = $activeSort   ?? 'default';
$activeAvail  = $activeAvail  ?? '';
$activeCuvee  = $activeCuvee  ?? false;
$cls         = ' is-active'; // single definition — used for radio filter active state
?>

<main class="page-wines-collection" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.collection')) ?></h1>
            <p class="page-hero__intro"><?= htmlspecialchars(__('collection.intro')) ?></p>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <div class="container">
        <nav class="breadcrumb" aria-label="<?= htmlspecialchars(__('common.breadcrumb')) ?>">
            <ol class="breadcrumb__list">
                <li class="breadcrumb__item"><a class="breadcrumb__link" href="/<?= htmlspecialchars($navLang) ?>"><?= htmlspecialchars(__('nav.home')) ?></a></li>
                <li class="breadcrumb__item"><a class="breadcrumb__link" href="/<?= htmlspecialchars($navLang) ?>/vins"><?= htmlspecialchars(__('nav.wines')) ?></a></li>
                <li class="breadcrumb__item"><a class="breadcrumb__link" href="/<?= htmlspecialchars($navLang) ?>/vins/collection"><?= htmlspecialchars(__('nav.collection')) ?></a></li>
            </ol>
        </nav>
    </div>

    <?php require_once SRC_PATH . '/View/partials/wines-case-banner.php'; ?>

    <div class="container">
        <?php require_once SRC_PATH . '/View/partials/delivery-discount-flyer.php'; ?>
    </div>

    <!-- Filtres collection -->
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
                                <input type="radio" name="color" value="<?= htmlspecialchars($val) ?>" <?= $activeColor === $val ? 'checked' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <fieldset class="wines-filters__colors">
                        <legend><?= htmlspecialchars(__('wine.filter_avail')) ?></legend>

                        <?php
                        $availOptions = [
                            ''          => __('wine.avail.all'),
                            'available' => __('wine.avail.available'),
                            'out'       => __('wine.avail.out'),
                        ];
                        foreach ($availOptions as $val => $label) : ?>
                            <label class="wines-filters__check<?= $activeAvail === $val ? $cls : '' ?>">
                                <input type="radio" name="avail" value="<?= htmlspecialchars($val) ?>" <?= $activeAvail === $val ? 'checked' : '' ?>>
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
                        <label for="collection-sort"><?= htmlspecialchars(__('wine.filter_sort')) ?></label>
                        <select id="collection-sort" name="sort">
                            <?php
                            $sortOptions = [
                                'default'      => __('wine.sort.default'),
                                'rating_desc'  => __('wine.sort.rating_desc'),
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
                        <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="wines-filters__collection-link">
                            &#8592; <?= htmlspecialchars(__('nav.wines')) ?>
                        </a>
                    </div>
                </div>
            </form>

            <p class="wines-filters__ttc-note"><?= htmlspecialchars(__('wine.ttc_note')) ?></p>
        </div>
    </section>

    <div data-ajax-content>
    <nav class="collection-nav" aria-label="<?= htmlspecialchars(__('wine.collection_nav')) ?>">
        <div class="container">
            <ul class="collection-nav__list">
                <?php foreach ($colorOrder as $color) :
                    // Vue collection sans pagination : seule une couleur présente dans le DOM
                    // a une section #collection-{color}. On masque les autres pour éviter les ancres mortes.
                    if (!isset($winesByColor[$color])) {
                        continue;
                    }
                    ?>
                    <li>
                        <a
                            href="#collection-<?= htmlspecialchars($color) ?>"
                            class="collection-nav__anchor"
                        >
                            <?= htmlspecialchars($colorLabels[$color] ?? $color) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <?php foreach ($colorOrder as $colorKey) :
        if (!isset($winesByColor[$colorKey])) {
            continue;
        }
        $groupWines = $winesByColor[$colorKey];
        $groupLabel = $colorLabels[$colorKey] ?? $colorKey;
        ?>
        <section
            id="collection-<?= htmlspecialchars($colorKey) ?>"
            class="collection-group"
            aria-labelledby="collection-heading-<?= htmlspecialchars($colorKey) ?>"
        >
            <div class="container">
                <h2
                    id="collection-heading-<?= htmlspecialchars($colorKey) ?>"
                    class="collection-group__title"
                >
                    <?= htmlspecialchars($groupLabel) ?>
                </h2>
                <div class="home-section__divider"></div>

                <?php $lazyThreshold = 8; ?>
                <div class="wines-grid wines-grid--collection">
                    <?php foreach ($groupWines as $wineIdx => $wine) :
                        if ($wineIdx === $lazyThreshold) : ?>
                        </div><!-- end eager grid -->
                        <template class="js-lazy-cards">
                        <div class="wines-grid wines-grid--collection wines-grid--lazy">
                        <?php endif; ?>
                        <?php
                        $comment     = ['fr' => $wine['oenological_comment_fr'] ?? '', 'en' => $wine['oenological_comment_en'] ?? ''];
                        $description = $comment[$navLang] ?? ($comment['fr'] ?? '');
                        $awardData   = ['fr' => $wine['award_fr'] ?? '', 'en' => $wine['award_en'] ?? ''];
                        $award       = $awardData[$navLang] ?? ($awardData['fr'] ?? '');
                        ?>
                        <article class="wine-card<?= !$wine['available'] ? ' wine-card--out-of-stock' : '' ?>">
                            <a
                                href="/<?= htmlspecialchars($navLang) ?>/vins/<?= htmlspecialchars($wine['slug']) ?>?ctx=collection"
                                class="wine-card__inner"
                                aria-label="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                            >
                                <div class="wine-card__image-wrap">
                                    <?php require SRC_PATH . '/View/partials/wine-card-picture.php'; // NOSONAR php:S2003 — require intentionnel dans foreach : inclusion multiple par carte ?>
                                    <?php if ($wine['available'] && (int) ($wine['stock_quantity'] ?? 0) === 0) : ?>
                                        <span class="wine-card__badge wine-card__badge--out">
                                            <?= htmlspecialchars(__('wine.out_of_stock')) ?>
                                        </span>
                                    <?php elseif ($wine['available'] && (int) ($wine['stock_quantity'] ?? 0) <= (int) ($_ENV['STOCK_ALERTE_LIMIT'] ?? 300)) : ?>
                                        <span class="wine-card__badge wine-card__badge--low-stock">
                                            <?= htmlspecialchars(__('wine.low_stock')) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($wine['certification_label'])) : ?>
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
                                <h3 class="wine-card__name">
                                    <?= htmlspecialchars($wine['label_name']) ?>
                                    <span class="wine-card__vintage"><?= (int) $wine['vintage'] ?></span>
                                </h3>
                                <?php if (!empty($wine['is_cuvee_speciale'])) : ?>
                                    <p class="wine-card__extra"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></p>
                                <?php endif; ?>
                                <?php if ($award !== '') : ?>
                                    <p class="wine-card__award">&#127942; <?= htmlspecialchars($award) ?></p>
                                <?php endif; ?>
                                <div class="wine-card__footer">
                                    <?php if ($wine['available']) : ?>
                                        <strong class="wine-card__price">
                                            <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                                        </strong>
                                    <?php else : ?>
                                        <strong class="wine-card__price wine-card__price--unavailable">
                                            <?= htmlspecialchars(__('wine.not_for_sale')) ?>
                                        </strong>
                                    <?php endif; ?>
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
                <?php if (count($groupWines) > $lazyThreshold) : ?>
                        </div><!-- end lazy grid -->
                        </template>
                        <div class="js-lazy-sentinel" aria-hidden="true"></div>
                <?php else : ?>
                </div><!-- end eager grid (all cards) -->
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (empty($winesByColor)) : ?>
        <div class="container">
            <p class="wines-catalog__empty"><?= htmlspecialchars(__('wine.empty')) ?></p>
        </div>
    <?php endif; ?>

    </div><!-- /data-ajax-content -->
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
