<?php

/**
 * Admin layout — ouverture
 * Variables attendues : $pageTitle, $adminSection, $adminUser, $breadcrumbs
 */

$adminSection = $adminSection ?? 'dashboard';
$pageTitle    = $pageTitle    ?? 'Admin';
$breadcrumbs  = $breadcrumbs  ?? [];

$navIcons = [
    'dashboard'  => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.5" y="1.5" width="4.5" height="4.5" rx="0.5"/><rect x="9" y="1.5" width="4.5" height="4.5" rx="0.5"/><rect x="1.5" y="9" width="4.5" height="4.5" rx="0.5"/><rect x="9" y="9" width="4.5" height="4.5" rx="0.5"/></svg>',
    'wines'      => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 1.5h7L9 7.5a1.5 1.5 0 01-3 0L4 1.5z"/><line x1="7.5" y1="9" x2="7.5" y2="12.5"/><line x1="5" y1="13.5" x2="10" y2="13.5"/></svg>',
    'orders'     => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4.5l5.5-3 5.5 3v6l-5.5 3-5.5-3v-6z"/><path d="M2 4.5l5.5 3 5.5-3"/><line x1="7.5" y1="7.5" x2="7.5" y2="13.5"/></svg>',
    'accounts'   => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="4.5" r="2.5"/><path d="M2 13.5c0-3.038 2.462-5.5 5.5-5.5s5.5 2.462 5.5 5.5"/></svg>',
    'pricing'    => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 1.5h5.5l6 6-5.5 5.5-6-6V1.5z"/><circle cx="4.5" cy="4.5" r="1" fill="currentColor" stroke="none"/></svg>',
    'news'       => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.5" y="2.5" width="12" height="10" rx="1"/><line x1="4" y1="5.5" x2="11" y2="5.5"/><line x1="4" y1="8" x2="11" y2="8"/><line x1="4" y1="10.5" x2="8" y2="10.5"/></svg>',
    'newsletter'   => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.5" y="3.5" width="12" height="8" rx="1"/><path d="M1.5 5l6 4 6-4"/></svg>',
    'order_forms'  => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="1.5" width="10" height="12" rx="1"/><line x1="5" y1="5" x2="10" y2="5"/><line x1="5" y1="7.5" x2="10" y2="7.5"/><line x1="5" y1="10" x2="8" y2="10"/><path d="M9.5 9.5l1.5 1.5" stroke-linecap="round"/><path d="M11 11l1.5-1.5" stroke-linecap="round"/></svg>',
    'stats'        => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.5" y="9" width="2.5" height="4.5" rx="0.5"/><rect x="6.25" y="5.5" width="2.5" height="8" rx="0.5"/><rect x="11" y="1.5" width="2.5" height="12" rx="0.5"/></svg>',
    'marketing'    => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 10.5l4-4 3 3 5-6"/><circle cx="13" cy="3.5" r="1.5" fill="currentColor" stroke="none"/></svg>',
    'invoices'     => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="1.5" width="10" height="12" rx="1"/><line x1="5" y1="5" x2="10" y2="5"/><line x1="5" y1="7.5" x2="10" y2="7.5"/><line x1="5" y1="10" x2="8" y2="10"/><path d="M9 9.5l1.5 2" stroke-linecap="round"/></svg>',
    'dpo'          => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.5 1.5L2 4v4c0 3 2.5 5.5 5.5 6 3-0.5 5.5-3 5.5-6V4L7.5 1.5z"/><path d="M5 7.5l2 2 3-3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'profile'      => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="11" height="7" rx="1"/><path d="M4.5 7V5a3 3 0 016 0v2"/><circle cx="7.5" cy="10.5" r="1" fill="currentColor" stroke="none"/></svg>',
    'logs'         => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.5" y="1.5" width="12" height="12" rx="1"/><line x1="4" y1="5" x2="11" y2="5"/><line x1="4" y1="7.5" x2="11" y2="7.5"/><line x1="4" y1="10" x2="8" y2="10"/></svg>',
    'admins'       => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="5.5" cy="4" r="2"/><circle cx="10" cy="4" r="2"/><path d="M1 13c0-2.5 2-4 4.5-4"/><path d="M8 10h5.5a1.5 1.5 0 011.5 1.5v1.5"/><line x1="11" y1="9" x2="11" y2="13"/><line x1="9" y1="11" x2="13" y2="11"/></svg>',
    'email_rate'   => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.5" y="3.5" width="12" height="8" rx="1"/><path d="M1.5 5l6 4 6-4"/><line x1="10.5" y1="9" x2="13" y2="9"/><line x1="12" y1="7.5" x2="12" y2="10.5"/></svg>',
    'cron_monitoring' => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="7.5" r="6"/><polyline points="7.5,4 7.5,7.5 10,9"/></svg>',
    'app_logs'       => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="1.5" width="11" height="12" rx="1"/><line x1="4" y1="4.5" x2="11" y2="4.5"/><line x1="4" y1="7" x2="11" y2="7"/><line x1="4" y1="9.5" x2="9" y2="9.5"/><circle cx="11.5" cy="11.5" r="0.75" fill="currentColor" stroke="none"/></svg>',
    'feature_flags'  => '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 2v11"/><path d="M2 2h9l-2 3.5 2 3.5H2"/></svg>',
];

