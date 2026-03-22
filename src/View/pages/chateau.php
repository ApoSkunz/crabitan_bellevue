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

    <section class="page-content home-section" id="histoire">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <img
                    src="/assets/images/vendanges-cheval.jpg"
                    alt="Vendanges à cheval — Château Crabitan Bellevue"
                    loading="lazy"
                    width="600"
                    height="400"
                >
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.history_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.history_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('home.history_text')) ?></p>
            </div>
        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
