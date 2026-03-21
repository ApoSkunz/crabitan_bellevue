<?php
$pageTitle = __('footer.legal_notice');
require SRC_PATH . '/View/partials/head.php';
require SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>

<main class="page-legal" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.legal_notice')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="legal-content container" aria-label="<?= htmlspecialchars(__('footer.legal_notice')) ?>">
        <h2><?= htmlspecialchars(__('legal.editor_title')) ?></h2>
        <p><?= htmlspecialchars(APP_NAME) ?></p>
        <p>Crabitan, 33410 Sainte-Croix-du-Mont — France</p>
        <p>
            <?php $phoneRaw = preg_replace('/\s/', '', __('home.location_phone')) ?? ''; ?>
            <a href="tel:<?= htmlspecialchars($phoneRaw) ?>"><?= htmlspecialchars(__('home.location_phone')) ?></a>
        </p>

        <h2><?= htmlspecialchars(__('legal.hosting_title')) ?></h2>
        <p><?= htmlspecialchars(__('legal.hosting_info')) ?></p>

        <h2><?= htmlspecialchars(__('legal.data_title')) ?></h2>
        <p><?= htmlspecialchars(__('legal.data_info')) ?></p>
    </section>
</main>

<?php require SRC_PATH . '/View/partials/footer.php'; ?>
