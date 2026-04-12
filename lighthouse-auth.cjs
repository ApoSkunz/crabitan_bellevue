/**
 * lighthouse-auth.js
 * Script Puppeteer pour LHCI : accepte la vérification d'âge
 * et s'authentifie en tant que client.verifie@dev.local
 * avant la collecte des pages protégées (espace compte).
 *
 * @param {import('puppeteer').Browser} browser
 * @param {{url: string, options: Record<string, unknown>}} context
 * @returns {Promise<{cookies: import('puppeteer').Protocol.Network.Cookie[]}>}
 */
module.exports = async (browser, _context) => {
    const page = await browser.newPage();

    // Naviguer d'abord sur /age-gate pour établir le contexte de domaine,
    // puis poser le cookie age_verified — setCookie avant toute navigation
    // n'attache pas le cookie au contexte Puppeteer sur certaines versions.
    await page.goto('http://localhost:8080/age-gate', { waitUntil: 'domcontentloaded', timeout: 30000 });

    await page.setCookie({
        name: 'age_verified',
        value: '1',
        domain: 'localhost',
        path: '/',
        sameSite: 'Lax',
    });

    await page.goto('http://localhost:8080/fr', { waitUntil: 'networkidle2', timeout: 30000 });

    // Ouvrir la modale de connexion
    await page.waitForSelector('#login-modal-trigger', { visible: true, timeout: 15000 });
    await page.click('#login-modal-trigger');
    await page.waitForSelector('#login-modal-email', { visible: true });

    // Remplir les identifiants du compte client vérifié de dev
    await page.type('#login-modal-email', 'client.verifie@dev.local');
    await page.type('#login-modal-password', 'Dev123456789!');

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
        page.click('.login-modal__submit'),
    ]);

    const cookies = await page.cookies();
    await page.close();

    return { cookies };
};
