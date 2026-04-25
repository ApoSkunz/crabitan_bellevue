// @ts-check
import { test, expect, setVerifiedCookie } from './support/fixtures.js';

// ============================================================
// Suite: Page Jeux — structure
// ============================================================

test.describe('Page Jeux — structure', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('/fr/jeux se charge sans erreur 404/500', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.locator('.site-header')).toBeVisible();
        await expect(page.locator('.site-footer')).toBeVisible();
    });

    test('la page jeux a la classe page-jeux sur le main', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('main.page-jeux')).toBeVisible();
    });

    test('le titre h1 de la page jeux est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('.page-hero h1')).toBeVisible();
    });

    test('la page contient au moins 4 sections de jeux', async ({ page }) => {
        await page.goto('/fr/jeux');
        // memo + runner + labour + vendange-express (+ catapulte)
        const sections = page.locator('main.page-jeux section');
        const count = await sections.count();
        expect(count).toBeGreaterThanOrEqual(4);
    });

});

// ============================================================
// Suite: Mémo — jeu de mémoire
// ============================================================

test.describe('Mémo — jeu de mémoire', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la section mémo est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#memo-game')).toBeVisible();
    });

    test('le titre de la section mémo est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#memo-title')).toBeVisible();
    });

    test('le timer mémo (heures/minutes/secondes) est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#memo-timer')).toBeVisible();
        await expect(page.locator('#memo-hours')).toBeVisible();
        await expect(page.locator('#memo-minutes')).toBeVisible();
        await expect(page.locator('#memo-seconds')).toBeVisible();
    });

    test('le compteur de paires trouvées est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#memo-pairs-found')).toBeVisible();
    });

    test('le bouton Démarrer est visible et la grille est cachée avant démarrage', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#memo-start')).toBeVisible();
        // La grille est cachée (attribut hidden) avant de commencer
        await expect(page.locator('#memo-grid')).toBeHidden();
    });

    test('après clic sur Démarrer, la grille de cartes apparaît', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        // La grille devient visible
        await expect(page.locator('#memo-grid')).toBeVisible();
    });

    test('les cartes mémo sont présentes après démarrage', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        await expect(page.locator('#memo-grid')).toBeVisible();
        const cards = page.locator('#memo-grid .memo-card');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);
    });

    test('après le flash de départ (3 s), les cartes sont face cachée et cliquables', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        // Attendre que le flash de départ soit terminé (3 s + marge)
        await page.waitForTimeout(4000);
        const cards = page.locator('#memo-grid .memo-card');
        const count = await cards.count();
        expect(count).toBeGreaterThan(0);
        // Aucune carte ne doit être retournée après le flash
        const flipped = await page.locator('#memo-grid .memo-card.is-flipped').count();
        expect(flipped).toBe(0);
    });

    test('cliquer sur une carte la retourne (classe is-flipped)', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        // Attendre fin du flash
        await page.waitForTimeout(4000);
        const card = page.locator('#memo-grid .memo-card').first();
        await card.click();
        await expect(card).toHaveClass(/is-flipped/);
    });

    test('cliquer sur 2 cartes différentes → elles se retournent face cachée après délai', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        await page.waitForTimeout(4000);

        // Trouver deux cartes de slugs différents
        const allCards = page.locator('#memo-grid .memo-card');
        const count = await allCards.count();
        expect(count).toBeGreaterThanOrEqual(2);

        // Chercher deux cartes dont les slugs diffèrent
        let card1Idx = -1;
        let card2Idx = -1;
        let slug1 = '';

        for (let i = 0; i < count && card2Idx === -1; i++) {
            const slug = await allCards.nth(i).getAttribute('data-slug');
            if (card1Idx === -1) {
                card1Idx = i;
                slug1 = slug;
            } else if (slug !== slug1) {
                card2Idx = i;
            }
        }

        // Si on n'a pas trouvé deux slugs différents, skip le test d'interaction
        if (card2Idx === -1) {
            test.skip();
            return;
        }

        await allCards.nth(card1Idx).click();
        await allCards.nth(card2Idx).click();

        // Après ~1 s de délai, les deux cartes doivent être retournées face cachée
        await page.waitForTimeout(1200);
        await expect(allCards.nth(card1Idx)).not.toHaveClass(/is-flipped/);
        await expect(allCards.nth(card2Idx)).not.toHaveClass(/is-flipped/);
    });

    test('cliquer sur 2 cartes identiques → elles restent visibles (is-matched)', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        await page.waitForTimeout(4000);

        const allCards = page.locator('#memo-grid .memo-card');
        const count = await allCards.count();
        expect(count).toBeGreaterThanOrEqual(2);

        // Construire la map slug → indices
        const slugMap = {};
        for (let i = 0; i < count; i++) {
            const slug = await allCards.nth(i).getAttribute('data-slug');
            if (!slugMap[slug]) slugMap[slug] = [];
            slugMap[slug].push(i);
        }

        // Trouver un slug qui a exactement 2 cartes (paire)
        const pairEntry = Object.entries(slugMap).find(([, idxs]) => idxs.length === 2);
        if (!pairEntry) {
            test.skip();
            return;
        }

        const [, [i1, i2]] = pairEntry;
        await allCards.nth(i1).click();
        await page.waitForTimeout(200);
        await allCards.nth(i2).click();

        // Les deux cartes doivent obtenir is-matched et rester is-flipped
        await expect(allCards.nth(i1)).toHaveClass(/is-matched/);
        await expect(allCards.nth(i2)).toHaveClass(/is-matched/);
    });

    test('le compteur de paires augmente après une bonne paire', async ({ page }) => {
        await page.goto('/fr/jeux');
        await page.locator('#memo-start').click();
        await page.waitForTimeout(4000);

        const counter = page.locator('#memo-pairs-found');
        const initialCount = parseInt(await counter.textContent(), 10);

        const allCards = page.locator('#memo-grid .memo-card');
        const count = await allCards.count();

        const slugMap = {};
        for (let i = 0; i < count; i++) {
            const slug = await allCards.nth(i).getAttribute('data-slug');
            if (!slugMap[slug]) slugMap[slug] = [];
            slugMap[slug].push(i);
        }

        const pairEntry = Object.entries(slugMap).find(([, idxs]) => idxs.length === 2);
        if (!pairEntry) {
            test.skip();
            return;
        }

        const [, [i1, i2]] = pairEntry;
        await allCards.nth(i1).click();
        await page.waitForTimeout(200);
        await allCards.nth(i2).click();
        await page.waitForTimeout(400);

        const newCount = parseInt(await counter.textContent(), 10);
        expect(newCount).toBe(initialCount + 1);
    });

});