$navSections = [
    'Tableau de bord' => [
        'dashboard' => ['url' => '/admin', 'label' => 'Tableau de bord'],
    ],
    'Catalogue' => [
        'wines'       => ['url' => '/admin/vins',               'label' => 'Vins'],
        'pricing'     => ['url' => '/admin/tarifs',             'label' => 'Tarifs'],
        'order_forms' => ['url' => '/admin/bons-de-commande',   'label' => 'Bons de commande'],
    ],
    'Commandes' => [
        'orders'   => ['url' => '/admin/commandes',       'label' => 'Commandes'],
        'invoices' => ['url' => '/admin/factures',        'label' => 'Factures'],
        'stats'    => ['url' => '/admin/statistiques',    'label' => 'Statistiques CA'],
    ],
    'Clients' => [
        'accounts' => ['url' => '/admin/comptes', 'label' => 'Comptes'],
    ],
    'Contenu' => [
        'news'       => ['url' => '/admin/actualites', 'label' => 'Actualités'],
        'newsletter' => ['url' => '/admin/newsletter', 'label' => 'Newsletter'],
        'marketing'  => ['url' => '/admin/marketing',  'label' => 'Marketing'],
    ],
    'Sécurité' => [
        'dpo'     => ['url' => '/admin/dpo',      'label' => 'DPO — RGPD'],
        'profile' => ['url' => '/admin/securite', 'label' => 'Sécurité'],
        'logs'    => ['url' => '/admin/logs',     'label' => 'Journal d\'audit'],
        'admins'  => ['url' => '/admin/admins',   'label' => 'Gestion des admins'],
    ],
    'Monitoring' => [
        'email_rate'      => ['url' => '/admin/emails-rate',       'label' => 'Monitoring emails'],
        'cron_monitoring' => ['url' => '/admin/monitoring-crons',  'label' => 'Monitoring crons'],
        'app_logs'        => ['url' => '/admin/logs-app',          'label' => 'Logs applicatifs'],
        'feature_flags'   => ['url' => '/admin/feature-flags',     'label' => 'Feature flags'],
    ],
];
$superOnlyKeys = ['logs', 'admins', 'email_rate', 'cron_monitoring', 'app_logs', 'feature_flags'];

$adminInitial = strtoupper(substr($adminUser['name'] ?? 'A', 0, 1));
$adminRole    = $adminUser['role'] ?? 'admin';

