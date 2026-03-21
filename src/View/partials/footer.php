<?php
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-main">
            <div class="footer-hve">
                <img src="/assets/images/hve.png" alt="Certification Haute Valeur Environnementale" width="56">
            </div>

            <nav class="footer-nav" aria-label="Navigation secondaire">
                <a href="/<?= htmlspecialchars($navLang) ?>"><?= htmlspecialchars(__('nav.home')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/actualites"><?= htmlspecialchars(__('nav.news')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/contact"><?= htmlspecialchars(__('nav.contact')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/mentions-legales"><?= htmlspecialchars(__('footer.legal_notice')) ?></a>
                <a href="/<?= htmlspecialchars($navLang) ?>/plan-du-site"><?= htmlspecialchars(__('footer.sitemap')) ?></a>
            </nav>

            <div class="footer-payments" aria-label="Moyens de paiement acceptés">
                <img src="/assets/images/payment-cb.svg" alt="Carte Bleue" width="38">
                <img src="/assets/images/payment-visa.svg" alt="Visa" width="38">
                <img src="/assets/images/payment-mastercard.svg" alt="Mastercard" width="38">
                <img src="/assets/images/payment-ca.svg" alt="Crédit Agricole up2pay" width="80">
            </div>
        </div>

        <div class="footer-divider" role="separator"></div>

        <div class="footer-bottom">
            <p class="footer-legal"><?= htmlspecialchars(__('footer.alcohol_warning')) ?></p>
            <p class="footer-copyright">
                &copy; 2019&ndash;<?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?> &mdash;
                <?= htmlspecialchars(__('footer.made_by')) ?>
            </p>
        </div>
    </div>
</footer>
<script src="/assets/js/main.js"></script>
</body>
</html>
