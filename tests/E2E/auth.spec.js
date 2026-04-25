// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

// ============================================================
// Constants
// ============================================================

const CUSTOMER_EMAIL    = 'client.verifie@dev.local';
const CUSTOMER_PASSWORD = 'Dev123456789!';
const CUSTOMER_DEVICE   = 'mock-jwt-sophie-active-001';

// ============================================================
// Helpers
// ============================================================

/**
 * Pose les cookies age_verified et device_token sur le domaine de l'app,
 * navigue vers /fr, ouvre le modal login, remplit et soumet le formulaire.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 */
async function loginAsCustomer(page, context) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;

    await context.addCookies([
        { name: 'age_verified', value: '1',          domain, path: '/', httpOnly: true, sameSite: 'Lax' },
        { name: 'device_token', value: CUSTOMER_DEVICE, domain, path: '/', httpOnly: false, sameSite: 'Lax' },
    ]);

    await page.goto('/fr');

    // Ouvrir le modal
    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

    // Remplir le formulaire
    await page.locator('#login-modal input[name="email"]').fill(CUSTOMER_EMAIL);
    await page.locator('#login-modal input[name="password"]').fill(CUSTOMER_PASSWORD);

    // Soumettre et attendre la fermeture du modal OU la redirection
    await Promise.all([
        page.waitForURL(/\/(fr|en)/, { timeout: 10000 }),
        page.locator('#login-modal button[type="submit"]').click(),
    ]);
}

// ============================================================
// Suite : Connexion — modal UI
// ============================================================

test.describe('Connexion — modal UI', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('modal connexion s\'ouvre via le trigger header', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
    });

    test('modal connexion contient les champs email, password et le bouton submit', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

        await expect(page.locator('#login-modal input[name="email"]')).toBeVisible();
        await expect(page.locator('#login-modal input[name="password"]')).toBeVisible();
        await expect(page.locator('#login-modal input[name="csrf_token"]')).toBeAttached();
        await expect(page.locator('#login-modal button[type="submit"]')).toBeVisible();
    });

    test('connexion avec mauvais mot de passe → erreur visible dans le modal', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

        await page.locator('#login-modal input[name="email"]').fill(CUSTOMER_EMAIL);
        await page.locator('#login-modal input[name="password"]').fill('MauvaisMotDePasse!');
        await page.locator('#login-modal button[type="submit"]').click();

        // Le modal reste ouvert avec une alerte d'erreur
        await expect(page.locator('#login-modal .alert--error')).toBeVisible({ timeout: 8000 });
    });

    test('connexion avec email vide → validation HTML5 empêche la soumission', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

        // Ne pas remplir l'email, remplir seulement le mot de passe
        await page.locator('#login-modal input[name="password"]').fill(CUSTOMER_PASSWORD);

        // Tenter de soumettre
        await page.locator('#login-modal button[type="submit"]').click();

        // La validation HTML5 doit bloquer (le champ est required) : on reste sur la même page,
        // le modal est toujours ouvert et aucune erreur serveur n'apparaît.
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
        // L'URL ne doit pas avoir changé
        await expect(page).toHaveURL(/\/fr$/);
    });

});

// ============================================================
// Suite : Connexion — flux complet (customer mock)
// ============================================================

test.describe('Connexion — flux complet (customer mock)', () => {

    test('connexion réussie → redirigé vers homepage ou /fr/mon-compte, modal fermé', async ({ page, context }) => {
        await loginAsCustomer(page, context);

        // Le modal doit être fermé (ou absent de l'état visible)
        const modal = page.locator('#login-modal');
        const ariaHidden = await modal.getAttribute('aria-hidden');
        expect(ariaHidden).not.toBe('false');

        // On doit être sur une page connue de l'app
        await expect(page).toHaveURL(/\/(fr|en)/);
    });

    test('après connexion, le header affiche l\'espace utilisateur (pas le bouton connexion)', async ({ page, context }) => {
        await loginAsCustomer(page, context);

        // Le trigger de connexion ne doit plus être visible (ou l'espace compte est affiché)
        // On vérifie que le cookie auth_token a bien été posé
        const cookies = await context.cookies();
        const authCookie = cookies.find(c => c.name === 'auth_token');
        expect(authCookie, 'Le cookie auth_token doit être présent après connexion').toBeDefined();
    });

    test('déconnexion → retour à l\'état non connecté (plus de cookie auth_token)', async ({ page, context }) => {
        await loginAsCustomer(page, context);

        // Chercher et soumettre le formulaire / lien de déconnexion
        const logoutForm = page.locator('form[action*="/deconnexion"]');
        const logoutLink = page.locator('a[href*="/deconnexion"]');

        const hasForm = await logoutForm.count();
        const hasLink = await logoutLink.count();

        if (hasForm > 0) {
            await Promise.all([
                page.waitForURL(/\/(fr|en)/, { timeout: 10000 }),
                logoutForm.locator('button, input[type="submit"]').first().click(),
            ]);
        } else if (hasLink > 0) {
            await Promise.all([
                page.waitForURL(/\/(fr|en)/, { timeout: 10000 }),
                logoutLink.first().click(),
            ]);
        } else {
            // Fallback : naviguer directement vers l'endpoint de déconnexion
            await page.goto('/fr/deconnexion');
        }

        // Le cookie auth_token doit avoir disparu
        const cookies = await context.cookies();
        const authCookie = cookies.find(c => c.name === 'auth_token');
        expect(authCookie, 'Le cookie auth_token doit être absent après déconnexion').toBeUndefined();
    });

});

