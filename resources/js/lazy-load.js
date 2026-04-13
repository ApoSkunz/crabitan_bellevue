/**
 * lazy-load.js
 *
 * Lazy reveal des cartes via Intersection Observer (fade-in au scroll).
 * L'infinite scroll a été retiré — la pagination classique est utilisée.
 *
 * Pages cibles : .page-wines, .page-wines-collection, .page-news, .page-gallery
 */

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

/**
 * Point d'entrée — initialise le lazy reveal des cartes.
 *
 * @return {void}
 */
export function initLazyLoad() {
    initCardReveal();
}
