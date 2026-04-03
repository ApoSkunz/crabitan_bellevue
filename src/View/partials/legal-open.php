<?php
/**
 * Ouverture commune aux pages légales (mentions-légales, politique-confidentialité).
 * Variables attendues : $pageTitle (string), $navLang (string), $isBare (bool)
 */
if (!$isBare) {
    require_once SRC_PATH . '/View/partials/head.php';
    require_once SRC_PATH . '/View/partials/header.php';
}
?>
<?php if ($isBare) : ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($navLang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bare-legal">
    <div class="bare-legal__bar">
        <span><?= htmlspecialchars($pageTitle) ?></span>
        <button type="button" class="bare-legal__close" onclick="window.close()" aria-label="Fermer">&#10005;</button>
    </div>
    <article class="legal-content container" aria-label="<?= htmlspecialchars($pageTitle) ?>">
<?php else : ?>
<main class="page-legal" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars($pageTitle) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>
    <article class="legal-content container" aria-label="<?= htmlspecialchars($pageTitle) ?>">
<?php endif; ?>
