<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('payment.redirect_title')) ?></title>
    <style>
        /* styles inline minimalistes — pas de dépendance CSS externe */
        body {
            font-family: sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .redirect-box {
            text-align: center;
            padding: 2rem;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #ccc;
            border-top-color: #8B6914;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 1rem auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="redirect-box">
    <div class="spinner"></div>
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
<script>document.getElementById('ca-payment-form').submit();</script>
</body>
</html>
