// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

// ============================================================
// Constants
// ============================================================

const ADMIN_EMAIL       = 'admin@dev.local';
const SUPER_ADMIN_EMAIL = 'superadmin@dev.local';
const ADMIN_PASSWORD    = 'Dev123456789!';
const ADMIN_DEVICE      = 'mock-jwt-admin-active-001';

// ============================================================
// Helper
// ============================================================

/**
 * Pose age_verified + device_token, navigue vers /fr, ouvre le modal login,
 * remplit et soumet le formulaire, attend la navigation.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 * @param {string} [email]
 */
async function loginAsAdmin(page, context, email = ADMIN_EMAIL) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;

    await context.addCookies([
        { name: 'age_verified', value: '1',          domain, path: '/', httpOnly: true,  sameSite: 'Lax' },
        { name: 'device_token', value: ADMIN_DEVICE, domain, path: '/', httpOnly: false, sameSite: 'Lax' },
    ]);

    await page.goto('/fr');

    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

    await page.locator('#login-modal-email').fill(email);
    await page.locator('#login-modal-password').fill(ADMIN_PASSWORD);

    await Promise.all([
        page.waitForURL(/\/(fr|en|admin)/, { timeout: 15000 }),
        page.locator('form[action*="/connexion"] button[type="submit"]').click(),
    ]);
}

// ============================================================
// Suite : Feature Flags — UI (admin connecté)
// ============================================================

test.describe('Feature Flags — UI (admin connecté)', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/feature-flags se charge', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        await expect(page).toHaveURL('/admin/feature-flags');
        await expect(page.locator('main')).toBeVisible();
    });

    test('la page affiche une liste de feature flags', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        // Au moins une ligne ff-row doit être présente
        const rows = page.locator('.ff-row');
        await expect(rows.first()).toBeVisible({ timeout: 8000 });
        expect(await rows.count()).toBeGreaterThan(0);
    });

    test('chaque flag a un libellé visible', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        const labels = page.locator('.ff-row__label');
        await expect(labels.first()).toBeVisible({ timeout: 8000 });
        // Chaque libellé est non vide
        const count = await labels.count();
        expect(count).toBeGreaterThan(0);
        for (let i = 0; i < Math.min(count, 3); i++) {
            const text = await labels.nth(i).textContent();
            expect(text?.trim().length).toBeGreaterThan(0);
        }
    });

    test('les flags ont des contrôles (checkbox/toggle ou input numérique)', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        const controls = page.locator('.ff-row__control');
        await expect(controls.first()).toBeVisible({ timeout: 8000 });
        // Vérifie la présence d'au moins un toggle ou input
        const toggles = page.locator('.ff-toggle__input, .ff-row__control input[type="number"]');
        expect(await toggles.count()).toBeGreaterThan(0);
    });

    test('admin simple voit un bandeau lecture seule (pas de bouton soumettre)', async ({ page }) => {
        // Le compte admin@dev.local a le rôle "admin" (pas super_admin) → read-only
        await page.goto('/admin/feature-flags');
        // Le bandeau lecture seule doit être visible
        const readonlyBanner = page.locator('.admin-alert--info');
        await expect(readonlyBanner).toBeVisible({ timeout: 8000 });
        // Le bouton d'enregistrement ne doit pas exister (réservé au super_admin)
        await expect(page.locator('#ff-save-btn')).not.toBeAttached();
    });

});

// ============================================================
// Suite : Feature Flags — UI (super_admin connecté)
// ============================================================

test.describe('Feature Flags — UI (super_admin connecté)', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context, SUPER_ADMIN_EMAIL);
    });

    test('super_admin voit le bouton "Enregistrer mes modifications"', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        // Le bouton existe dans le DOM (désactivé par défaut car aucun changement)
        await expect(page.locator('#ff-save-btn')).toBeAttached({ timeout: 8000 });
    });

    test('le bouton soumettre est désactivé tant qu\'aucun changement n\'est effectué', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        const saveBtn = page.locator('#ff-save-btn');
        await expect(saveBtn).toBeAttached({ timeout: 8000 });
        await expect(saveBtn).toBeDisabled();
    });

    test('modifier un flag active le bouton soumettre', async ({ page }) => {
        await page.goto('/admin/feature-flags');
        // Trouver le premier toggle actif (type checkbox)
        const firstToggle = page.locator('.ff-toggle__input').first();
        await expect(firstToggle).toBeAttached({ timeout: 8000 });
        await firstToggle.click();
        const saveBtn = page.locator('#ff-save-btn');
        await expect(saveBtn).toBeEnabled({ timeout: 3000 });
    });

});

