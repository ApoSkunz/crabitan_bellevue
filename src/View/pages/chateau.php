<?php
$pageTitle = __('nav.chateau');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
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
                <img
                    src="/assets/images/gallery/premiere-famille.jpg"
                    alt="La famille Solane — fin XIXe siècle"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
                <img
                    src="/assets/images/gallery/vendanges-cheval.jpg"
                    alt="Vendanges 1956 — Château Crabitan Bellevue"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
                <img
                    src="/assets/images/gallery/vendanges-1956.jpg"
                    alt="Vendanges années 1970 — Château Crabitan Bellevue"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
                <img
                    src="/assets/images/gallery/chai-barriques.jpg"
                    alt="Le chai — Château Crabitan Bellevue"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
                <img
                    src="/assets/images/gallery/proprietaire.jpeg"
                    alt="Nicolas et Corinne Solane — Château Crabitan Bellevue"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
