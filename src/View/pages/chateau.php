<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('nav.chateau');
$metaDescription = $navLang === 'en'
    ? 'Discover the history of Château Crabitan Bellevue, its vineyard and its commitment to Sainte-Croix-du-Mont AOC wines.'
    : 'Découvrez l\'histoire du Château Crabitan Bellevue, son vignoble et son engagement pour les vins de Sainte-Croix-du-Mont AOC.';
$seoSlug    = 'le-chateau';
$seoOgTitle = __('nav.chateau') . ' — ' . APP_NAME;
$seoOgImage = '/assets/images/gallery/premiere-famille-600w.jpg';
require_once SRC_PATH . '/View/partials/seo-meta.php';

$jsonLd = json_encode([
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => __('nav.chateau') . ' — ' . APP_NAME,
    'description'   => $metaDescription,
    'datePublished' => '1870-01-01',
    'dateModified'  => '2026-01-01',
    'author'        => ['@type' => 'Organization', 'name' => 'GFA Bernard Solane et Fils'],
    'publisher'     => [
        '@type' => 'Organization',
        'name'  => APP_NAME,
        'logo'  => ['@type' => 'ImageObject', 'url' => APP_BASE_URL . '/assets/images/logo/crabitan-bellevue-logo.png'],
    ],
    'image'         => APP_BASE_URL . '/assets/images/gallery/premiere-famille-600w.jpg',
    'about'         => ['@type' => 'Thing', 'name' => 'Château Crabitan Bellevue — Histoire et vignoble'],
    'url'           => APP_BASE_URL . '/' . $navLang . '/le-chateau',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-chateau" id="main-content">

    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.history_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.chateau')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <!-- Section 1 : Les Origines -->
    <section class="home-section" id="origines">
        <div class="home-section__inner container">
            <div class="home-section__visual home-section__visual--vintage">
                <picture>
                    <source srcset="/assets/images/gallery/premiere-famille-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/premiere-famille-600w.jpg"
                        alt="<?= htmlspecialchars(__('chateau.img_origins_alt')) ?>"
                        fetchpriority="high"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('chateau.origins_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('chateau.origins_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('chateau.origins_text')) ?></p>
            </div>
        </div>
    </section>

    <!-- Section 2 : Vendanges 1956 -->
    <section class="home-section home-section--dark" id="vendanges-1956">
        <div class="home-section__inner container home-section__inner--reverse">
            <div class="home-section__visual home-section__visual--vintage">
                <picture>
                    <source srcset="/assets/images/gallery/vendanges-cheval-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/vendanges-cheval-600w.jpg"
                        alt="<?= htmlspecialchars(__('chateau.img_1956_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('chateau.year1956_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('chateau.year1956_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('chateau.year1956_text')) ?></p>
            </div>
        </div>
    </section>

    <!-- Section 3 : 1975 — Château Crabitan Bellevue -->
    <section class="home-section" id="annee-1975">
        <div class="home-section__inner container">
            <div class="home-section__visual home-section__visual--vintage">
                <picture>
                    <source srcset="/assets/images/gallery/vendanges-1956-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/vendanges-1956-600w.jpg"
                        alt="<?= htmlspecialchars(__('chateau.img_1975_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('chateau.year1975_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('chateau.year1975_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('chateau.year1975_text')) ?></p>
            </div>
        </div>
    </section>

    <!-- Section 4 : 1994 — GFA Bernard Solane et Fils -->
    <section class="home-section home-section--dark" id="annee-1994">
        <div class="home-section__inner container home-section__inner--reverse">
            <div class="home-section__visual">
                <picture>
                    <source srcset="/assets/images/gallery/chai-barriques-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/chai-barriques-600w.jpg"
                        alt="<?= htmlspecialchars(__('chateau.img_1994_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('chateau.year1994_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('chateau.year1994_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('chateau.year1994_text')) ?></p>
            </div>
        </div>
    </section>

    <!-- Section 5 : Aujourd'hui -->
    <section class="home-section" id="aujourd-hui">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <picture>
                    <source srcset="/assets/images/gallery/proprietaire-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/proprietaire-600w.jpg"
                        alt="<?= htmlspecialchars(__('chateau.img_today_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('chateau.today_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('chateau.today_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('chateau.today_text')) ?></p>
            </div>
        </div>
    </section>

</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
