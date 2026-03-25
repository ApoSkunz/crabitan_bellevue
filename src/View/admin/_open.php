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
];

$navItems = [
    'dashboard'  => ['url' => '/admin',             'label' => 'Tableau de bord'],
    'wines'      => ['url' => '/admin/vins',         'label' => 'Vins'],
    'orders'     => ['url' => '/admin/commandes',    'label' => 'Commandes'],
    'accounts'   => ['url' => '/admin/comptes',      'label' => 'Comptes'],
    'pricing'    => ['url' => '/admin/tarifs',       'label' => 'Tarifs'],
    'news'       => ['url' => '/admin/actualites',   'label' => 'Actualités'],
    'newsletter'  => ['url' => '/admin/newsletter',          'label' => 'Newsletter'],
    'order_forms' => ['url' => '/admin/bons-de-commande',    'label' => 'Bons de commande'],
];

$adminInitial = strtoupper(substr($adminUser['name'] ?? 'A', 0, 1));
$adminRole    = $adminUser['role'] ?? 'admin';
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
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.admin-flash--success').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity 400ms ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 420);
            }, 2500);
        });
    });
    </script>
</head>
<body>

<div class="admin-shell">

    <!-- ================================================================
         Sidebar
    ================================================================ -->
    <aside class="admin-sidebar" aria-label="Navigation admin">

        <div class="admin-sidebar__logo">
            <a href="/admin" class="admin-sidebar__brand" aria-label="Accueil admin">
                Crabitan Bellevue
            </a>
            <p class="admin-sidebar__title">Back-office</p>
        </div>

        <nav class="admin-nav" aria-label="Menu principal">
            <?php foreach ($navItems as $key => $item) : ?>
                <a
                    href="<?= htmlspecialchars($item['url']) ?>"
                    class="admin-nav__item<?= $adminSection === $key ? ' active' : '' ?>"
                    <?= $adminSection === $key ? 'aria-current="page"' : '' ?>
                >
                    <span class="admin-nav__icon"><?= $navIcons[$key] ?? '' ?></span>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
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
            <a href="/fr/deconnexion" class="admin-logout">
                <span aria-hidden="true">↩</span> Déconnexion
            </a>
        </div>

    </aside>

    <!-- ================================================================
         Contenu principal
    ================================================================ -->
    <div class="admin-main">

        <!-- Topbar -->
        <header class="admin-topbar">
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

            <a href="/fr" class="admin-btn admin-btn--outline admin-btn--sm" style="margin-left:auto;" target="_blank" rel="noopener">
                Voir le site ↗
            </a>
        </header>

        <!-- Content -->
        <main class="admin-content" id="admin-main-content">