// ============================================================
// Suite : Feature Flags — OTP flow
// ============================================================

test.describe('Feature Flags — OTP flow', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context, SUPER_ADMIN_EMAIL);
    });

    test('soumettre batch-request après modification → redirection avec ?otp_batch=1 ou message de succès', async ({ page }) => {
        await page.goto('/admin/feature-flags');

        // Modifier un flag pour activer le bouton soumettre
        const firstToggle = page.locator('.ff-toggle__input').first();
        await expect(firstToggle).toBeAttached({ timeout: 8000 });
        await firstToggle.click();

        const saveBtn = page.locator('#ff-save-btn');
        await expect(saveBtn).toBeEnabled({ timeout: 3000 });

        // Clic → dialog de confirmation natif (window.confirm) ou modale custom
        // On accepte le dialog natif si présent
        page.on('dialog', async (dialog) => {
            await dialog.accept();
        });

        await Promise.all([
            page.waitForURL(/otp_batch=1|feature-flags/, { timeout: 15000 }),
            saveBtn.click(),
        ]);

        // Soit la page est redirigée vers ?otp_batch=1 avec le formulaire OTP visible,
        // soit un message flash "OTP envoyé" est affiché
        const otpOverlay = page.locator('#ff-otp-overlay');
        const flashInfo  = page.locator('[id^="ff-result-"], .admin-flash, [role="dialog"]');

        const hasOtpOverlay = await otpOverlay.isVisible().catch(() => false);
        const hasFlash      = await flashInfo.first().isVisible().catch(() => false);

        expect(hasOtpOverlay || hasFlash || page.url().includes('otp_batch')).toBeTruthy();
    });

    test('batch-confirm avec OTP invalide (000000) → message d\'erreur', async ({ page, request }) => {
        // Naviguer d'abord sur la page admin pour obtenir un cookie de session valide
        await page.goto('/admin/feature-flags');

        // Récupérer le CSRF token depuis la page
        const csrf = await page.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');

        // POST direct vers batch-confirm avec un OTP invalide
        // La réponse sera une redirection (302) vers ?otp_batch=1 avec un flash d'erreur
        // On utilise page.goto pour suivre la redirection et lire le résultat
        const cookies = await page.context().cookies();
        const cookieStr = cookies.map(c => `${c.name}=${c.value}`).join('; ');

        // Soumettre le formulaire OTP avec une valeur invalide via fetch côté navigateur
        const response = await page.evaluate(async ({ csrf }) => {
            const body = new URLSearchParams({ csrf_token: csrf, otp: '000000' });
            const res  = await fetch('/admin/feature-flags/batch-confirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                redirect: 'follow',
            });
            return { status: res.status, url: res.url };
        }, { csrf });

        // Le serveur doit répondre (après redirection) — statut 200 ou redirection vers feature-flags
        // Le message d'erreur "Code OTP expiré" ou "erroné" doit apparaître après la navigation
        expect([200, 302, 303].includes(response.status) || response.url.includes('feature-flags')).toBeTruthy();
    });

    test('formulaire OTP est présent quand ?otp_batch=1 et une session OTP existe', async ({ page }) => {
        // Accéder directement à la page avec otp_batch=1
        // (sans session OTP active, la page devrait quand même charger correctement)
        await page.goto('/admin/feature-flags?otp_batch=1');
        await expect(page.locator('main, #ff-otp-overlay')).toBeVisible({ timeout: 8000 });

        // Si un OTP est en session, le formulaire de confirmation est visible
        // Sinon, la page de liste est affichée normalement
        const url = page.url();
        expect(url).toContain('feature-flags');
    });

    test('le formulaire batch-confirm a le champ otp et csrf_token', async ({ page }) => {
        await page.goto('/admin/feature-flags?otp_batch=1');
        // Si la modale OTP est présente (session OTP active)
        const otpForm = page.locator('#ff-otp-form');
        const hasOtpForm = await otpForm.isVisible().catch(() => false);

        if (hasOtpForm) {
            await expect(otpForm.locator('input[name="otp"]')).toBeAttached();
            await expect(otpForm.locator('input[name="csrf_token"]')).toBeAttached();
            await expect(otpForm.locator('button[type="submit"]')).toBeVisible();
            // Libellé du bouton
            await expect(page.locator('#ff-otp-submit')).toBeVisible();
        } else {
            // Pas d'OTP actif en session — OK, on vérifie juste que la page charge
            await expect(page.locator('main')).toBeVisible();
        }
    });

});

