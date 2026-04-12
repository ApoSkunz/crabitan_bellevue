/**
 * lighthouse-auth.cjs
 * Script Puppeteer pour LHCI :
 * 1. Bypass age-gate en soumettant le vrai formulaire PHP (cookie set server-side).
 * 2. Connexion au compte client de test si disponible en BDD CI.
 *
 * @param {import('puppeteer').Browser} browser
 * @param {{url: string, options: Record<string, unknown>}} context
 * @returns {Promise<{cookies: import('puppeteer').Protocol.Network.Cookie[]}>}
 */
module.exports = async (browser, _context) => {
    const page = await browser.newPage();

    // ── 1. Age-gate ────────────────────────────────────────────────────────────
    // Soumettre le vrai formulaire PHP pour que setcookie() s'exécute server-side.
    // Poser le cookie manuellement via page.setCookie() ne fonctionne pas car
    // Puppeteer ne peut pas définir de cookies pour localhost avant toute navigation.
    await page.goto('http://localhost:8080/age-gate', {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
    });

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }),
        page.click('#age-gate-enter button[type="submit"]'),
    ]);

    // ── 2. Connexion (optionnelle — non bloquante si compte absent) ────────────
    try {
        const trigger = await page.waitForSelector('#login-modal-trigger', {
            visible: true,
            timeout: 10000,
        });

        await trigger.click();
        await page.waitForSelector('#login-modal-email', { visible: true, timeout: 5000 });

        await page.type('#login-modal-email', 'client.verifie@dev.local');
        await page.type('#login-modal-password', 'Dev123456789!');

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }).catch(() => {}),
            page.click('.login-modal__submit'),
        ]);
    } catch (_e) {
        // Login facultatif — les pages publiques sont auditées avec l'age-gate bypassé.
        // Les pages protégées (/mon-compte) montreront la page non-connectée.
    }

    const cookies = await page.cookies();
    await page.close();

    return { cookies };
};
