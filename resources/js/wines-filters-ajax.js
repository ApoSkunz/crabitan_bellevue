/**
 * wines-filters-ajax.js
 *
 * Filtres instantanés AJAX pour /vins et /vins/collection.
 *
 * Intercepte les changements sur les formulaires `form[data-ajax-filter]`,
 * fetch la nouvelle page en arrière-plan, remplace le bloc `[data-ajax-content]`
 * avec un effet de fondu, et pousse l'URL via history.pushState.
 *
 * Dépendances : ré-initialise le lazy-load (reveal + infinite scroll) après swap.
 */

import { initLazyLoad } from './lazy-load.js';

/**
 * Construit l'URL cible à partir du formulaire + reset page=1.
 *
 * @param {HTMLFormElement} form
 * @return {string}
 */
function buildUrl(form) {
    const params = new URLSearchParams(new FormData(form));
    params.delete('page');
    const qs = params.toString();
    return globalThis.location.pathname + (qs ? '?' + qs : '');
}

/**
 * Charge et swap le contenu AJAX.
 *
 * @param {string} url
 * @param {HTMLElement} container
 * @return {Promise<void>}
 */
async function swapContent(url, container) {
    container.classList.add('is-loading');

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Network error');
        const html = await response.text();

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newContent = doc.querySelector('[data-ajax-content]');

        if (newContent) {
            container.replaceWith(newContent);
            globalThis.history.pushState({ ajaxFilter: true }, '', url);
            // Laisser un tick pour le reflow puis retirer is-loading
            requestAnimationFrame(() => {
                newContent.classList.remove('is-loading');
            });
            // Ré-init lazy reveal + infinite scroll sur le nouveau DOM
            initLazyLoad();
        } else {
            container.classList.remove('is-loading');
        }
    } catch {
        container.classList.remove('is-loading');
    }
}

/**
 * Initialise les filtres AJAX sur les formulaires `form[data-ajax-filter]`.
 *
 * @return {void}
 */
export function initWinesFiltersAjax() {
    const forms = document.querySelectorAll('form[data-ajax-filter]');
    if (!forms.length) return;

    forms.forEach((form) => {
        form.addEventListener('change', (e) => {
            const t = e.target;
            if (!t || !(t.matches('select, input[type="radio"], input[type="checkbox"]'))) return;
            e.preventDefault();

            const container = document.querySelector('[data-ajax-content]');
            if (!container) return;

            swapContent(buildUrl(form), container);
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const container = document.querySelector('[data-ajax-content]');
            if (!container) return;
            swapContent(buildUrl(form), container);
        });
    });

    globalThis.addEventListener('popstate', () => {
        const container = document.querySelector('[data-ajax-content]');
        if (!container) return;
        swapContent(globalThis.location.pathname + globalThis.location.search, container);
    });
}
