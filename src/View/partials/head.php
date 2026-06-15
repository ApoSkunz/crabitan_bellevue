<!DOCTYPE html>
<html lang="<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? '') ?><?= isset($pageTitle) ? ' — ' : '' ?><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="<?= ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' ?>">
<?php if (!empty($metaDescription)) : ?>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
<?php endif; ?>
<?php if (!empty($canonical)) : ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
<?php endif; ?>
<?php if (!empty($hreflangFr)) : ?>
    <link rel="alternate" hreflang="fr" href="<?= htmlspecialchars($hreflangFr) ?>">
    <link rel="alternate" hreflang="en" href="<?= htmlspecialchars($hreflangEn ?? $hreflangFr) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($hreflangFr) ?>">
<?php endif; ?>
<?php if (!empty($ogTitle)) : ?>
    <meta property="og:site_name" content="<?= htmlspecialchars(APP_NAME) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription ?? ($metaDescription ?? '')) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType ?? 'website') ?>">
<?php if (!empty($ogUrl)) : ?>
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
<?php endif; ?>
<?php if (!empty($ogImage)) : ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription ?? ($metaDescription ?? '')) ?>">
<?php if (!empty($ogImage)) : ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($jsonLd)) : ?>
    <script type="application/ld+json" nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>"><?= $jsonLd ?></script>
<?php endif; ?>
<?php if (!empty($breadcrumbLd)) : ?>
    <script type="application/ld+json" nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>"><?= $breadcrumbLd ?></script>
<?php endif; ?>
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
<?php if (!empty($preloadCarousel)) : ?>
    <link rel="preload" as="image"
          imagesrcset="/assets/images/carousel/vignoble-ete-800w.webp 800w, /assets/images/carousel/vignoble-ete-1600w.webp 1600w"
          imagesizes="100vw">
<?php endif; ?>
    <link rel="preload" as="font" href="/assets/fonts/Cinzel-Regular.otf" type="font/otf" crossorigin>
    <link rel="preload" as="font" href="/assets/fonts/CaviarDreams.woff" type="font/woff" crossorigin>
    <link rel="stylesheet" href="/assets/css/main.css">
    <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">window.__appDebug = <?= (defined('APP_ENV') && APP_ENV === 'development') ? 'true' : 'false' ?>;</script>

<?php if (isset($_COOKIE['auth_token'])) : ?>
    <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
    (function () {
        'use strict';
        var LANG      = '<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr', ENT_QUOTES) ?>';
        var CHANNEL   = 'cb-session';
        var LEADER_TS = 'cb-session-poll-ts';
        var INTERVAL  = 30000;

        var bc = new BroadcastChannel(CHANNEL);

        bc.onmessage = function (e) {
            if (e.data === 'logout') doLogout();
        };

        function doLogout() {
            try { bc.close(); } catch (_) {}
            window.location.href = '/' + LANG + '/session-expiree';
        }

        async function poll() {
            var now  = Date.now();
            var last = parseInt(localStorage.getItem(LEADER_TS) || '0', 10);
            if (now - last < INTERVAL - 2000) return;
            localStorage.setItem(LEADER_TS, now);
            try {
                var r = await fetch('/api/session/ping', { credentials: 'same-origin' });
                if (r.status === 401) {
                    bc.postMessage('logout');
                    doLogout();
                }
            } catch (_) {
                // Coupure réseau — pas de déconnexion automatique
            }
        }

        setInterval(poll, INTERVAL);
    }());
    </script>
<?php endif; ?>
<?php
$majorityPending   = isset($_SESSION['majority_pending']) && $_SESSION['majority_pending'] === true;
$majorityHasCookie = isset($_COOKIE['auth_token']);
if ($majorityPending && $majorityHasCookie) :
    $majorityRedirect = $_SERVER['REQUEST_URI'] ?? "/{$navLang}/mon-compte";
    include_once SRC_PATH . '/View/components/majority-modal.php';
endif;
?>
</head>
<body>
<a class="skip-link" href="#main-content"><?= htmlspecialchars(__('accessibility.skip_to_content')) ?></a>
<?php if (($_COOKIE['age_verified'] ?? '') !== '1') : ?>
<?php require_once SRC_PATH . '/View/partials/age-gate-overlay.php'; ?>
<?php endif; ?>
