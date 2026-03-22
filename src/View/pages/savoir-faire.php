<?php
$pageTitle = __('nav.savoir_faire');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-savoir-faire" id="main-content">

    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.savoir_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.savoir_faire')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <!-- Section 1 : Le Vignoble -->
    <section class="home-section" id="vignoble">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <img
                    src="/assets/images/carousel/vignoble-ete.jpg"
                    alt="<?= htmlspecialchars(__('savoir.img_vignoble_alt')) ?>"
                    loading="lazy"
                    width="600"
                    height="400"
                >
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('savoir.vignoble_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('savoir.vignoble_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('savoir.vignoble_text')) ?></p>
            </div>
        </div>
    </section>

    <!-- Section 2 : La Vinification -->
    <section class="home-section home-section--dark" id="vinification">
        <div class="home-section__inner container home-section__inner--reverse">
            <div class="home-section__visual">
                <img
                    src="/assets/images/carousel/raisins-recolte.jpg"
                    alt="<?= htmlspecialchars(__('savoir.img_vinif_alt')) ?>"
                    loading="lazy"
                    width="600"
                    height="400"
                >
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('savoir.vinif_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('savoir.vinif_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('savoir.vinif_text')) ?></p>
            </div>
        </div>
    </section>

    <!-- Section 3 : L'Élevage -->
    <section class="home-section" id="elevage">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <img
                    src="/assets/images/gallery/chai-barriques.jpg"
                    alt="<?= htmlspecialchars(__('savoir.img_elevage_alt')) ?>"
                    loading="lazy"
                    width="600"
                    height="400"
                >
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('savoir.elevage_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('savoir.elevage_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('savoir.elevage_text')) ?></p>
            </div>
        </div>
    </section>

</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
