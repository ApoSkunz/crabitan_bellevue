<!DOCTYPE html>
<html lang="<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?><?= isset($pageTitle) ? ' — ' : '' ?><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="<?= ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
