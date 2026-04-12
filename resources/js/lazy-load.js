/**
 * lazy-load.js
 *
 * 1. Lazy reveal des cartes via Intersection Observer (fade-in au scroll).
 * 2. Infinite scroll : chargement AJAX de la page suivante quand la sentinelle
 *    entre dans le viewport, avec pushState (?page=N) et spinner.
 *
 * Pages cibles : .page-wines, .page-wines-collection, .page-news, .page-gallery
 */

// ============================================================
// 1. Lazy reveal des cartes (Intersection Observer)
// ============================================================

/**
 * Observe les cartes hors viewport et leur applique une classe d'entrée
 * quand elles deviennent visibles.
 *
 * @return {void}
 */
function initCardReveal() {
    const cards = document.querySelectorAll(
        '.wine-card, .news-card, .gallery-item'
    );

    if (!cards.length || !('IntersectionObserver' in globalThis)) return;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -40px 0px', threshold: 0.05 }
    );

    cards.forEach((card) => {
        card.classList.add('js-reveal');
        observer.observe(card);
    });
}

// ============================================================
// 2. Infinite scroll (pagination AJAX + pushState)
// ============================================================

/** Sélecteurs du conteneur de cartes selon la page courante. */
const GRID_SELECTORS = [
    '.wines-grid',       // /vins et /vins/collection
    '.home-news__grid',  // /actualites
    '#gallery-container', // /galerie
];

/**
 * Renvoie l'URL de la page suivante depuis le lien rel="next" de la pagination,
 * ou null si on est à la dernière page.
 *
 * @return {string|null}
 */
function getNextPageUrl() {
    const nextLink = document.querySelector('a[rel="next"].wines-pagination__btn');
    return nextLink ? nextLink.href : null;
}

/**
 * Crée ou récupère l'élément spinner de chargement.
 *
 * @return {HTMLElement}
 */
function getOrCreateSpinner() {
    let spinner = document.getElementById('lazy-load-spinner');
    if (!spinner) {
        spinner = document.createElement('div');
        spinner.id = 'lazy-load-spinner';
        spinner.className = 'lazy-load-spinner';
        spinner.setAttribute('aria-live', 'polite');
        spinner.setAttribute('aria-label', 'Chargement en cours…');
        spinner.innerHTML = '<span class="lazy-load-spinner__ring" aria-hidden="true"></span>';
    }
    return spinner;
}

/**
 * Crée la sentinelle (élément vide observé en bas de page).
 *
 * @return {HTMLElement}
 */
function createSentinel() {
    const el = document.createElement('div');
    el.className = 'lazy-load-sentinel';
    el.setAttribute('aria-hidden', 'true');
    return el;
}

/**
 * Met à jour la pagination DOM avec celle de la page suivante.
 *
 * @param {Document} doc - Document parsé de la page suivante
 * @return {void}
 */
function swapPagination(doc) {
    const oldNav = document.querySelector('nav.wines-pagination');
    const newNav = doc.querySelector('nav.wines-pagination');
    if (oldNav && newNav) {
        oldNav.replaceWith(newNav.cloneNode(true));
    } else if (oldNav && !newNav) {
        oldNav.remove();
    }
}

/**
 * Initialise le chargement infini sur les pages catalogue, collection,
 * actualités et galerie.
 *
 * @return {void}
 */
function initInfiniteScroll() {
    if (!('IntersectionObserver' in globalThis)) return;

    // Trouver le conteneur de cartes actif
    let grid = null;
    let gridSelector = '';
    for (const sel of GRID_SELECTORS) {
        grid = document.querySelector(sel);
        if (grid) { gridSelector = sel; break; }
    }
    if (!grid) return;

    // Vérifier qu'il y a une page suivante
    if (!getNextPageUrl()) return;

    let loading = false;
    let exhausted = false;

    const spinner = getOrCreateSpinner();
    const sentinel = createSentinel();

    // Insérer sentinelle + spinner après le dernier élément de pagination ou après la grille
    const paginationNav = document.querySelector('nav.wines-pagination, div.container > nav[aria-label="Pagination"]');
    const insertAfter = paginationNav || grid;
    insertAfter.after(sentinel);
    sentinel.before(spinner);

    const observer = new IntersectionObserver(
        (entries) => {
            if (loading || exhausted) return;
            if (!entries[0].isIntersecting) return;

            const nextUrl = getNextPageUrl();
            if (!nextUrl) {
                exhausted = true;
                sentinel.remove();
                return;
            }

            loading = true;
            spinner.classList.add('is-visible');

            fetch(nextUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => {
                    if (!r.ok) throw new Error('Network error');
                    return r.text();
                })
                .then((html) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newGrid = doc.querySelector(gridSelector);

                    if (newGrid) {
                        const fragment = document.createDocumentFragment();
                        [...newGrid.children].forEach((card) => {
                            card.classList.add('js-reveal');
                            fragment.appendChild(card.cloneNode(true));
                        });
                        grid.appendChild(fragment);

                        // Reveal des nouvelles cartes
                        initCardReveal();
                    }

                    // Mettre à jour l'URL avec pushState
                    const pageMatch = /[?&]page=(\d+)/.exec(nextUrl);
                    if (pageMatch) {
                        const url = new URL(nextUrl);
                        globalThis.history.pushState({ page: pageMatch[1] }, '', url.pathname + url.search);
                    }

                    // Mettre à jour la pagination DOM
                    swapPagination(doc);

                    spinner.classList.remove('is-visible');
                    loading = false;

                    // Vérifier s'il reste une page suivante
                    if (!getNextPageUrl()) {
                        exhausted = true;
                        sentinel.remove();
                    }
                })
                .catch(() => {
                    spinner.classList.remove('is-visible');
                    loading = false;
                });
        },
        { rootMargin: '0px 0px 300px 0px', threshold: 0 }
    );

    observer.observe(sentinel);
}

// ============================================================
// Export public
// ============================================================

/**
 * Point d'entrée — initialise le lazy reveal et l'infinite scroll.
 *
 * @return {void}
 */
export function initLazyLoad() {
    initCardReveal();
    initInfiniteScroll();
}
