<?php
$pageTitle = __('nav.contact');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-contact" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('contact.tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.contact')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="contact-section home-section" id="contact">
        <div class="container">
            <div class="home-location__inner">

                <div class="home-location__info">
                    <div class="home-location__address">
                        <p class="home-location__name"><?= htmlspecialchars(APP_NAME) ?></p>
                        <p><?= htmlspecialchars(__('home.location_address')) ?></p>
                        <p>France</p>
                    </div>
                    <div class="home-location__contact">
                        <h2 class="home-location__contact-title">
                            <?= htmlspecialchars(__('home.location_contact')) ?>
                        </h2>
                        <p>
                            <?php $phoneRaw = preg_replace('/\s/', '', __('home.location_phone')) ?? ''; ?>
                            <a href="tel:<?= htmlspecialchars($phoneRaw) ?>">
                                <?= htmlspecialchars(__('home.location_phone')) ?>
                            </a>
                        </p>
                    </div>
                </div>

                <div class="home-location__map">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d362595.44420850335!2d-0.5876012943361956!3d44.76496434602363!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd556ceb75abc90d%3A0x56c6cb9d5cb560f4!2sCh%C3%A2teau%20Crabitan%20Bellevue!5e0!3m2!1sfr!2sfr!4v1586246850971!5m2!1sfr!2sfr"
                        width="100%"
                        height="400"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Localisation du Château Crabitan Bellevue"
                    ></iframe>
                </div>

            </div>
        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
