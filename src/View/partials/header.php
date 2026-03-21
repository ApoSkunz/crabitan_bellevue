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
    <div class="header-main">
        <a href="/<?= htmlspecialchars($navLang) ?>" class="header-logo" aria-label="<?= htmlspecialchars(APP_NAME) ?>">
            <img src="/assets/images/crabitan-bellevue-logo.png" alt="" width="48" height="48">
            <span class="header-logo__text">Château<br>Crabitan&#160;Bellevue</span>
        </a>

        <p class="header-title" aria-hidden="true">Château Crabitan Bellevue</p>

        <div class="header-actions">
            <nav class="lang-switch" aria-label="Langue / Language">
                <?php foreach (['fr', 'en'] as $l) : ?>
                    <a
                        href="/<?= htmlspecialchars($l) ?>"
                        lang="<?= htmlspecialchars($l) ?>"
                        class="<?= $l === $navLang ? 'active' : '' ?>"
                        <?= $l === $navLang ? 'aria-current="true"' : '' ?>
                    ><?= strtoupper(htmlspecialchars($l)) ?></a>
                <?php endforeach; ?>
            </nav>

            <button
                id="theme-toggle"
                class="theme-toggle"
                type="button"
                aria-label="Basculer le thème jour / nuit"
            >
                <span class="icon-sun" aria-hidden="true">&#9728;</span>
                <span class="icon-moon" aria-hidden="true">&#9790;</span>
            </button>

            <?php if ($isLogged) : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte" class="btn btn--ghost">
                    <?= htmlspecialchars(__('nav.account')) ?>
                </a>
            <?php else : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/connexion" class="btn btn--white">
                    <?= htmlspecialchars(__('nav.login')) ?>
                </a>
            <?php endif; ?>

            <button
                id="header-burger"
                class="header-burger"
                type="button"
                aria-expanded="false"
                aria-controls="header-nav-mobile"
                aria-label="Menu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>

    <nav class="header-nav" aria-label="Navigation principale">
        <div class="header-nav__inner">
            <a href="/<?= htmlspecialchars($navLang) ?>" class="header-nav__link">
                <?= htmlspecialchars(__('nav.home')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="header-nav__link">
                <?= htmlspecialchars(__('nav.wines')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection" class="header-nav__link">
                <?= htmlspecialchars(__('nav.collection')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/actualites" class="header-nav__link">
                <?= htmlspecialchars(__('nav.news')) ?>
            </a>
            <?php if ($isLogged) : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/panier" class="header-nav__link">
                    <?= htmlspecialchars(__('nav.cart')) ?>
                </a>
                <a href="/<?= htmlspecialchars($navLang) ?>/deconnexion" class="header-nav__link">
                    <?= htmlspecialchars(__('nav.logout')) ?>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <nav id="header-nav-mobile" class="header-nav--mobile" aria-label="Menu mobile">
        <a href="/<?= htmlspecialchars($navLang) ?>"><?= htmlspecialchars(__('nav.home')) ?></a>
        <a href="/<?= htmlspecialchars($navLang) ?>/vins"><?= htmlspecialchars(__('nav.wines')) ?></a>
        <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection"><?= htmlspecialchars(__('nav.collection')) ?></a>
        <a href="/<?= htmlspecialchars($navLang) ?>/actualites"><?= htmlspecialchars(__('nav.news')) ?></a>
        <?php if ($isLogged) : ?>
            <a href="/<?= htmlspecialchars($navLang) ?>/panier"><?= htmlspecialchars(__('nav.cart')) ?></a>
            <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte"><?= htmlspecialchars(__('nav.account')) ?></a>
            <a href="/<?= htmlspecialchars($navLang) ?>/deconnexion"><?= htmlspecialchars(__('nav.logout')) ?></a>
        <?php else : ?>
            <a href="/<?= htmlspecialchars($navLang) ?>/connexion"><?= htmlspecialchars(__('nav.login')) ?></a>
            <a href="/<?= htmlspecialchars($navLang) ?>/inscription"><?= htmlspecialchars(__('nav.register')) ?></a>
        <?php endif; ?>
    </nav>
</header>