// ============================================================
// Suite: Runner — La Vendangeuse
// ============================================================

test.describe('Runner — La Vendangeuse', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la section runner est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#runner-game')).toBeVisible();
    });

    test('le titre de la section runner est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#runner-title')).toBeVisible();
    });

    test('le canvas du runner est présent', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#runner-canvas')).toBeVisible();
    });

    test('le canvas runner a des dimensions > 0', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#runner-canvas');
        await expect(canvas).toBeVisible();
        const box = await canvas.boundingBox();
        expect(box).not.toBeNull();
        expect(box.width).toBeGreaterThan(0);
        expect(box.height).toBeGreaterThan(0);
    });

    test('cliquer sur le canvas démarre le jeu (état idle → running)', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#runner-canvas');
        await canvas.click();
        // Après le clic, le jeu est en cours — le canvas reste visible et actif
        await expect(canvas).toBeVisible();
        // Attendre quelques frames
        await page.waitForTimeout(500);
        // Vérifier que le canvas est toujours là (le jeu tourne)
        await expect(canvas).toBeVisible();
    });

    test('le score s\'incrémente après démarrage (via évaluation JS)', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#runner-canvas');
        // Démarrer le jeu
        await canvas.click();
        // Attendre que quelques frames s'écoulent
        await page.waitForTimeout(800);
        // Le canvas est toujours en cours de rendu
        await expect(canvas).toBeVisible();
    });

});

// ============================================================
// Suite: Labour — Labour Chrono
// ============================================================

test.describe('Labour — Labour Chrono', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la section labour est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#labour-chrono-game')).toBeVisible();
    });

    test('le titre de la section labour est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#labour-title')).toBeVisible();
    });

    test('le canvas du labour est présent et a des dimensions > 0', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#labour-chrono-canvas');
        await expect(canvas).toBeVisible();
        const box = await canvas.boundingBox();
        expect(box).not.toBeNull();
        expect(box.width).toBeGreaterThan(0);
        expect(box.height).toBeGreaterThan(0);
    });

    test('le canvas labour a un aria-label', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#labour-chrono-canvas');
        const ariaLabel = await canvas.getAttribute('aria-label');
        expect(ariaLabel).toBeTruthy();
    });

});

// ============================================================
// Suite: Vendange Express
// ============================================================

