import '../scss/main.scss';
import { initCarbonBadge } from './carbon-badge.js';

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
    applyTheme(saved === 'dark' ? 'dark' : 'light');
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
// Carousel héro — transition fade automatique
// ============================================================

function initCarousel() {
    const carousel = document.getElementById('hero-carousel');
    if (!carousel) return;

    const slides = carousel.querySelectorAll('.carousel__slide');
    const dots   = carousel.querySelectorAll('.carousel__dot');
    const prev   = document.getElementById('carousel-prev');
    const next   = document.getElementById('carousel-next');

    if (!slides.length) return;

    let current  = 0;
    let timer    = null;
    const DELAY  = 5000;

    function goTo(index) {
        slides[current].classList.remove('is-active');
        slides[current].setAttribute('aria-hidden', 'true');
        dots[current].classList.remove('is-active');
        dots[current].setAttribute('aria-selected', 'false');

        current = (index + slides.length) % slides.length;

        slides[current].classList.add('is-active');
        slides[current].setAttribute('aria-hidden', 'false');
        dots[current].classList.add('is-active');
        dots[current].setAttribute('aria-selected', 'true');
    }

    function startAuto() {
        stopAuto(); // évite les intervalles multiples si startAuto est rappelé
        timer = setInterval(() => goTo(current + 1), DELAY);
    }

    function stopAuto() {
        clearInterval(timer);
    }

    prev?.addEventListener('click', () => { stopAuto(); goTo(current - 1); startAuto(); });
    next?.addEventListener('click', () => { stopAuto(); goTo(current + 1); startAuto(); });

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            stopAuto();
            goTo(parseInt(dot.dataset.slide, 10));
            startAuto();
        });
    });

    // Pause au survol
    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);

    // Navigation clavier
    carousel.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft')  { stopAuto(); goTo(current - 1); startAuto(); }
        if (e.key === 'ArrowRight') { stopAuto(); goTo(current + 1); startAuto(); }
    });

    startAuto();
}

// ============================================================
// Account panel — drawer latéral
// ============================================================

function initAccountPanel() {
    const trigger  = document.getElementById('account-panel-trigger');
    const panel    = document.getElementById('account-panel');
    const closeBtn = document.getElementById('account-panel-close');
    const backdrop = document.getElementById('account-panel-backdrop');

    if (!trigger || !panel) return;

    function openPanel() {
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        closeBtn?.focus();
        document.body.style.overflow = 'hidden';
    }

    function closePanel() {
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
        document.body.style.overflow = '';
    }

    trigger.addEventListener('click', () => {
        panel.classList.contains('is-open') ? closePanel() : openPanel();
    });

    closeBtn?.addEventListener('click', closePanel);
    backdrop?.addEventListener('click', closePanel);

    // Fermeture avec Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) closePanel();
    });
}

// ============================================================
// Toast notification
// ============================================================

function showToast(msg, isError = false) {
    const toast = document.getElementById('cb-toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'cb-toast' + (isError ? ' cb-toast--error' : '');
    toast.removeAttribute('hidden');
    clearTimeout(toast.__timer);
    toast.__timer = setTimeout(() => toast.setAttribute('hidden', ''), 3000);
}

// ============================================================
// Panier hors connexion — localStorage
// ============================================================

const CART_KEY = 'cb-cart';

function getLocalCart() {
    try {
        return JSON.parse(localStorage.getItem(CART_KEY) || '[]');
    } catch {
        return [];
    }
}

function saveLocalCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
}

function addToLocalCart(item) {
    const cart    = getLocalCart();
    const existing = cart.find((i) => i.id === item.id);
    if (existing) {
        existing.qty += item.qty;
    } else {
        cart.push(item);
    }
    saveLocalCart(cart);
    updateCartCount();
}

function getLocalCartCount() {
    return getLocalCart().reduce((sum, i) => sum + (i.qty || 1), 0);
}

function updateCartCount() {
    const badge = document.getElementById('header-cart-count');
    if (!badge) return;
    badge.textContent = window.__userLogged ? 0 : getLocalCartCount();
}

// ============================================================
// Cart modal — add-to-cart pop-in (ouvert pour tous)
// ============================================================

