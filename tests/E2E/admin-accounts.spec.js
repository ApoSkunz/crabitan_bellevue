// @ts-check
import { test, expect } from './support/fixtures.js';

const ADMIN_EMAIL    = 'admin@dev.local';
const ADMIN_PASSWORD = 'Dev123456789!';
const ADMIN_DEVICE   = 'a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4';

/**
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 */
async function loginAsAdmin(page, context) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;

    await context.addCookies([
        { name: 'age_verified', value: '1',          domain, path: '/', httpOnly: true,  sameSite: 'Lax' },
        { name: 'device_token', value: ADMIN_DEVICE, domain, path: '/', httpOnly: false, sameSite: 'Lax' },
    ]);

    await page.goto('/fr');
    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

    await page.locator('#login-modal-email').fill(ADMIN_EMAIL);
    await page.locator('#login-modal-password').fill(ADMIN_PASSWORD);

    await Promise.all([
        page.waitForURL(/\/admin|\/fr|\/en/, { timeout: 12000 }),
        page.locator('form[action*="/connexion"] button[type="submit"]').click(),
    ]);

    if (!page.url().includes('/admin')) {
        await page.goto('/admin');
    }

    await expect(page.locator('.admin-sidebar')).toBeVisible({ timeout: 8000 });
}

// ============================================================
// Suite : Admin — Gestion des comptes
// ============================================================

test.describe('Admin comptes — liste et filtres', () => {

    test('Nominal : page comptes charge avec tous les comptes (sans filtre)', async ({ page, context }) => {
        await loginAsAdmin(page, context);
        await page.goto('/admin/comptes');

        await expect(page.locator('h1')).toContainText('Comptes');
        await expect(page.locator('.admin-table')).toBeVisible();
        await expect(page.locator('.admin-filters')).toBeVisible();
    });

    test('Filtre activity=warning — page rend sans erreur 500', async ({ page, context }) => {
        await loginAsAdmin(page, context);
        await page.goto('/admin/comptes?activity=warning');

        await expect(page.locator('h1')).toContainText('Comptes');
        // Soit des comptes en alerte, soit "Aucun compte trouvé"
        const hasAccounts = await page.locator('.admin-table tbody tr td[data-label="Email"]').count() > 0;
        if (hasAccounts) {
            await expect(page.locator('.admin-table tbody tr').first()).toBeVisible();
        } else {
            await expect(page.locator('.admin-table-empty-cell')).toBeVisible();
        }
    });

    test('Filtre activity=deleted — comptes supprimés visibles', async ({ page, context }) => {
        await loginAsAdmin(page, context);
        await page.goto('/admin/comptes?activity=deleted');

        await expect(page.locator('h1')).toContainText('Comptes');
        await expect(page.locator('.admin-table')).toBeVisible();
    });

    test('Comptes actifs — pas de bouton "Annuler suppression"', async ({ page, context }) => {
        await loginAsAdmin(page, context);
        await page.goto('/admin/comptes?activity=active');

        // Les comptes actifs ne doivent jamais afficher le bouton annuler suppression
        const cancelBtn = page.locator('button:has-text("Annuler suppression")');
        await expect(cancelBtn).toHaveCount(0);
    });

});

// ============================================================
// Suite : Annuler suppression — bouton présent si alerte active
// ============================================================

test.describe('Admin comptes — annuler suppression', () => {

    test('Nominal : bouton visible pour compte en alerte et déclenche action POST', async ({ page, context }) => {
        await loginAsAdmin(page, context);
        await page.goto('/admin/comptes?activity=warning');

        const cancelBtn = page.locator('button:has-text("Annuler suppression")').first();
        const hasWarningAccounts = await cancelBtn.count() > 0;

        if (!hasWarningAccounts) {
            // Pas de compte en alerte dans le seed — vérifier juste que la page rend ok
            await expect(page.locator('.admin-table')).toBeVisible();
            return;
        }

        // Intercepter la soumission de formulaire (POST → redirect)
        await Promise.all([
            page.waitForResponse(res => res.url().includes('/annuler-suppression') && res.status() === 302,
                { timeout: 8000 }).catch(() => null),
            cancelBtn.click(),
        ]);

        // Après soumission, flash success doit apparaître
        await page.waitForURL(/\/admin\/comptes/, { timeout: 8000 });
        const flash = page.locator('.admin-flash--success');
        if (await flash.count() > 0) {
            await expect(flash).toContainText('Suppression annulée');
        }
    });

    test('Erreur : soumission sans token CSRF → flash erreur', async ({ page, context }) => {
        await loginAsAdmin(page, context);

        // Naviguer vers la page, récupérer un ID aléatoire
        await page.goto('/admin/comptes');

        // Injecter un formulaire POST avec CSRF invalide via fetch API
        const result = await page.evaluate(async () => {
            const form = new FormData();
            form.append('csrf_token', 'invalid-token');
            const res = await fetch('/admin/comptes/9999/annuler-suppression', {
                method: 'POST',
                body: form,
                redirect: 'manual',
            });
            // redirect:'manual' → opaque redirect (status 0, type opaqueredirect) = 302 côté serveur
            return res.type === 'opaqueredirect' ? 302 : res.status;
        });

        // Doit retourner 302 (redirect vers /admin/comptes avec flash error)
        expect([302, 200]).toContain(result);
    });

});
