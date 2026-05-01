<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('payment.redirect_title')) ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-payment-redirect">
<div class="redirect-box">
    <div class="payment-spinner"></div>
    <p><?= htmlspecialchars(__('payment.redirect_message')) ?></p>
    <form id="ca-payment-form" method="POST" action="<?= htmlspecialchars($url) ?>">
        <?php foreach ($fields as $name => $value) : ?>
            <input type="hidden"
                   name="<?= htmlspecialchars($name) ?>"
                   value="<?= htmlspecialchars($value) ?>">
        <?php endforeach; ?>
        <noscript>
            <button type="submit"><?= htmlspecialchars(__('payment.redirect_button')) ?></button>
        </noscript>
    </form>
</div>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">document.getElementById('ca-payment-form').submit();</script>
</body>
</html>
