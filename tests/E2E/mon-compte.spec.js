// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

// ============================================================
// Helpers
// ============================================================

const DOMAIN = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;

/**
 * Authentifie Sophie (client.verifie@dev.local) via le modal de connexion.
 * Pose le cookie age_verified=1 et le device_token de confiance avant toute navigation,
 * ce qui permet au MFA de passer sans challenge OTP.
 *
 * @param {import('@playwright/test').Page}           page
 * @param {import('@playwright/test').BrowserContext} context
 */
async function loginAsCustomer(page, context) {
    // 1. Cookies préalables : age gate + appareil de confiance
    await context.addCookies([
        {
            name: 'age_verified',
            value: '1',
            domain: DOMAIN,
            path: '/',
            httpOnly: true,
            sameSite: 'Lax',
            expires: Math.floor(Date.now() / 1000) + 3600,
        },
        {
            name: 'device_token',
            value: 'device-uuid-sophie-macbook',
            domain: DOMAIN,
            path: '/',
            httpOnly: true,
            sameSite: 'Lax',
            expires: Math.floor(Date.now() / 1000) + 3600,
        },
    ]);

    // 2. Charger la homepage
    await page.goto('/fr');

    // 3. Ouvrir le modal de connexion
    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

    // 4. Remplir les champs
    await page.locator('#login-modal-email').fill('client.verifie@dev.local');
    await page.locator('#login-modal-password').fill('Dev123456789!');

    // 5. Soumettre et attendre la navigation (modal ferme ou redirect vers mon-compte)
    await Promise.all([
        page.waitForURL(/.+/, { timeout: 15000 }),
        page.locator('form[action*="/connexion"] button[type="submit"]').click(),
    ]);
}

// ============================================================
// Suite : Accès espace client — non connecté
// ============================================================

test.describe('Accès espace client — non connecté', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('/fr/mon-compte redirige vers homepage avec modal login ouvert', async ({ page }) => {
        await page.goto('/fr/mon-compte');

        // Redirection vers /?login=1
        await expect(page).toHaveURL(/\/fr\?login=1$/);

        // Le modal de connexion doit être ouvert
        await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');
    });

});

// ============================================================
// Suite : Dashboard mon-compte — connecté
// ============================================================

test.describe('Dashboard mon-compte — connecté', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte se charge avec header et main visibles', async ({ page }) => {
        await page.goto('/fr/mon-compte');

        await expect(page.locator('.site-header')).toBeVisible();
        await expect(page.locator('main.account-page')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);
        await expect(page).not.toHaveURL(/\?login=1/);
    });

    test('la page affiche le prénom ou un élément de bienvenue', async ({ page }) => {
        await page.goto('/fr/mon-compte');

        // Le dashboard contient soit .account-header__name (prénom) soit .account-header__title (bienvenue)
        const headerTitle = page.locator('.account-header__title');
        await expect(headerTitle).toBeVisible();

        // Sophie Durand doit apparaître dans .account-header__name
        const nameEl = page.locator('.account-header__name');
        const count = await nameEl.count();
        if (count > 0) {
            await expect(nameEl).toContainText('Sophie');
        }
    });

    test('les liens de navigation espace client sont présents', async ({ page }) => {
        await page.goto('/fr/mon-compte');

        const nav = page.locator('.account-nav');
        await expect(nav).toBeVisible();

        // Liens obligatoires pour un compte particulier
        await expect(nav.locator('a[href*="/mon-compte/commandes"]')).toBeVisible();
        await expect(nav.locator('a[href*="/mon-compte/profil"]')).toBeVisible();
        await expect(nav.locator('a[href*="/mon-compte/adresses"]')).toBeVisible();
        await expect(nav.locator('a[href*="/mon-compte/securite"]')).toBeVisible();
    });

    test('les cards de raccourci (commandes, adresses, favoris) sont présentes', async ({ page }) => {
        await page.goto('/fr/mon-compte');

        const dashboard = page.locator('.account-dashboard');
        await expect(dashboard).toBeVisible();

        // Au moins une account-card doit être présente
        const cards = dashboard.locator('.account-card');
        expect(await cards.count()).toBeGreaterThan(0);
    });

});