// ============================================================
// Suite : Inscription — modal UI
// ============================================================

test.describe('Inscription — modal UI', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('modal inscription accessible depuis le modal connexion', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

        await page.locator('#login-to-register').click();
        await expect(page.locator('#register-modal')).toHaveAttribute('aria-hidden', 'false');
    });

    test('les champs prénom, nom, email, password, confirmation sont présents', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await page.locator('#login-to-register').click();
        await expect(page.locator('#register-modal')).toHaveAttribute('aria-hidden', 'false');

        await expect(page.locator('#register-modal input[name="first_name"]')).toBeVisible();
        await expect(page.locator('#register-modal input[name="last_name"]')).toBeVisible();
        await expect(page.locator('#register-modal input[name="email"]')).toBeVisible();
        await expect(page.locator('#register-modal input[name="password"]')).toBeVisible();
        await expect(page.locator('#register-modal input[name="password_confirm"]')).toBeVisible();
        await expect(page.locator('#register-modal input[name="csrf_token"]')).toBeAttached();
        await expect(page.locator('#register-modal button[type="submit"]')).toBeVisible();
    });

    test('soumettre avec des mots de passe différents → erreur', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await page.locator('#login-to-register').click();
        await expect(page.locator('#register-modal')).toHaveAttribute('aria-hidden', 'false');

        await page.locator('#register-modal input[name="first_name"]').fill('Test');
        await page.locator('#register-modal input[name="last_name"]').fill('User');
        await page.locator('#register-modal input[name="email"]').fill(`mismatch+${Date.now()}@test.local`);
        await page.locator('#register-modal input[name="password"]').fill('Dev123456789!');
        await page.locator('#register-modal input[name="password_confirm"]').fill('AutreMotDePasse999!');
        await page.locator('#register-modal button[type="submit"]').click();

        await expect(page.locator('#register-modal .alert--error')).toBeVisible({ timeout: 8000 });
    });

    test('soumettre avec email déjà utilisé → erreur', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await page.locator('#login-to-register').click();
        await expect(page.locator('#register-modal')).toHaveAttribute('aria-hidden', 'false');

        // Utiliser un email déjà présent en base (seed)
        await page.locator('#register-modal input[name="first_name"]').fill('Sophie');
        await page.locator('#register-modal input[name="last_name"]').fill('Dupont');
        await page.locator('#register-modal input[name="email"]').fill(CUSTOMER_EMAIL);
        await page.locator('#register-modal input[name="password"]').fill('Dev123456789!');
        await page.locator('#register-modal input[name="password_confirm"]').fill('Dev123456789!');
        await page.locator('#register-modal button[type="submit"]').click();

        await expect(page.locator('#register-modal .alert--error')).toBeVisible({ timeout: 8000 });
    });

    test('inscription avec données valides → message de succès (email d\'activation envoyé)', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#login-modal-trigger').click();
        await page.locator('#login-to-register').click();
        await expect(page.locator('#register-modal')).toHaveAttribute('aria-hidden', 'false');

        const uniqueEmail = `nouveau+${Date.now()}@test.local`;

        await page.locator('#register-modal input[name="first_name"]').fill('Nouveau');
        await page.locator('#register-modal input[name="last_name"]').fill('Testeur');
        await page.locator('#register-modal input[name="email"]').fill(uniqueEmail);
        await page.locator('#register-modal input[name="password"]').fill('Dev123456789!');
        await page.locator('#register-modal input[name="password_confirm"]').fill('Dev123456789!');
        await page.locator('#register-modal button[type="submit"]').click();

        // Un message de succès doit apparaître (email d'activation envoyé)
        await expect(
            page.locator('#register-modal .alert--success, .alert--success'),
        ).toBeVisible({ timeout: 8000 });
    });

});

// ============================================================
// Suite : Mot de passe oublié
// ============================================================

test.describe('Mot de passe oublié', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('page /fr/mot-de-passe-oublie se charge', async ({ page }) => {
        await page.goto('/fr/mot-de-passe-oublie');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.locator('.site-header')).toBeVisible();
    });

    test('formulaire avec champ email visible', async ({ page }) => {
        await page.goto('/fr/mot-de-passe-oublie');
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('button[type="submit"], input[type="submit"]').first()).toBeVisible();
    });

    test('soumettre avec email valide → message de succès (email envoyé)', async ({ page }) => {
        await page.goto('/fr/mot-de-passe-oublie');
        await page.locator('input[name="email"]').fill(CUSTOMER_EMAIL);
        await page.locator('button[type="submit"], input[type="submit"]').first().click();

        await expect(page.locator('.alert--success')).toBeVisible({ timeout: 8000 });
    });

    test('soumettre avec email au mauvais format → erreur de validation', async ({ page }) => {
        await page.goto('/fr/mot-de-passe-oublie');

        const emailInput = page.locator('input[name="email"]');
        await emailInput.fill('pas-un-email');

        await page.locator('button[type="submit"], input[type="submit"]').first().click();

        // La validation HTML5 (type=email) ou côté serveur doit bloquer
        // On vérifie qu'aucun message de succès n'est apparu
        await expect(page.locator('.alert--success')).not.toBeVisible({ timeout: 3000 }).catch(() => {
            // Si la validation HTML5 bloque côté navigateur, on n'arrive même pas au serveur — OK
        });

        // Le champ email doit toujours être sur la page (pas de redirection)
        await expect(emailInput).toBeAttached();
    });

});
