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
<?php $_eiMinSession = in_array($exitIntentVariant, ['boutique', 'collection'], true) ? 900000 : 0; ?>
<div id="exit-intent-overlay" class="exit-intent-overlay" aria-hidden="true" role="region"
     aria-labelledby="exit-intent-title"
     data-variant-type="<?= $_eiIsNewsletter ? 'newsletter' : 'commerce' ?>"
     data-min-session="<?= $_eiMinSession ?>">
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
    var overlay  = document.getElementById('exit-intent-overlay');
    var backdrop = document.getElementById('exit-intent-backdrop');
    var closeBtn = document.getElementById('exit-intent-close');
    var dismiss  = document.querySelector('.exit-intent-overlay__dismiss');

    var variantType  = (overlay && overlay.dataset.variantType) || 'commerce';
    var COOKIE_NAME  = variantType === 'newsletter' ? 'eid_nl' : 'eid_commerce';
    var COOKIE_TTL   = 7 * 24 * 3600; // 7 jours en secondes
    var MIN_SESSION  = overlay ? (parseInt(overlay.dataset.minSession, 10) || 0) : 0;
    var pageEntryTime = Date.now();

    function hasCookie(name) {
        return document.cookie.split('; ').some(function (c) {
            return c === name + '=1' || c.indexOf(name + '=') === 0;
        });
    }

    function setCookie(name) {
        document.cookie = name + '=1; path=/; max-age=' + COOKIE_TTL + '; SameSite=Lax; Secure';
    }

    if (hasCookie(COOKIE_NAME)) { return; }

    var shown = false;
    var lastY = 0;

    function show() {
        if (shown || hasCookie(COOKIE_NAME)) { return; }
        if (MIN_SESSION > 0 && Date.now() - pageEntryTime < MIN_SESSION) { return; }
        shown = true;
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('exit-intent-overlay--visible');
    }

    function hide(dismissed) {
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('exit-intent-overlay--visible');
        if (dismissed) { setCookie(COOKIE_NAME); }
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

    if (closeBtn) { closeBtn.addEventListener('click', function () { hide(true); }); }
    if (dismiss)  { dismiss.addEventListener('click',   function () { hide(true); }); }
    if (backdrop) { backdrop.addEventListener('click',  function () { hide(true); }); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hide(true); }
    });
}());
</script>
