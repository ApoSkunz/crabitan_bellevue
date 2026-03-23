<?php
$wineName  = ($wine['label_name'] ?? '') . ' ' . ($wine['vintage'] ?? '');
$pageTitle = $wineName;
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

// Décodage des champs JSON
$oeno    = json_decode($wine['oenological_comment'] ?? '{}', true) ?? [];
$soil    = json_decode($wine['soil']                ?? '{}', true) ?? [];
$pruning = json_decode($wine['pruning']             ?? '{}', true) ?? [];
$harvest = json_decode($wine['harvest']             ?? '{}', true) ?? [];
$vinif   = json_decode($wine['vinification']        ?? '{}', true) ?? [];
$barrel  = json_decode($wine['barrel_fermentation'] ?? '{}', true) ?? [];
$award   = json_decode($wine['award']               ?? '{}', true) ?? [];
$extra   = json_decode($wine['extra_comment']       ?? '{}', true) ?? [];

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
                        </strong>
                        <span class="wine-detail__stock wine-detail__stock--available">
                            <?= htmlspecialchars(__('wine.available')) ?>
                        </span>
                        <button
                            type="button"
                            class="btn btn--gold js-add-to-cart"
                            data-wine-id="<?= (int) $wine['id'] ?>"
                            data-wine-name="<?= htmlspecialchars($wine['label_name'] . ' ' . $wine['vintage']) ?>"
                            data-wine-price="<?= htmlspecialchars(number_format((float) $wine['price'], 2, ',', ' ') . ' €') ?>"
                            data-wine-image="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
                        >
                            <?= htmlspecialchars(__('wine.add_to_cart')) ?>
                        </button>
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
                        <dt><?= htmlspecialchars(__('wine.appellation')) ?></dt>
                        <dd><?= htmlspecialchars($wine['city']) ?></dd>

                        <dt><?= htmlspecialchars(__('wine.variety')) ?></dt>
                        <dd><?= htmlspecialchars($wine['variety_of_vine']) ?></dd>

                        <dt><?= htmlspecialchars(__('wine.area')) ?></dt>
                        <dd><?= number_format((float) $wine['area'], 2, ',', ' ') ?> ha</dd>

                        <dt><?= htmlspecialchars(__('wine.age')) ?></dt>
                        <dd><?= (int) $wine['age_of_vineyard'] ?> <?= htmlspecialchars(__('wine.years')) ?></dd>

                        <?php if ($soilText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.soil')) ?></dt>
                            <dd><?= htmlspecialchars($soilText) ?></dd>
                        <?php endif; ?>

                        <?php if ($pruningText !== '') : ?>
                            <dt><?= htmlspecialchars(__('wine.pruning')) ?></dt>
                            <dd><?= htmlspecialchars($pruningText) ?></dd>
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

                        <?php if ($wine['certification_label']) : ?>
                            <dt><?= htmlspecialchars(__('wine.certification')) ?></dt>
                            <dd><?= htmlspecialchars($wine['certification_label']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($extraText !== '') : ?>
                    <div class="wine-detail__section wine-detail__section--extra">
                        <p><?= $extraText /* HTML autorisé ici (lien Andreas Larsson) */ ?></p>
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
</main>

<!-- Zoom overlay -->
<!-- NOSONAR Web:S6819 — custom overlay with full JS focus/keyboard management; <dialog> migration deferred -->
<div id="wine-zoom-overlay" class="wine-zoom-overlay" aria-hidden="true" role="dialog" aria-label="<?= htmlspecialchars(__('wine.zoom')) ?>">
    <div class="wine-zoom-overlay__backdrop" id="wine-zoom-backdrop"></div>
    <button class="wine-zoom-overlay__close" id="wine-zoom-close" type="button" aria-label="Fermer">&times;</button>
    <img
        id="wine-zoom-img"
        src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
        alt="<?= htmlspecialchars($wineName) ?>"
        class="wine-zoom-overlay__image"
    >
</div>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
