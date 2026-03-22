<?php
$pageTitle = __('footer.sitemap');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>

<main class="page-sitemap" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.sitemap')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="sitemap-content container" aria-label="<?= htmlspecialchars(__('footer.sitemap')) ?>">
        <nav class="sitemap-nav" aria-label="Plan du site">
            <ul class="sitemap-list">
                <li><a href="/<?= htmlspecialchars($navLang) ?>"><?= htmlspecialchars(__('nav.home')) ?></a></li>
                <li>
                    <a href="/<?= htmlspecialchars($navLang) ?>/vins"><?= htmlspecialchars(__('nav.wines')) ?></a>
                    <ul>
                        <li><a href="/<?= htmlspecialchars($navLang) ?>/vins/collection"><?= htmlspecialchars(__('nav.collection')) ?></a></li>
                    </ul>
                </li>
                <li><a href="/<?= htmlspecialchars($navLang) ?>/le-chateau"><?= htmlspecialchars(__('nav.chateau')) ?></a></li>
                <li><a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire"><?= htmlspecialchars(__('nav.savoir_faire')) ?></a></li>
                <li><a href="/<?= htmlspecialchars($navLang) ?>/actualites"><?= htmlspecialchars(__('nav.news')) ?></a></li>
                <li><a href="/<?= htmlspecialchars($navLang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a></li>
                <li><a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales"><?= htmlspecialchars(__('footer.legal_notice')) ?></a></li>
                <li><a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite"><?= htmlspecialchars(__('footer.privacy_policy')) ?></a></li>
            </ul>
        </nav>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
