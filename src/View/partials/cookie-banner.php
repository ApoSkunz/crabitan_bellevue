<?php

$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>
<aside
    id="cookie-banner"
    class="cookie-banner"
    aria-label="<?= htmlspecialchars(__('cookie.banner_label')) ?>"
>
    <div class="cookie-banner__body">
        <p class="cookie-banner__text">
            <?= htmlspecialchars(__('cookie.text')) ?>
            <a href="/assets/docs/mentions-legales.pdf" download="Mentions-Legales.pdf">
                <?= htmlspecialchars(__('cookie.learn_more')) ?>
            </a>
        </p>
        <p class="cookie-banner__required" hidden aria-live="polite">
            <?= htmlspecialchars(__('cookie.required')) ?>
        </p>
    </div>
    <div class="cookie-banner__actions">
        <button id="cookie-accept" class="btn btn--gold" type="button">
            <?= htmlspecialchars(__('cookie.accept')) ?>
        </button>
        <button id="cookie-refuse" class="btn btn--ghost" type="button">
            <?= htmlspecialchars(__('cookie.refuse')) ?>
        </button>
    </div>
</aside>
