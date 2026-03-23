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

    <!-- Formulaire de contact -->
    <section class="contact-form-section" id="contact-form">
        <div class="container">
            <h2 class="contact-form__title"><?= htmlspecialchars(__('contact.section_form')) ?></h2>
            <div class="home-section__divider home-section__divider--center"></div>

            <form class="contact-form" method="post" action="/<?= htmlspecialchars($navLang) ?>/contact" novalidate>

                <fieldset class="contact-form__fieldset">
                    <legend class="contact-form__legend"><?= htmlspecialchars(__('form.gender')) ?></legend>
                    <div class="contact-form__radios">
                        <label class="contact-form__radio">
                            <input type="radio" name="gender" value="m" required>
                            <span><?= htmlspecialchars(__('form.gender.m')) ?></span>
                        </label>
                        <label class="contact-form__radio">
                            <input type="radio" name="gender" value="f">
                            <span><?= htmlspecialchars(__('form.gender.f')) ?></span>
                        </label>
                        <label class="contact-form__radio">
                            <input type="radio" name="gender" value="other">
                            <span><?= htmlspecialchars(__('form.gender.other')) ?></span>
                        </label>
                    </div>
                </fieldset>

                <div class="contact-form__row">
                    <div class="contact-form__group">
                        <label for="contact-firstname"><?= htmlspecialchars(__('form.firstname')) ?> *</label>
                        <input type="text" id="contact-firstname" name="firstname"
                               autocomplete="given-name" required>
                    </div>
                    <div class="contact-form__group">
                        <label for="contact-lastname"><?= htmlspecialchars(__('form.lastname')) ?> *</label>
                        <input type="text" id="contact-lastname" name="lastname"
                               autocomplete="family-name" required>
                    </div>
                </div>

                <div class="contact-form__row">
                    <div class="contact-form__group">
                        <label for="contact-email"><?= htmlspecialchars(__('auth.email')) ?> *</label>
                        <input type="email" id="contact-email" name="email"
                               autocomplete="email" required>
                    </div>
                    <div class="contact-form__group">
                        <label for="contact-subject"><?= htmlspecialchars(__('contact.form_subject')) ?> *</label>
                        <select id="contact-subject" name="subject" required>
                            <option value="" disabled selected>
                                <?= htmlspecialchars(__('contact.form_subject')) ?>
                            </option>
                            <option value="general">
                                <?= htmlspecialchars(__('contact.subject.general')) ?>
                            </option>
                            <option value="order">
                                <?= htmlspecialchars(__('contact.subject.order')) ?>
                            </option>
                            <option value="bon_commande">
                                <?= htmlspecialchars(__('contact.subject.bon_commande')) ?>
                            </option>
                            <option value="visit">
                                <?= htmlspecialchars(__('contact.subject.visit')) ?>
                            </option>
                            <option value="press">
                                <?= htmlspecialchars(__('contact.subject.press')) ?>
                            </option>
                            <option value="other">
                                <?= htmlspecialchars(__('contact.subject.other')) ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="contact-form__group">
                    <label for="contact-message"><?= htmlspecialchars(__('contact.form_message')) ?> *</label>
                    <textarea id="contact-message" name="message" rows="6" required></textarea>
                </div>

                <div class="contact-form__rgpd">
                    <label class="contact-form__checkbox">
                        <input type="checkbox" name="rgpd" value="1" required>
                        <span><?= htmlspecialchars(__('contact.form_rgpd')) ?></span>
                    </label>
                </div>

                <div class="contact-form__submit">
                    <button type="submit" class="btn btn--gold">
                        <?= htmlspecialchars(__('btn.submit')) ?>
                    </button>
                </div>

            </form>
        </div>
    </section>

    <!-- Localisation -->
    <section class="contact-section home-section" id="contact-location">
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
                            <?= htmlspecialchars(__('contact.section_where')) ?>
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
