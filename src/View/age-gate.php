<!DOCTYPE html>
<html lang="<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="age-gate-page">

<main class="age-gate" role="main">
    <p class="age-gate__quote">&ldquo; <?= htmlspecialchars(__('age_gate.quote')) ?> &rdquo;</p>

    <div class="age-gate__card">
        <button class="age-gate__info" type="button" aria-label="<?= htmlspecialchars(__('age_gate.info_label')) ?>">
            &#x2139;
        </button>

        <div class="age-gate__logo">
            <img src="/assets/images/logo.png" alt="<?= htmlspecialchars(APP_NAME) ?>" width="100" height="80">
            <p class="age-gate__logo-text">Château<br>Crabitan&#8209;Bellevue</p>
        </div>

        <p class="age-gate__intro"><?= htmlspecialchars(__('age_gate.intro')) ?></p>

        <form id="age-gate-form" class="age-gate__form" method="post" action="/age-gate">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="age-gate__choices" role="group" aria-labelledby="age-gate-legend">
                <p id="age-gate-legend" class="sr-only"><?= htmlspecialchars(__('age_gate.choice_legend')) ?></p>

                <label class="age-gate__choice">
                    <input type="radio" name="legal_age" value="1" required>
                    <span><?= htmlspecialchars(__('age_gate.legal')) ?></span>
                </label>

                <label class="age-gate__choice">
                    <input type="radio" name="legal_age" value="0">
                    <span><?= htmlspecialchars(__('age_gate.not_legal')) ?></span>
                </label>
            </div>

            <p id="age-gate-error" class="age-gate__error" hidden>
                <?= htmlspecialchars(__('age_gate.error')) ?>
            </p>

            <div class="age-gate__footer">
                <label class="age-gate__remember">
                    <input type="checkbox" name="remember" value="1">
                    <span><?= htmlspecialchars(__('age_gate.remember')) ?></span>
                </label>
                <button type="submit" class="btn btn--gold"><?= htmlspecialchars(__('age_gate.enter')) ?></button>
            </div>
        </form>
    </div>
</main>

<script src="/assets/js/main.js"></script>
</body>
</html>
