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

    // ── Cookie banner obligatoire ───────────────────────────────────────────

    test('entrer sans répondre au cookie → bandeau secoué + message affiché', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('input[name="legal_age"][value="1"]').check();
        await page.locator('button[type="submit"]').click();

        await expect(page.locator('#cookie-banner')).toHaveClass(/is-shaking/);
        await expect(page.locator('.cookie-banner__required')).toBeVisible();
    });

    test('accepter cookies → localStorage cb-cookie-consent = accepted', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-accept').click();
        const consent = await page.evaluate(() => localStorage.getItem('cb-cookie-consent'));
        expect(consent).toBe('accepted');
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    test('refuser cookies → localStorage cb-cookie-consent = refused', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        const consent = await page.evaluate(() => localStorage.getItem('cb-cookie-consent'));
        expect(consent).toBe('refused');
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    // ── Majeur ─────────────────────────────────────────────────────────────

    test('majeur + cookies répondus → redirigé vers le site, cookie age_verified posé', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        await page.locator('input[name="legal_age"][value="1"]').check();
        await page.locator('button[type="submit"]').click();

        await expect(page).not.toHaveURL(/\/age-gate/);

        const cookies = await context.cookies();
        const ageCookie = cookies.find(c => c.name === 'age_verified');
        expect(ageCookie).toBeDefined();
        expect(ageCookie?.value).toBe('1');
    });

    test('majeur sans "se souvenir" → cookie session (pas d\'expiry fixe)', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        await page.locator('input[name="legal_age"][value="1"]').check();
        // Ne pas cocher "se souvenir"
        await page.locator('button[type="submit"]').click();

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
        await page.locator('input[name="legal_age"][value="1"]').check();
        await page.locator('button[type="submit"]').click();

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

    // ── Mineur ─────────────────────────────────────────────────────────────

    test('mineur → message erreur visible + formulaire bloqué', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        await page.locator('input[name="legal_age"][value="0"]').check();
        await page.locator('button[type="submit"]').click();

        await expect(page.locator('#age-gate-error')).toBeVisible();
        await expect(page.locator('input[name="legal_age"]').first()).toBeDisabled();
        await expect(page.locator('button[type="submit"]')).toBeDisabled();
    });

    test('mineur → redirection Google après 3 secondes', async ({ page, context }) => {
        await resetState(context, page);
        await page.locator('#cookie-refuse').click();
        await page.locator('input[name="legal_age"][value="0"]').check();
        await page.locator('button[type="submit"]').click();

        // Attendre la redirection (timeout 5s pour couvrir les 3s + latence)
        await page.waitForURL('https://www.google.com/**', { timeout: 5000 });
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
