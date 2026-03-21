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

        if (!selected) {
            e.preventDefault();
            return;
        }

        // Si mineur sélectionné, on bloque
        if (selected.value !== '1') {
            e.preventDefault();
            const msg = document.getElementById('age-gate-error');
            if (msg) msg.removeAttribute('hidden');
        }
    });
}

// ============================================================
// Init
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initThemeToggle();
    initBurger();
    initAgeGate();
});
