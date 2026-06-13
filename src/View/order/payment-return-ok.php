<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('payment.success_title')) ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<?php require_once SRC_PATH . '/View/partials/header.php'; ?>

<main class="page-payment-wait" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('payment.success_heading')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="container">
        <div class="payment-wait">
            <div class="payment-wait__spinner" aria-hidden="true"></div>
            <h2 class="payment-wait__heading"><?= htmlspecialchars(__('payment.success_heading')) ?></h2>
            <p class="payment-wait__message"><?= htmlspecialchars(__('payment.success_message')) ?></p>
            <?php if ($ref !== '') : ?>
            <p class="payment-wait__ref">
                <?= htmlspecialchars($lang === 'en' ? 'Order reference' : 'Référence commande') ?> :
                <strong><?= htmlspecialchars($ref) ?></strong>
            </p>
            <?php endif; ?>
            <p class="payment-wait__fallback" id="js-payment-fallback" hidden>
                <?= sprintf(
                    __('payment.success_fallback'),
                    htmlspecialchars($lang)
                ) ?>
            </p>
        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    'use strict';

    var MAX_ATTEMPTS = 5;
    var INTERVAL_MS  = 3000;
    var attempts     = 0;
    var ordersUrl    = '/<?= htmlspecialchars($lang) ?>/mon-compte/commandes';
    var ref          = <?= json_encode($ref) ?>;

    function checkOrder() {
        attempts++;
        var url = '/<?= htmlspecialchars($lang) ?>/commande/check-confirmation' +
                  (ref ? '?ref=' + encodeURIComponent(ref) : '');

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ready) {
                    window.location.href = ordersUrl;
                } else if (attempts >= MAX_ATTEMPTS) {
                    showFallback();
                } else {
                    setTimeout(checkOrder, INTERVAL_MS);
                }
            })
            .catch(function () {
                if (attempts >= MAX_ATTEMPTS) {
                    showFallback();
                } else {
                    setTimeout(checkOrder, INTERVAL_MS);
                }
            });
    }

    function showFallback() {
        var el = document.getElementById('js-payment-fallback');
        if (el) { el.hidden = false; }
    }

    setTimeout(checkOrder, INTERVAL_MS);
})();
</script>
</body>
</html>
