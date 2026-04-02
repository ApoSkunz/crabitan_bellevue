// @ts-check
import { test, expect } from './support/fixtures.js';

const CONSENT_KEY = 'cb-cookie-consent';

/** Supprime le cookie de consentement et recharge la page */
async function clearConsent(context, page) {
    await context.clearCookies();
    await page.goto('/fr');
}

// ============================================================
// Suite Cookie Consent RGPD / CNIL
// ============================================================

test.describe('Cookie Consent — bandeau CNIL', () => {

    // ── Affichage initial ────────────────────────────────────────────────────

    test('bandeau visible à la première visite (aucun cookie de consentement)', async ({ page, context }) => {
        await clearConsent(context, page);
        await expect(page.locator('#cookie-banner')).toBeVisible();
    });

    test('aucun script Google Analytics chargé avant consentement', async ({ page, context }) => {
        await clearConsent(context, page);

        // eslint-disable-next-line no-undef
        const gaLoaded = await page.evaluate(() => window.__gaLoaded ?? false);
        expect(gaLoaded).toBe(false);

        const gaScript = await page.$('script[src*="googletagmanager.com"]');
        expect(gaScript).toBeNull();
    });

    // ── Refus ───────────────────────────────────────────────────────────────

    test('bandeau masqué après refus', async ({ page, context }) => {
        await clearConsent(context, page);

        await page.locator('#cookie-refuse').click();
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    test('aucun script GA chargé après refus', async ({ page, context }) => {
        await clearConsent(context, page);

        await page.locator('#cookie-refuse').click();

        // eslint-disable-next-line no-undef
        const gaLoaded = await page.evaluate(() => window.__gaLoaded ?? false);
        expect(gaLoaded).toBe(false);
    });

    test('cookie de consentement posé avec valeur "refused" après refus', async ({ page, context }) => {
        await clearConsent(context, page);

        await page.locator('#cookie-refuse').click();

        const cookies = await context.cookies();
        const consent = cookies.find((c) => c.name === CONSENT_KEY);
        expect(consent).toBeDefined();
        expect(decodeURIComponent(consent?.value ?? '')).toBe('refused');
    });

    // ── Acceptation ─────────────────────────────────────────────────────────

    test('bandeau masqué après acceptation', async ({ page, context }) => {
        await clearConsent(context, page);

        await page.locator('#cookie-accept').click();
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    test('cookie de consentement posé avec valeur "accepted" après acceptation', async ({ page, context }) => {
        await clearConsent(context, page);

        await page.locator('#cookie-accept').click();

        const cookies = await context.cookies();
        const consent = cookies.find((c) => c.name === CONSENT_KEY);
        expect(consent).toBeDefined();
        expect(decodeURIComponent(consent?.value ?? '')).toBe('accepted');
    });

    // ── Bandeau absent à la visite suivante ──────────────────────────────────

    test('bandeau absent à la deuxième visite si consentement déjà donné', async ({ page, context }) => {
        await clearConsent(context, page);
        await page.locator('#cookie-refuse').click();

        await page.goto('/fr');
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);
    });

    // ── Lien politique de confidentialité ────────────────────────────────────

    test('lien du bandeau pointe vers politique-de-confidentialite', async ({ page, context }) => {
        await clearConsent(context, page);

        const href = await page.locator('#cookie-banner a').getAttribute('href');
        expect(href).toContain('politique-de-confidentialite');
    });

    // ── Re-gestion depuis le footer ──────────────────────────────────────────

    test('bouton Gérer les cookies du footer réaffiche le bandeau', async ({ page, context }) => {
        await clearConsent(context, page);
        await page.locator('#cookie-refuse').click();
        await expect(page.locator('#cookie-banner')).toHaveClass(/is-hidden/);

        await page.locator('#cookie-manage').click();
        await expect(page.locator('#cookie-banner')).not.toHaveClass(/is-hidden/);
    });

    test('après re-gestion, acceptation repose le cookie "accepted"', async ({ page, context }) => {
        await clearConsent(context, page);
        await page.locator('#cookie-refuse').click();

        await page.locator('#cookie-manage').click();
        await page.locator('#cookie-accept').click();

        const cookies = await context.cookies();
        const consent = cookies.find((c) => c.name === CONSENT_KEY);
        expect(decodeURIComponent(consent?.value ?? '')).toBe('accepted');
    });
});
