<?php
$pageTitle = __('nav.collection');
require SRC_PATH . '/View/partials/head.php';
require SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>

<main class="page-wines-collection" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.collection')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="wines-collection" aria-label="<?= htmlspecialchars(__('nav.collection')) ?>">
        <div class="container">
            <p class="wines-catalog__intro"><?= htmlspecialchars(__('home.wines_text')) ?></p>
            <div class="wines-catalog__actions">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="btn btn--gold">
                    &#8592; <?= htmlspecialchars(__('nav.wines')) ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php require SRC_PATH . '/View/partials/footer.php'; ?>
