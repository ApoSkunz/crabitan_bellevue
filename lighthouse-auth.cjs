/**
 * lighthouse-auth.cjs
 * Script Puppeteer pour LHCI — bypass age-gate.
 *
 * ⚠️  PHP -S est single-threaded : tout page.goto() concurrent avec les
 *     audits LHCI provoque un timeout 30 s × N URLs (33 pages = ~16 min perdues).
 *
 * Fix : retourner le cookie age_verified directement, sans navigation.
 * LHCI injecte automatiquement les cookies retournés sur chaque page auditée
 * avant de lancer le crawler Lighthouse.
 *
 * @param {import('puppeteer').Browser} _browser Non utilisé
 * @param {{url: string}} _context Non utilisé
 * @returns {{ cookies: import('puppeteer').Protocol.Network.Cookie[] }}
 */
module.exports = async (_browser, _context) => {
    return {
        cookies: [
            {
                name: 'age_verified',
                value: '1',
                domain: 'localhost',
                path: '/',
                httpOnly: true,
                sameSite: 'Lax',
                secure: false,
            },
        ],
    };
};
