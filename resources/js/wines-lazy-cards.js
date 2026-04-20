/**
 * wines-lazy-cards.js
 *
 * Lazy-render des cartes vins hors viewport sur /vins/collection.
 *
 * Les cards au-delà des N premières par groupe sont placées dans un
 * élément <template class="js-lazy-cards"> par le serveur PHP.
 * Ce module les extrait et les injecte dans la grille quand le sentinel
 * entre dans le viewport, réduisant le DOM initial.
 */

/**
 * Initialise l'IntersectionObserver sur les sentinels lazy-cards.
 *
 * @return {void}
 */
export function initLazyCards() {
    if (!('IntersectionObserver' in globalThis)) {
        // Fallback : révéler toutes les cartes immédiatement
        document.querySelectorAll('template.js-lazy-cards').forEach((tpl) => {
            tpl.parentElement?.insertBefore(tpl.content, tpl);
            tpl.remove();
        });
        document.querySelectorAll('.js-lazy-sentinel').forEach((s) => s.remove());
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                const sentinel = entry.target;
                const tpl = sentinel.previousElementSibling;

                if (tpl?.tagName === 'TEMPLATE' && tpl.classList.contains('js-lazy-cards')) {
                    sentinel.parentElement?.insertBefore(tpl.content, sentinel);
                    tpl.remove();
                }

                sentinel.remove();
                observer.unobserve(sentinel);
            });
        },
        { rootMargin: '300px' }
    );

    document.querySelectorAll('.js-lazy-sentinel').forEach((s) => observer.observe(s));
}
