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

// Détection du lien actif dans la nav
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$isActive    = static function (string $segment) use ($currentPath, $navLang): string {
    return str_contains($currentPath, $segment) ? ' active' : '';
};
?>
<header class="site-header">
    <div class="header-main">
        <a href="/<?= htmlspecialchars($navLang) ?>" class="header-logo" aria-label="<?= htmlspecialchars(APP_NAME) ?>">
            <img src="/assets/images/crabitan-bellevue-logo.png" alt="" width="48" height="48">
        </a>

        <p class="header-title" aria-hidden="true">Château Crabitan Bellevue</p>

        <div class="header-actions">
            <a href="/<?= htmlspecialchars($navLang) ?>/contact" class="header-contact">
                <?= htmlspecialchars(__('nav.contact')) ?>
            </a>

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
                <button
                    id="account-panel-trigger"
                    class="btn btn--ghost"
                    type="button"
                    aria-expanded="false"
                    aria-controls="account-panel"
                >
                    <?= htmlspecialchars(__('nav.account')) ?>
                </button>
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
            <?php
            $isHome = $isActive('/vins') === ''
                && $isActive('/savoir-faire') === ''
                && $isActive('/le-chateau') === ''
                && $isActive('/panier') === '';
            ?>
            <a href="/<?= htmlspecialchars($navLang) ?>" class="header-nav__link<?= $isHome ? ' active' : '' ?>">
                <?= htmlspecialchars(__('nav.home')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="header-nav__link<?= $isActive('/vins') ?>">
                <?= htmlspecialchars(__('nav.wines')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/le-chateau"
               class="header-nav__link<?= $isActive('/le-chateau') ?>">
                <?= htmlspecialchars(__('nav.chateau')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire"
               class="header-nav__link<?= $isActive('/savoir-faire') ?>">
                <?= htmlspecialchars(__('nav.savoir_faire')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection"
               class="header-nav__link<?= $isActive('/vins/collection') ?>">
                <?= htmlspecialchars(__('nav.collection')) ?>
            </a>
            <?php if ($isLogged) : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/panier"
                   class="header-nav__link<?= $isActive('/panier') ?>">
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
        <a href="/<?= htmlspecialchars($navLang) ?>/le-chateau"><?= htmlspecialchars(__('nav.chateau')) ?></a>
        <a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire"><?= htmlspecialchars(__('nav.savoir_faire')) ?></a>
        <a href="/<?= htmlspecialchars($navLang) ?>/actualites"><?= htmlspecialchars(__('nav.news')) ?></a>
        <a href="/<?= htmlspecialchars($navLang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
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

<?php if ($isLogged) : ?>
<div
    id="account-panel"
    class="account-panel"
    aria-hidden="true"
    aria-label="<?= htmlspecialchars(__('panel.title')) ?>"
>
    <div class="account-panel__backdrop" id="account-panel-backdrop"></div>
    <aside
        class="account-panel__drawer"
        role="complementary"
        aria-label="<?= htmlspecialchars(__('panel.title')) ?>"
    >
        <div class="account-panel__header">
            <span class="account-panel__title"><?= htmlspecialchars(__('panel.title')) ?></span>
            <button
                id="account-panel-close"
                class="account-panel__close"
                type="button"
                aria-label="Fermer le panel"
            >&times;</button>
        </div>

        <div class="account-panel__logo">
            <img src="/assets/images/crabitan-bellevue-logo.png" alt="" width="56" height="56">
        </div>

        <nav class="account-panel__nav" aria-label="Navigation compte">
            <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte">
                <?= htmlspecialchars(__('panel.account')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/commandes">
                <?= htmlspecialchars(__('panel.orders')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/adresses">
                <?= htmlspecialchars(__('panel.addresses')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/favoris">
                <?= htmlspecialchars(__('panel.favorites')) ?>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/deconnexion" class="account-panel__logout">
                <?= htmlspecialchars(__('panel.logout')) ?>
            </a>
        </nav>

        <div class="account-panel__footer">
            <p><?= htmlspecialchars(APP_NAME) ?></p>
        </div>
    </aside>
</div>
<?php endif; ?>