// Génère le token CSRF si absent (session admin sans passage par le frontend)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> — Admin · Crabitan Bellevue</title>
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/main.css">
    <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
    window.__appUrl = '<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/'), ENT_QUOTES) ?>';

    // ── Pop-in de confirmation globale ───────────────────────────────────
    var __confirmModal   = null;
    var __confirmResolve = null;

    function __openConfirm(msg, onConfirm) {
        if (!__confirmModal) { if (window.confirm(msg)) { onConfirm(); } return; }
        document.getElementById('admin-confirm-msg').textContent = msg;
        __confirmResolve = onConfirm;
        __confirmModal.style.display = 'flex';
        setTimeout(function () { document.getElementById('admin-confirm-ok').focus(); }, 30);
    }

    function __closeConfirm() {
        if (__confirmModal) { __confirmModal.style.display = 'none'; }
        __confirmResolve = null;
    }

    // ── Handlers délégués — conformité CSP ──────────────────────────────
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-admin-confirm');
        if (!msg) { return; }
        e.preventDefault();
        var form = e.target;
        __openConfirm(msg, function () {
            form.removeAttribute('data-admin-confirm');
            form.submit();
        });
    });
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-admin-confirm]');
        if (link) {
            e.preventDefault();
            var href = link.href;
            __openConfirm(link.getAttribute('data-admin-confirm'), function () { window.location.href = href; });
        }
        var btn = e.target.closest('button[data-pwd-target]');
        if (btn) {
            var input = document.getElementById(btn.dataset.pwdTarget);
            if (input) {
                var h = input.type === 'password';
                input.type = h ? 'text' : 'password';
                var show = btn.querySelector('.eye-icon--show');
                var hide = btn.querySelector('.eye-icon--hide');
                if (show) { show.style.display = h ? 'none' : ''; }
                if (hide) { hide.style.display = h ? '' : 'none'; }
                btn.setAttribute('aria-label', h ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            }
        }
    });
    document.addEventListener('change', function (e) {
        if (e.target.matches('select[data-auto-submit]') && e.target.form) {
            e.target.form.submit();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        // ── Création de la pop-in de confirmation ────────────────────────
        var modal = document.createElement('div');
        modal.id = 'admin-confirm-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;';

        var inner = document.createElement('div');
        inner.style.cssText = 'background:#fff;border-radius:8px;padding:2rem 2.5rem;max-width:400px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.22);';

        var msgEl = document.createElement('p');
        msgEl.id = 'admin-confirm-msg';
        msgEl.style.cssText = 'font-size:0.9rem;color:#3d3425;margin:0 0 1.5rem;line-height:1.5;';

        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:1rem;justify-content:flex-end;';

        var cancelBtn = document.createElement('button');
        cancelBtn.id = 'admin-confirm-cancel';
        cancelBtn.type = 'button';
        cancelBtn.className = 'admin-btn admin-btn--outline';
        cancelBtn.textContent = 'Annuler';

        var okBtn = document.createElement('button');
        okBtn.id = 'admin-confirm-ok';
        okBtn.type = 'button';
        okBtn.className = 'admin-btn admin-btn--danger';
        okBtn.textContent = 'Confirmer';

        actions.appendChild(cancelBtn);
        actions.appendChild(okBtn);
        inner.appendChild(msgEl);
        inner.appendChild(actions);
        modal.appendChild(inner);
        document.body.appendChild(modal);
        __confirmModal = modal;

        cancelBtn.addEventListener('click', __closeConfirm);
        okBtn.addEventListener('click', function () {
            modal.style.display = 'none';
            var resolve = __confirmResolve;
            __confirmResolve = null;
            if (resolve) { resolve(); }
        });
        modal.addEventListener('click', function (e) { if (e.target === modal) { __closeConfirm(); } });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') { __closeConfirm(); }
        });

        // ── Flash messages auto-hide ─────────────────────────────────────
        document.querySelectorAll('.admin-flash--success').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity 400ms ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 420);
            }, 2500);
        });

        // Scroll item actif dans la sidebar (focus page sélectionnée)
        var activeNav = document.querySelector('.admin-nav__item.active');
        if (activeNav && typeof activeNav.scrollIntoView === 'function') {
            activeNav.scrollIntoView({ block: 'center', behavior: 'instant' });
        }
    });
    </script>
</head>
<body>

