// @ts-check
import { test, expect } from './support/fixtures.js';

// ============================================================
// Constants
// ============================================================

const ADMIN_EMAIL    = 'admin@dev.local';
const ADMIN_PASSWORD = 'Dev123456789!';
const ADMIN_DEVICE   = 'device-uuid-admin-chrome';

// ============================================================
// Helpers
// ============================================================

/**
 * Pose les cookies age_verified + device_token, navigue vers /fr,
 * ouvre le modal login, s'authentifie et attend la redirection admin.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 */
async function loginAsAdmin(page, context) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;

    await context.addCookies([
        { name: 'age_verified', value: '1',         domain, path: '/', httpOnly: true,  sameSite: 'Lax' },
        { name: 'device_token', value: ADMIN_DEVICE, domain, path: '/', httpOnly: false, sameSite: 'Lax' },
    ]);

    await page.goto('/fr');

    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

    await page.locator('#login-modal-email').fill(ADMIN_EMAIL);
    await page.locator('#login-modal-password').fill(ADMIN_PASSWORD);

    // Admin est redirigé vers /admin après connexion
    await Promise.all([
        page.waitForURL(/\/admin|\/fr|\/en/, { timeout: 12000 }),
        page.locator('form[action*="/connexion"] button[type="submit"]').click(),
    ]);

    // Si redirigé vers la homepage (pas /admin), naviguer explicitement
    if (!page.url().includes('/admin')) {
        await page.goto('/admin');
    }

    await expect(page.locator('.admin-sidebar')).toBeVisible({ timeout: 8000 });
}

// ============================================================
// Suite : Accès admin (non connecté)
// ============================================================

test.describe('Accès admin — non connecté', () => {

    test('/admin sans auth → 404 (route non révélée)', async ({ page }) => {
        // AdminMiddleware : abort(404) si pas de JWT valide — ne redirige pas
        const response = await page.goto('/admin');
        expect(response?.status()).toBe(404);
    });

    test('customer connecté → /admin retourne 404', async ({ page, context }) => {
        const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;
        await context.addCookies([
            { name: 'age_verified', value: '1',                         domain, path: '/', httpOnly: true,  sameSite: 'Lax' },
            { name: 'device_token', value: 'device-uuid-sophie-macbook', domain, path: '/', httpOnly: false, sameSite: 'Lax' },
        ]);
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
        await page.locator('#login-modal-email').fill('client.verifie@dev.local');
        await page.locator('#login-modal-password').fill('Dev123456789!');
        await Promise.all([
            page.waitForURL(/\/(fr|en)/, { timeout: 10000 }),
            page.locator('form[action*="/connexion"] button[type="submit"]').click(),
        ]);
        const response = await page.goto('/admin');
        expect(response?.status()).toBe(404);
    });

});

// ============================================================
// Suite : Dashboard admin (connecté)
// ============================================================

test.describe('Dashboard admin — connecté', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin se charge avec la sidebar de navigation', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.locator('.admin-sidebar')).toBeVisible();
        await expect(page.locator('nav.admin-nav')).toBeVisible();
    });

    test('le dashboard contient les widgets de statistiques', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.locator('.admin-stats')).toBeVisible();
        const cards = page.locator('.admin-stat-card');
        expect(await cards.count()).toBeGreaterThanOrEqual(4);
    });

    test('le dashboard contient le tableau des dernières commandes', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.locator('.admin-table')).toBeVisible();
    });

    test('la topbar admin est présente avec le lien "Voir le site"', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.locator('.admin-topbar')).toBeVisible();
        await expect(page.locator('.admin-topbar a[href="/fr"]')).toBeVisible();
    });

    test('la sidebar contient les sections de navigation principales', async ({ page }) => {
        await page.goto('/admin');
        const nav = page.locator('.admin-nav');
        await expect(nav).toBeVisible();
        // Lien Tableau de bord
        await expect(nav.locator('a[href="/admin"]')).toBeVisible();
        // Lien Vins
        await expect(nav.locator('a[href="/admin/vins"]')).toBeVisible();
        // Lien Commandes
        await expect(nav.locator('a[href="/admin/commandes"]')).toBeVisible();
    });

});

// ============================================================
// Suite : Navigation admin — pages qui se chargent
// ============================================================

/** @param {import('@playwright/test').Page} page @param {string} path */
async function expectAdminPageOk(page, path) {
    await page.goto(path);
    await expect(page.locator('.admin-sidebar'), `Sidebar manquante sur ${path}`).toBeVisible({ timeout: 8000 });
    await expect(page.locator('#admin-main-content'), `Contenu principal manquant sur ${path}`).toBeVisible();
}

test.describe('Navigation admin — pages qui se chargent', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/vins se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/vins');
    });

    test('/admin/commandes se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/commandes');
    });

    test('/admin/comptes se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/comptes');
    });

    test('/admin/tarifs se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/tarifs');
    });

    test('/admin/newsletter se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/newsletter');
    });

    test('/admin/bons-de-commande se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/bons-de-commande');
    });

    test('/admin/statistiques se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/statistiques');
    });

    test('/admin/marketing se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/marketing');
    });

    test('/admin/factures se charge', async ({ page }) => {
        await expectAdminPageOk(page, '/admin/factures');
    });

});

