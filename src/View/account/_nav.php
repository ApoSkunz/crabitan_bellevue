<?php
/**
 * Navigation latérale de l'espace client.
 * Variables attendues : $lang (string), $activeSection (string)
 */
$nav = [
    'index'    => ['url' => "/{$lang}/mon-compte",           'label' => __('panel.account')],
    'orders'   => ['url' => "/{$lang}/mon-compte/commandes", 'label' => __('panel.orders')],
    'addresses'=> ['url' => "/{$lang}/mon-compte/adresses",  'label' => __('panel.addresses')],
    'favorites'=> ['url' => "/{$lang}/mon-compte/favoris",   'label' => __('panel.favorites')],
    'security' => ['url' => "/{$lang}/mon-compte/securite",  'label' => __('account.security')],
];
?>
<nav class="account-nav" aria-label="<?= __('account.nav_label') ?>">
    <?php foreach ($nav as $key => $item) : ?>
        <a
            class="account-nav__link<?= $activeSection === $key ? ' account-nav__link--active' : '' ?>"
            href="<?= htmlspecialchars($item['url']) ?>"
        >
            <?= $item['label'] ?>
        </a>
    <?php endforeach; ?>
    <a class="account-nav__link account-nav__link--export"
       href="/<?= htmlspecialchars($lang) ?>/mon-compte/export">
        <?= __('account.export') ?>
    </a>
    <a class="account-nav__link account-nav__link--logout"
       href="/<?= htmlspecialchars($lang) ?>/deconnexion">
        <?= __('panel.logout') ?>
    </a>
</nav>
