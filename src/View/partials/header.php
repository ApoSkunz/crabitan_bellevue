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
        // Token invalide ou expiré : l'utilisateur est traité comme non connecté
    }
}

// Détection du lien actif dans la nav
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$activeClass = ' active';
$isActive    = static function (string $segment) use ($currentPath, $activeClass): string {
    return str_contains($currentPath, $segment) ? $activeClass : '';
};

// Génère l'URL pour le switch de langue en remplaçant le préfixe lang dans l'URI courante
$rawPath      = strtok($currentPath, '?') ?: '/';
$pathSegments = explode('/', ltrim($rawPath, '/'));
$langSwitch   = static function (string $targetLang) use ($pathSegments): string {
    $supported = ['fr', 'en'];
    if (in_array($pathSegments[0] ?? '', $supported, true)) {
        $pathSegments[0] = $targetLang;
        return '/' . implode('/', $pathSegments);
    }
    return '/' . $targetLang;
};
?>
<header class="site-header">
    <div class="header-main">
        <!-- Gauche : logo + langue + contact -->
        <div class="header-left">
            <a href="/<?= htmlspecialchars($navLang) ?>" class="header-logo" aria-label="<?= htmlspecialchars(APP_NAME) ?>">
                <img src="/assets/images/logo/crabitan-bellevue-logo-modern.svg" alt="" width="48" height="48">
            </a>

            <nav class="lang-switch" aria-label="Langue / Language">
                <?php foreach (['fr', 'en'] as $l) : ?>
                    <a
                        href="<?= htmlspecialchars($langSwitch($l)) ?>"
                        lang="<?= htmlspecialchars($l) ?>"
                        class="<?= $l === $navLang ? 'active' : '' ?>"
                        <?= $l === $navLang ? 'aria-current="true"' : '' ?>
                    ><?= strtoupper(htmlspecialchars($l)) ?></a>
                <?php endforeach; ?>
            </nav>

            <a href="/<?= htmlspecialchars($navLang) ?>/contact" class="header-contact">
                <?= htmlspecialchars(__('nav.contact')) ?>
            </a>
        </div>

        <p class="header-title" aria-hidden="true">Château Crabitan Bellevue</p>

        <!-- Droite : thème + panier + compte + burger -->
        <div class="header-actions">
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
                <a
                    href="/<?= htmlspecialchars($navLang) ?>/panier"
                    class="header-cart<?= str_contains($currentPath, '/panier') ? $activeClass : '' ?>"
                    aria-label="<?= htmlspecialchars(__('nav.cart')) ?>"
                >
                    <span class="header-cart__wrap">
                        <span class="header-cart__badge" class="header-cart__count">0</span>
                        <span class="header-cart__icon">&#128722;</span>
                    </span>
                    <span class="header-cart__label"><?= htmlspecialchars(__('nav.cart')) ?></span>
                </a>
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
                <button
                    type="button"
                    class="header-cart js-cart-login-prompt"
                    aria-label="<?= htmlspecialchars(__('nav.cart')) ?>"
                    data-login-url="/<?= htmlspecialchars($navLang) ?>/connexion"
                >
                    <span class="header-cart__wrap">
                        <span class="header-cart__badge" class="header-cart__count">0</span>
                        <span class="header-cart__icon">&#128722;</span>
                    </span>
                    <span class="header-cart__label"><?= htmlspecialchars(__('nav.cart')) ?></span>
                </button>
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
            $isHome      = (bool) preg_match('#^/' . preg_quote($navLang, '#') . '/?$#', $currentPath);
            $isWinesOnly = (bool) preg_match('#^/' . preg_quote($navLang, '#') . '/vins(?:\?|$)#', $currentPath);
            ?>
            <a href="/<?= htmlspecialchars($navLang) ?>" class="header-nav__link<?= $isHome ? $activeClass : '' ?>">
                <span><?= htmlspecialchars(__('nav.home')) ?></span>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="header-nav__link<?= $isWinesOnly ? $activeClass : '' ?>">
                <span><?= htmlspecialchars(__('nav.wines')) ?></span>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/le-chateau"
               class="header-nav__link<?= $isActive('/le-chateau') ?>">
                <span><?= htmlspecialchars(__('nav.chateau')) ?></span>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire"
               class="header-nav__link<?= $isActive('/savoir-faire') ?>">
                <span><?= htmlspecialchars(__('nav.savoir_faire')) ?></span>
            </a>
            <a href="/<?= htmlspecialchars($navLang) ?>/vins/collection"
               class="header-nav__link<?= $isActive('/vins/collection') ?>">
                <span><?= htmlspecialchars(__('nav.collection')) ?></span>
            </a>
            <?php if ($isLogged) : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/panier"
                   class="header-nav__link<?= $isActive('/panier') ?>">
                    <span><?= htmlspecialchars(__('nav.cart')) ?></span>
                </a>
                <a href="/<?= htmlspecialchars($navLang) ?>/deconnexion" class="header-nav__link">
                    <span><?= htmlspecialchars(__('nav.logout')) ?></span>
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

<script>window.__userLogged = <?= $isLogged ? 'true' : 'false' ?>; window.__navLang = '<?= htmlspecialchars($navLang) ?>';</script>

<!-- ============================================================ -->
<!-- Modal ajout au panier                                         -->
<!-- ============================================================ -->
<!-- NOSONAR Web:S6819 — custom modal with full JS focus/keyboard management; <dialog> migration deferred -->
<div
    id="cart-modal"
    class="cart-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cart-modal-title"
>
    <div class="cart-modal__backdrop" id="cart-modal-backdrop"></div>
    <div class="cart-modal__inner">
        <div class="cart-modal__header">
            <!-- NOSONAR Web:S6850 — title content set dynamically by JS before modal opens -->
            <h2 id="cart-modal-title" class="cart-modal__title"></h2>
            <button id="cart-modal-close" class="cart-modal__close" type="button" aria-label="Fermer">&times;</button>
        </div>
        <div class="cart-modal__body">
            <div class="cart-modal__product">
                <div class="cart-modal__image-wrap">
                    <img id="cart-modal-image" src="" alt="" class="cart-modal__image">
                </div>
                <div class="cart-modal__product-info">
                    <p id="cart-modal-price" class="cart-modal__price"></p>
                    <p id="cart-modal-total" class="cart-modal__total"></p>
                    <div class="cart-modal__qty-wrap">
                        <label for="cart-modal-qty"><?= htmlspecialchars(__('cart.qty')) ?></label>
                        <div class="cart-modal__qty-controls">
                            <button type="button" id="cart-qty-minus" class="cart-modal__qty-btn" aria-label="-">&#8722;</button>
                            <input type="number" id="cart-modal-qty" name="qty" min="1" max="96" value="1" class="cart-modal__qty-input">
                            <button type="button" id="cart-qty-plus" class="cart-modal__qty-btn" aria-label="+">&#43;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <form method="POST" id="cart-modal-form" action="">
            <input type="hidden" name="wine_id" id="cart-modal-wine-id" value="">
            <input type="hidden" name="qty" id="cart-modal-qty-hidden" value="1">
            <div class="cart-modal__footer">
                <button type="button" id="cart-modal-cancel" class="btn btn--ghost"><?= htmlspecialchars(__('btn.cancel')) ?></button>
                <button type="submit" class="btn btn--gold"><?= htmlspecialchars(__('wine.add_to_cart')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Toast notification -->
<div id="cb-toast" class="cb-toast" aria-live="polite" aria-atomic="true" hidden></div>

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
            <img src="/assets/images/logo/crabitan-bellevue-logo.png" alt="" width="56" height="56">
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
