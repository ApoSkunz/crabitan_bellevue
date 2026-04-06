<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('payment.success_title')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .payment-wait {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            text-align: center;
            padding: 2rem;
        }
        .payment-wait__spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--color-surface, #f0ebe1);
            border-top-color: var(--color-gold, #8B6914);
            border-radius: 50%;
            animation: pw-spin 0.9s linear infinite;
            margin-bottom: 1.5rem;
        }
        @keyframes pw-spin { to { transform: rotate(360deg); } }
        .payment-wait__heading {
            font-family: var(--font-serif, serif);
            font-size: 1.5rem;
            color: var(--color-text, #1a1a1a);
            margin: 0 0 0.75rem;
        }
        .payment-wait__message {
            color: var(--color-text-muted, #666);
            margin: 0 0 0.5rem;
        }
        .payment-wait__ref {
            font-size: 0.9rem;
            color: var(--color-text-muted, #666);
        }
        .payment-wait__fallback {
            margin-top: 2rem;
            font-size: 0.85rem;
            color: var(--color-text-muted, #666);
        }
        .payment-wait__fallback a {
            color: var(--color-gold, #8B6914);
        }
    </style>
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

<script>
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
