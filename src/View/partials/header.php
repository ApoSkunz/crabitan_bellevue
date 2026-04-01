<?php
$currentLang = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';
$navLang     = $lang ?? $currentLang;
$token       = $_COOKIE['auth_token'] ?? null;
$isLogged    = false;
$isAdmin     = false;

// CSRF pour la modal connexion (session démarrée dans config.php)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$modalCsrf       = $_SESSION['csrf'];
$modalError      = $_SESSION['flash']['modal_error'] ?? null;
$registerErrors  = $_SESSION['flash']['register_errors'] ?? [];
$registerOld     = $_SESSION['flash']['register_old'] ?? [];
$registerSuccess = !empty($_SESSION['flash']['register_success']);
$registerOpen    = !empty($registerErrors) || !empty($registerOld) || $registerSuccess;
$forgotSuccess   = !empty($_SESSION['flash']['forgot_success']);
$flashInfo       = $_SESSION['flash']['info'] ?? null;
unset($_SESSION['flash']['modal_error'], $_SESSION['flash']['register_errors'], $_SESSION['flash']['register_old'], $_SESSION['flash']['register_success'], $_SESSION['flash']['forgot_success'], $_SESSION['flash']['info']);

$resetModalData    = $_SESSION['reset_modal'] ?? null;
$resetOpen         = isset($_GET['modal']) && $_GET['modal'] === 'reset' && $resetModalData !== null;
$resetToken        = $resetModalData['token'] ?? '';
$resetValid        = $resetModalData['valid'] ?? false;
$resetError        = $resetModalData['error'] ?? null;
$resetSuccess      = !empty($resetModalData['success']);
if ($resetOpen) {
    $_SESSION['reset_modal']['error'] = null;
    if ($resetSuccess) {
        unset($_SESSION['reset_modal']);
    }
}

$isCompany = false;

