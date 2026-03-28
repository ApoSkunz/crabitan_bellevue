<?php
/**
 * Navigation latérale de l'espace client.
 * Variables attendues : $lang (string), $activeSection (string)
 */
$nav = [
    'index'     => [
        'url'   => "/{$lang}/mon-compte",
        'label' => __('panel.account'),
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    ],
    'profile'   => [
        'url'   => "/{$lang}/mon-compte/profil",
        'label' => __('account.profile'),
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ],
    'orders'    => [
        'url'   => "/{$lang}/mon-compte/commandes",
        'label' => __('panel.orders'),
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
    ],
    'addresses' => [
        'url'   => "/{$lang}/mon-compte/adresses",
        'label' => __('panel.addresses'),
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
    ],
    'favorites' => [
        'url'   => "/{$lang}/mon-compte/favoris",
        'label' => __('panel.favorites'),
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    ],
    'security'  => [
        'url'   => "/{$lang}/mon-compte/securite",
        'label' => __('account.security'),
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
    ],
];
?>
<script>
(function () {
    function setNavTop() {
        var h = document.querySelector('.site-header');
        if (h) {
            document.documentElement.style.setProperty(
                '--account-nav-top',
                (h.getBoundingClientRect().height + 16) + 'px'
            );
        }
    }
    setNavTop();
    window.addEventListener('resize', setNavTop);
})();
</script>
<nav class="account-nav" aria-label="<?= __('account.nav_label') ?>">
    <?php foreach ($nav as $key => $item) : ?>
        <a
            class="account-nav__link<?= $activeSection === $key ? ' account-nav__link--active' : '' ?>"
            href="<?= htmlspecialchars($item['url']) ?>"
        >
            <?= $item['icon'] ?>
            <?= $item['label'] ?>
        </a>
    <?php endforeach; ?>
    <a class="account-nav__link account-nav__link--export<?= ($activeSection ?? '') === 'export' ? ' account-nav__link--active' : '' ?>"
       href="/<?= htmlspecialchars($lang) ?>/mon-compte/export">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <?= __('account.export') ?>
    </a>
    <a class="account-nav__link account-nav__link--logout"
       href="/<?= htmlspecialchars($lang) ?>/deconnexion">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <?= __('panel.logout') ?>
    </a>
</nav>
