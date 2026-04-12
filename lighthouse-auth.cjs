/**
 * lighthouse-auth.cjs
 * Script Puppeteer pour LHCI — bypass age-gate + cookie banner.
 *
 * ⚠️  LHCI 0.15 ignore la valeur de retour du puppeteerScript.
 *     Les cookies doivent être injectés via page.setCookie() en utilisant
 *     la propriété `url` (pas `domain`), ce qui ne nécessite aucune navigation
 *     et évite de bloquer PHP -S (single-threaded).
 *
 * Cookies injectés :
 *   - age_verified=1           → bypass vérification d'âge (Art. L3342-1 CSP)
 *   - cb-cookie-consent=refused → masque le bandeau ePrivacy sans charger GA
 *
 * @param {import('puppeteer').Browser} browser Instance Puppeteer partagée avec Lighthouse
 * @param {{url: string}} _context URL en cours d'audit (non utilisée ici)
 */
module.exports = async (browser, _context) => {
    const page = await browser.newPage();

    await page.setCookie(
        {
            name: 'age_verified',
            value: '1',
            url: 'http://localhost:8080',
            path: '/',
            httpOnly: false,
            sameSite: 'Lax',
            secure: false,
        },
        {
            name: 'cb-cookie-consent',
            value: 'refused',
            url: 'http://localhost:8080',
            path: '/',
            httpOnly: false,
            sameSite: 'Lax',
            secure: false,
        },
    );

    await page.close();
};
