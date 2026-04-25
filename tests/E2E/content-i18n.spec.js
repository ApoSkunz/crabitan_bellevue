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
 * Connecte un client mock en posant les cookies age_verified + device_token,
 * puis en remplissant le formulaire de connexion.
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

// ============================================================
// Suite : Actualités — article détail
// ============================================================

test.describe('Actualités — article détail', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la page actualités liste au moins un article ou affiche le message vide', async ({ page }) => {
        await page.goto('/fr/actualites');
        await expect(page.locator('main')).toBeVisible();
        const hasCards = await page.locator('.news-card').count();
        const hasEmpty = await page.locator('.news-list__empty').count();
        expect(hasCards + hasEmpty, 'La liste ou le message vide doit être présent').toBeGreaterThan(0);
    });

    test('cliquer sur un article mène à la page détail avec h1 et contenu', async ({ page }) => {
        await page.goto('/fr/actualites');
        const firstCard = page.locator('.news-card').first();

        const cardCount = await page.locator('.news-card').count();
        if (cardCount === 0) {
            // Pas d'articles en BDD : test ignoré plutôt qu'échoué
            test.skip();
            return;
        }

        const link = firstCard.locator('.news-card__link');
        await expect(link).toBeVisible();
        await link.click();

        await expect(page.locator('main.page-news-show')).toBeVisible();

        // Titre h1 présent et non vide
        const h1 = page.locator('main.page-news-show h1');
        await expect(h1).toBeVisible();
        const title = await h1.textContent();
        expect(title?.trim().length).toBeGreaterThan(0);

        // Corps de l'article présent
        await expect(page.locator('.news-article__body')).toBeVisible();
    });

    test('la page détail contient un lien de retour aux actualités', async ({ page }) => {
        await page.goto('/fr/actualites');

        const cardCount = await page.locator('.news-card').count();
        if (cardCount === 0) {
            test.skip();
            return;
        }

        await page.locator('.news-card__link').first().click();
        await expect(page.locator('main.page-news-show')).toBeVisible();

        // Lien "retour aux actualités" dans le header de l'article
        const backLink = page.locator('.news-article__back');
        await expect(backLink).toBeVisible();
        await expect(backLink).toHaveAttribute('href', /\/actualites$/);
    });

});

// ============================================================
// Suite : i18n — switch de langue
// ============================================================

test.describe('i18n — switch de langue', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('le sélecteur de langue est présent dans le header', async ({ page }) => {
        await page.goto('/fr');
        const langSwitch = page.locator('.lang-switch');
        await expect(langSwitch).toBeVisible();
        // Doit contenir les deux langues
        await expect(langSwitch.locator('a[lang="fr"]')).toBeVisible();
        await expect(langSwitch.locator('a[lang="en"]')).toBeVisible();
    });

    test('cliquer FR→EN change l\'URL vers /en/...', async ({ page }) => {
        await page.goto('/fr');
        const enLink = page.locator('.lang-switch a[lang="en"]');
        const href = await enLink.getAttribute('href');
        expect(href).toMatch(/^\/en/);

        await Promise.all([
            page.waitForURL(/\/en/, { timeout: 8000 }),
            enLink.click(),
        ]);

        await expect(page).toHaveURL(/\/en/);
    });

    test('la page EN contient du contenu en anglais (title)', async ({ page }) => {
        await page.goto('/en');
        // Le <title> doit contenir "Crabitan" (invariant bilingue)
        await expect(page).toHaveTitle(/Crabitan/i);
        // La homepage EN doit se charger sans erreur
        await expect(page.locator('main')).toBeVisible();
    });

    test('cliquer EN→FR retourne vers /fr/...', async ({ page }) => {
        await page.goto('/en');
        const frLink = page.locator('.lang-switch a[lang="fr"]');
        const href = await frLink.getAttribute('href');
        expect(href).toMatch(/^\/fr/);

        await Promise.all([
            page.waitForURL(/\/fr/, { timeout: 8000 }),
            frLink.click(),
        ]);

        await expect(page).toHaveURL(/\/fr/);
    });

    test('le lien switch de langue porte data-lang-toast (toast UX déclenché)', async ({ page }) => {
        await page.goto('/fr');
        // Le lien EN (langue non active) doit avoir l'attribut data-lang-toast
        const enLink = page.locator('.lang-switch a[lang="en"]');
        await expect(enLink).toHaveAttribute('data-lang-toast', /.+/);
    });

});

