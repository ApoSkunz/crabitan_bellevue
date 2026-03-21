import '../scss/main.scss';

// ============================================================
// Thème jour / nuit
// ============================================================

const THEME_KEY = 'cb-theme';
const root = document.documentElement;

function applyTheme(theme) {
    root.dataset.theme = theme;
    localStorage.setItem(THEME_KEY, theme);
}

function initTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    applyTheme(saved === 'light' ? 'light' : 'dark');
}

function initThemeToggle() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(next);
    });
}

// ============================================================
// Burger menu (mobile)
// ============================================================

function initBurger() {
    const burger = document.getElementById('header-burger');
    const mobileNav = document.getElementById('header-nav-mobile');
    if (!burger || !mobileNav) return;

    burger.addEventListener('click', () => {
        const isOpen = mobileNav.classList.toggle('is-open');
        burger.setAttribute('aria-expanded', String(isOpen));
    });
}

// ============================================================
// Age gate — validation côté client
// ============================================================

function initAgeGate() {
    const form = document.getElementById('age-gate-form');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        const selected = form.querySelector('input[name="legal_age"]:checked');

        // Aucun choix sélectionné
        if (!selected) {
            e.preventDefault();
            return;
        }

        // Mineur : afficher l'erreur, bloquer l'interaction, rediriger vers Google
        if (selected.value !== '1') {
            e.preventDefault();
            const msg = document.getElementById('age-gate-error');
            if (msg) msg.removeAttribute('hidden');

            // Bloquer tous les champs et boutons
            form.querySelectorAll('input, button').forEach(el => { el.disabled = true; });

            // Redirection après 3 secondes
            setTimeout(() => { window.location.href = 'https://www.google.com'; }, 3000); // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression
            return;
        }

        // Cookie banner : réponse obligatoire avant d'entrer
        if (!localStorage.getItem(COOKIE_CONSENT_KEY)) {
            e.preventDefault();
            if (typeof window.__cookieBannerPending === 'function') {
                window.__cookieBannerPending();
            }
        }
    });
}

// ============================================================
// Google Analytics — chargé uniquement si consentement donné
// ============================================================

// Remplacer G-XXXXXXXXXX par votre Measurement ID Google Analytics 4
const GA_ID = 'G-XXXXXXXXXX';

function loadGoogleAnalytics() {
    if (window.__gaLoaded || !GA_ID || GA_ID === 'G-XXXXXXXXXX') return;
    window.__gaLoaded = true;

    const script = document.createElement('script');
    script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_ID}`;
    script.async = true;
    document.head.appendChild(script);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA_ID);
}

// ============================================================
// Bandeau cookies RGPD
// ============================================================

const COOKIE_CONSENT_KEY = 'cb-cookie-consent';

function shakeCookieBanner(banner) {
    banner.classList.remove('is-shaking');
    // Force reflow pour relancer l'animation
    void banner.offsetWidth;
    banner.classList.add('is-shaking');
    banner.addEventListener('animationend', () => banner.classList.remove('is-shaking'), { once: true });
}

function initCookieBanner() {
    const banner = document.getElementById('cookie-banner');
    if (!banner) return;

    const existing = localStorage.getItem(COOKIE_CONSENT_KEY);

    // Déjà répondu : charger GA si accepté et masquer le bandeau
    if (existing) {
        if (existing === 'accepted') loadGoogleAnalytics();
        banner.classList.add('is-hidden');
        return;
    }

    const requiredMsg = banner.querySelector('.cookie-banner__required');

    document.getElementById('cookie-accept')?.addEventListener('click', () => {
        localStorage.setItem(COOKIE_CONSENT_KEY, 'accepted');
        loadGoogleAnalytics();
        banner.classList.add('is-hidden');
    });

    document.getElementById('cookie-refuse')?.addEventListener('click', () => {
        localStorage.setItem(COOKIE_CONSENT_KEY, 'refused');
        banner.classList.add('is-hidden');
    });

    // Expose pour que l'age gate puisse déclencher la validation
    window.__cookieBannerPending = () => {
        shakeCookieBanner(banner);
        if (requiredMsg) requiredMsg.removeAttribute('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'end' });
    };
}

// ============================================================
// Init
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initThemeToggle();
    initBurger();
    initAgeGate();
    initCookieBanner();
});