<div class="admin-shell">

    <!-- ================================================================
         Sidebar
    ================================================================ -->
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigation admin">

        <div class="admin-sidebar__logo">
            <a href="/admin" class="admin-sidebar__brand" aria-label="Crabitan Bellevue — accueil admin">
                Crabitan Bellevue
            </a>
            <p class="admin-sidebar__title">Back-office</p>
        </div>

        <nav class="admin-nav" aria-label="Menu principal">
            <?php foreach ($navSections as $sectionLabel => $items) : ?>
                <?php
                $visibleItems = array_filter(
                    $items,
                    static fn($_v, $k): bool => !(in_array($k, $superOnlyKeys, true) && $adminRole !== 'super_admin'),
                    ARRAY_FILTER_USE_BOTH
                );
                if (empty($visibleItems)) {
                    continue;
                }
                ?>
                <div class="admin-nav__section-label"><?= htmlspecialchars($sectionLabel) ?></div>
                <?php foreach ($visibleItems as $key => $item) : ?>
                    <a
                        href="<?= htmlspecialchars($item['url']) ?>"
                        class="admin-nav__item<?= $adminSection === $key ? ' active' : '' ?>"
                        data-label="<?= htmlspecialchars($item['label']) ?>"
                        <?= $adminSection === $key ? 'aria-current="page"' : '' ?>
                    >
                        <span class="admin-nav__icon"><?= $navIcons[$key] ?? '' ?></span>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__footer">
            <div class="admin-sidebar__user">
                <div class="admin-sidebar__avatar" aria-hidden="true"><?= htmlspecialchars($adminInitial) ?></div>
                <div>
                    <div class="admin-sidebar__user-name"><?= htmlspecialchars($adminUser['name'] ?? 'Admin') ?></div>
                    <div class="admin-sidebar__user-role"><?= htmlspecialchars($adminRole) ?></div>
                </div>
            </div>
            <form method="POST" action="/fr/deconnexion" class="admin-logout-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                <button type="submit" class="admin-logout">
                    <span aria-hidden="true">↩</span> Déconnexion
                </button>
            </form>
        </div>

    </aside>

    <!-- ================================================================
         Contenu principal
    ================================================================ -->
    <!-- Backdrop mobile (ferme la sidebar) -->
    <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop" aria-hidden="true"></div>

    <div class="admin-main">

        <!-- Topbar -->
        <header class="admin-topbar">
            <!-- Burger mobile -->
            <button
                type="button"
                class="admin-topbar__burger"
                id="admin-burger"
                aria-label="Ouvrir le menu"
                aria-expanded="false"
                aria-controls="admin-sidebar"
            >
                <span></span><span></span><span></span>
            </button>

            <nav class="admin-breadcrumb" aria-label="Fil d'Ariane">
                <?php foreach ($breadcrumbs as $i => $crumb) :
                    $isLast = $i === count($breadcrumbs) - 1;
                    ?>
                    <?php if (!$isLast) : ?>
                        <?php if (isset($crumb['url'])) : ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                        <?php else : ?>
                            <span><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php endif; ?>
                        <span aria-hidden="true"> / </span>
                    <?php else : ?>
                        <span class="current" aria-current="page"><?= htmlspecialchars($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <a href="/fr" class="admin-btn admin-btn--outline admin-btn--sm" style="margin-left:auto;">
                Voir le site
            </a>
        </header>

        <!-- Content -->
        <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
        (function () {
            var burger   = document.getElementById('admin-burger');
            var sidebar  = document.getElementById('admin-sidebar');
            var backdrop = document.getElementById('admin-sidebar-backdrop');
            if (!burger || !sidebar || !backdrop) return;

            function openSidebar() {
                sidebar.classList.add('is-open');
                backdrop.classList.add('is-visible');
                burger.setAttribute('aria-expanded', 'true');
                burger.setAttribute('aria-label', 'Fermer le menu');
            }
            function closeSidebar() {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-visible');
                burger.setAttribute('aria-expanded', 'false');
                burger.setAttribute('aria-label', 'Ouvrir le menu');
            }

            burger.addEventListener('click', function () {
                sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
            });
            backdrop.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeSidebar();
            });
        })();
        </script>
        <main class="admin-content" id="admin-main-content">
