<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('footer.sitemap');
$metaDescription = $navLang === 'en'
    ? 'Sitemap of the Château Crabitan Bellevue website — find all our pages easily.'
    : 'Plan du site du Château Crabitan Bellevue — retrouvez facilement toutes nos pages.';
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<string, array<string,string>|null> $wineImages */
$wineImages = $wineImages ?? [];

$fallbackWineImg = '/assets/images/gallery/apercu-vins.jpg';

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
                <img src="<?= $wineImg($wineImages['sweet'] ?? null, $fallbackWineImg) ?>"
                     alt="<?= htmlspecialchars(__('wine.color.sweet')) ?>"
                     fetchpriority="high" width="300" height="420">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=sweet">
                    <?= htmlspecialchars(__('sitemap.wine_sweet')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--reverse sitemap-wine-row--red">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['red'] ?? null, $fallbackWineImg) ?>"
                     alt="<?= htmlspecialchars(__('wine.color.red')) ?>"
                     loading="lazy" width="300" height="420">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=red">
                    <?= htmlspecialchars(__('sitemap.wine_red')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--white">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['white'] ?? null, $fallbackWineImg) ?>"
                     alt="<?= htmlspecialchars(__('wine.color.white')) ?>"
                     loading="lazy" width="300" height="420">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=white">
                    <?= htmlspecialchars(__('sitemap.wine_white')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--reverse sitemap-wine-row--rose">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['rosé'] ?? null, $fallbackWineImg) ?>"
                     alt="<?= htmlspecialchars(__('wine.color.rosé')) ?>"
                     loading="lazy" width="300" height="420">
            </div>
            <div class="sitemap-wine-row__body">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins?color=ros%C3%A9">
                    <?= htmlspecialchars(__('sitemap.wine_rose')) ?>
                </a>
            </div>
        </div>

        <div class="sitemap-wine-row sitemap-wine-row--collection">
            <div class="sitemap-wine-row__img">
                <img src="<?= $wineImg($wineImages['collection'] ?? null, $fallbackWineImg) ?>"
                     alt="<?= htmlspecialchars(__('nav.collection')) ?>"
                     loading="lazy" width="300" height="420">
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
                <picture>
                    <source srcset="/assets/images/gallery/premiere-famille-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/premiere-famille-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.chateau')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/vendanges-cheval-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/vendanges-cheval-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.savoir_faire')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/vins"
               class="sitemap-card sitemap-card--soft-zoom sitemap-card--fit">
                <picture>
                    <source srcset="/assets/images/gallery/exposition-vins-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/exposition-vins-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
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
        <div class="sitemap-grid sitemap-grid--3">

            <a href="/<?= htmlspecialchars($navLang) ?>" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/domaine-ancien-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/domaine-ancien-600w.jpg"
                         alt="" loading="lazy" width="600" height="499">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.home')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/contact" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/proprietaire-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/proprietaire-600w.jpg"
                         alt="" loading="lazy" width="600" height="306">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('nav.contact')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/actualites" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/domaine-noir-blanc-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/domaine-noir-blanc-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
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
                <picture>
                    <source srcset="/assets/images/gallery/vendanges-1956-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/vendanges-1956-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('jeux.title')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/support" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/etiquette-1933-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/etiquette-1933-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('support.title')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/plan-du-site" class="sitemap-card">
                <img src="/assets/images/gallery/anciens-proprietaires.jpg"
                     alt="" loading="lazy" width="558" height="248">
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.sitemap')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/chai-barriques-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/chai-barriques-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.legal_notice')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/chien-vignes-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/chien-vignes-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.privacy_policy')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/conditions-generales-de-vente" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/apercu-vins-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/apercu-vins-600w.jpg"
                         alt="" loading="lazy" width="600" height="469">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.cgv')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/credits-medias" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/botrytis-cinerea-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/botrytis-cinerea-600w.jpg"
                         alt="" loading="lazy" width="600" height="150">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('footer.credits_medias')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/galerie" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/chai-barriques-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/chai-barriques-600w.jpg"
                         alt="" loading="lazy" width="600" height="267">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('gallery.title')) ?></span>
            </a>

            <a href="/<?= htmlspecialchars($navLang) ?>/accessibilite" class="sitemap-card">
                <picture>
                    <source srcset="/assets/images/gallery/domaine-ancien-600w.webp" type="image/webp">
                    <img src="/assets/images/gallery/domaine-ancien-600w.jpg"
                         alt="" loading="lazy" width="600" height="499">
                </picture>
                <span class="sitemap-card__label"><?= htmlspecialchars(__('accessibility.title')) ?></span>
            </a>

        </div>
    </section>


</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