// ============================================================
// Suite : Profil
// ============================================================

test.describe('Profil', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte/profil se charge', async ({ page }) => {
        await page.goto('/fr/mon-compte/profil');

        await expect(page.locator('main.account-page')).toBeVisible();
        await expect(page).not.toHaveURL(/\?login=1/);
    });

    test('le formulaire de profil contient les champs firstname, lastname et newsletter', async ({ page }) => {
        await page.goto('/fr/mon-compte/profil');

        // Champs individuels présents dans le formulaire principal
        await expect(page.locator('#firstname')).toBeVisible();
        await expect(page.locator('#lastname')).toBeVisible();

        // Checkbox newsletter présente
        await expect(page.locator('input[name="newsletter"]')).toBeAttached();
    });

    test('modifier le prénom et soumettre affiche un message de succès', async ({ page }) => {
        await page.goto('/fr/mon-compte/profil');

        // Vider + remplir le champ prénom
        const firstnameInput = page.locator('#firstname');
        await firstnameInput.fill('Sophie');

        // Soumettre
        await Promise.all([
            page.waitForURL(/.+/, { timeout: 15000 }),
            page.locator('.account-form button[type="submit"]').first().click(),
        ]);

        // Message de succès
        await expect(page.locator('.alert--success')).toBeVisible();
    });

});

// ============================================================
// Suite : Commandes
// ============================================================

test.describe('Commandes', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte/commandes se charge', async ({ page }) => {
        await page.goto('/fr/mon-compte/commandes');

        await expect(page.locator('main.account-page')).toBeVisible();
        await expect(page).not.toHaveURL(/\?login=1/);
    });

    test('la page affiche les commandes ou le message "aucune commande"', async ({ page }) => {
        await page.goto('/fr/mon-compte/commandes');

        const hasTable = await page.locator('.account-table').count();
        const hasEmpty = await page.locator('.account-empty').count();

        // L'un ou l'autre doit être présent
        expect(hasTable + hasEmpty).toBeGreaterThan(0);
    });

    test('Sophie a au moins une commande listée', async ({ page }) => {
        // Sophie a des commandes seed (CBV-2026-000001…)
        await page.goto('/fr/mon-compte/commandes');

        await expect(page.locator('.account-table')).toBeVisible();
        const rows = page.locator('.account-table tbody tr');
        expect(await rows.count()).toBeGreaterThan(0);
    });

});

// ============================================================
// Suite : Adresses
// ============================================================

test.describe('Adresses', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte/adresses se charge', async ({ page }) => {
        await page.goto('/fr/mon-compte/adresses');

        await expect(page.locator('main.account-page')).toBeVisible();
        await expect(page).not.toHaveURL(/\?login=1/);
    });

    test('le bouton pour ajouter une adresse est présent', async ({ page }) => {
        await page.goto('/fr/mon-compte/adresses');

        // Bouton toggle pour afficher le formulaire d'ajout
        await expect(page.locator('.js-address-add-toggle').first()).toBeVisible();
    });

    test('Sophie a des adresses enregistrées', async ({ page }) => {
        // Sophie a billing + delivery dans le seed
        await page.goto('/fr/mon-compte/adresses');

        const addressCards = page.locator('.account-address-card');
        expect(await addressCards.count()).toBeGreaterThan(0);
    });

    test('cliquer sur "Ajouter" révèle le formulaire d\'ajout d\'adresse', async ({ page }) => {
        await page.goto('/fr/mon-compte/adresses');

        const addBtn = page.locator('.js-address-add-toggle').first();
        await addBtn.click();

        // Le formulaire caché doit devenir visible
        await expect(page.locator('#address-add-form')).toBeVisible();
        await expect(page.locator('#address-add-form form')).toBeVisible();
    });

});

// ============================================================
// Suite : Sécurité
// ============================================================