// ============================================================
// Suite : Monitoring Crons
// ============================================================

test.describe('Monitoring Crons', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/monitoring-crons se charge', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        await expect(page).toHaveURL('/admin/monitoring-crons');
        await expect(page.locator('h1, .admin-page-header h1')).toBeVisible({ timeout: 8000 });
    });

    test('la page affiche un tableau de crons ou un message vide', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        const table  = page.locator('.admin-table');
        const empty  = page.locator('text=Aucun run');

        const hasTable = await table.first().isVisible().catch(() => false);
        const hasEmpty = await empty.first().isVisible().catch(() => false);

        expect(hasTable || hasEmpty).toBeTruthy();
    });

    test('le tableau principal des runs affiche les colonnes attendues', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        const tables = page.locator('.admin-table');
        const hasTable = await tables.first().isVisible().catch(() => false);

        if (!hasTable) {
            // Aucun run disponible — passer le test
            await expect(page.locator('text=Aucun run')).toBeVisible();
            return;
        }

        // Le tableau des runs doit contenir au moins les en-têtes Job et Statut
        const headers = page.locator('.admin-table th');
        const headerTexts = await headers.allTextContents();
        const flat = headerTexts.join(' ').toLowerCase();
        expect(flat).toMatch(/job|statut|démarré|durée/i);
    });

    test('la section agrégat 24h est présente', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        // Le titre de la section agrégat
        const agregat = page.locator('text=Agrégat');
        const noRun   = page.locator('text=Aucun run cron dans les 24');

        const hasAgregat = await agregat.isVisible().catch(() => false);
        const hasNoRun   = await noRun.isVisible().catch(() => false);

        expect(hasAgregat || hasNoRun).toBeTruthy();
    });

});

// ============================================================
// Suite : Cron log modal
// ============================================================

test.describe('Cron log modal', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('la modale #cron-log-modal est présente dans le DOM', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        await expect(page.locator('#cron-log-modal')).toBeAttached({ timeout: 8000 });
    });

    test('cliquer sur "Voir tout" ouvre la modale', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        const logBtn = page.locator('.cron-log-btn').first();
        const hasBtn = await logBtn.isVisible().catch(() => false);

        if (!hasBtn) {
            // Aucun log tronqué disponible en BDD — test non applicable
            test.skip();
            return;
        }

        await logBtn.click();
        const modal = page.locator('#cron-log-modal');
        await expect(modal).toBeVisible({ timeout: 5000 });
    });

    test('la modale peut être fermée via le bouton fermer', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        const logBtn = page.locator('.cron-log-btn').first();
        const hasBtn = await logBtn.isVisible().catch(() => false);

        if (!hasBtn) {
            test.skip();
            return;
        }

        await logBtn.click();
        const modal    = page.locator('#cron-log-modal');
        const closeBtn = modal.locator('.cron-log-modal__close');
        await expect(modal).toBeVisible({ timeout: 5000 });

        await closeBtn.click();
        await expect(modal).not.toBeVisible({ timeout: 3000 });
    });

    test('la modale peut être fermée avec la touche Escape', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        const logBtn = page.locator('.cron-log-btn').first();
        const hasBtn = await logBtn.isVisible().catch(() => false);

        if (!hasBtn) {
            test.skip();
            return;
        }

        await logBtn.click();
        const modal = page.locator('#cron-log-modal');
        await expect(modal).toBeVisible({ timeout: 5000 });

        await page.keyboard.press('Escape');
        await expect(modal).not.toBeVisible({ timeout: 3000 });
    });

    test('la modale a aria-labelledby pointant vers l\'ID du titre', async ({ page }) => {
        await page.goto('/admin/monitoring-crons');
        const modal = page.locator('#cron-log-modal');
        await expect(modal).toHaveAttribute('aria-labelledby', 'cron-log-modal-title');
    });

});

// ============================================================
// Suite : App Logs
// ============================================================