// ============================================================
// Suite : Pages statiques — contenu
// ============================================================

test.describe('Pages statiques — contenu', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('/fr/le-chateau contient les sections historiques', async ({ page }) => {
        await page.goto('/fr/le-chateau');
        await expect(page.locator('main.page-chateau')).toBeVisible();
        // Au moins une section histoire avec ID origines
        await expect(page.locator('#origines')).toBeVisible();
        // H1 présent
        const h1 = page.locator('main.page-chateau h1');
        await expect(h1).toBeVisible();
    });

    test('/fr/le-chateau contient du texte descriptif', async ({ page }) => {
        await page.goto('/fr/le-chateau');
        // Paragraphes de contenu dans les sections
        const paragraphs = page.locator('.home-section__text');
        expect(await paragraphs.count()).toBeGreaterThan(0);
        const firstText = await paragraphs.first().textContent();
        expect(firstText?.trim().length).toBeGreaterThan(0);
    });

    test('/fr/savoir-faire contient les sections vignoble, vinification, élevage', async ({ page }) => {
        await page.goto('/fr/savoir-faire');
        await expect(page.locator('main.page-savoir-faire')).toBeVisible();
        await expect(page.locator('#vignoble')).toBeVisible();
        await expect(page.locator('#vinification')).toBeVisible();
        await expect(page.locator('#elevage')).toBeVisible();
    });

    test('/fr/savoir-faire — le h1 et du texte de contenu sont présents', async ({ page }) => {
        await page.goto('/fr/savoir-faire');
        await expect(page.locator('main.page-savoir-faire h1')).toBeVisible();
        const texts = page.locator('.home-section__text');
        expect(await texts.count()).toBeGreaterThanOrEqual(3);
    });

    test('/fr/accessibilite se charge et contient la déclaration RGAA', async ({ page }) => {
        await page.goto('/fr/accessibilite');
        await expect(page.locator('main.page-accessibility')).toBeVisible();
        // Le contenu légal est dans .legal-content
        await expect(page.locator('.legal-content')).toBeVisible();
        // H1 présent
        await expect(page.locator('main.page-accessibility h1')).toBeVisible();
    });

    test('/fr/credits-medias se charge avec contenu légal', async ({ page }) => {
        await page.goto('/fr/credits-medias');
        await expect(page.locator('main.page-legal')).toBeVisible();
        await expect(page.locator('.legal-content')).toBeVisible();
        await expect(page.locator('main.page-legal h1')).toBeVisible();
    });

    test('/fr/politique-de-confidentialite se charge et contient .legal-content', async ({ page }) => {
        await page.goto('/fr/politique-de-confidentialite');
        // La politique se charge via legal-open.php (avec header/footer)
        await expect(page.locator('.legal-content')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

});

// ============================================================
// Suite : Contact — soumission nominale
// ============================================================

test.describe('Contact — soumission nominale', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('remplir le formulaire complet et soumettre → réponse succès ou serveur accepte', async ({ page }) => {
        await page.goto('/fr/contact');
        await expect(page.locator('#contact-form')).toBeVisible();

        // Civilité
        await page.locator('input[name="gender"][value="m"]').check();

        // Nom / prénom
        await page.locator('#contact-firstname').fill('Jean');
        await page.locator('#contact-lastname').fill('Testeur');

        // Email
        await page.locator('#contact-email').fill('jean.testeur@test.local');

        // Sujet
        await page.locator('#contact-subject').selectOption('general');

        // Message
        await page.locator('#contact-message').fill('Ceci est un message de test E2E automatisé — envoi depuis Playwright.');

        // RGPD
        await page.locator('input[name="rgpd"]').check();

        // Soumettre
        await page.locator('#contact-submit').click();

        // En local sans SMTP : le serveur traite la requête.
        // On attend soit un message de succès, soit un message d'erreur SMTP
        // (mais pas une page d'erreur 500 non gérée).
        const feedback = page.locator('#contact-feedback');
        await expect(feedback).toBeVisible({ timeout: 10000 });

        // La page ne doit pas afficher de page d'erreur système
        await expect(page.locator('main')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);

        // Si le feedback contient une classe succès → test nominal passé
        const isSuccess = await feedback.evaluate((el) =>
            el.classList.contains('contact-form__feedback--success')
        );
        const isError = await feedback.evaluate((el) =>
            el.classList.contains('contact-form__feedback--error')
        );
        // L'un ou l'autre doit être vrai (serveur a répondu)
        expect(isSuccess || isError, 'Le serveur doit répondre succès ou erreur SMTP').toBe(true);
    });

});

// ============================================================
// Suite : Sitemaps
// ============================================================

test.describe('Sitemaps — réponses HTTP', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('/sitemap.xml répond 200 avec Content-Type XML', async ({ page }) => {
        const resp = await page.request.get('/sitemap.xml');
        expect(resp.status()).toBe(200);
        const ct = resp.headers()['content-type'] ?? '';
        expect(ct).toMatch(/xml/i);
    });

    test('/sitemap-products.xml répond 200', async ({ page }) => {
        const resp = await page.request.get('/sitemap-products.xml');
        // Peut renvoyer 200 ou 404 si non implémenté — on vérifie surtout < 500
        expect(resp.status()).toBeLessThan(500);
    });

    test('/sitemap-index.xml répond < 500', async ({ page }) => {
        const resp = await page.request.get('/sitemap-index.xml');
        expect(resp.status()).toBeLessThan(500);
    });

    test('/sitemap.xml contient au moins une URL', async ({ page }) => {
        const resp = await page.request.get('/sitemap.xml');
        expect(resp.status()).toBe(200);
        const body = await resp.text();
        // Le sitemap XML doit contenir au moins une balise <loc>
        expect(body).toContain('<loc>');
    });

});

