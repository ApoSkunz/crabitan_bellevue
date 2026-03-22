<?php
$pageTitle = __('nav.collection');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');

$colorLabels = [
    'sweet' => __('wine.color.sweet'),
    'white' => __('wine.color.white'),
    'red'   => __('wine.color.red'),
    'rosé'  => __('wine.color.rosé'),
];

// Ordre d'affichage des groupes
$colorOrder = ['sweet', 'white', 'red', 'rosé'];
?>

<main class="page-wines-collection" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.collection')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <nav class="collection-nav" aria-label="<?= htmlspecialchars(__('wine.collection_nav')) ?>">
        <div class="container">
            <ul class="collection-nav__list">
                <li>
                    <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="btn btn--outline">
                        &#8592; <?= htmlspecialchars(__('nav.wines')) ?>
                    </a>
                </li>
                <?php foreach ($colorOrder as $color) :
                    if (!isset($winesByColor[$color])) {
                        continue;
                    }
                    ?>
                    <li>
                        <a href="#collection-<?= htmlspecialchars($color) ?>" class="collection-nav__anchor">
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

                <div class="wines-grid wines-grid--collection">
                    <?php foreach ($groupWines as $wine) :
                        $comment     = json_decode($wine['oenological_comment'] ?? '{}', true) ?? [];
                        $description = $comment[$navLang] ?? ($comment['fr'] ?? '');
                        $awardData   = json_decode($wine['award'] ?? '{}', true) ?? [];
                        $award       = $awardData[$navLang] ?? ($awardData['fr'] ?? '');
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
                                <?php if ($award !== '') : ?>
                                    <p class="wine-card__award">&#127942; <?= htmlspecialchars($award) ?></p>
                                <?php endif; ?>
                                <div class="wine-card__footer">
                                    <strong class="wine-card__price">
                                        <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                                    </strong>
                                    <div class="wine-card__actions">
                                        <?php if ($wine['available'] && $wine['quantity'] > 0) : ?>
                                            <?php if ($isLogged) : ?>
                                                <button
                                                    type="button"
                                                    class="wine-card__cart js-add-to-cart"
                                                    data-wine-id="<?= (int) $wine['id'] ?>"
                                                    aria-label="<?= htmlspecialchars(__('wine.add_to_cart') . ' : ' . $wine['label_name']) ?>"
                                                >&#128722;</button>
                                            <?php else : ?>
                                                <a
                                                    href="/<?= htmlspecialchars($navLang) ?>/connexion"
                                                    class="wine-card__cart"
                                                    aria-label="<?= htmlspecialchars(__('wine.add_to_cart') . ' : ' . $wine['label_name']) ?>"
                                                >&#128722;</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="wine-card__likes">
                                            <button
                                                type="button"
                                                class="wine-card__heart js-favorite"
                                                data-wine-id="<?= (int) $wine['id'] ?>"
                                                aria-label="<?= htmlspecialchars(__('wine.favorites') . ' : ' . $wine['label_name']) ?>"
                                                aria-pressed="false"
                                            >&#9825;</button>
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
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (empty($winesByColor)) : ?>
        <div class="container">
            <p class="wines-catalog__empty"><?= htmlspecialchars(__('wine.empty')) ?></p>
        </div>
    <?php endif; ?>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
