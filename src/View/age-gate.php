<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'fr') ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="age-gate-page">

<nav class="age-gate__lang" aria-label="Langue / Language">
    <?php foreach (['fr', 'en'] as $l) :
        $href = '/age-gate?' . http_build_query(['lang' => $l, 'redirect' => $redirect]);
        ?>
        <a
            href="<?= htmlspecialchars($href) ?>"
            lang="<?= htmlspecialchars($l) ?>"
            class="<?= $l === ($lang ?? 'fr') ? 'active' : '' ?>"
            <?= $l === ($lang ?? 'fr') ? 'aria-current="true"' : '' ?>
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
    <p class="age-gate__subquote"><?= htmlspecialchars(__('age_gate.subquote')) ?></p>

    <div class="age-gate__card-wrapper">
        <span></span><span></span><span></span><span></span>
        <div class="age-gate__card">
            <div class="age-gate__logo">
                <img src="/assets/images/logo/crabitan-bellevue-logo-restored.png" alt="<?= htmlspecialchars(APP_NAME) ?>" width="195" height="130">
            </div>

            <p class="age-gate__intro"><?= htmlspecialchars(__('age_gate.intro')) ?></p>

            <p class="age-gate__declare"><?= __('age_gate.declare') ?></p>

            <div class="age-gate__footer">
                <label class="age-gate__remember">
                    <input type="checkbox" name="remember" value="1" form="age-gate-enter">
                    <span><?= htmlspecialchars(__('age_gate.remember')) ?></span>
                </label>
                <div class="age-gate__footer-actions">
                    <form method="post" action="/age-gate" id="age-gate-enter">
                        <input type="hidden" name="legal_age" value="1">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                        <button type="submit" class="btn btn--gold"><?= htmlspecialchars(__('age_gate.enter')) ?></button>
                    </form>
                    <form method="post" action="/age-gate">
                        <input type="hidden" name="legal_age" value="0">
                        <button type="submit" class="age-overlay__btn-exit age-overlay__btn-exit--outline"><?= htmlspecialchars(__('age_gate.exit')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>

<footer class="age-gate__legal-footer">
    <a href="/<?= htmlspecialchars($lang ?? 'fr') ?>/politique-de-confidentialite" target="_blank" rel="noopener noreferrer">
        <?= htmlspecialchars(__('footer.privacy_policy')) ?>
    </a>
    <span aria-hidden="true">·</span>
    <a href="/<?= htmlspecialchars($lang ?? 'fr') ?>/mentions-legales" target="_blank" rel="noopener noreferrer">
        <?= htmlspecialchars(__('footer.legal_notice')) ?>
    </a>
</footer>

<?php require_once SRC_PATH . '/View/partials/cookie-banner.php'; ?>

<script src="/assets/js/main.js" defer></script>
</body>
</html>