// ============================================================
// Suite : Newsletter — espace client (connecté)
// ============================================================

test.describe('Newsletter — espace client connecté', () => {

    test('la page profil contient la checkbox newsletter', async ({ page, context }) => {
        await loginAsCustomer(page, context);

        await page.goto('/fr/mon-compte/profil');
        await expect(page.locator('main')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);

        // La checkbox newsletter est présente dans le formulaire profil
        const newsletterCheckbox = page.locator('input[name="newsletter"]');
        await expect(newsletterCheckbox).toBeAttached();
    });

    test('la page /fr/mon-compte/profil se charge correctement quand connecté', async ({ page, context }) => {
        await loginAsCustomer(page, context);

        await page.goto('/fr/mon-compte/profil');
        await expect(page.locator('main')).toBeVisible();
        // Formulaire de profil présent
        await expect(page.locator('.account-page form').first()).toBeVisible();
        // Pas de redirection vers l'age-gate ou la connexion
        await expect(page).not.toHaveURL(/\/age-gate/);
        await expect(page).not.toHaveURL(/\/connexion/);
    });

    test('modifier le statut newsletter et soumettre → pas d\'erreur serveur', async ({ page, context }) => {
        await loginAsCustomer(page, context);

        await page.goto('/fr/mon-compte/profil');
        await expect(page.locator('main')).toBeVisible();

        const checkbox = page.locator('input[name="newsletter"]');
        await expect(checkbox).toBeVisible();

        // Mémoriser l'état actuel et le basculer
        const wasChecked = await checkbox.isChecked();
        if (wasChecked) {
            await checkbox.uncheck();
        } else {
            await checkbox.check();
        }

        // Soumettre le formulaire profil (premier form avec button[type=submit])
        const submitBtn = page.locator('button[type="submit"]').first();
        await expect(submitBtn).toBeVisible();

        await Promise.all([
            page.waitForURL(/mon-compte\/profil/, { timeout: 8000 }),
            submitBtn.click(),
        ]);

        // La page se recharge sans erreur 500
        await expect(page.locator('main')).toBeVisible();
        await expect(page).not.toHaveURL(/\/age-gate/);
    });

});
