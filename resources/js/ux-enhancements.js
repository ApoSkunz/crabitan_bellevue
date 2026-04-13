/**
 * ux-enhancements.js
 *
 * Ensemble d'améliorations UX :
 *   1. Auto-submit forms : soumission instantanée des filtres au change.
 *   2. Lang switch toast : confirmation visuelle avant reload.
 *   3. Focus trap : capture le focus dans les modales et flash dialogues.
 */

// ============================================================
// 1. Auto-submit forms (data-auto-submit)
// ============================================================

/**
 * Active la soumission instantanée d'un formulaire au change d'un input.
 * - Forms portant `data-auto-submit` : tous selects/radio/checkbox déclenchent submit().
 * - Boutons `.js-noscript-only` à l'intérieur sont masqués (fallback noscript).
 *
 * @return {void}
 */
export function initAutoSubmitFilters() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.querySelectorAll('.js-noscript-only').forEach((el) => {
            el.style.display = 'none';
        });

        form.addEventListener('change', (e) => {
            const t = e.target;
            if (!t || !(t.matches('select, input[type="radio"], input[type="checkbox"]'))) return;
            form.submit();
        });
    });

    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        if (select.closest('form[data-auto-submit]')) return;
        select.addEventListener('change', () => {
            const form = select.form;
            if (form) form.submit();
        });
    });
}

// ============================================================
// 2. Lang switch toast (avant reload)
// ============================================================

/**
 * Affiche un toast 1.2s avant reload quand la langue est changée.
 * Hook : éléments avec data-lang-toast (ex. select de l'en-tête, form profil).
 *
 * @return {void}
 */
export function initLangSwitchToast() {
    const triggers = document.querySelectorAll('[data-lang-toast]');
    if (!triggers.length) return;

    const showToast = (msg) => {
        let toast = document.getElementById('lang-switch-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'lang-switch-toast';
            toast.className = 'lang-switch-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.classList.add('is-visible');
    };

    triggers.forEach((el) => {
        el.addEventListener('click', () => {
            const msg = el.dataset.langToast || 'Langue mise à jour…';
            showToast(msg);
        });
    });
}

// ============================================================
// 3. Focus trap pour modales et flash dialogues
// ============================================================

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Capture le focus à l'intérieur d'un conteneur (modale, dialogue).
 * Tab et Shift+Tab restent dans le scope.
 *
 * @param {HTMLElement} container
 * @return {() => void} fonction de désactivation
 */
function trapFocusIn(container) {
    const handler = (e) => {
        if (e.key !== 'Tab') return;
        const focusables = container.querySelectorAll(FOCUSABLE);
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    };
    container.addEventListener('keydown', handler);
    return () => container.removeEventListener('keydown', handler);
}

/**
 * Active le focus trap sur les modales visibles et les alertes flash.
 *
 * @return {void}
 */
export function initFocusTrap() {
    const observer = new MutationObserver(() => attach());

    const attach = () => {
        const candidates = document.querySelectorAll(
            '.modal.is-open, [role="dialog"][aria-hidden="false"], .login-modal.is-open, .register-modal.is-open, [role="alert"]:not([aria-hidden="true"]):not([data-focus-trapped])'
        );
        candidates.forEach((el) => {
            if (el.dataset.focusTrapped === '1') return;
            el.dataset.focusTrapped = '1';
            trapFocusIn(el);
            const firstFocusable = el.querySelector(FOCUSABLE);
            if (firstFocusable && el.getAttribute('role') !== 'alert') {
                firstFocusable.focus();
            }
        });
    };

    attach();
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'aria-hidden'] });
}