test.describe('Sécurité', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte/securite se charge', async ({ page }) => {
        await page.goto('/fr/mon-compte/securite');

        await expect(page.locator('main.account-page')).toBeVisible();
        await expect(page).not.toHaveURL(/\?login=1/);
    });

    test('le formulaire de changement de mot de passe est visible', async ({ page }) => {
        await page.goto('/fr/mon-compte/securite');

        // Sophie a un mot de passe (pas un compte Google) — le formulaire doit être visible
        await expect(page.locator('#current_password')).toBeVisible();
        await expect(page.locator('#new_password')).toBeVisible();
        await expect(page.locator('#new_password_confirm')).toBeVisible();
    });

    test('soumettre avec un mauvais mot de passe actuel affiche une erreur', async ({ page }) => {
        await page.goto('/fr/mon-compte/securite');

        // Remplir avec un mot de passe actuel invalide
        await page.locator('#current_password').fill('MauvaisMotDePasse999!');
        await page.locator('#new_password').fill('NouveauMotDePasse12!');
        await page.locator('#new_password_confirm').fill('NouveauMotDePasse12!');

        await Promise.all([
            page.waitForURL(/.+/, { timeout: 15000 }),
            page.locator('form[action*="/securite/mot-de-passe"] button[type="submit"]').click(),
        ]);

        // Un message d'erreur doit apparaître (erreur password ou rate_limit)
        const errorAlert = page.locator('.alert--error, .form-error');
        await expect(errorAlert.first()).toBeVisible();
    });

    test('la section appareils de confiance est présente', async ({ page }) => {
        await page.goto('/fr/mon-compte/securite');

        await expect(page.locator('#appareils')).toBeVisible();
    });

});

// ============================================================
// Suite : RGPD — export des données
// ============================================================

test.describe('RGPD — export des données', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte/export se charge', async ({ page }) => {
        await page.goto('/fr/mon-compte/export');

        await expect(page.locator('main.account-page')).toBeVisible();
        await expect(page).not.toHaveURL(/\?login=1/);
    });

    test('la page contient le bouton d\'export des données', async ({ page }) => {
        await page.goto('/fr/mon-compte/export');

        // Bouton de demande d'export (POST /mon-compte/export/envoyer)
        const exportBtn = page.locator('form[action*="/export/envoyer"] button[type="submit"]');
        await expect(exportBtn).toBeVisible();
    });

    test('la liste des données exportées est affichée', async ({ page }) => {
        await page.goto('/fr/mon-compte/export');

        // La page liste ce qui est inclus dans l'export
        await expect(page.locator('.account-export__list')).toBeVisible();
        const items = page.locator('.account-export__list li');
        expect(await items.count()).toBeGreaterThan(0);
    });

    test('le lien vers l\'export est présent dans la navigation', async ({ page }) => {
        await page.goto('/fr/mon-compte');

        await expect(page.locator('.account-nav .account-nav__link--export')).toBeVisible();
    });

});

// ============================================================
// Suite : Newsletter — préférence dans le profil
// ============================================================

test.describe('Newsletter — préférence dans le profil', () => {

    test.beforeEach(async ({ page, context }) => {
        await loginAsCustomer(page, context);
    });

    test('/fr/mon-compte/profil affiche l\'option d\'abonnement newsletter', async ({ page }) => {
        await page.goto('/fr/mon-compte/profil');

        // La checkbox newsletter est présente dans le formulaire profil
        await expect(page.locator('input[name="newsletter"]')).toBeAttached();
        await expect(page.locator('label:has(input[name="newsletter"])')).toBeVisible();
    });

    test('le toggle newsletter est une checkbox fonctionnelle', async ({ page }) => {
        await page.goto('/fr/mon-compte/profil');

        const checkbox = page.locator('input[name="newsletter"]');
        const initialState = await checkbox.isChecked();

        // Basculer l'état
        await checkbox.setChecked(!initialState);
        expect(await checkbox.isChecked()).toBe(!initialState);
    });

});
