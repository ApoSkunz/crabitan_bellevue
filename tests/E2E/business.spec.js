// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

// ============================================================
// Scénarios métiers — Catalogue & Vins
// ============================================================

test.describe('Catalogue — navigation et filtres', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la page catalogue liste des vins', async ({ page }) => {
        await page.goto('/fr/vins');
        const cards = page.locator('.wine-card');
        await expect(cards.first()).toBeVisible();
        expect(await cards.count()).toBeGreaterThan(0);
    });

    test('le filtre couleur réduit la liste', async ({ page }) => {
        await page.goto('/fr/vins');
        const totalBefore = await page.locator('.wine-card').count();
        // Le filtre est un formulaire GET — sélectionner la couleur puis soumettre
        await page.locator('input[name="color"][value="red"]').check();
        await page.locator('.wines-filters__submit').click();
        await page.waitForLoadState('load');
        const totalAfter = await page.locator('.wine-card').count();
        expect(totalAfter).toBeLessThanOrEqual(totalBefore);
    });

    test('la page collection liste des vins avec images', async ({ page }) => {
        await page.goto('/fr/vins/collection');
        const cards = page.locator('.wine-card');
        await expect(cards.first()).toBeVisible();
        const img = cards.first().locator('img');
        await expect(img).toHaveAttribute('src', /.+/);
    });

    test('le détail d\'un vin est accessible depuis le catalogue', async ({ page }) => {
        await page.goto('/fr/vins');
        const firstLink = page.locator('.wine-card a, a.wine-card').first();
        const href = await firstLink.getAttribute('href');
        expect(href).toMatch(/\/fr\/vins\/.+/);
        await firstLink.click();
        await expect(page.locator('main')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

});

// ============================================================
// Scénarios métiers — Panier
// ============================================================

test.describe('Panier — ajout et consultation', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('le panier est accessible', async ({ page }) => {
        await page.goto('/fr/panier');
        await expect(page.locator('main')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

    test('ajouter un vin au panier incrémente le compteur', async ({ page, context }) => {
        await page.goto('/fr/vins');
        // Non connecté : panier stocké en cookie cb-cart — lu via context.cookies()
        async function readCartCount() {
            const cookies = await context.cookies();
            const cartCookie = cookies.find((c) => c.name === 'cb-cart');
            if (!cartCookie) return 0;
            try {
                const cart = JSON.parse(decodeURIComponent(cartCookie.value));
                return /** @type {{qty?:number}[]} */ (cart).reduce((s, i) => s + (i.qty || 1), 0);
            } catch { return 0; }
        }
        const countBefore = await readCartCount();
        // Ouvrir la modale panier
        await page.locator('.js-add-to-cart').first().click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'false');
        // Soumettre (non connecté → cookie cb-cart, ferme la modale)
        await page.locator('#cart-modal-form button[type="submit"]').click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'true');
        const countAfter = await readCartCount();
        expect(countAfter).toBeGreaterThan(countBefore);
    });

});

// ============================================================
// Scénarios métiers — Support / FAQ
// ============================================================

test.describe('Support — accordéon FAQ', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la page support affiche les questions FAQ', async ({ page }) => {
        await page.goto('/fr/support');
        const triggers = page.locator('.faq-accordion__trigger');
        expect(await triggers.count()).toBeGreaterThanOrEqual(1);
    });

    test('un clic sur une question ouvre le panneau', async ({ page }) => {
        await page.goto('/fr/support');
        const firstBtn = page.locator('.faq-accordion__trigger').first();
        await expect(firstBtn).toHaveAttribute('aria-expanded', 'false');
        await firstBtn.click();
        await expect(firstBtn).toHaveAttribute('aria-expanded', 'true');
        const panelId = await firstBtn.getAttribute('aria-controls');
        await expect(page.locator(`#${panelId}`)).toBeVisible();
    });

    test('un deuxième clic referme le panneau', async ({ page }) => {
        await page.goto('/fr/support');
        const firstBtn = page.locator('.faq-accordion__trigger').first();
        await firstBtn.click();
        await firstBtn.click();
        await expect(firstBtn).toHaveAttribute('aria-expanded', 'false');
    });

});

// ============================================================
// Scénarios métiers — Mentions légales mode bare
// ============================================================

test.describe('Mentions légales — mode bare (cookie banner)', () => {

    test('?bare=1 affiche le contenu sans header ni footer', async ({ page }) => {
        await page.goto('/fr/mentions-legales?bare=1');
        await expect(page.locator('.bare-legal__bar')).toBeVisible();
        await expect(page.locator('.site-header')).toHaveCount(0);
        await expect(page.locator('.site-footer')).toHaveCount(0);
        await expect(page.locator('.legal-content')).toBeVisible();
    });

    test('le bouton fermer est présent en mode bare', async ({ page }) => {
        await page.goto('/fr/mentions-legales?bare=1');
        await expect(page.locator('.bare-legal__close')).toBeVisible();
    });

});

// ============================================================
// Scénarios métiers — Thème
// ============================================================

test.describe('Thème — toggle jour/nuit', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('le thème par défaut est light', async ({ page }) => {
        await page.goto('/fr');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    });

    test('le toggle bascule vers dark', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#theme-toggle').click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    });

    test('le thème est persisté en localStorage', async ({ page }) => {
        await page.goto('/fr');
        await page.locator('#theme-toggle').click();
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    });

});

// ============================================================
// Scénarios métiers — Contact
// ============================================================

test.describe('Contact — formulaire', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('le formulaire de contact est présent', async ({ page }) => {
        await page.goto('/fr/contact');
        await expect(page.locator('#contact-form')).toBeVisible();
    });

    test('soumettre le formulaire vide affiche un message d\'erreur de validation', async ({ page }) => {
        await page.goto('/fr/contact');
        await page.locator('#contact-submit').click();
        // Validation JS côté client — #contact-feedback visible avec classe error
        const feedback = page.locator('#contact-feedback');
        await expect(feedback).toBeVisible();
        await expect(feedback).toHaveClass(/contact-form__feedback--error/);
    });

});
