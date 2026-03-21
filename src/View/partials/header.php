<?php
$currentLang = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';
$navLang     = $lang ?? $currentLang;
$token       = $_COOKIE['auth_token'] ?? null;
$isLogged    = false;

if ($token) {
    try {
        \Core\Jwt::decode($token);
        $isLogged = true;
    } catch (\Throwable) {
    }
}
?>
<header class="site-header">
    <nav class="nav" aria-label="Navigation principale">
        <a href="/<?= $navLang ?>" class="nav__logo">
            <?= htmlspecialchars(APP_NAME) ?>
        </a>

        <ul class="nav__links">
            <li><a href="/<?= $navLang ?>/vins"><?= __('nav.wines') ?></a></li>
            <li><a href="/<?= $navLang ?>/vins/collection"><?= __('nav.collection') ?></a></li>
            <li><a href="/<?= $navLang ?>/actualites"><?= __('nav.news') ?></a></li>
            <?php if ($isLogged) : ?>
                <li><a href="/<?= $navLang ?>/panier"><?= __('nav.cart') ?></a></li>
                <li><a href="/<?= $navLang ?>/mon-compte"><?= __('nav.account') ?></a></li>
                <li><a href="/<?= $navLang ?>/deconnexion"><?= __('nav.logout') ?></a></li>
            <?php else : ?>
                <li><a href="/<?= $navLang ?>/connexion"><?= __('nav.login') ?></a></li>
                <li><a href="/<?= $navLang ?>/inscription"><?= __('nav.register') ?></a></li>
            <?php endif; ?>
        </ul>

        <div class="nav__lang">
            <?php foreach (['fr', 'en'] as $l) : ?>
                <?php if ($l !== $navLang) : ?>
                    <a href="/<?= $l ?>" class="nav__lang-switch" lang="<?= $l ?>"><?= strtoupper($l) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
