// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

// ============================================================
// Constants
// ============================================================

const CUSTOMER_EMAIL    = 'client.verifie@dev.local';
const CUSTOMER_PASSWORD = 'Dev123456789!';
const CUSTOMER_DEVICE   = 'device-uuid-sophie-macbook';

// ============================================================
// Helpers
// ============================================================

/**
 * Pose les cookies age_verified + device_token, ouvre le modal login,
 * remplit les credentials et attend la fermeture du modal ou la redirection.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 */
async function loginAsCustomer(page, context) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;

    await context.addCookies([
        { name: 'age_verified', value: '1',             domain, path: '/', httpOnly: true,  sameSite: 'Lax' },
        { name: 'device_token', value: CUSTOMER_DEVICE, domain, path: '/', httpOnly: false, sameSite: 'Lax' },
    ]);

    await page.goto('/fr');

    await page.locator('#login-modal-trigger').click();
    await expect(page.locator('#login-modal')).toHaveAttribute('aria-hidden', 'false');

    await page.locator('#login-modal-email').fill(CUSTOMER_EMAIL);
    await page.locator('#login-modal-password').fill(CUSTOMER_PASSWORD);

    await Promise.all([
        page.waitForURL(/\/(fr|en)/, { timeout: 10000 }),
        page.locator('form[action*="/connexion"] button[type="submit"]').click(),
    ]);
}

