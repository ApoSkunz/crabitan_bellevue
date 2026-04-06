<!DOCTYPE html>
<html lang="<?= htmlspecialchars(defined('CURRENT_LANG') ? CURRENT_LANG : 'fr') ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?><?= isset($pageTitle) ? ' — ' : '' ?><?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="robots" content="<?= ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/main.css">

<?php if (isset($_COOKIE['auth_token'])) : ?>
    <script>
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
</head>
<body>
<?php if (($_COOKIE['age_verified'] ?? '') !== '1') : ?>
<?php require_once SRC_PATH . '/View/partials/age-gate-overlay.php'; ?>
<?php endif; ?>
