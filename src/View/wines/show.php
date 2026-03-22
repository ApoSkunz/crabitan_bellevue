<?php
$pageTitle = $slug ?? '';
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>

<main class="page-wine-show" id="main-content">
    <section class="wine-detail container" aria-label="<?= htmlspecialchars(__('nav.wines')) ?>">
        <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="news-article__back">
            &#8592; <?= htmlspecialchars(__('nav.wines')) ?>
        </a>
        <h1 class="home-section__title"><?= htmlspecialchars($slug ?? '') ?></h1>
        <div class="home-section__divider"></div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
