// @ts-check
import { test, expect } from './support/fixtures.js';

// ============================================================
// Helpers
// ============================================================

/**
 * Pose le cookie age_verified=1 via l'injection Playwright et navigue vers le chemin donné.
 * À utiliser pour les tests qui n'impliquent pas de redirections PHP intermédiaires.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 * @param {string} path
 */
async function visitVerified(page, context, path = '/fr') {
    const primaryDomain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;
    // Ajoute le cookie pour le domaine principal + localhost (fallback si PHP APP_URL pointe vers localhost)
    const domains = [...new Set([primaryDomain, 'localhost'])];
    for (const domain of domains) {
        await context.addCookies([{
            name: 'age_verified', value: '1', domain, path: '/',
            httpOnly: true, sameSite: 'Lax',
            expires: Math.floor(Date.now() / 1000) + 3600,
        }]);
    }
    await page.goto(path);
}

/**
 * Ouvre le modal de connexion via le déclencheur header.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openLoginModal(page) {
    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
}

/**
 * Ouvre le modal d'inscription via login modal → lien "S'inscrire".
 * (le bouton direct est dans le menu mobile uniquement)
 *
 * @param {import('@playwright/test').Page} page
 */
async function openRegisterModal(page) {
    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
    await page.locator('#login-to-register').click();
    await expect(page.locator('#register-modal')).toHaveAttribute('aria-hidden', 'false');
}

// ============================================================
// Suite Google OAuth
// ============================================================

test.describe('Google OAuth — boutons et flux d\'erreur', () => {

    // ── Bouton dans le modal connexion ──────────────────────────────────────

    test('le bouton "Continuer avec Google" est visible dans le modal connexion', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await openLoginModal(page);

        const googleLink = page.locator('#login-modal a[href*="/auth/google"]');
        await expect(googleLink).toBeVisible();
    });

    test('le lien Google du modal connexion pointe vers /fr/auth/google', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await openLoginModal(page);

        const googleLink = page.locator('#login-modal a[href*="/auth/google"]');
        await expect(googleLink).toHaveAttribute('href', /\/fr\/auth\/google$/);
    });

    // ── Bouton dans le modal inscription ────────────────────────────────────

    test('le bouton "Continuer avec Google" est visible dans le modal inscription', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await openRegisterModal(page);

        const googleLink = page.locator('#register-modal a[href*="/auth/google"]');
        await expect(googleLink).toBeVisible();
    });

    test('le lien Google du modal inscription pointe vers /fr/auth/google', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await openRegisterModal(page);

        const googleLink = page.locator('#register-modal a[href*="/auth/google"]');
        await expect(googleLink).toHaveAttribute('href', /\/fr\/auth\/google$/);
    });

    // ── Erreur critique — callback Google avec error param ──────────────────

    test('callback avec error=access_denied ouvre le modal login avec une erreur', async ({ page, context }) => {
        // Le cookie age_verified est posé avant la navigation — /auth/google est public mais /fr ne l'est pas
        await visitVerified(page, context, '/fr');
        await page.goto('/fr/auth/google/callback?error=access_denied');

        await expect(page).toHaveURL(/\/fr(\?login=1)?/);
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
        await expect(page.locator('#login-modal .alert--error')).toBeVisible();
    });

    // ── Erreur — state CSRF invalide ─────────────────────────────────────────

    test('callback avec state invalide redirige vers login avec erreur', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await page.goto('/fr/auth/google/callback?code=fake-code&state=attacker-state');

        await expect(page).toHaveURL(/\/fr(\?login=1)?/);
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
        await expect(page.locator('#login-modal .alert--error')).toBeVisible();
    });

    // ── Page de rattachement sans session ────────────────────────────────────

    test('/auth/google/link sans pending_google_link en session redirige vers login', async ({ page, context }) => {
        await visitVerified(page, context, '/fr');
        await page.goto('/fr/auth/google/link');

        await expect(page).not.toHaveURL(/\/auth\/google\/link/);
    });

});
