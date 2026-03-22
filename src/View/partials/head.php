<!DOCTYPE html>
<html lang="<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?><?= isset($pageTitle) ? ' — ' : '' ?><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="<?= ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="icon" type="image/png" href="/assets/images/logo/crabitan-bellevue-logo.png">
    <link rel="stylesheet" href="/assets/css/main.css">

    <?php
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
    if (!$isLocal) :
        ?>
    <!-- Global site tag (gtag.js) - Google Analytics --><!-- NOSONAR Web:SRI — external Google Analytics script; SRI not applicable to dynamically-versioned CDN assets -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-174142197-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'UA-174142197-1');
    </script>
    <?php endif; ?>
</head>
<body>
