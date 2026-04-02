<?php
// Pré-remplissage email si connecté
$footerUserEmail = '';
$footerToken = $_COOKIE['auth_token'] ?? null;
if ($footerToken) {
    try {
        $footerPayload   = \Core\Jwt::decode($footerToken);
        $footerAccountId = (int) ($footerPayload['sub'] ?? 0);
        $footerAccount   = (new \Model\AccountModel())->findById($footerAccountId);
        $footerUserEmail = $footerAccount ? (string) ($footerAccount['email'] ?? '') : '';
    } catch (\Throwable) {
        // Token invalide ou expiré — pas de pré-remplissage
    }
}
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-main">
            <div class="footer-hve">
                <img
                    src="/assets/images/badges/haute-valeur-environnementale.png"
                    alt="Certification Haute Valeur Environnementale"
                    width="56" loading="lazy"
                >
            </div>

            <nav class="footer-nav" aria-label="Navigation secondaire">
                <a href="/<?= htmlspecialchars($navLang) ?>"><?= htmlspecialchars(__('nav.home')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/actualites"><?= htmlspecialchars(__('nav.news')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales">
                    <?= htmlspecialchars(__('footer.legal_notice')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite">
                    <?= htmlspecialchars(__('footer.privacy_policy')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/plan-du-site">
                    <?= htmlspecialchars(__('footer.sitemap')) ?></a>
            </nav>

            <div class="footer-payments" aria-label="Moyens de paiement acceptés">
                <img src="/assets/images/payment/payment-cb-banner.png"
                    alt="CB, Visa, Mastercard" height="28" loading="lazy">
            </div>
        </div>

        <section class="footer-newsletter" aria-label="<?= htmlspecialchars(__('newsletter.footer_title')) ?>">
            <form id="footer-newsletter-form"
                  action="/<?= htmlspecialchars($navLang) ?>/newsletter/inscription"
                  method="POST"
                  novalidate
                  data-msg-success="<?= htmlspecialchars(__('newsletter.confirm_sent')) ?>"
                  data-msg-invalid="<?= htmlspecialchars(__('newsletter.invalid_email')) ?>"
                  data-msg-error="<?= htmlspecialchars(__('error.generic')) ?>">
                <p class="footer-newsletter__title">
                    <?= htmlspecialchars(__('newsletter.footer_title')) ?>
                </p>
                <div class="footer-newsletter__row">
                    <label for="footer-newsletter-email" class="sr-only">
                        <?= htmlspecialchars(__('newsletter.email_placeholder')) ?>
                    </label>
                    <input
                        type="email"
                        id="footer-newsletter-email"
                        name="email"
                        placeholder="<?= htmlspecialchars(__('newsletter.email_placeholder')) ?>"
                        value="<?= htmlspecialchars($footerUserEmail) ?>"
                        autocomplete="email"
                        required
                    >
                    <button type="submit" class="btn btn--primary btn--sm">
                        <?= htmlspecialchars(__('newsletter.subscribe_btn')) ?>
                    </button>
                </div>
                <p class="footer-newsletter__feedback" aria-live="polite" hidden></p>
            </form>
        </section>

        <div class="footer-carbon">
            <div id="wcb" class="carbonbadge"></div>
        </div>

        <hr class="footer-divider">

        <div class="footer-bottom">
            <div class="footer-legal">
                <span class="footer-alcohol-pictos" aria-hidden="true">
                    <!-- Pictogramme interdit aux moins de 18 ans -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="26" height="26" focusable="false">
                        <circle cx="16" cy="16" r="14.5" fill="none" stroke="currentColor" stroke-width="2.2"/>
                        <line x1="5.3" y1="5.3" x2="26.7" y2="26.7" stroke="currentColor" stroke-width="2.2"/>
                        <text x="16" y="21.5" text-anchor="middle" font-size="11.5" font-weight="900" fill="currentColor" font-family="Arial,sans-serif">18</text>
                    </svg>
                    <!-- Pictogramme interdit aux femmes enceintes -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="26" height="26" focusable="false">
                        <circle cx="16" cy="16" r="14.5" fill="none" stroke="currentColor" stroke-width="2.2"/>
                        <line x1="5.3" y1="5.3" x2="26.7" y2="26.7" stroke="currentColor" stroke-width="2.2"/>
                        <circle cx="16" cy="7.5" r="2.6" fill="currentColor"/>
                        <path d="M13.8,11 C12.2,14.5 11.8,19 13.2,22 L15,22 C14.5,19 15.2,17 17,16.5 C20,16 21,18.5 20.5,22 L22.2,22 C23,19 22.5,14 20.5,11 Z" fill="currentColor"/>
                    </svg>
                </span>
                <span><?= htmlspecialchars(__('footer.alcohol_warning')) ?></span>
            </div>
            <p class="footer-copyright">
                &copy; 2019&ndash;<?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?> &mdash;
                <?= htmlspecialchars(__('footer.made_by')) ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/webmaster">
                    <?= htmlspecialchars(__('footer.webmaster')) ?>
                </a>
            </p>
        </div>
    </div>
</footer>

<?php require_once SRC_PATH . '/View/partials/cookie-banner.php'; ?>

<script src="/assets/js/main.js"></script>
</body>
</html>
