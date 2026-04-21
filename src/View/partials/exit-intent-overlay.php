<?php

/**
 * Exit-intent overlay — inclus via footer.php quand $exitIntentVariant est défini.
 * Variantes : show | boutique | collection | cart | newsletter
 */

if (!isset($exitIntentVariant) || ($isAdmin ?? false)) {
    return;
}

$_eiLang = $navLang ?? $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');

$_eiConfig = [
    'show'       => ['title' => 'exit_intent.show.title',       'body' => 'exit_intent.show.body',       'cta' => 'exit_intent.show.cta',       'path' => '/vins'],
    'boutique'   => ['title' => 'exit_intent.boutique.title',   'body' => 'exit_intent.boutique.body',   'cta' => 'exit_intent.boutique.cta',   'path' => '/vins/collection'],
    'collection' => ['title' => 'exit_intent.collection.title', 'body' => 'exit_intent.collection.body', 'cta' => 'exit_intent.collection.cta', 'path' => '/vins'],
    'cart'       => ['title' => 'exit_intent.cart.title',       'body' => 'exit_intent.cart.body',       'cta' => 'exit_intent.cart.cta',       'path' => '/panier'],
    'newsletter' => ['title' => 'exit_intent.newsletter.title', 'body' => 'exit_intent.newsletter.body', 'cta' => 'exit_intent.newsletter.cta', 'path' => ''],
];

$_eiIsNewsletter = ($exitIntentVariant === 'newsletter');
$_eiCfg          = $_eiConfig[$exitIntentVariant] ?? $_eiConfig['show'];
$_eiHref         = '/' . htmlspecialchars($_eiLang) . $_eiCfg['path'];
?>
<!-- Exit-intent pop-in -->
<!-- NOSONAR Web:S6819 — custom overlay with full JS focus/keyboard management -->
<div id="exit-intent-overlay" class="exit-intent-overlay" aria-hidden="true" role="region"
     aria-labelledby="exit-intent-title">
    <div class="exit-intent-overlay__backdrop" id="exit-intent-backdrop"></div>
    <div class="exit-intent-overlay__box">
        <button class="exit-intent-overlay__close" id="exit-intent-close" type="button"
                aria-label="<?= htmlspecialchars(__('common.close')) ?>">&times;</button>
        <p class="exit-intent-overlay__title" id="exit-intent-title">
            <?= htmlspecialchars(__($_eiCfg['title'])) ?>
        </p>
        <p class="exit-intent-overlay__body">
            <?= htmlspecialchars(__($_eiCfg['body'])) ?>
        </p>
        <?php if ($_eiIsNewsletter) : ?>
        <form id="exit-intent-newsletter-form"
              class="exit-intent-overlay__newsletter-form"
              action="/<?= htmlspecialchars($_eiLang) ?>/newsletter/inscription"
              method="post"
              data-msg-success="<?= htmlspecialchars(__('newsletter.confirm_sent')) ?>"
              data-msg-invalid="<?= htmlspecialchars(__('newsletter.invalid_email')) ?>">
            <div class="exit-intent-overlay__newsletter-row">
                <label for="exit-intent-newsletter-email" class="sr-only">
                    <?= htmlspecialchars(__('newsletter.email_placeholder')) ?>
                </label>
                <?php $_eiPrefill = isset($footerUserEmail) && $footerUserEmail !== '' ? $footerUserEmail : ''; ?>
                <input type="email"
                       id="exit-intent-newsletter-email"
                       name="email"
                       placeholder="<?= htmlspecialchars(__('newsletter.email_placeholder')) ?>"
                       autocomplete="email"
                       value="<?= htmlspecialchars($_eiPrefill) ?>"
                       <?= $_eiPrefill !== '' ? 'readonly' : '' ?>
                       required>
                <button type="submit" class="btn btn--gold">
                    <?= htmlspecialchars(__($_eiCfg['cta'])) ?>
                </button>
            </div>
            <p class="exit-intent-overlay__newsletter-feedback" aria-live="polite" hidden></p>
        </form>
        <button type="button" class="exit-intent-overlay__dismiss">
            <?= htmlspecialchars(__('exit_intent.dismiss')) ?>
        </button>
        <?php else : ?>
        <div class="exit-intent-overlay__actions">
            <a href="<?= $_eiHref ?>" class="btn btn--gold exit-intent-overlay__cta">
                <?= htmlspecialchars(__($_eiCfg['cta'])) ?>
            </a>
            <button type="button" class="exit-intent-overlay__dismiss">
                <?= htmlspecialchars(__('exit_intent.dismiss')) ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    'use strict';
    var STORAGE_KEY = 'exit_intent_shown';
    if (sessionStorage.getItem(STORAGE_KEY)) { return; }

    var overlay  = document.getElementById('exit-intent-overlay');
    var backdrop = document.getElementById('exit-intent-backdrop');
    var closeBtn = document.getElementById('exit-intent-close');
    var dismiss  = document.querySelector('.exit-intent-overlay__dismiss');
    var shown    = false;
    var lastY    = 0;

    function show() {
        if (shown) { return; }
        shown = true;
        sessionStorage.setItem(STORAGE_KEY, '1');
        overlay.removeAttribute('aria-hidden');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('exit-intent-overlay--visible');
    }

    function hide() {
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('exit-intent-overlay--visible');
    }

    document.documentElement.addEventListener('mouseleave', function (e) {
        if (e.clientY < 10) { show(); }
    });

    var scrollUpAcc = 0;
    document.addEventListener('scroll', function () {
        var y = window.scrollY;
        if (y < lastY) {
            scrollUpAcc += lastY - y;
        } else {
            scrollUpAcc = 0;
        }
        if (scrollUpAcc >= 150) { show(); }
        lastY = y;
    }, { passive: true });

    if (closeBtn) { closeBtn.addEventListener('click', hide); }
    if (dismiss)  { dismiss.addEventListener('click', hide); }
    if (backdrop) { backdrop.addEventListener('click', hide); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hide(); }
    });
}());
</script>
