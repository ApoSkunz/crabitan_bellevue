<?php
$pageTitle = __('footer.webmaster');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-webmaster" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('footer.made_by')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.webmaster')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="webmaster-content container" aria-label="<?= htmlspecialchars(__('footer.webmaster')) ?>">
        <p class="home-section__text"><?= htmlspecialchars(__('webmaster.bio')) ?></p>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
