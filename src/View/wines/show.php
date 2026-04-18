<?php
$isAdmin   = $isAdmin ?? false;
$wineName  = ($wine['label_name'] ?? '') . ' ' . ($wine['vintage'] ?? '');
$pageTitle = $wineName;
$appUrl        = rtrim(defined('APP_URL') ? APP_URL : ($_ENV['APP_URL'] ?? ''), '/');
$_oeno         = ['fr' => $wine['oenological_comment_fr'] ?? '', 'en' => $wine['oenological_comment_en'] ?? ''];
$_oenoText     = (string) ($_oeno[$navLang] ?? ($_oeno['fr'] ?? ''));
$metaDescription = mb_substr(strip_tags($_oenoText), 0, 155);
$canonical     = $appUrl . '/' . $navLang . '/vins/' . ($wine['slug'] ?? '');
$hreflangFr    = $appUrl . '/fr/vins/' . ($wine['slug'] ?? '');
$hreflangEn    = $appUrl . '/en/vins/' . ($wine['slug'] ?? '');
$ogTitle       = $wineName . ' — Château Crabitan Bellevue';
$ogDescription = $metaDescription;
$ogImage       = $appUrl . '/assets/images/wines/' . ($wine['image_path'] ?? '');
$ogUrl         = $canonical;
$ogType        = 'product';
$jsonLd        = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => $wineName,
    'image'    => $ogImage,
    'description' => $metaDescription,
    'brand'    => ['@type' => 'Brand', 'name' => 'Château Crabitan Bellevue'],
    'offers'   => [
        '@type'         => 'Offer',
        'price'         => number_format((float) ($wine['price'] ?? 0), 2, '.', ''),
        'priceCurrency' => 'EUR',
        'availability'  => !empty($wine['available'])
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
        'url'           => $canonical,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

// Champs bilingues (colonnes _fr / _en séparées)
$oeno    = ['fr' => $wine['oenological_comment_fr'] ?? '', 'en' => $wine['oenological_comment_en'] ?? ''];
$soil    = ['fr' => $wine['soil_fr'] ?? '',                'en' => $wine['soil_en'] ?? ''];
$pruning = ['fr' => $wine['pruning_fr'] ?? '',             'en' => $wine['pruning_en'] ?? ''];
$harvest = ['fr' => $wine['harvest_fr'] ?? '',             'en' => $wine['harvest_en'] ?? ''];
$vinif   = ['fr' => $wine['vinification_fr'] ?? '',        'en' => $wine['vinification_en'] ?? ''];
$barrel  = ['fr' => $wine['barrel_fermentation_fr'] ?? '', 'en' => $wine['barrel_fermentation_en'] ?? ''];
$award   = ['fr' => $wine['award_fr'] ?? '',               'en' => $wine['award_en'] ?? ''];
$extra   = ['fr' => $wine['extra_comment_fr'] ?? '',       'en' => $wine['extra_comment_en'] ?? ''];

$nutrition       = $nutrition ?? [];
$avgRating       = $avgRating   ?? null;
$ratingCount     = $ratingCount ?? 0;
$enableExtraInfo = filter_var($_ENV['ENABLE_EXTRA_INFO'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$hasAlcohol      = !empty($wine['alcohol_degree']);
$hasAging        = !empty($wine['aging_potential']);

$description  = $oeno[$navLang]    ?? ($oeno['fr']    ?? '');
$soilText     = $soil[$navLang]    ?? ($soil['fr']    ?? '');
$pruningText  = $pruning[$navLang] ?? ($pruning['fr'] ?? '');
$harvestText  = $harvest[$navLang] ?? ($harvest['fr'] ?? '');
$vinifText    = $vinif[$navLang]   ?? ($vinif['fr']   ?? '');
$barrelText   = $barrel[$navLang]  ?? ($barrel['fr']  ?? '');
$awardText    = $award[$navLang]   ?? ($award['fr']   ?? '');
$extraText    = $extra[$navLang]   ?? ($extra['fr']   ?? '');

$colorLabels = [
    'sweet' => __('wine.color.sweet'),
    'white' => __('wine.color.white'),
    'red'   => __('wine.color.red'),
    'rosé'  => __('wine.color.rosé'),
];
$colorLabel = $colorLabels[$wine['wine_color']] ?? $wine['wine_color'];
?>

<main class="page-wine-show" id="main-content">
    <section class="wine-detail container" aria-labelledby="wine-title">

        <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="news-article__back">
            &#8592; <?= htmlspecialchars(__('nav.wines')) ?>
        </a>

        <div class="wine-detail__layout">

            <!-- Colonne image -->
            <div class="wine-detail__visual">
                <div class="wine-detail__image-wrap">
                    <img
                        src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
                        alt="<?= htmlspecialchars($wineName) ?>"
                        class="wine-detail__image js-wine-zoom"
                        width="400"
                        height="560"
                        fetchpriority="high"
                        title="<?= htmlspecialchars(__('wine.zoom')) ?>"
                    >
                    <span class="wine-detail__zoom-hint" aria-hidden="true">&#128269;</span>
                </div>
            </div>

            <!-- Colonne infos -->
            <div class="wine-detail__info">
                <span class="wine-card__color"><?= htmlspecialchars($colorLabel) ?></span>
                <h1 id="wine-title" class="wine-detail__title">
                    <?= htmlspecialchars($wine['label_name']) ?>
                    <span class="wine-detail__vintage"><?= (int) $wine['vintage'] ?></span>
                </h1>
                <div class="home-section__divider"></div>

                <?php if ($wine['available']) : ?>
                    <div class="wine-detail__buy">
                        <strong class="wine-detail__price">
                            <?= number_format((float) $wine['price'], 2, ',', ' ') ?> €
                            <?php if ($avgRating !== null && $ratingCount >= 2) : ?>
                                <span class="wine-card__rating"><?= number_format($avgRating, 1) ?>/5 (<?= $ratingCount ?> <?= htmlspecialchars(__('wine.reviews')) ?>)</span>
                            <?php endif; ?>
                        </strong>
                        <?php if ((int) ($wine['stock_quantity'] ?? 0) <= (int) ($_ENV['STOCK_ALERTE_LIMIT'] ?? 300)) : ?>
                            <span class="wine-detail__stock wine-detail__stock--low-stock">
                                <?= htmlspecialchars(__('wine.low_stock')) ?>
                            </span>
                        <?php else : ?>
                        <span class="wine-detail__stock wine-detail__stock--available">
                            <?= htmlspecialchars(__('wine.available')) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!$isAdmin) : ?>
                        <div class="wine-detail__actions">
                            <button
                                type="button"
                                class="btn btn--gold js-add-to-cart"
                                data-wine-id="<?= (int) $wine['id'] ?>"
                                data-wine-name="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                                data-wine-price="<?= htmlspecialchars(number_format((float) $wine['price'], 2, ',', ' ') . ' €') ?>"
                                data-wine-image="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
                                data-wine-cuvee="<?= !empty($wine['is_cuvee_speciale']) ? htmlspecialchars(__('wine.cuvee_speciale')) : '' ?>"
                            >
                                <?= htmlspecialchars(__('wine.add_to_cart')) ?>
                            </button>
                            <button
                                type="button"
                                class="btn btn--ghost js-favorite<?= ($isLiked ?? false) ? ' is-liked' : '' ?>"
                                data-wine-id="<?= (int) $wine['id'] ?>"
                                data-liked="<?= ($isLiked ?? false) ? 'true' : 'false' ?>"
                                data-login-msg="<?= htmlspecialchars(__('wine.favorite_login')) ?>"
                                aria-label="<?= htmlspecialchars(($isLiked ?? false) ? __('account.remove_favorite') : __('wine.add_favorite')) ?>"
                                aria-pressed="<?= ($isLiked ?? false) ? 'true' : 'false' ?>"
                            >
                                <span class="js-favorite-icon"><?= ($isLiked ?? false) ? '♥' : '♡' ?></span>
                                <span class="js-favorite-label"><?= htmlspecialchars(($isLiked ?? false) ? __('account.remove_favorite') : __('wine.add_favorite')) ?></span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <p class="wine-detail__out-of-stock"><?= htmlspecialchars(__('wine.out_of_stock')) ?></p>
                <?php endif; ?>

                <p class="wine-detail__ttc-note"><?= htmlspecialchars(__('wine.ttc_note')) ?></p>

                <?php if (!empty($wine['is_cuvee_speciale'])) : ?>
                    <p class="wine-card__extra"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></p>
                <?php endif; ?>

                <?php if ($awardText !== '') : ?>
                    <p class="wine-detail__award">&#127942; <?= htmlspecialchars($awardText) ?></p>
                <?php endif; ?>

                <?php if ($description !== '') : ?>
                    <div class="wine-detail__section">
                        <h2 class="wine-detail__section-title"><?= htmlspecialchars(__('wine.tasting')) ?></h2>
                        <p class="wine-detail__text"><?= htmlspecialchars($description) ?></p>
                    </div>
                <?php endif; ?>

                <?php
                $pairingKey  = 'wine.pairing.' . $wine['wine_color'];
                $pairingText = __($pairingKey);
                if ($pairingText !== $pairingKey) : ?>
                    <div class="wine-detail__section">
                        <h2 class="wine-detail__section-title"><?= htmlspecialchars(__('wine.pairing_title')) ?></h2>
                        <p class="wine-detail__text"><?= htmlspecialchars($pairingText) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Tableau technique -->
                <div class="wine-detail__section">
                    <h2 class="wine-detail__section-title"><?= htmlspecialchars(__('wine.technical')) ?></h2>
                    <dl class="wine-detail__specs">
                        <?php if ($wine['certification_label']) : ?>
                            <dt><?= htmlspecialchars(__('wine.certification')) ?></dt>
                            <dd><?= htmlspecialchars($wine['certification_label']) ?></dd>
                        <?php endif; ?>

                        <dt><?= htmlspecialchars(__('wine.appellation')) ?></dt>
                        <dd><?= htmlspecialchars($wine['city']) ?></dd>

                        <?php if ($hasAlcohol) : ?>
                            <dt><?= htmlspecialchars(__('wine.alcohol_degree')) ?></dt>
                            <dd><?= htmlspecialchars($wine['alcohol_degree']) ?> % vol.</dd>
                        <?php endif; ?>

                        <?php if ($hasAging) : ?>
                            <dt><?= htmlspecialchars(__('wine.aging_potential')) ?></dt>
                            <dd><?= htmlspecialchars($wine['aging_potential']) ?></dd>
                        <?php endif; ?>

                        <dt><?= htmlspecialchars(__('wine.variety')) ?></dt>
                        <dd><?= htmlspecialchars($wine['variety_of_vine']) ?></dd>

                        <dt><?= htmlspecialchars(__('wine.age')) ?></dt>
                        <dd><?= (int) $wine['age_of_vineyard'] ?> <?= htmlspecialchars(__('wine.years')) ?></dd>

                        <?php if ($soilText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.soil')) ?></dt>
                            <dd><?= htmlspecialchars($soilText) ?></dd>
                        <?php endif; ?>

                        <?php if ($harvestText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.harvest')) ?></dt>
                            <dd><?= htmlspecialchars($harvestText) ?></dd>
                        <?php endif; ?>

                        <?php if ($vinifText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.vinification')) ?></dt>
                            <dd><?= htmlspecialchars($vinifText) ?></dd>
                        <?php endif; ?>

                        <?php if ($barrelText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.aging')) ?></dt>
                            <dd><?= htmlspecialchars($barrelText) ?></dd>
                        <?php endif; ?>

                        <dt><?= htmlspecialchars(__('wine.area')) ?></dt>
                        <dd><?= number_format((float) $wine['area'], 2, ',', ' ') ?> ha</dd>

                        <?php if ($pruningText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.pruning')) ?></dt>
                            <dd><?= htmlspecialchars($pruningText) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($wine['production_quantity'])) : ?>
                            <dt><?= htmlspecialchars(__('wine.quantity')) ?></dt>
                            <dd><?= number_format((int) $wine['production_quantity'], 0, ',', ' ') ?> <?= htmlspecialchars(__('wine.bottles')) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($extraText !== '') : ?>
                    <div class="wine-detail__section wine-detail__section--extra">
                        <p><?= $extraText /* HTML autorisé ici (lien Andreas Larsson) */ ?></p>
                    </div>
                <?php endif; ?>

                <?php
                $hasNutrition   = $enableExtraInfo && (bool) array_filter(array_intersect_key(
                    $nutrition,
                    array_flip(['energy_kj','fat_g','saturated_fat_g','carbohydrate_g','sugars_g','protein_g','salt_g'])
                ));
                $ingDecoded   = json_decode((string) ($nutrition['ingredients'] ?? ''), true) ?? [];
                $ingText      = (string) ($ingDecoded[$lang] ?? ($ingDecoded['fr'] ?? ($nutrition['ingredients'] ?? '')));
                $hasIngredients = $enableExtraInfo && $ingText !== '';
                ?>

                <?php if ($hasIngredients) : ?>
                    <div class="wine-detail__section">
                        <h2 class="wine-detail__section-title"><?= htmlspecialchars(__('wine.ingredients')) ?></h2>
                        <p class="wine-detail__text"><?= htmlspecialchars($ingText) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($hasNutrition) : ?>
                    <div class="wine-detail__section">
                        <h2 class="wine-detail__section-title"><?= htmlspecialchars(__('wine.nutritional_declaration')) ?></h2>
                        <table class="wine-nutrition__table" aria-label="<?= htmlspecialchars(__('wine.nutritional_declaration')) ?>">
                            <thead>
                                <tr>
                                    <th scope="col"></th>
                                    <th scope="col"><?= htmlspecialchars(__('wine.nutrition_per_100ml')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $nutRows = [
                                ['energy_kj',        __('wine.energy'),        false],
                                ['fat_g',            __('wine.fat'),           false],
                                ['saturated_fat_g',  __('wine.saturated_fat'), true],
                                ['carbohydrate_g',   __('wine.carbohydrate'),  false],
                                ['sugars_g',         __('wine.sugars'),        true],
                                ['protein_g',        __('wine.protein'),       false],
                                ['salt_g',           __('wine.salt'),          false],
                            ];
                            foreach ($nutRows as [$field, $label, $sub]) :
                                $val  = $nutrition[$field]  ?? null;
                                if ($val === null) : continue; endif;
                                if ($field === 'energy_kj') :
                                    $kcal    = (int) round((int) $val / 4.184);
                                    $display = htmlspecialchars((string) $val) . ' kJ / ' . $kcal . ' kcal';
                                else :
                                    $display = htmlspecialchars((string) $val) . ' g';
                                endif;
                            ?>
                                <tr class="<?= $sub ? 'wine-nutrition__row--sub' : '' ?>">
                                    <th scope="row"><?= htmlspecialchars($label) ?></th>
                                    <td><?= $display ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <a
                    href="/<?= htmlspecialchars($navLang) ?>/vins/<?= htmlspecialchars($wine['slug']) ?>/fiche-technique"
                    class="wine-detail__download"
                    aria-label="<?= htmlspecialchars(__('wine.download_sheet') . ' : ' . $wineName) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    &#128196; <?= htmlspecialchars(__('wine.download_sheet')) ?>
                </a>
            </div>
        </div>
    </section>

    <?php
    $accountId = $accountId ?? 0;
    $userId    = $accountId > 0 ? $accountId : null;
    ?>

    <?php if ($avgRating !== null || $accountId > 0) : ?>
    <section class="wine-rating container" aria-label="<?= htmlspecialchars(__('wine.rating.title')) ?>">

        <?php if ($avgRating !== null) : ?>
        <div class="wine-rating__avg">
            <?php
            $fullStars  = (int) round($avgRating);
            for ($s = 1; $s <= 5; $s++) { ?>
            <span class="wine-rating__star-display<?= $s <= $fullStars ? ' wine-rating__star-display--filled' : '' ?>" aria-hidden="true">★</span>
            <?php } ?>
            <span class="wine-rating__score"><?= number_format($avgRating, 1) ?>/5</span>
            <span class="wine-rating__count">(<?= $ratingCount ?> <?= htmlspecialchars(__('wine.rating.reviews')) ?>)</span>
        </div>
        <?php endif; ?>

        <?php if ($userId !== null && $accountRating === null) : ?>
        <form class="wine-rating__form js-rating-form"
              action="/<?= htmlspecialchars($navLang) ?>/rating/vin/<?= (int) $wine['id'] ?>"
              method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <p class="wine-rating__widget-label"><?= htmlspecialchars(__('wine.rating.widget_label')) ?></p>
            <div class="wine-rating__stars" role="group" aria-label="<?= htmlspecialchars(__('wine.rating.your_note')) ?>">
                <?php for ($i = 1; $i <= 5; $i++) : ?>
                <button type="submit" name="note" value="<?= $i ?>" class="wine-rating__star js-star"
                        aria-label="<?= $i ?> <?= $i > 1 ? 'étoiles' : 'étoile' ?>">★</button>
                <?php endfor; ?>
            </div>
        </form>
        <?php elseif ($userId !== null && $accountRating !== null) : ?>
        <p class="wine-rating__already">
            <?= htmlspecialchars(__('wine.rating.already')) ?>
            <?= (int) ($accountRating['rating'] ?? 0) ?>/5 ★
        </p>
        <?php endif; ?>

    </section>
    <?php endif; ?>

    <?php if (!empty($similarWines)) : ?>
    <section class="wine-cross-sell" aria-label="<?= htmlspecialchars(__('wine.similar.title')) ?>">
        <div class="container">
            <h2 class="wine-cross-sell__title"><?= htmlspecialchars(__('wine.similar.title')) ?></h2>
            <div class="wine-cross-sell__grid">
                <?php foreach ($similarWines as $sw) :
                    $swSlug    = htmlspecialchars((string) ($sw['slug']       ?? ''));
                    $swName    = htmlspecialchars((string) ($sw['label_name'] ?? ''));
                    $swVintage = (int) ($sw['vintage'] ?? 0);
                    $swPrice   = (float) ($sw['price'] ?? 0.0);
                    $swImage   = htmlspecialchars((string) ($sw['image_path'] ?? ''));
                    $swId      = (int) ($sw['id'] ?? 0);
                    $swCuvee   = !empty($sw['is_cuvee_speciale']);
                ?>
                <article class="wine-card wine-cross-sell__card">
                    <a href="/<?= htmlspecialchars($navLang) ?>/vins/<?= $swSlug ?>"
                       class="wine-card__inner"
                       aria-label="<?= $swName ?>">
                        <div class="wine-card__image-wrap">
                            <img src="/assets/images/wines/<?= $swImage ?>"
                                 alt="<?= $swName ?>"
                                 class="wine-card__image"
                                 width="200" height="280"
                                 loading="lazy">
                        </div>
                    </a>
                    <div class="wine-card__body">
                        <h3 class="wine-card__name">
                            <?= $swName ?>
                            <?php if ($swVintage > 0) : ?>
                            <span class="wine-card__vintage"><?= $swVintage ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php if ($swCuvee) : ?>
                        <p class="wine-card__extra"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></p>
                        <?php endif; ?>
                        <div class="wine-card__footer">
                            <strong class="wine-card__price">
                                <?= number_format($swPrice, 2, ',', ' ') ?>&nbsp;€
                            </strong>
                            <?php if (!$isAdmin) : ?>
                            <button type="button"
                                    class="wine-card__cart js-add-to-cart"
                                    data-wine-id="<?= $swId ?>"
                                    data-wine-name="<?= $swName ?>"
                                    data-wine-price="<?= htmlspecialchars(number_format($swPrice, 2, ',', ' ') . ' €') ?>"
                                    data-wine-image="/assets/images/wines/<?= $swImage ?>"
                                    data-wine-cuvee="<?= $swCuvee ? htmlspecialchars(__('wine.cuvee_speciale')) : '' ?>"
                                    aria-label="<?= htmlspecialchars(__('wine.add_to_cart')) ?> — <?= $swName ?>">
                                &#128722;
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- Zoom overlay -->
<!-- NOSONAR Web:S6819 — custom overlay with full JS focus/keyboard management; <dialog> migration deferred -->
<div id="wine-zoom-overlay" class="wine-zoom-overlay" aria-hidden="true" role="dialog" aria-label="<?= htmlspecialchars(__('wine.zoom')) ?>">
    <div class="wine-zoom-overlay__backdrop" id="wine-zoom-backdrop"></div>
    <button class="wine-zoom-overlay__close" id="wine-zoom-close" type="button" aria-label="<?= __('common.close') ?>">&times;</button>
    <img
        id="wine-zoom-img"
        src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
        alt="<?= htmlspecialchars($wineName) ?>"
        class="wine-zoom-overlay__image"
    >
</div>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
