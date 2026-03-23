<?php
$pageTitle = __('footer.sitemap');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<string, array<string,string>|null> $wineImages */
$wineImages = $wineImages ?? [];

/**
 * Retourne le src d'une image de vin ou un fallback gallery.
 *
 * @param array<string,string>|null $wine
 */
$wineImg = static function (?array $wine, string $fallback): string {
    return ($wine !== null && !empty($wine['image_path']))
        ? '/assets/images/wines/' . htmlspecialchars($wine['image_path'])
        : $fallback;
};
?>

<main class="page-sitemap" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.sitemap')) ?></h1>
            <p class="sitemap-hero__intro"><?= htmlspecialchars(__('sitemap.intro')) ?></p>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <!-- Nos vins — alternating rows (images depuis la BDD) -->
    <section class="sitemap-wines" aria-label="<?= htmlspecialchars(__('sitemap.section_wines')) ?>">

        <div class="sitemap-wine-row sitemap-wine-row--sweet">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['sweet'] ?? null, '/assets/images/gallery/nos-vins.jpg') ?>"
                     alt="<?= htmlspecialchars(__('wine.color.sweet')) ?>" loading="lazy">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=sweet">
                    <?= htmlspecialchars(__('sitemap.wine_sweet')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--reverse sitemap-wine-row--red">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['red'] ?? null, '/assets/images/gallery/nos-vins.jpg') ?>"
                     alt="<?= htmlspecialchars(__('wine.color.red')) ?>" loading="lazy">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=red">
                    <?= htmlspecialchars(__('sitemap.wine_red')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--white">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['white'] ?? null, '/assets/images/gallery/nos-vins.jpg') ?>"
                     alt="<?= htmlspecialchars(__('wine.color.white')) ?>" loading="lazy">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=white">
                    <?= htmlspecialchars(__('sitemap.wine_white')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--reverse sitemap-wine-row--rose">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['rosé'] ?? null, '/assets/images/gallery/nos-vins.jpg') ?>"
                     alt="<?= htmlspecialchars(__('wine.color.rosé')) ?>" loading="lazy">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=ros%C3%A9">
                    <?= htmlspecialchars(__('sitemap.wine_rose')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--collection">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['collection'] ?? null, '/assets/images/gallery/nos-vins.jpg') ?>"
                     alt="<?= htmlspecialchars(__('nav.collection')) ?>" loading="lazy">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection">
                    <?= htmlspecialchars(__('sitemap.wine_collection')) ?>
                </a>
            </div>
        </div>

    </section>

    <!-- Pages principales -->
    <section class="sitemap-cards-section container"
             aria-label="<?= htmlspecialchars(__('sitemap.section_main')) ?>">
        <h2 class="sitemap-cards-section__title">
            <?= htmlspecialchars(strtoupper(__('sitemap.section_main'))) ?>
        </h2>
        <div class="sitemap-grid sitemap-grid--3">

            <a href="/<?= htmlspecialchars($navLang) ?>/le-chateau" class="sitemap-card">
                <img src="/assets/images/gallery/premiere-famille.jpg"
                     alt="<?= htmlspecialchars(__('nav.chateau')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.chateau')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire" class="sitemap-card">
                <img src="/assets/images/gallery/vendanges-cheval.jpg"
                     alt="<?= htmlspecialchars(__('nav.savoir_faire')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.savoir_faire')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/vins"
               class="sitemap-card sitemap-card--soft-zoom sitemap-card--fit">
                <img src="/assets/images/gallery/exposition-vins.jpg"
                     alt="<?= htmlspecialchars(__('nav.wines')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.wines')) ?></span>
            </a>

        </div>
    </section>

    <!-- Pages annexes -->
    <section class="sitemap-cards-section sitemap-cards-section--dark container"
             aria-label="<?= htmlspecialchars(__('sitemap.section_annex')) ?>">
        <h2 class="sitemap-cards-section__title">
            <?= htmlspecialchars(strtoupper(__('sitemap.section_annex'))) ?>
        </h2>
        <div class="sitemap-grid sitemap-grid--4">

            <a href="/<?= htmlspecialchars($navLang) ?>" class="sitemap-card">
                <img src="/assets/images/gallery/domaine-ancien.jpg"
                     alt="<?= htmlspecialchars(__('nav.home')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.home')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/contact" class="sitemap-card">
                <img src="/assets/images/gallery/proprietaire.jpeg"
                     alt="<?= htmlspecialchars(__('nav.contact')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.contact')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/inscription" class="sitemap-card">
                <img src="/assets/images/gallery/vendanges-1956.jpg"
                     alt="<?= htmlspecialchars(__('nav.register')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.register')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/actualites" class="sitemap-card">
                <img src="/assets/images/gallery/domaine-noir-blanc.jpg"
                     alt="<?= htmlspecialchars(__('nav.news')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.news')) ?></span>
            </a>

        </div>
    </section>

    <!-- Autres pages -->
    <section class="sitemap-cards-section container"
             aria-label="<?= htmlspecialchars(__('sitemap.section_other')) ?>">
        <h2 class="sitemap-cards-section__title">
            <?= htmlspecialchars(strtoupper(__('sitemap.section_other'))) ?>
        </h2>
        <div class="sitemap-grid sitemap-grid--4">

            <a href="/<?= htmlspecialchars($navLang) ?>/jeux" class="sitemap-card">
                <img src="/assets/images/gallery/apercu-vins.jpg"
                     alt="<?= htmlspecialchars(__('jeux.title')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('jeux.title')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/support" class="sitemap-card sitemap-card--fit">
                <img src="/assets/images/gallery/etiquette-1933.jpg"
                     alt="<?= htmlspecialchars(__('support.title')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('support.title')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/plan-du-site" class="sitemap-card">
                <img src="/assets/images/gallery/anciens-proprietaires.jpg"
                     alt="<?= htmlspecialchars(__('footer.sitemap')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.sitemap')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales" class="sitemap-card">
                <img src="/assets/images/gallery/chai-barriques.jpg"
                     alt="<?= htmlspecialchars(__('footer.legal_notice')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.legal_notice')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite" class="sitemap-card">
                <img src="/assets/images/gallery/chien-vignes.jpg"
                     alt="<?= htmlspecialchars(__('footer.privacy_policy')) ?>" loading="lazy">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.privacy_policy')) ?></span>
            </a>

        </div>
    </section>


</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
