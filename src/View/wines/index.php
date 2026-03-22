<?php
$pageTitle = __('nav.wines');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>

<main class="page-wines" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.wines')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="wines-catalog" aria-label="<?= htmlspecialchars(__('nav.wines')) ?>">
        <div class="container">
            <p class="wines-catalog__intro"><?= htmlspecialchars(__('home.wines_text')) ?></p>
            <div class="wines-catalog__actions">
                <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection" class="btn btn--gold">
                    <?= htmlspecialchars(__('nav.collection')) ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
