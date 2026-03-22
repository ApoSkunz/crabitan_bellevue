<?php
$pageTitle = __('nav.wines');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');

$colorLabels = [
    'sweet'      => __('wine.color.sweet'),
    'white'      => __('wine.color.white'),
    'red'        => __('wine.color.red'),
    'rosé'       => __('wine.color.rosé'),
    'sparkling'  => __('wine.color.sparkling'),
    'champagne'  => __('wine.color.champagne'),
];
?>

<main class="page-wines" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.wines')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES                                                       -->
    <!-- ============================================================ -->
    <section class="wines-filters" aria-label="<?= htmlspecialchars(__('wine.filter_label')) ?>">
        <div class="container">
            <form class="wines-filters__form" method="GET" action="">
                <fieldset class="wines-filters__colors">
                    <legend><?= htmlspecialchars(__('wine.filter_show')) ?></legend>

                    <label class="wines-filters__check<?= $activeColor === null ? ' is-active' : '' ?>">
                        <input type="radio" name="color" value="" <?= $activeColor === null ? 'checked' : '' ?>>
                        <?= htmlspecialchars(__('wine.color.all')) ?>
                    </label>

                    <?php foreach ($colorLabels as $val => $label) : ?>
                        <label class="wines-filters__check<?= $activeColor === $val ? ' is-active' : '' ?>">
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

                <div class="wines-filters__sort">
                    <label for="wines-sort"><?= htmlspecialchars(__('wine.filter_sort')) ?></label>
                    <select id="wines-sort" name="sort">
                        <?php
                        $sortOptions = [
                            'default'      => __('wine.sort.default'),
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

                <button type="submit" class="btn btn--gold wines-filters__submit">
                    <?= htmlspecialchars(__('wine.filter_apply')) ?>
                </button>

                <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection" class="wines-filters__collection-link">
                    <?= htmlspecialchars(__('wine.view_collection')) ?>
                </a>
            </form>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- GRILLE                                                        -->
    <!-- ============================================================ -->
    <section class="wines-catalog" aria-label="<?= htmlspecialchars(__('nav.wines')) ?>">
        <div class="container">
            <?php if (empty($wines)) : ?>
                <p class="wines-catalog__empty"><?= htmlspecialchars(__('wine.empty')) ?></p>
            <?php else : ?>
                <div class="wines-grid">
                    <?php foreach ($wines as $wine) :
                        $comment     = json_decode($wine['oenological_comment'] ?? '{}', true) ?? [];
                        $description = $comment[$navLang] ?? ($comment['fr'] ?? '');
                        $awardData   = json_decode($wine['award'] ?? '{}', true) ?? [];
                        $award       = $awardData[$navLang] ?? ($awardData['fr'] ?? '');
                        $colorLabel  = $colorLabels[$wine['wine_color']] ?? $wine['wine_color'];
                        ?>
                        <article class="wine-card<?= !$wine['available'] || $wine['quantity'] <= 0 ? ' wine-card--out-of-stock' : '' ?>">
                            <a
                                href="/<?= htmlspecialchars($navLang) ?>/vins/<?= htmlspecialchars($wine['slug']) ?>"
                                class="wine-card__inner"
                                aria-label="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                            >
                                <div class="wine-card__image-wrap">
                                    <img
                                        src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
                                        alt="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                                        class="wine-card__image"
                                        loading="lazy"
                                        width="300"
                                        height="420"
                                    >
                                    <?php if (!$wine['available'] || $wine['quantity'] <= 0) : ?>
                                        <span class="wine-card__badge wine-card__badge--out">
                                            <?= htmlspecialchars(__('wine.out_of_stock')) ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="wine-card__badge wine-card__badge--qty">
                                            <?= (int) $wine['quantity'] ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($wine['certification_label']) : ?>
                                        <span class="wine-card__cert">
                                            <?= htmlspecialchars($wine['certification_label']) ?>
                                        </span>
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
                                <?php if ($award !== '') : ?>
                                    <p class="wine-card__award">&#127942; <?= htmlspecialchars($award) ?></p>
                                <?php endif; ?>
                                <div class="wine-card__footer">
                                    <strong class="wine-card__price">
                                        <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                                    </strong>
                                    <button
                                        type="button"
                                        class="wine-card__heart js-favorite"
                                        data-wine-id="<?= (int) $wine['id'] ?>"
                                        aria-label="<?= htmlspecialchars(__('wine.favorites') . ' : ' . $wine['label_name']) ?>"
                                        aria-pressed="false"
                                    >&#9825;</button>
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