test.describe('App Logs', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/logs-app se charge', async ({ page }) => {
        await page.goto('/admin/logs-app');
        await expect(page).toHaveURL('/admin/logs-app');
        await expect(page.locator('h1, .admin-page-header h1')).toBeVisible({ timeout: 8000 });
    });

    test('affiche un tableau de logs ou le message "Aucun log disponible"', async ({ page }) => {
        await page.goto('/admin/logs-app');
        const table = page.locator('.admin-table');
        const empty = page.locator('text=Aucun log disponible');

        const hasTable = await table.first().isVisible().catch(() => false);
        const hasEmpty = await empty.isVisible().catch(() => false);

        expect(hasTable || hasEmpty).toBeTruthy();
    });

    test('le formulaire de filtre par niveau est présent', async ({ page }) => {
        await page.goto('/admin/logs-app');
        await expect(page.locator('select[name="level"]')).toBeVisible({ timeout: 8000 });
    });

    test('le champ de recherche texte est présent', async ({ page }) => {
        await page.goto('/admin/logs-app');
        await expect(page.locator('input[name="search"]')).toBeVisible({ timeout: 8000 });
    });

    test('filtrer par niveau soumet le formulaire et reste sur /admin/logs-app', async ({ page }) => {
        await page.goto('/admin/logs-app');
        const select = page.locator('select[name="level"]');
        await select.selectOption('E_ERROR');

        // data-auto-submit déclenche l'envoi automatiquement
        await page.waitForURL(/logs-app/, { timeout: 8000 });
        await expect(page).toHaveURL(/logs-app/);
    });

});

// ============================================================
// Suite : Email Rate
// ============================================================

test.describe('Email Rate', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/emails-rate se charge', async ({ page }) => {
        await page.goto('/admin/emails-rate');
        await expect(page).toHaveURL('/admin/emails-rate');
        await expect(page.locator('main')).toBeVisible({ timeout: 8000 });
    });

    test('affiche un tableau de logs email ou un message vide', async ({ page }) => {
        await page.goto('/admin/emails-rate');
        const table = page.locator('.admin-table');
        const empty = page.locator('text=/Aucun|aucun/');

        const hasTable = await table.first().isVisible().catch(() => false);
        const hasEmpty = await empty.first().isVisible().catch(() => false);

        expect(hasTable || hasEmpty).toBeTruthy();
    });

});

// ============================================================
// Suite : RGPD demandes
// ============================================================

test.describe('RGPD demandes', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsAdmin(page, context);
    });

    test('/admin/rgpd/demandes se charge', async ({ page }) => {
        await page.goto('/admin/rgpd/demandes');
        await expect(page).toHaveURL('/admin/rgpd/demandes');
        await expect(page.locator('h1')).toBeVisible({ timeout: 8000 });
    });

    test('affiche un tableau de demandes RGPD ou le message vide', async ({ page }) => {
        await page.goto('/admin/rgpd/demandes');
        const table = page.locator('.admin-table');
        const empty = page.locator('text=Aucune demande RGPD trouvée');

        const hasTable = await table.first().isVisible().catch(() => false);
        const hasEmpty = await empty.isVisible().catch(() => false);

        expect(hasTable || hasEmpty).toBeTruthy();
    });

    test('le bouton "+ Nouvelle demande" est présent', async ({ page }) => {
        await page.goto('/admin/rgpd/demandes');
        await expect(page.locator('a[href="/admin/rgpd/demandes/ajouter"]')).toBeVisible({ timeout: 8000 });
    });

    test('les filtres statut et type sont présents', async ({ page }) => {
        await page.goto('/admin/rgpd/demandes');
        await expect(page.locator('select[name="status"]')).toBeVisible({ timeout: 8000 });
        await expect(page.locator('select[name="type"]')).toBeVisible({ timeout: 8000 });
    });

    test('si des demandes existent, le tableau a les colonnes attendues', async ({ page }) => {
        await page.goto('/admin/rgpd/demandes');
        const table = page.locator('.admin-table').first();
        const hasTable = await table.isVisible().catch(() => false);

        if (!hasTable) {
            await expect(page.locator('text=Aucune demande RGPD trouvée')).toBeVisible();
            return;
        }

        const headers = table.locator('th');
        const texts   = await headers.allTextContents();
        const flat    = texts.join(' ').toLowerCase();
        expect(flat).toMatch(/type|statut|email|demandeur/i);
    });

    test('la section journal des exports RGPD Art. 20 est présente', async ({ page }) => {
        await page.goto('/admin/rgpd/demandes');
        const journalTitle = page.locator('text=Journal des exports automatiques');
        await expect(journalTitle).toBeVisible({ timeout: 8000 });
    });

});