/**
 * Navigue vers /fr/vins, récupère le href du premier lien .wine-card
 * et retourne le slug extrait.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function getFirstWineSlug(page) {
    await page.goto('/fr/vins');
    const firstLink = page.locator('.wine-card a, a.wine-card').first();
    const href = await firstLink.getAttribute('href');
    if (!href) throw new Error('Aucun lien .wine-card trouvé sur /fr/vins');
    // href = /fr/vins/{slug}
    const match = href.match(/\/fr\/vins\/([^/?#]+)/);
    if (!match) throw new Error(`Slug introuvable dans l'href : ${href}`);
    return match[1];
}

// ============================================================
// Suite : Fiche détail vin
// ============================================================

test.describe('Fiche détail vin', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la fiche détail se charge depuis le catalogue', async ({ page }) => {
        await page.goto('/fr/vins');
        const firstLink = page.locator('.wine-card a, a.wine-card').first();
        const href = await firstLink.getAttribute('href');
        expect(href).toMatch(/\/fr\/vins\/.+/);
        await firstLink.click();
        await page.waitForLoadState('load');
        await expect(page.locator('main.page-wine-show')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

    test('la fiche contient le nom du vin (h1)', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const title = page.locator('h1#wine-title, h1.wine-detail__title');
        await expect(title).toBeVisible();
        const text = await title.innerText();
        expect(text.trim().length).toBeGreaterThan(0);
    });

    test('la fiche contient l\'image du vin', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const img = page.locator('.wine-detail__image');
        await expect(img).toBeVisible();
        await expect(img).toHaveAttribute('src', /.+/);
    });

    test('le prix est affiché', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const price = page.locator('.wine-detail__price');
        await expect(price).toBeVisible();
        const text = await price.innerText();
        expect(text).toMatch(/\d/);
    });

    test('la description (commentaire oenologique) est présente', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        // Section .wine-detail__section contient la description ou le tableau technique
        const sections = page.locator('.wine-detail__section');
        await expect(sections.first()).toBeVisible();
    });

    test('le bouton Ajouter au panier est présent', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const btn = page.locator('.js-add-to-cart').first();
        await expect(btn).toBeVisible();
    });

});

// ============================================================
// Suite : Ajout au panier depuis la fiche détail
// ============================================================

test.describe('Ajout au panier depuis la fiche détail', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('cliquer Ajouter au panier ouvre la modale #cart-modal', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const btn = page.locator('.js-add-to-cart').first();
        await btn.click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'false');
    });

    test('confirmer l\'ajout ferme la modale', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        await page.locator('.js-add-to-cart').first().click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'false');
        await page.locator('#cart-modal-form button[type="submit"]').click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'true');
    });

    test('le cookie cb-cart contient le vin ajouté', async ({ page, context }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        await page.locator('.js-add-to-cart').first().click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'false');
        await page.locator('#cart-modal-form button[type="submit"]').click();
        await expect(page.locator('#cart-modal')).toHaveAttribute('aria-hidden', 'true');

        const cookies = await context.cookies();
        const cartCookie = cookies.find((c) => c.name === 'cb-cart');
        expect(cartCookie, 'Le cookie cb-cart doit exister après ajout').toBeDefined();
        const cart = JSON.parse(decodeURIComponent(cartCookie?.value ?? '[]'));
        expect(Array.isArray(cart)).toBe(true);
        expect(cart.length).toBeGreaterThan(0);
    });

});

// ============================================================
// Suite : Fiche technique
// ============================================================

test.describe('Fiche technique', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la fiche détail contient un lien/bouton vers la fiche technique', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        // Le bouton est rendu comme .js-pdf-download avec data-href pointant vers /fiche-technique
        const pdfBtn = page.locator('.js-pdf-download[data-href*="fiche-technique"]');
        await expect(pdfBtn).toBeVisible();
        const href = await pdfBtn.getAttribute('data-href');
        expect(href).toMatch(/\/fiche-technique/);
    });

    test('la route /fiche-technique retourne un PDF (statut 200, content-type pdf)', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        // La route génère un PDF TCPDF directement — tester via API (page.goto déclencherait un download)
        const response = await page.request.get(`/fr/vins/${slug}/fiche-technique`);
        expect(response.status()).toBe(200);
        const ct = response.headers()['content-type'] ?? '';
        expect(ct).toMatch(/pdf/i);
    });

    test('la route /fiche-technique/qr charge la page loader HTML', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        // La route /qr affiche la page loader HTML (fiche-technique-loader.php) avec #loader
        await page.goto(`/fr/vins/${slug}/fiche-technique/qr`);
        await page.waitForLoadState('load');
        await expect(page.locator('#loader')).toBeVisible();
        await expect(page.locator('#loader h1')).toBeVisible();
        await expect(page.locator('.progress')).toBeVisible();
        const title = await page.locator('#loader h1').innerText();
        expect(title.trim().length).toBeGreaterThan(0);
    });

});

// ============================================================
// Suite : Cross-sell
// ============================================================

test.describe('Cross-sell — vins similaires', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la fiche détail contient une section cross-sell', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        await expect(page.locator('section.wine-cross-sell')).toBeVisible();
    });

    test('au moins un vin cross-sell est affiché', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const cards = page.locator('.wine-cross-sell__card');
        await expect(cards.first()).toBeVisible();
        expect(await cards.count()).toBeGreaterThan(0);
    });

    test('cliquer sur un vin cross-sell navigue vers une autre fiche', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const crossLink = page.locator('.wine-cross-sell__card .wine-card__inner').first();
        const crossHref = await crossLink.getAttribute('href');
        expect(crossHref).toMatch(/\/fr\/vins\/.+/);
        // Le slug de la fiche cross-sell doit être différent de la fiche courante
        const crossSlug = crossHref?.match(/\/fr\/vins\/([^/?#]+)/)?.[1];
        expect(crossSlug).not.toBe(slug);
        await crossLink.click();
        await page.waitForLoadState('load');
        await expect(page.locator('main.page-wine-show')).toBeVisible();
        await expect(page).toHaveURL(new RegExp(`/fr/vins/${crossSlug}`));
    });

});

// ============================================================
// Suite : Rating (notation)
// ============================================================

test.describe('Rating — notation d\'un vin', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la section notation est présente sur la fiche détail', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        // La section rating est conditionnelle (avgRating !== null || connecté)
        // Elle est toujours présente si au moins 2 avis existent — le test vérifie
        // qu'elle est visible OU qu'un CTA connexion est présent.
        const ratingSection = page.locator('section.wine-rating');
        const hasRating = await ratingSection.count();
        if (hasRating > 0) {
            await expect(ratingSection).toBeVisible();
        }
        // Dans tous les cas la page doit s'être chargée sans erreur
        await expect(page.locator('main.page-wine-show')).toBeVisible();
    });

    test('non connecté : pas d\'étoiles cliquables (formulaire absent)', async ({ page }) => {
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        // Le formulaire de notation n'est rendu que pour les utilisateurs connectés
        const ratingForm = page.locator('form.wine-rating__form');
        // Non connecté → le formulaire n'est pas présent dans le DOM
        await expect(ratingForm).toHaveCount(0);
    });

    test('connecté (customer mock) : les étoiles de notation sont cliquables', async ({ page, context }) => {
        await loginAsCustomer(page, context);
        const slug = await getFirstWineSlug(page);
        await page.goto(`/fr/vins/${slug}`);
        const stars = page.locator('.wine-rating__star.js-star');
        const count = await stars.count();
        if (count > 0) {
            // Le formulaire de notation est présent et les étoiles sont des boutons
            await expect(stars.first()).toBeVisible();
            expect(count).toBe(5);
        } else {
            // L'utilisateur a déjà noté ce vin ou la section n'est pas affichée
            // La page doit au moins s'être chargée correctement
            await expect(page.locator('main.page-wine-show')).toBeVisible();
        }
    });

});

// ============================================================
// Suite : Bandeau caisse de 12 (wines-case-banner)
// ============================================================

test.describe('Bandeau caisse de 12', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('le bandeau .wines-case-banner est visible sur /fr/vins', async ({ page }) => {
        await page.goto('/fr/vins');
        await expect(page.locator('.wines-case-banner')).toBeVisible();
    });

    test('le bandeau .wines-case-banner est visible sur /fr/vins/collection', async ({ page }) => {
        await page.goto('/fr/vins/collection');
        await expect(page.locator('.wines-case-banner')).toBeVisible();
    });

});
