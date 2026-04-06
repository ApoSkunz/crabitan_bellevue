// @ts-check
import { test, expect } from './support/fixtures.js';

// ============================================================
// Helpers
// ============================================================

/** Vide cookies + localStorage avant chaque test */
async function resetState(context, page) {
    await context.clearCookies();
    await page.goto('/age-gate');
    await page.evaluate(() => localStorage.clear());
}

/** Pose le cookie age_verified directement (simule un utilisateur déjà vérifié) */
async function setVerifiedCookie(context, remember = false) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;
    const ttl = 397 * 24 * 3600; // 13 mois
    const cookies = [
        { name: 'age_verified', value: '1', domain, path: '/', httpOnly: true, sameSite: 'Lax' },
    ];
    if (remember) {
        cookies.push({
            name: 'age_remember', value: '1', domain, path: '/',
            httpOnly: true, sameSite: 'Lax',
            expires: Math.floor(Date.now() / 1000) + ttl,
        });
    }
    await context.addCookies(cookies);
}

// ============================================================
// Suite Age Gate
// ============================================================

test.describe('Age Gate — comportements', () => {

    // ── Affichage initial ───────────────────────────────────────────────────

    test('affiche la page age gate si non vérifié', async ({ page, context }) => {
        await context.clearCookies();
        await page.goto('/age-gate');
        await expect(page.locator('.age-gate__card')).toBeVisible();
        await expect(page.locator('.age-gate__quote')).toBeVisible();
    });

    test('affiche le bandeau cookie au chargement', async ({ page, context }) => {
        await context.clearCookies();
        await page.goto('/age-gate');
        await expect(page.locator('#cookie-banner')).toBeVisible();
    });

    // ── Redirection si déjà vérifié ─────────────────────────────────────────

    test('utilisateur déjà vérifié → redirigé depuis /age-gate', async ({ page, context }) => {
        await setVerifiedCookie(context);
        await page.goto('/age-gate');
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

    // ── Cookie banner non-bloquant ──────────────────────────────────────────

    test('bandeau cookie visible sur la page age-gate', async ({ page, context }) => {
        await resetState(context, page);
        await expect(page.locator('#cookie-banner')).toBeVisible();
    });

    test('soumettre sans répondre au cookie → formulaire non bloqué', async ({ page, context }) => {
        await resetState(context, page);

        // Cliquer ENTRER sans avoir répondu au cookie — la soumission doit passer librement
        await page.locator('#age-gate-enter button[type="submit"]').click();

        // Le banner ne doit pas secouer — la soumission passe librement
        await expect(page.locator('#cookie-banner')).not.toHaveClass(/is-shaking/);
        // La page doit avoir changé (soumission réussie)
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

    test('accepter cookies → cookie cb-cookie-consent = accepted', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-accept').click();
        const cookies = await context.cookies();
        const consent = cookies.find((c) => c.name === 'cb-cookie-consent');
        expect(decodeURIComponent(consent?.value ?? '')).toBe('accepted');
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    test('refuser cookies → cookie cb-cookie-consent = refused', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        const cookies = await context.cookies();
        const consent = cookies.find((c) => c.name === 'cb-cookie-consent');
        expect(decodeURIComponent(consent?.value ?? '')).toBe('refused');
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    // ── Majeur ─────────────────────────────────────────────────────────────

    test('majeur + cookies répondus → redirigé vers le site, cookie age_verified posé', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        await page.locator('#age-gate-enter button[type="submit"]').click();

        await expect(page).not.toHaveURL(/\/age-gate/);

        const cookies = await context.cookies();
        const ageCookie = cookies.find(c => c.name === 'age_verified');
        expect(ageCookie).toBeDefined();
        expect(ageCookie?.value).toBe('1');
    });

    test('majeur sans "se souvenir" → cookie session (pas d\'expiry fixe)', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        // Ne pas cocher "se souvenir"
        await page.locator('#age-gate-enter button[type="submit"]').click();

        const cookies = await context.cookies();
        const ageCookie = cookies.find(c => c.name === 'age_verified');
        expect(ageCookie).toBeDefined();
        // Session cookie : expires = -1 dans Playwright
        expect(ageCookie?.expires).toBe(-1);

        const rememberCookie = cookies.find(c => c.name === 'age_remember');
        expect(rememberCookie).toBeUndefined();
    });

    test('"se souvenir de moi" → cookie persistant ~13 mois + age_remember posé', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        await page.locator('input[name="remember"]').check();
        await page.locator('#age-gate-enter button[type="submit"]').click();

        const cookies = await context.cookies();
        const ageCookie     = cookies.find(c => c.name === 'age_verified');
        const rememberCookie = cookies.find(c => c.name === 'age_remember');

        expect(ageCookie).toBeDefined();
        expect(rememberCookie).toBeDefined();

        // Vérifier que l'expiry est bien ~13 mois (397 jours ± 60s de marge)
        const expectedTtl = 397 * 24 * 3600;
        const nowSec      = Math.floor(Date.now() / 1000);
        expect(ageCookie?.expires).toBeGreaterThan(nowSec + expectedTtl - 60);
        expect(ageCookie?.expires).toBeLessThan(nowSec + expectedTtl + 60);
    });

    // ── Mineur / Quitter ───────────────────────────────────────────────────

    test('cliquer Quitter → redirigé hors du site (Google)', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();

        // Bouton QUITTER → soumet legal_age=0, le serveur redirige vers Google
        // On attend la navigation externe (timeout 5s)
        await Promise.all([
            page.waitForURL(/google\.com/, { timeout: 5000 }),
            page.locator('.age-overlay__btn-exit--outline').click(),
        ]);
    });

    test('cliquer Quitter → aucun cookie age_verified posé', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();

        await Promise.all([
            page.waitForURL(/google\.com/, { timeout: 5000 }),
            page.locator('.age-overlay__btn-exit--outline').click(),
        ]);

        const cookies = await context.cookies();
        const ageCookie = cookies.find(c => c.name === 'age_verified');
        expect(ageCookie).toBeUndefined();
    });

    // ── Navigation protégée ─────────────────────────────────────────────────

    test('non vérifié → accès à une page protégée redirige vers age-gate', async ({ page, context }) => {
        await context.clearCookies();
        await page.goto('/fr');
        await expect(page).toHaveURL(/\/age-gate/);
    });

    test('mentions légales accessibles sans vérification', async ({ page, context }) => {
        await context.clearCookies();
        await page.goto('/fr/mentions-legales');
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

});
