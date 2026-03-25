<?php
/**
 * Admin layout — ouverture
 * Variables attendues : $pageTitle, $adminSection, $adminUser, $breadcrumbs
 */
$adminSection = $adminSection ?? 'dashboard';
$pageTitle    = $pageTitle    ?? 'Admin';
$breadcrumbs  = $breadcrumbs  ?? [];

$navItems = [
    'dashboard'    => ['url' => '/admin',               'label' => 'Tableau de bord', 'icon' => '◈'],
    'wines'        => ['url' => '/admin/vins',           'label' => 'Vins',            'icon' => '◉'],
    'orders'       => ['url' => '/admin/commandes',      'label' => 'Commandes',       'icon' => '◎'],
    'accounts'     => ['url' => '/admin/comptes',        'label' => 'Comptes',         'icon' => '◍'],
    'pricing'      => ['url' => '/admin/tarifs',         'label' => 'Tarifs',          'icon' => '◇'],
    'news'         => ['url' => '/admin/actualites',     'label' => 'Actualités',      'icon' => '◻'],
    'newsletter'   => ['url' => '/admin/newsletter',     'label' => 'Newsletter',      'icon' => '◼'],
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
    <link rel="icon" href="/assets/images/logo/crabitan-bellevue-logo-modern.svg" type="image/svg+xml">
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
                    <span class="admin-nav__icon" aria-hidden="true"><?= $item['icon'] ?></span>
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
