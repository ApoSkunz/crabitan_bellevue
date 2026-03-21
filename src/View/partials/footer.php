<?php
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-main">
            <div class="footer-hve">
                <img
                    src="/assets/images/haute-valeur-environnementale.png"
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
                <a href="/<?= htmlspecialchars($navLang) ?>/plan-du-site">
                    <?= htmlspecialchars(__('footer.sitemap')) ?></a>
                <a
                    href="https://www.websitecarbon.com"
                    target="_blank"
                    rel="noopener noreferrer"
                ><?= htmlspecialchars(__('footer.carbon')) ?></a>
            </nav>

            <div class="footer-payments" aria-label="Moyens de paiement acceptés">
                <img src="/assets/images/payment-cb-banner.png"
                    alt="CB, Visa, Mastercard" height="28" loading="lazy">
                <img src="/assets/images/payment-ca-up2pay.png"
                    alt="Crédit Agricole up2pay e-Transactions" height="28" loading="lazy">
            </div>
        </div>

        <div class="footer-carbon">
            <div id="wcb" class="carbonbadge"></div>
        </div>

        <div class="footer-divider" role="separator"></div>

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

<?php require SRC_PATH . '/View/partials/cookie-banner.php'; ?>

<script src="/assets/js/main.js"></script>
<script>
    const $q = e => document.getElementById(e),
        url = encodeURIComponent(window.location.href),
        newRequest = function (e = true) {
            fetch("https://api.websitecarbon.com/b?url=" + url)
                .then(e => { if (!e.ok) throw Error(e); return e.json(); })
                .then(n => {
                    e && renderResult(n);
                    n.t = (new Date()).getTime();
                    localStorage.setItem("wcb_" + url, JSON.stringify(n));
                })
                .catch(() => {
                    const g = $q("wcb_g");
                    if (g) g.innerHTML = "No result";
                    localStorage.removeItem("wcb_" + url);
                });
        },
        renderResult = function (e) {
            const g = $q("wcb_g"), p = $q("wcb_2");
            if (g) g.innerHTML = e.c + "g de CO<sub>2</sub>/vue";
            if (p) p.insertAdjacentHTML("beforeEnd", "Plus propre que " + e.p + "% des pages testées");
        };

    if ("fetch" in window && $q("wcb")) {
        const css = `<style>.carbonbadge{--b2:#c9a84c;font-size:13px;line-height:1.15;text-align:center}
.carbonbadge a,.carbonbadge p{text-align:center;display:inline-flex;justify-content:center;align-items:center;
font-size:1em;margin:.2em 0;line-height:1.15;font-family:system-ui,sans-serif}
#wcb_g,.carbonbadge a{padding:.3em .5em;color:#2a2218;background:#f5f0e8;border:.125rem solid var(--b2);border-radius:.3em 0 0 .3em}
#wcb_g{border-right:0;min-width:8.2em}.carbonbadge a{border-radius:0 .3em .3em 0;border-left:0;
background:#080808;color:#f5f0e8;text-decoration:none;font-weight:700;border-color:var(--b2)}</style>`;
        $q("wcb").insertAdjacentHTML("beforeEnd", css);
        $q("wcb").insertAdjacentHTML(
            "beforeEnd",
            '<div id="wcb_p"><p id="wcb_g">Mesure CO<sub>2</sub>&hellip;</p>' +
            '<a target="_blank" rel="noopener" href="https://websitecarbon.com">Website Carbon</a></div>' +
            '<p id="wcb_2"></p>'
        );
        const stored = localStorage.getItem("wcb_" + url);
        const now = (new Date()).getTime();
        if (stored) {
            const t = JSON.parse(stored);
            renderResult(t);
            if (now - t.t > 864e5) newRequest(false);
        } else {
            newRequest();
        }
    }
</script>
</body>
</html>