function initCartModal() {
    const modal     = document.getElementById('cart-modal');
    const backdrop  = document.getElementById('cart-modal-backdrop');
    const closeBtn  = document.getElementById('cart-modal-close');
    const cancelBtn = document.getElementById('cart-modal-cancel');
    const form      = document.getElementById('cart-modal-form');
    const titleEl   = document.getElementById('cart-modal-title');
    const priceEl   = document.getElementById('cart-modal-price');
    const totalEl   = document.getElementById('cart-modal-total');
    const imgEl     = document.getElementById('cart-modal-image');
    const wineIdEl  = document.getElementById('cart-modal-wine-id');
    const qtyInput  = document.getElementById('cart-modal-qty');
    const qtyHidden = document.getElementById('cart-modal-qty-hidden');
    const minusBtn  = document.getElementById('cart-qty-minus');
    const plusBtn   = document.getElementById('cart-qty-plus');

    if (!modal) return;

    // Parse "15,50 €" → 15.50 (float)
    function parsePrice(str) {
        return parseFloat((str || '0').replace(/\s/g, '').replace(',', '.').replace('€', '')) || 0;
    }

    function refreshTotal() {
        if (!totalEl) return;
        const unitPrice = parsePrice(priceEl.textContent);
        const qty       = parseInt(qtyInput.value, 10) || 1;
        const total     = (unitPrice * qty).toFixed(2).replace('.', ',');
        totalEl.textContent = 'Total : ' + total + ' €';
    }

    function openModal(btn) {
        const wineId    = btn.dataset.wineId    || '';
        const wineName  = btn.dataset.wineName  || '';
        const winePrice = btn.dataset.winePrice || '';
        const wineImage = btn.dataset.wineImage || '';

        titleEl.textContent   = wineName;
        priceEl.textContent   = winePrice;
        imgEl.src             = wineImage;
        imgEl.alt             = wineName;
        wineIdEl.value        = wineId;
        qtyInput.value        = 1;
        qtyHidden.value       = 1;

        refreshTotal();

        form.action = '/' + (window.__navLang || 'fr') + '/panier/ajouter';

        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    // Open on js-add-to-cart click (delegated) — tous les utilisateurs
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-add-to-cart');
        if (!btn) return;
        openModal(btn);
    });

    // Soumission du formulaire
    form?.addEventListener('submit', (e) => {
        if (!window.__userLogged) {
            e.preventDefault();
            addToLocalCart({
                id:    parseInt(wineIdEl.value, 10),
                qty:   parseInt(qtyHidden.value, 10) || 1,
                name:  titleEl.textContent,
                price: priceEl.textContent,
                image: imgEl.src,
            });
            closeModal();
            showToast(
                document.documentElement.lang === 'en'
                    ? 'Added to cart. Log in to place your order.'
                    : 'Ajouté au panier. Connectez-vous pour passer commande.',
                false
            );
        }
        // Si connecté : le formulaire se soumet normalement vers le serveur
    });

    backdrop?.addEventListener('click', closeModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);

    // Qty controls
    function updateQty(delta) {
        const min = parseInt(qtyInput.min, 10) || 1;
        const max = parseInt(qtyInput.max, 10) || 96;
        const val = Math.min(max, Math.max(min, (parseInt(qtyInput.value, 10) || 1) + delta));
        qtyInput.value  = val;
        qtyHidden.value = val;
    }

    minusBtn?.addEventListener('click', () => { updateQty(-1); refreshTotal(); });
    plusBtn?.addEventListener('click',  () => { updateQty(1);  refreshTotal(); });
    qtyInput?.addEventListener('input', () => { qtyHidden.value = qtyInput.value; refreshTotal(); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
    });
}

// ============================================================
// Cart login prompt (bouton panier header — non connecté)
// ============================================================

function initCartLoginPrompt() {
    document.querySelectorAll('.js-cart-login-prompt').forEach((btn) => {
        btn.addEventListener('click', () => {
            const loginUrl = btn.dataset.loginUrl || ('/' + (window.__navLang || 'fr') + '/connexion');
            showToast(
                document.documentElement.lang === 'en'
                    ? 'The cart requires an account. Redirecting to login…'
                    : 'Le panier nécessite un compte. Redirection vers la connexion…',
                false
            );
            setTimeout(() => { window.location.href = loginUrl; }, 2500); // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression — loginUrl is server-rendered via htmlspecialchars(), not user input // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression
        });
    });
}

// ============================================================
// Like button — auth guard
// ============================================================

function initFavoriteAuth() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-favorite');
        if (!btn) return;

        if (!window.__userLogged) {
            e.stopImmediatePropagation();
            showToast(btn.dataset.loginMsg || 'Connectez-vous pour aimer ce vin.', false);
        }
    }, true); // capture phase so we intercept before any other handler
}

// ============================================================
// Wine image zoom
// ============================================================

function initWineZoom() {
    const overlay  = document.getElementById('wine-zoom-overlay');
    const backdrop = document.getElementById('wine-zoom-backdrop');
    const closeBtn = document.getElementById('wine-zoom-close');

    if (!overlay) return;

    function openZoom() {
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeBtn?.focus();
    }

    function closeZoom() {
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-wine-zoom').forEach((img) => {
        img.addEventListener('click', openZoom);
    });

    backdrop?.addEventListener('click', closeZoom);
    closeBtn?.addEventListener('click', closeZoom);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.getAttribute('aria-hidden') === 'false') closeZoom();
    });
}

// ============================================================
// Anchor scroll — compensate sticky header on hash navigation
// ============================================================

function initAnchorScroll() {
    if (!window.location.hash) return;
    const target = document.getElementById(window.location.hash.slice(1));
    if (!target) return;

    // requestAnimationFrame ensures layout is computed before measuring
    requestAnimationFrame(() => {
        const header = document.querySelector('.site-header');
        const offset = (header ? header.offsetHeight : 0) + 24;
        const y = target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: Math.max(0, y), behavior: 'instant' });
    });
}

// ============================================================
// Init
// ============================================================

// ============================================================
// Support — FAQ accordéon
// ============================================================

function initFaqAccordion() {
    const accordion = document.getElementById('faq-accordion');
    if (!accordion) return;

    accordion.querySelectorAll('.faq-accordion__trigger').forEach((btn) => {
        btn.addEventListener('click', () => {
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            const panelId  = btn.getAttribute('aria-controls');
            const panel    = document.getElementById(panelId);

            // Fermer tous les autres
            accordion.querySelectorAll('.faq-accordion__trigger').forEach((other) => {
                if (other !== btn) {
                    other.setAttribute('aria-expanded', 'false');
                    const otherId = other.getAttribute('aria-controls');
                    document.getElementById(otherId).hidden = true;
                }
            });

            btn.setAttribute('aria-expanded', String(!expanded));
            panel.hidden = expanded;
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initThemeToggle();
    initBurger();
    initAgeGate();
    initCookieBanner();
    initCarousel();
    initAccountPanel();
    initCartModal();
    initCartLoginPrompt();
    initFavoriteAuth();
    initWineZoom();
    updateCartCount();
    initAnchorScroll();
    initFaqAccordion();
    initCarbonBadge();

    // Chargement à la demande — uniquement sur la page jeux
    if (document.getElementById('memo-game')) {
        import('./memo-game.js').then((m) => m.initMemoGame());
    }
});