if ($token) {
    try {
        $jwtPayload = \Core\Jwt::decode($token);
        $isLogged   = true;
        $isAdmin    = in_array($jwtPayload['role'] ?? '', ['admin', 'super_admin'], true);

        if (!$isAdmin) {
            $headerUserId      = (int) ($jwtPayload['sub'] ?? 0);
            $headerAccountData = (new \Model\AccountModel())->findById($headerUserId);
            $isCompany         = $headerAccountData && ($headerAccountData['account_type'] ?? '') === 'company';
        }
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
<?php include __DIR__ . '/age_gate.php'; ?>
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
                <?php if (!$isAdmin) : ?>
                <a
                    href="/<?= htmlspecialchars($navLang) ?>/panier"
                    class="header-cart<?= str_contains($currentPath, '/panier') ? $activeClass : '' ?>"
                    aria-label="<?= htmlspecialchars(__('nav.cart')) ?>"
                >
                    <span class="header-cart__wrap">
                        <span class="header-cart__badge header-cart__count">0</span>
                        <span class="header-cart__icon">&#128722;</span>
                    </span>
                    <span class="header-cart__label"><?= htmlspecialchars(__('nav.cart')) ?></span>
                </a>
                <?php endif; ?>
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
                        <span class="header-cart__badge header-cart__count">0</span>
                        <span class="header-cart__icon">&#128722;</span>
                    </span>
                    <span class="header-cart__label"><?= htmlspecialchars(__('nav.cart')) ?></span>
                </button>
                <button
                    id="login-modal-trigger"
                    type="button"
                    class="btn btn--white"
                    aria-expanded="false"
                    aria-controls="login-modal"
                >
                    <?= htmlspecialchars(__('nav.login')) ?>
                </button>
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
            <?php if (!$isAdmin) : ?>
            <a href="/<?= htmlspecialchars($navLang) ?>/panier"><?= htmlspecialchars(__('nav.cart')) ?></a>
            <?php endif; ?>
            <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte"><?= htmlspecialchars(__('nav.account')) ?></a>
            <a href="/<?= htmlspecialchars($navLang) ?>/deconnexion"><?= htmlspecialchars(__('nav.logout')) ?></a>
        <?php else : ?>
            <button type="button" class="header-nav--mobile__modal-btn" data-open-modal="login-modal"><?= htmlspecialchars(__('nav.login')) ?></button>
            <button type="button" class="header-nav--mobile__modal-btn" data-open-modal="register-modal"><?= htmlspecialchars(__('nav.register')) ?></button>
        <?php endif; ?>
    </nav>
</header>

<script>
window.__userLogged       = <?= $isLogged ? 'true' : 'false' ?>;
window.__navLang          = '<?= htmlspecialchars($navLang) ?>';
window.__authModalError   = <?= $modalError ? 'true' : 'false' ?>;
window.__authRegisterOpen   = <?= $registerOpen ? 'true' : 'false' ?>;
window.__registerSuccess    = <?= $registerSuccess ? 'true' : 'false' ?>;
window.__forgotSuccess      = <?= $forgotSuccess ? 'true' : 'false' ?>;
window.__flashInfo          = <?= $flashInfo ? json_encode(htmlspecialchars($flashInfo)) : 'null' ?>;
window.__resetOpen        = <?= $resetOpen ? 'true' : 'false' ?>;
window.__resetToken       = <?= json_encode($resetToken) ?>;
window.__resetValid       = <?= $resetValid ? 'true' : 'false' ?>;
window.__resetSuccess     = <?= $resetSuccess ? 'true' : 'false' ?>;
window.__resetSuccessMsg  = <?= $resetSuccess ? json_encode(htmlspecialchars(__('auth.password_updated'))) : 'null' ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.querySelector('.login-modal__form[action*="connexion"]');
    if (!loginForm) return;
    loginForm.addEventListener('submit', function () {
        var btn = loginForm.querySelector('button[type="submit"]');
        if (!btn) return;
        var inner = loginForm.closest('.login-modal__inner');
        if (inner) inner.classList.add('cb-loading');
        btn.disabled = true;
        btn.innerHTML =
            '<span style="display:inline-block;width:.85em;height:.85em;border:2px solid currentColor;'
            + 'border-top-color:transparent;border-radius:50%;animation:cb-spin .6s linear infinite;'
            + 'vertical-align:middle;margin-right:.4em;"></span>Connexion en cours\u2026';
    });
});
</script>
<style>
@keyframes cb-spin{to{transform:rotate(360deg)}}
.login-modal__inner.cb-loading{opacity:.6;pointer-events:none;}
.login-modal__inner.cb-loading .login-modal__submit{opacity:1;pointer-events:auto;}
</style>

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
            <div class="cart-modal__title-wrap">
                <!-- NOSONAR Web:S6850 — title content set dynamically by JS before modal opens -->
                <h2 id="cart-modal-title" class="cart-modal__title"></h2>
                <p id="cart-modal-cuvee" class="cart-modal__cuvee" hidden></p>
            </div>
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
        <div id="cart-modal-success" class="cart-modal__success" hidden>
            <span class="cart-modal__success-icon">✓</span>
            <p id="cart-modal-success-msg" class="cart-modal__success-msg"></p>
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

<!-- ============================================================ -->
<!-- Modal connexion                                               -->
<!-- ============================================================ -->
<?php if (!$isLogged) : ?>
<!-- NOSONAR Web:S6819 — custom modal with full JS focus/keyboard management; <dialog> migration deferred -->
<div
    id="login-modal"
    class="login-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="login-modal-title"
