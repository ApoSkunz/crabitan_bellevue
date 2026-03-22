// @ts-check
import { test, expect } from './support/fixtures.js';

// ============================================================
// Helpers
// ============================================================

async function visitVerified(page, context, path = '/fr') {
    const domain = new URL(process.env.APP_URL || 'http://localhost:8000').hostname;
    await context.addCookies([
        { name: 'age_verified', value: '1', domain, path: '/', httpOnly: true, sameSite: 'Lax' },
    ]);
    await page.goto(path);
}

// ============================================================
// Suite Homepage
// ============================================================

test.describe('Homepage — structure et sections', () => {

    // ── Page charge correctement ────────────────────────────────────────────

    test('la homepage se charge sans erreur 404/500', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page).not.toHaveURL(/\/age-gate/);
        await expect(page.locator('main.home-page')).toBeVisible();
    });

    test('le <title> contient le nom du château', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page).toHaveTitle(/Crabitan/i);
    });

    // ── Carousel ────────────────────────────────────────────────────────────

    test('le carrousel héro est visible', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('.hero-carousel')).toBeVisible();
        await expect(page.locator('.carousel__slide.is-active')).toBeVisible();
    });

    test('les boutons prev/next du carrousel sont présents', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#carousel-prev')).toBeVisible();
        await expect(page.locator('#carousel-next')).toBeVisible();
    });

    test('les dots de navigation du carrousel sont présents', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        const dots = page.locator('.carousel__dot');
        await expect(dots).toHaveCount(5);
    });

    test('le CTA hero redirige vers la page vins', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        const cta = page.locator('.carousel__cta');
        await expect(cta).toHaveAttribute('href', /\/vins/);
    });

    // ── Sections ────────────────────────────────────────────────────────────

    test('la section Nos Vins est présente', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#nos-vins')).toBeVisible();
    });

    test('la section Notre Histoire est présente', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#histoire')).toBeVisible();
    });

    test('la section Savoir-Faire est présente', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#savoir-faire')).toBeVisible();
    });

    test('la section Vidéo est présente', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#video-domaine')).toBeVisible();
        await expect(page.locator('.home-video__element')).toBeVisible();
    });

    test('la section Actualités est présente', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#actualites')).toBeVisible();
    });

    test('la section Localisation est présente avec une iframe de carte', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#localisation')).toBeVisible();
        await expect(page.locator('#localisation iframe')).toBeVisible();
    });

    // ── Header / Footer ─────────────────────────────────────────────────────

    test('le header est présent', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('.site-header')).toBeVisible();
    });

    test('le footer est présent', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('.site-footer')).toBeVisible();
    });

    test('le footer contient les pictos alcool', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('.footer-alcohol-pictos')).toBeVisible();
    });

    // ── Alternance dark/light ────────────────────────────────────────────────

    test('la section historique a la classe home-section--dark', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#histoire')).toHaveClass(/home-section--dark/);
    });

    test('la section actualités n\'a pas la classe home-section--dark', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        const newsSection = page.locator('#actualites');
        const cls = await newsSection.getAttribute('class');
        expect(cls).not.toContain('home-section--dark');
    });

    test('la section localisation a la classe home-section--dark', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await expect(page.locator('#localisation')).toHaveClass(/home-section--dark/);
    });

});