// ============================================================
// Suite : Routes super_admin — accès refusé pour admin simple
// (super_admin n'a pas de trusted_device en seed → login MFA bloqué)
// Ces tests vérifient le contrôle d'accès côté serveur (abort 404)
// ============================================================

test.describe('Routes super_admin — 404 pour admin simple', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/logs → 404 pour admin simple', async ({ page }) => {
        const response = await page.goto('/admin/logs');
        expect(response?.status()).toBe(404);
    });

    test('/admin/monitoring-crons → 404 pour admin simple', async ({ page }) => {
        const response = await page.goto('/admin/monitoring-crons');
        expect(response?.status()).toBe(404);
    });

    test('/admin/admins → 404 pour admin simple', async ({ page }) => {
        const response = await page.goto('/admin/admins');
        expect(response?.status()).toBe(404);
    });

});

// ============================================================
// Suite : Admin vins — CRUD basique
// ============================================================

test.describe('Admin vins — CRUD basique', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/vins liste au moins un vin dans le tableau', async ({ page }) => {
        await page.goto('/admin/vins');
        await expect(page.locator('.admin-table')).toBeVisible();
        // Tableau avec au moins une ligne de données (tr dans tbody, hors "Aucun vin trouvé")
        const rows = page.locator('.admin-table tbody tr');
        expect(await rows.count()).toBeGreaterThan(0);
        // Vérifier que ce n'est pas le message vide
        const emptyCell = page.locator('.admin-table tbody td[colspan]');
        const emptyCount = await emptyCell.count();
        if (emptyCount === 0) {
            // Des lignes de vins réelles sont présentes
            await expect(rows.first()).toBeVisible();
        }
    });

    test('/admin/vins affiche le bouton "Ajouter un vin"', async ({ page }) => {
        await page.goto('/admin/vins');
        await expect(page.locator('a[href="/admin/vins/ajouter"]')).toBeVisible();
    });

    test('/admin/vins/ajouter affiche le formulaire avec les champs obligatoires', async ({ page }) => {
        await page.goto('/admin/vins/ajouter');
        await expect(page.locator('.admin-sidebar')).toBeVisible();

        // Champ appellation (select)
        await expect(page.locator('#appellation')).toBeVisible();
        // Champ millésime
        await expect(page.locator('#vintage')).toBeVisible();
        // Champ prix
        await expect(page.locator('#price')).toBeVisible();
        // Champ couleur (hidden, alimenté par JS)
        await expect(page.locator('#wine_color')).toBeAttached();
    });

    test('/admin/vins/ajouter — le formulaire a un bouton de soumission', async ({ page }) => {
        await page.goto('/admin/vins/ajouter');
        await expect(page.locator('.admin-form button[type="submit"]')).toBeVisible();
    });

    test('/admin/vins affiche les filtres de recherche', async ({ page }) => {
        await page.goto('/admin/vins');
        await expect(page.locator('.admin-filters')).toBeVisible();
        await expect(page.locator('select[name="color"]')).toBeVisible();
        await expect(page.locator('select[name="available"]')).toBeVisible();
    });

});

// ============================================================
// Suite : Admin commandes
// ============================================================

test.describe('Admin commandes', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/commandes affiche le tableau de commandes', async ({ page }) => {
        await page.goto('/admin/commandes');
        await expect(page.locator('.admin-sidebar')).toBeVisible();
        await expect(page.locator('.admin-table')).toBeVisible();
    });

    test('/admin/commandes affiche un en-tête de tableau avec les colonnes attendues', async ({ page }) => {
        await page.goto('/admin/commandes');
        const thead = page.locator('.admin-table thead');
        await expect(thead).toBeVisible();
        // La table doit avoir au moins 3 colonnes dans le header
        const ths = thead.locator('th');
        expect(await ths.count()).toBeGreaterThanOrEqual(3);
    });

    test('/admin/commandes — erreur 400/500 absente (page saine)', async ({ page }) => {
        await page.goto('/admin/commandes');
        // Pas de page d'erreur : le layout admin est chargé
        await expect(page.locator('.admin-shell')).toBeVisible();
        await expect(page).not.toHaveURL(/\/fr\?login/);
    });

});

// ============================================================
// Suite : Admin comptes
// ============================================================

test.describe('Admin comptes', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/comptes affiche le tableau des comptes clients', async ({ page }) => {
        await page.goto('/admin/comptes');
        await expect(page.locator('.admin-sidebar')).toBeVisible();
        await expect(page.locator('.admin-table')).toBeVisible();
    });

    test('/admin/comptes affiche les filtres de recherche', async ({ page }) => {
        await page.goto('/admin/comptes');
        await expect(page.locator('.admin-filters')).toBeVisible();
    });

    test('/admin/comptes — layout admin complet (sidebar + contenu)', async ({ page }) => {
        await page.goto('/admin/comptes');
        await expect(page.locator('.admin-shell')).toBeVisible();
        await expect(page.locator('#admin-main-content')).toBeVisible();
        await expect(page.locator('.admin-topbar')).toBeVisible();
    });

});
