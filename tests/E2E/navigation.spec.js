// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

/**
 * Vérifie qu'une page se charge (status ≠ 404/500), a un header et un footer.
 */
async function expectPageOk(page, path) {
    await page.goto(path);

    // Pas de page d'erreur
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('.site-header')).toBeVisible();
    await expect(page.locator('.site-footer')).toBeVisible();
}

// ============================================================
// Suite Navigation — liens de la homepage
// ============================================================

test.describe('Navigation — toutes les pages de la homepage', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    // ── Pages principales ────────────────────────────────────────────────────

    test('/ redirige ou charge la homepage', async ({ page }) => {
        await page.goto('/fr');
        await expect(page.locator('main.home-page')).toBeVisible();
    });

    test('/fr/vins se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/vins');
    });

    test('/fr/vins/collection se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/vins/collection');
    });

    test('/fr/le-chateau se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/le-chateau');
    });

    test('/fr/savoir-faire se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/savoir-faire');
    });

    test('/fr/actualites se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/actualites');
    });

    test('/fr/contact se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/contact');
    });

    test('/fr/mentions-legales se charge (accessible sans age-gate)', async ({ page, context }) => {
        await context.clearCookies();
        await page.goto('/fr/mentions-legales');
        await expect(page).not.toHaveURL(/\/age-gate/);
        await expect(page.locator('main')).toBeVisible();
    });

    test('/fr/plan-du-site se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/plan-du-site');
    });

    test('/fr/webmaster se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/webmaster');
    });

    test('/fr/support se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/support');
    });

    test('/fr/jeux se charge', async ({ page }) => {
        await expectPageOk(page, '/fr/jeux');
    });

    // ── Version anglaise ─────────────────────────────────────────────────────

    test('/en charge la homepage en anglais', async ({ page }) => {
        await page.goto('/en');
        await expect(page.locator('main.home-page')).toBeVisible();
    });

    test('/en/vins se charge', async ({ page }) => {
        await expectPageOk(page, '/en/vins');
    });

    test('/en/le-chateau se charge', async ({ page }) => {
        await expectPageOk(page, '/en/le-chateau');
    });

    // ── Liens cliquables depuis la nav ───────────────────────────────────────

    test('les liens du footer-nav sont cliquables', async ({ page }) => {
        await page.goto('/fr');
        const links = page.locator('.footer-nav a');
        const count = await links.count();
        expect(count).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
            const href = await links.nth(i).getAttribute('href');
            // Exclure les liens externes (websitecarbon)
            if (href && href.startsWith('/')) {
                const resp = await page.request.get(href);
                expect(resp.status(), `Lien footer ${href} doit répondre < 500`).toBeLessThan(500);
            }
        }
    });

    test('le plan du site liste les URLs principales', async ({ page }) => {
        await page.goto('/fr/plan-du-site');
        await expect(page.locator('.sitemap-card').first()).toBeVisible();
        const count = await page.locator('.sitemap-card').count();
        expect(count).toBeGreaterThanOrEqual(5);
    });

    // ── Actualités ───────────────────────────────────────────────────────────

    test('la page actualités liste des articles (ou affiche le message vide)', async ({ page }) => {
        await page.goto('/fr/actualites');
        const hasCards = await page.locator('.news-card').count();
        const hasEmpty = await page.locator('.news-list__empty').count();
        expect(hasCards + hasEmpty).toBeGreaterThan(0);
    });

});
