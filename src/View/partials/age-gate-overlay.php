<?php
/**
 * Partial — overlay barrière d'âge (Art. L3342-1 CSP).
 * Affiché si cookie age_verified absent. Requiert : $navLang (string).
 */
$overlayLang = $navLang ?? 'fr';
$currentUri  = $_SERVER['REQUEST_URI'] ?? '/' . $overlayLang;

$langUri = static function (string $lang, string $uri): string {
    return preg_replace('#^/(fr|en)(/|$)#', '/' . $lang . '$2', $uri) ?? '/' . $lang;
};

$siteOrigin  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$httpReferer = $_SERVER['HTTP_REFERER'] ?? '';
$exitUrl = (!empty($httpReferer) && !str_starts_with($httpReferer, $siteOrigin))
    ? $httpReferer
    : 'https://www.google.com';
?>
<div class="age-overlay" id="age-overlay" role="dialog" aria-modal="true" aria-labelledby="age-overlay-title">

    <nav class="age-gate__lang" aria-label="Langue / Language">
        <?php foreach (['fr', 'en'] as $l) : ?>
        <a
            href="<?= htmlspecialchars($langUri($l, $currentUri)) ?>"
            lang="<?= htmlspecialchars($l) ?>"
            class="<?= $l === $overlayLang ? 'active' : '' ?>"
            <?= $l === $overlayLang ? 'aria-current="true"' : '' ?>
        ><?= strtoupper($l) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="age-gate__topbar">
        <button
            id="theme-toggle"
            class="theme-toggle"
            type="button"
            aria-label="<?= __('common.toggle_theme') ?>"
        >
            <span class="icon-sun" aria-hidden="true">&#9728;</span>
            <span class="icon-moon" aria-hidden="true">&#9790;</span>
        </button>
    </div>

    <main class="age-gate" role="main">
        <p class="age-gate__quote"><?= htmlspecialchars(__('age_gate.quote')) ?></p>

        <div class="age-gate__card-wrapper">
            <span></span><span></span><span></span><span></span>
            <div class="age-gate__card">
                <div class="age-gate__logo">
                    <img
                        src="/assets/images/logo/crabitan-bellevue-logo-restored.png"
                        alt="<?= htmlspecialchars(APP_NAME) ?>"
                        width="195"
                        height="130"
                    >
                </div>

                <p id="age-overlay-title" class="age-gate__intro"><?= htmlspecialchars(__('age_gate.intro')) ?></p>

                <div class="age-gate__footer">
                    <form method="POST" action="/age-gate">
                        <input type="hidden" name="legal_age" value="1">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUri) ?>">
                        <button type="submit" class="btn btn--gold">
                            <?= htmlspecialchars(__('age_gate.enter')) ?>
                        </button>
                    </form>
                    <form method="POST" action="/age-gate">
                        <input type="hidden" name="legal_age" value="0">
                        <input type="hidden" name="exit_url" value="<?= htmlspecialchars($exitUrl) ?>">
                        <button type="submit" class="age-overlay__btn-exit age-overlay__btn-exit--outline">
                            <?= htmlspecialchars(__('age_gate.exit')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer class="age-gate__legal-footer">
        <a href="/<?= htmlspecialchars($overlayLang) ?>/politique-de-confidentialite" target="_blank" rel="noopener noreferrer">
            <?= htmlspecialchars(__('footer.privacy_policy')) ?>
        </a>
        <span aria-hidden="true">·</span>
        <a href="/<?= htmlspecialchars($overlayLang) ?>/mentions-legales" target="_blank" rel="noopener noreferrer">
            <?= htmlspecialchars(__('footer.legal_notice')) ?>
        </a>
    </footer>

    <?php require_once SRC_PATH . '/View/partials/cookie-banner.php'; ?>

</div>