>
    <div class="login-modal__backdrop" id="login-modal-backdrop"></div>
    <div class="login-modal__inner">
        <div class="login-modal__header">
            <!-- NOSONAR Web:S6850 — title updated dynamically by JS when switching panels -->
            <h2 id="login-modal-title" class="login-modal__title"
                data-title-login="<?= htmlspecialchars(__('auth.login')) ?>"
                data-title-forgot="<?= htmlspecialchars(__('auth.forgot_password')) ?>"
            ><?= htmlspecialchars(__('auth.login')) ?></h2>
            <button id="login-modal-close" class="login-modal__close" type="button" aria-label="Fermer">&times;</button>
        </div>
        <div class="login-modal__body">
            <!-- Panel connexion -->
            <div id="login-panel">
                <div class="login-modal__social">
                    <span class="btn-social-wrap" title="<?= htmlspecialchars(__('auth.modal.social_soon')) ?>">
                        <button type="button" class="btn btn-social btn-social--google" disabled aria-disabled="true">
                            <img src="/assets/images/login/Google__G__logo.png" alt="" width="18" height="18">
                            <?= htmlspecialchars(__('auth.modal.google')) ?>
                        </button>
                    </span>
                    <span class="btn-social-wrap" title="<?= htmlspecialchars(__('auth.modal.social_soon')) ?>">
                        <button type="button" class="btn btn-social btn-social--apple" disabled aria-disabled="true">
                            <img src="/assets/images/login/Apple_logo_black.svg" alt="" width="16" height="18" class="btn-social__apple-logo">
                            <?= htmlspecialchars(__('auth.modal.apple')) ?>
                        </button>
                    </span>
                </div>
                <p class="login-modal__or"><span><?= htmlspecialchars(__('auth.modal.or')) ?></span></p>
                <form method="POST" action="/<?= htmlspecialchars($navLang) ?>/connexion" class="login-modal__form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($modalCsrf) ?>">
                    <input type="hidden" name="redirect_back" value="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>">
                    <?php if ($modalError) : ?>
                        <div class="alert alert--error" role="alert"><?= htmlspecialchars($modalError) ?></div>
                    <?php endif; ?>
                    <div class="login-modal__field">
                        <label for="login-modal-email"><?= htmlspecialchars(__('auth.email')) ?></label>
                        <input type="email" id="login-modal-email" name="email" required autocomplete="email">
                    </div>
                    <div class="login-modal__field">
                        <label for="login-modal-password"><?= htmlspecialchars(__('auth.password')) ?></label>
                        <div class="login-modal__password-wrap">
                            <input type="password" id="login-modal-password" name="password" required autocomplete="current-password">
                            <button type="button" class="login-modal__pwd-toggle" aria-label="Afficher le mot de passe" data-target="login-modal-password">
                                <span class="pwd-eye pwd-eye--show" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                <span class="pwd-eye pwd-eye--hide" aria-hidden="true" hidden><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></span>
                            </button>
                        </div>
                    </div>
                    <button type="button" id="forgot-password-btn" class="login-modal__forgot">
                        <?= htmlspecialchars(__('auth.forgot_password')) ?>
                    </button>
                    <div class="login-modal__remember">
                        <input type="checkbox" id="remember-me" name="remember_me" value="1">
                        <label for="remember-me"><?= htmlspecialchars(__('auth.remember_me')) ?></label>
                    </div>
                    <button type="submit" class="btn btn--gold login-modal__submit"><?= htmlspecialchars(__('auth.login')) ?></button>
                </form>
                <div class="login-modal__register">
                    <p class="login-modal__register-label"><?= htmlspecialchars(__('auth.modal.no_account')) ?></p>
                    <button type="button" id="login-to-register" class="btn btn--ghost login-modal__register-btn">
                        <?= htmlspecialchars(__('auth.modal.sign_up')) ?>
                    </button>
                </div>
            </div>

            <!-- Panel mot de passe oublié -->
            <div id="forgot-panel" hidden>
                <button type="button" id="forgot-back-btn" class="login-modal__back">
                    &#8592; <?= htmlspecialchars(__('btn.back')) ?>
                </button>
                <?php if ($forgotSuccess) : ?>
                    <div class="alert alert--success" role="alert" aria-live="polite">
                        <?= htmlspecialchars(__('auth.reset_email_sent')) ?>
                    </div>
                <?php else : ?>
                    <p class="login-modal__forgot-desc"><?= htmlspecialchars(__('auth.forgot_instructions')) ?></p>
                    <form method="POST" action="/<?= htmlspecialchars($navLang) ?>/mot-de-passe-oublie"
                          id="forgot-modal-form" class="login-modal__form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($modalCsrf) ?>">
                        <div class="login-modal__field">
                            <label for="forgot-modal-email"><?= htmlspecialchars(__('auth.email')) ?></label>
                            <input type="email" id="forgot-modal-email" name="email" required autocomplete="email">
                        </div>
                        <button type="submit" class="btn btn--gold login-modal__submit">
                            <?= htmlspecialchars(__('btn.submit')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal inscription                                             -->
<!-- ============================================================ -->
<!-- NOSONAR Web:S6819 — custom modal with full JS focus/keyboard management; <dialog> migration deferred -->
<div
    id="register-modal"
    class="register-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="register-modal-title"
>
    <div class="register-modal__backdrop" id="register-modal-backdrop"></div>
    <div class="register-modal__inner">
        <div class="register-modal__header">
            <!-- NOSONAR Web:S6850 — title is static translated string -->
            <h2 id="register-modal-title" class="register-modal__title"><?= htmlspecialchars(__('auth.register')) ?></h2>
            <button id="register-modal-close" class="register-modal__close" type="button" aria-label="Fermer">&times;</button>
        </div>
        <div class="register-modal__body">
            <?php if ($registerSuccess) : ?>
                <div class="alert alert--success" role="alert" aria-live="polite">
                    <?= htmlspecialchars(__('auth.register_success')) ?>
                </div>
            <?php else : ?>
            <div class="register-modal__social">
                <span class="btn-social-wrap" title="<?= htmlspecialchars(__('auth.modal.social_soon')) ?>">
                    <button type="button" class="btn btn-social btn-social--google" disabled aria-disabled="true">
                        <img src="/assets/images/login/Google__G__logo.png" alt="" width="18" height="18">
                        <?= htmlspecialchars(__('auth.modal.google')) ?>
                    </button>
                </span>
                <span class="btn-social-wrap" title="<?= htmlspecialchars(__('auth.modal.social_soon')) ?>">
                    <button type="button" class="btn btn-social btn-social--apple" disabled aria-disabled="true">
                        <img src="/assets/images/login/Apple_logo_black.svg" alt="" width="16" height="18" class="btn-social__apple-logo">
                        <?= htmlspecialchars(__('auth.modal.apple')) ?>
                    </button>
                </span>
            </div>
            <p class="register-modal__or"><span><?= htmlspecialchars(__('auth.modal.or')) ?></span></p>
            <form method="POST" action="/<?= htmlspecialchars($navLang) ?>/inscription" class="register-modal__form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($modalCsrf) ?>">

                <?php if (!empty($registerErrors['email']) && count($registerErrors) === 1) : ?>
                    <div class="alert alert--error" role="alert"><?= htmlspecialchars($registerErrors['email']) ?></div>
                <?php elseif (!empty($registerErrors)) : ?>
                    <div class="alert alert--error" role="alert"><?= htmlspecialchars(reset($registerErrors)) ?></div>
                <?php endif; ?>

                <!-- Type de compte -->
                <div class="register-modal__field register-modal__field--radio">
                    <span class="register-modal__label"><?= htmlspecialchars(__('form.account_type')) ?></span>
                    <div class="register-modal__radio-group">
                        <label class="register-modal__radio">
                            <input type="radio" name="account_type" value="individual"
                                <?= ($registerOld['accountType'] ?? 'individual') !== 'company' ? 'checked' : '' ?>>
                            <?= htmlspecialchars(__('form.account_type.individual')) ?>
                        </label>
                        <label class="register-modal__radio">
                            <input type="radio" name="account_type" value="company"
                                <?= ($registerOld['accountType'] ?? '') === 'company' ? 'checked' : '' ?>>
                            <?= htmlspecialchars(__('form.account_type.company')) ?>
                        </label>
                    </div>
                </div>

                <!-- Champs particulier -->
                <div class="js-reg-individual"<?= ($registerOld['accountType'] ?? '') === 'company' ? ' hidden' : '' ?>>
                    <div class="register-modal__field register-modal__field--radio">
                        <span class="register-modal__label"><?= htmlspecialchars(__('form.civility')) ?></span>
                        <div class="register-modal__radio-group">
                            <?php foreach (['M' => __('form.civility.m'), 'F' => __('form.civility.f'), 'other' => __('form.civility.other')] as $val => $label) : ?>
                                <label class="register-modal__radio">
                                    <input type="radio" name="civility" value="<?= htmlspecialchars($val) ?>"
                                        <?= ($registerOld['civility'] ?? '') === $val ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($registerErrors['civility'])) : ?>
                            <span class="register-modal__error"><?= htmlspecialchars($registerErrors['civility']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="register-modal__row">
                        <div class="register-modal__field">
                            <label for="reg-lastname"><?= htmlspecialchars(__('form.lastname')) ?></label>
                            <input type="text" id="reg-lastname" name="lastname"
                                   value="<?= htmlspecialchars($registerOld['lastname'] ?? '') ?>"
                                   autocomplete="family-name">
                            <?php if (!empty($registerErrors['lastname'])) : ?>
                                <span class="register-modal__error"><?= htmlspecialchars($registerErrors['lastname']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="register-modal__field">
                            <label for="reg-firstname"><?= htmlspecialchars(__('form.firstname')) ?></label>
                            <input type="text" id="reg-firstname" name="firstname"
                                   value="<?= htmlspecialchars($registerOld['firstname'] ?? '') ?>"
                                   autocomplete="given-name">
                            <?php if (!empty($registerErrors['firstname'])) : ?>
                                <span class="register-modal__error"><?= htmlspecialchars($registerErrors['firstname']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Champs entreprise -->
                <div class="js-reg-company"<?= ($registerOld['accountType'] ?? '') !== 'company' ? ' hidden' : '' ?>>
                    <div class="register-modal__field">
                        <label for="reg-company"><?= htmlspecialchars(__('form.company')) ?></label>
                        <input type="text" id="reg-company" name="company_name"
                               value="<?= htmlspecialchars($registerOld['company'] ?? '') ?>"
                               autocomplete="organization">
                        <?php if (!empty($registerErrors['company_name'])) : ?>
                            <span class="register-modal__error"><?= htmlspecialchars($registerErrors['company_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Email -->
                <div class="register-modal__field">
                    <label for="reg-email"><?= htmlspecialchars(__('auth.email')) ?></label>
                    <input type="email" id="reg-email" name="email"
                           value="<?= htmlspecialchars($registerOld['email'] ?? '') ?>"
                           autocomplete="email" required>
                    <?php if (!empty($registerErrors['email'])) : ?>
                        <span class="register-modal__error"><?= htmlspecialchars($registerErrors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Mot de passe -->
                <div class="register-modal__field">
                    <label for="reg-password"><?= htmlspecialchars(__('auth.password')) ?></label>
                    <div class="register-modal__password-wrap">
                        <input type="password" id="reg-password" name="password"
                               autocomplete="new-password" required minlength="12">
                        <button type="button" class="register-modal__pwd-toggle" aria-label="Afficher le mot de passe" data-target="reg-password">
                            <span class="pwd-eye pwd-eye--show" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                            <span class="pwd-eye pwd-eye--hide" aria-hidden="true" hidden><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></span>
                        </button>
                    </div>
                    <?php if (!empty($registerErrors['password'])) : ?>
                        <span class="register-modal__error"><?= htmlspecialchars($registerErrors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Confirmation mot de passe -->
                <span class="register-modal__error js-pwd-match-error" hidden></span>
                <div class="register-modal__field">
                    <label for="reg-password-confirm"><?= htmlspecialchars(__('form.password_confirm')) ?></label>
                    <div class="register-modal__password-wrap">
                        <input type="password" id="reg-password-confirm" name="password_confirm"
                               autocomplete="new-password" required minlength="12"
                               data-mismatch-label="<?= htmlspecialchars(__('form.password_mismatch')) ?>">
                        <button type="button" class="register-modal__pwd-toggle" aria-label="Afficher le mot de passe" data-target="reg-password-confirm">
                            <span class="pwd-eye pwd-eye--show" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                            <span class="pwd-eye pwd-eye--hide" aria-hidden="true" hidden><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></span>
                        </button>
                    </div>
                </div>

                <p class="register-modal__hint"><?= htmlspecialchars(__('form.password_hint')) ?></p>

                <!-- Newsletter -->
                <label class="register-modal__newsletter">
                    <input type="checkbox" name="newsletter" value="1"
                           <?= !empty($registerOld['newsletter']) ? 'checked' : '' ?>>
                    <?= htmlspecialchars(__('form.newsletter')) ?>
                </label>

                <button type="submit" class="btn btn--gold register-modal__submit">
                    <?= htmlspecialchars(__('auth.register')) ?>
                </button>
            </form>

            <div class="register-modal__login">
                <p class="register-modal__login-label"><?= htmlspecialchars(__('auth.modal.have_account')) ?></p>
                <button type="button" id="register-to-login" class="btn btn--ghost register-modal__login-btn">
                    <?= htmlspecialchars(__('auth.modal.sign_in')) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$isLogged) : ?>
<!-- ============================================================ -->
<!-- Modal réinitialisation mot de passe                          -->
<!-- ============================================================ -->
<!-- NOSONAR Web:S6819 — custom modal with full JS focus/keyboard management; <dialog> migration deferred -->
<div
    id="reset-modal"
    class="login-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="reset-modal-title"
>
    <div class="login-modal__backdrop" id="reset-modal-backdrop"></div>
    <div class="login-modal__inner">
        <div class="login-modal__header">
            <!-- NOSONAR Web:S6850 — title is static translated string -->
            <h2 id="reset-modal-title" class="login-modal__title">
                <?= htmlspecialchars(__('auth.reset_password')) ?>
            </h2>
            <button id="reset-modal-close" class="login-modal__close" type="button" aria-label="Fermer">&times;</button>
        </div>
        <div class="login-modal__body">
            <?php if (!$resetValid) : ?>
                <div class="alert alert--error" role="alert">
                    <?= htmlspecialchars(__('auth.reset_invalid')) ?>
                </div>
                <button type="button" id="reset-to-forgot"
                        class="btn btn--gold btn--full" style="margin-top:0.75rem;">
                    <?= htmlspecialchars(__('auth.forgot_password')) ?>
                </button>
            <?php elseif ($resetSuccess) : ?>
                <div id="reset-modal-success" class="alert alert--success" role="status" data-no-auto-dismiss>
                    <?= htmlspecialchars(__('auth.password_updated')) ?>
                </div>
            <?php else : ?>
                <?php if ($resetError) : ?>
                    <div class="alert alert--error" role="alert"><?= htmlspecialchars($resetError) ?></div>
                <?php endif; ?>
                <form id="reset-modal-form" method="POST" action="" class="login-modal__form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($modalCsrf) ?>">
                    <div class="login-modal__field">
                        <label for="reset-modal-password"><?= htmlspecialchars(__('auth.password')) ?></label>
                        <div class="login-modal__password-wrap">
                            <input type="password" id="reset-modal-password" name="password"
                                   required minlength="12" autocomplete="new-password">
                            <button type="button" class="login-modal__pwd-toggle"
                                    aria-label="Afficher le mot de passe" data-target="reset-modal-password">
                                <span class="pwd-eye pwd-eye--show" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                <span class="pwd-eye pwd-eye--hide" aria-hidden="true" hidden><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></span>
                            </button>
                        </div>
                    </div>
                    <div class="login-modal__field">
                        <label for="reset-modal-confirm"><?= htmlspecialchars(__('form.password_confirm')) ?></label>
                        <div class="login-modal__password-wrap">
                            <input type="password" id="reset-modal-confirm" name="password_confirm"
                                   required minlength="12" autocomplete="new-password">
                            <button type="button" class="login-modal__pwd-toggle"
                                    aria-label="Afficher le mot de passe" data-target="reset-modal-confirm">
                                <span class="pwd-eye pwd-eye--show" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                                <span class="pwd-eye pwd-eye--hide" aria-hidden="true" hidden><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></span>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn--gold login-modal__submit">
                        <?= htmlspecialchars(__('btn.save')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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

        <nav class="account-panel__nav" aria-label="Navigation compte">
            <?php if ($isAdmin) : ?>
                <span class="account-panel__section-label">Administration</span>
                <a href="/admin">Tableau de bord</a>
                <a href="/admin/vins">Vins</a>
                <a href="/admin/commandes">Commandes</a>
                <a href="/admin/comptes">Comptes</a>
                <a href="/admin/tarifs">Tarifs</a>
                <a href="/admin/actualites">Actualités</a>
                <a href="/admin/newsletter">Newsletter</a>
                <a href="/admin/bons-de-commande">Bons de commande</a>
                <a href="/admin/statistiques">Statistiques CA</a>
                <a href="/admin/securite">Sécurité</a>
            <?php else : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte">
                    <?= htmlspecialchars(__('panel.account')) ?>
                </a>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/profil">
                    <?= htmlspecialchars(__('panel.profile')) ?>
                </a>
                <?php if (!$isCompany) : ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/commandes">
                    <?= htmlspecialchars(__('panel.orders')) ?>
                </a>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/adresses">
                    <?= htmlspecialchars(__('panel.addresses')) ?>
                </a>
                <?php endif; ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/favoris">
                    <?= htmlspecialchars(__('panel.favorites')) ?>
                </a>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/securite">
                    <?= htmlspecialchars(__('panel.security')) ?>
                </a>
                <a href="/<?= htmlspecialchars($navLang) ?>/mon-compte/export">
                    <?= htmlspecialchars(__('panel.export')) ?>
                </a>
            <?php endif; ?>
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
