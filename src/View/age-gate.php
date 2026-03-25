<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'fr') ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        aria-label="Basculer le thème jour / nuit"
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
                <img src="/assets/images/logo/crabitan-bellevue-logo.png" alt="<?= htmlspecialchars(APP_NAME) ?>" width="130" height="130">
                <p class="age-gate__logo-text">Château<br>Crabitan&#160;Bellevue</p>
            </div>

            <p class="age-gate__intro"><?= htmlspecialchars(__('age_gate.intro')) ?></p>

            <form id="age-gate-form" method="post" action="/age-gate">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="age-gate__choices-wrap">
                    <fieldset class="age-gate__choices">
                        <legend class="sr-only"><?= htmlspecialchars(__('age_gate.choice_legend')) ?></legend>

                        <label class="age-gate__choice">
                            <input type="radio" name="legal_age" value="1" required>
                            <span><?= htmlspecialchars(__('age_gate.legal')) ?></span>
                        </label>

                        <label class="age-gate__choice">
                            <input type="radio" name="legal_age" value="0">
                            <span><?= htmlspecialchars(__('age_gate.not_legal')) ?></span>
                        </label>
                    </fieldset>

                    <p id="age-gate-error" class="age-gate__error" hidden>
                        <?= htmlspecialchars(__('age_gate.error')) ?>
                    </p>
                </div>

                <div class="age-gate__footer">
                    <label class="age-gate__remember">
                        <input type="checkbox" name="remember" value="1">
                        <span><?= htmlspecialchars(__('age_gate.remember')) ?></span>
                    </label>
                    <button type="submit" class="btn btn--gold"><?= htmlspecialchars(__('age_gate.enter')) ?></button>
                </div>
            </form>
        </div>
    </div>

</main>

<?php require_once SRC_PATH . '/View/partials/cookie-banner.php'; ?>

<script src="/assets/js/main.js"></script>
</body>
</html>
