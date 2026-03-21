<!DOCTYPE html>
<html lang="<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?><?= isset($pageTitle) ? ' — ' : '' ?><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="<?= ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="icon" type="image/png" href="/assets/images/crabitan-bellevue-logo.png">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