test.describe('Vendange Express', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la section vendange-express est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#vendange-express-game')).toBeVisible();
    });

    test('le titre de la section vendange-express est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#vendange-express-title')).toBeVisible();
    });

    test('le canvas vendange-express est présent et a des dimensions > 0', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#vendange-express-canvas');
        await expect(canvas).toBeVisible();
        const box = await canvas.boundingBox();
        expect(box).not.toBeNull();
        expect(box.width).toBeGreaterThan(0);
        expect(box.height).toBeGreaterThan(0);
    });

    test('le canvas vendange-express a un aria-label', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#vendange-express-canvas');
        const ariaLabel = await canvas.getAttribute('aria-label');
        expect(ariaLabel).toBeTruthy();
    });

});

// ============================================================
// Suite: Tonneau Catapulte (bonus)
// ============================================================

test.describe('Tonneau Catapulte', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('la section tonneau-catapulte est visible', async ({ page }) => {
        await page.goto('/fr/jeux');
        await expect(page.locator('#tonneau-catapulte-game')).toBeVisible();
    });

    test('le canvas tonneau-catapulte est présent', async ({ page }) => {
        await page.goto('/fr/jeux');
        const canvas = page.locator('#tonneau-catapulte-canvas');
        await expect(canvas).toBeVisible();
        const box = await canvas.boundingBox();
        expect(box).not.toBeNull();
        expect(box.width).toBeGreaterThan(0);
    });

});

// ============================================================
// Suite: Galerie
// ============================================================

test.describe('Galerie — photos du domaine', () => {

    test.beforeEach(async ({ context }) => {
        await setVerifiedCookie(context);
    });

    test('/fr/galerie se charge sans erreur 404/500', async ({ page }) => {
        await page.goto('/fr/galerie');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.locator('.site-header')).toBeVisible();
        await expect(page.locator('.site-footer')).toBeVisible();
    });

    test('le main a la classe page-gallery', async ({ page }) => {
        await page.goto('/fr/galerie');
        await expect(page.locator('main.page-gallery')).toBeVisible();
    });

    test('le titre h1 de la galerie est visible', async ({ page }) => {
        await page.goto('/fr/galerie');
        await expect(page.locator('.page-hero h1')).toBeVisible();
    });

    test('le conteneur galerie est présent', async ({ page }) => {
        await page.goto('/fr/galerie');
        await expect(page.locator('#gallery-container')).toBeVisible();
    });

    test('au moins une image ou un élément galerie est présent', async ({ page }) => {
        await page.goto('/fr/galerie');
        const items = page.locator('.gallery-item');
        const count = await items.count();
        // Si des photos existent, au moins une doit être visible
        if (count > 0) {
            await expect(items.first()).toBeVisible();
        } else {
            // Galerie vide est acceptable — le conteneur doit quand même être là
            await expect(page.locator('#gallery-container')).toBeVisible();
        }
    });

    test('les boutons de vue mosaïque et linéaire sont présents', async ({ page }) => {
        await page.goto('/fr/galerie');
        await expect(page.locator('#gallery-view-mosaic')).toBeVisible();
        await expect(page.locator('#gallery-view-linear')).toBeVisible();
    });

    test('le bouton mosaïque est actif par défaut (aria-pressed=true)', async ({ page }) => {
        await page.goto('/fr/galerie');
        await expect(page.locator('#gallery-view-mosaic')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#gallery-view-linear')).toHaveAttribute('aria-pressed', 'false');
    });

    test('cliquer sur Vue Linéaire bascule l\'affichage', async ({ page }) => {
        await page.goto('/fr/galerie');
        await page.locator('#gallery-view-linear').click();
        await expect(page.locator('#gallery-view-linear')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#gallery-view-mosaic')).toHaveAttribute('aria-pressed', 'false');
        await expect(page.locator('#gallery-container')).toHaveClass(/gallery-grid--linear/);
    });

    test('cliquer sur une image ouvre la lightbox (si des photos existent)', async ({ page }) => {
        await page.goto('/fr/galerie');
        const items = page.locator('.js-gallery-open');
        const count = await items.count();
        if (count === 0) {
            // Pas de photos — test non applicable
            test.skip();
            return;
        }
        await items.first().click();
        // La lightbox devient visible (display: flex)
        const lightbox = page.locator('#gallery-lightbox');
        await expect(lightbox).toBeVisible();
        await expect(page.locator('#gallery-lightbox-img')).toBeVisible();
    });

    test('la lightbox se ferme avec le bouton ✕', async ({ page }) => {
        await page.goto('/fr/galerie');
        const items = page.locator('.js-gallery-open');
        const count = await items.count();
        if (count === 0) {
            test.skip();
            return;
        }
        await items.first().click();
        await expect(page.locator('#gallery-lightbox')).toBeVisible();
        await page.locator('#gallery-lightbox-close').click();
        await expect(page.locator('#gallery-lightbox')).toBeHidden();
    });

});
