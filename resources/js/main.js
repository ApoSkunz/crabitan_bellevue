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
const COOKIE_CONSENT_TTL = 397 * 24 * 3600; // 13 mois — durée max CNIL/RGPD

function getConsentCookie() {
    const match = document.cookie.split('; ').find((c) => c.startsWith(COOKIE_CONSENT_KEY + '='));
    return match ? decodeURIComponent(match.slice(COOKIE_CONSENT_KEY.length + 1)) : null;
}

function setConsentCookie(value) {
    document.cookie = COOKIE_CONSENT_KEY + '=' + encodeURIComponent(value)
        + '; path=/; max-age=' + COOKIE_CONSENT_TTL + '; SameSite=Lax';
}

function attachConsentListeners(banner) {
    document.getElementById('cookie-accept')?.addEventListener('click', () => {
        setConsentCookie('accepted');
        loadGoogleAnalytics();
        banner.classList.add('is-hidden');
    }, { once: true });

    document.getElementById('cookie-refuse')?.addEventListener('click', () => {
        setConsentCookie('refused');
        banner.classList.add('is-hidden');
    }, { once: true });
}

function initCookieBanner() {
    const banner = document.getElementById('cookie-banner');
    if (!banner) return;

    const existing = getConsentCookie();

    // Déjà répondu : charger GA si accepté et masquer le bandeau
    if (existing) {
        if (existing === 'accepted') loadGoogleAnalytics();
        banner.classList.add('is-hidden');
    } else {
        attachConsentListeners(banner);
    }

    // Re-gestion depuis le footer : réaffiche le bandeau
    document.getElementById('cookie-manage')?.addEventListener('click', () => {
        document.cookie = COOKIE_CONSENT_KEY + '=; path=/; max-age=0; SameSite=Lax';
        banner.classList.remove('is-hidden');
        attachConsentListeners(banner);
    });
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

function showToast(msg, isError = false, duration = 1500) {
    const toast = document.getElementById('cb-toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'cb-toast' + (isError ? ' cb-toast--error' : '');
    toast.removeAttribute('hidden');
    clearTimeout(toast.__timer);
    toast.__timer = setTimeout(() => toast.setAttribute('hidden', ''), duration);
}

// ============================================================
// Panier hors connexion — cookies
// ============================================================

const CART_KEY    = 'cb-cart';
const CART_MAX_AGE = 7 * 24 * 3600; // 7 jours

function getLocalCart() {
    const match = document.cookie.split('; ').find((c) => c.startsWith(CART_KEY + '='));
    if (!match) return [];
    try {
        return JSON.parse(decodeURIComponent(match.slice(CART_KEY.length + 1)));
    } catch {
        return [];
    }
}

function saveLocalCart(cart) {
    const val = encodeURIComponent(JSON.stringify(cart));
    document.cookie = CART_KEY + '=' + val + '; path=/; max-age=' + CART_MAX_AGE + '; SameSite=Lax';
}

function addToLocalCart(item) {
    const cart     = getLocalCart();
    const existing = cart.find((i) => i.id === item.id);
    if (existing) {
        existing.qty += item.qty;
    } else {
        // Stocke uniquement {id, qty} — nom, image et prix récupérés depuis BDD via /api/cart/details
        cart.push({ id: item.id, qty: item.qty });
    }
    saveLocalCart(cart);
    updateCartCount();
}

function getLocalCartCount() {
    return getLocalCart().reduce((sum, i) => sum + (i.qty || 1), 0);
}

function updateCartCount(serverCount = null) {
    const badge = document.querySelector('.header-cart__count');
    if (!badge) return;
    if (serverCount !== null) {
        badge.textContent = serverCount;
    } else {
        badge.textContent = getLocalCartCount();
    }
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

    let currentCuvee = '';

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
        currentCuvee    = btn.dataset.wineCuvee || '';

        titleEl.textContent   = wineName;
        priceEl.textContent   = winePrice;
        imgEl.src             = wineImage;
        imgEl.alt             = wineName;
        wineIdEl.value        = wineId;
        qtyInput.value        = 1;
        qtyHidden.value       = 1;

        const cuveeEl = document.getElementById('cart-modal-cuvee');
        if (cuveeEl) {
            if (currentCuvee) {
                cuveeEl.textContent = '\u2605 ' + currentCuvee;
                cuveeEl.hidden = false;
            } else {
                cuveeEl.hidden = true;
            }
        }

        refreshTotal();

        form.action = '/' + (window.__navLang || 'fr') + '/panier/ajouter';

        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const successEl = document.getElementById('cart-modal-success');
        const bodyEl    = modal.querySelector('.cart-modal__body');
        const footerEl  = modal.querySelector('.cart-modal__footer');
        if (successEl) successEl.hidden = true;
        if (bodyEl)    bodyEl.hidden    = false;
        if (footerEl)  footerEl.hidden  = false;
    }

    function showCartSuccess(qty) {
        const successEl = document.getElementById('cart-modal-success');
        const msgEl     = document.getElementById('cart-modal-success-msg');
        const bodyEl    = modal.querySelector('.cart-modal__body');
        const footerEl  = modal.querySelector('.cart-modal__footer');
        const isEn      = document.documentElement.lang === 'en';
        const msg = isEn
            ? qty + ' bottle' + (qty > 1 ? 's' : '') + ' added to your cart!'
            : qty + ' bouteille' + (qty > 1 ? 's' : '') + ' ajout\u00e9e' + (qty > 1 ? 's' : '') + ' au panier\u00a0!';
        if (msgEl)     msgEl.textContent = msg;
        if (bodyEl)    bodyEl.hidden     = true;
        if (footerEl)  footerEl.hidden   = true;
        if (successEl) successEl.hidden  = false;
        updateCartCount();
        setTimeout(closeModal, 1200);
    }

    // Open on js-add-to-cart click (delegated) — tous les utilisateurs
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-add-to-cart');
        if (!btn) return;
        openModal(btn);
    });

    // Soumission du formulaire — interceptée pour les deux cas (invité et connecté)
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const qty    = parseInt(qtyHidden.value, 10) || 1;
        const wineId = parseInt(wineIdEl.value, 10);
        const item   = { id: wineId, qty };

        if (!window.__userLogged) {
            // Invité : stockage cookie local uniquement, sans price
            addToLocalCart(item);
            showCartSuccess(qty);
            return;
        }

        // Connecté : POST API uniquement — pas de cookie local (BDD = source de vérité)
        const csrfInput = document.getElementById('cart-modal-csrf');
        const csrf      = csrfInput?.value ?? '';

        try {
            const res  = await fetch('/api/cart/add', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ wine_id: wineId, quantity: qty, csrf_token: csrf }),
            });
            const data = await res.json();

            if (!data.success) throw new Error('API error');

            updateCartCount(data.total_quantity ?? null);
            showCartSuccess(qty);
        } catch {
            const isEn = document.documentElement.lang === 'en';
            showToast(
                isEn ? 'Failed to add to cart. Please try again.' : 'Échec de l\'ajout au panier. Veuillez réessayer.',
                true
            );
            closeModal();
        }
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
// Login modal — header trigger (non connecté)
// ============================================================

function initLoginModal() {
    const trigger      = document.getElementById('login-modal-trigger');
    const modal        = document.getElementById('login-modal');
    const closeBtn     = document.getElementById('login-modal-close');
    const backdrop     = document.getElementById('login-modal-backdrop');
    const titleEl      = document.getElementById('login-modal-title');
    const loginPanel   = document.getElementById('login-panel');
    const forgotPanel  = document.getElementById('forgot-panel');

    if (!trigger || !modal) return;

    function showLoginPanel() {
        loginPanel?.removeAttribute('hidden');
        forgotPanel?.setAttribute('hidden', '');
        if (titleEl) titleEl.textContent = titleEl.dataset.titleLogin || titleEl.textContent;
    }

    function showForgotPanel() {
        loginPanel?.setAttribute('hidden', '');
        forgotPanel?.removeAttribute('hidden');
        if (titleEl) titleEl.textContent = titleEl.dataset.titleForgot || titleEl.textContent;
        document.getElementById('forgot-modal-email')?.focus();
    }

    function openModal() {
        showLoginPanel();
        modal.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        closeBtn?.focus();
    }

    function openModalForgot() {
        modal.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        showForgotPanel();
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        showLoginPanel();
        trigger.focus();
        if (window.__forgotSuccess) {
            window.__forgotSuccess = false;
            window.location.replace(window.location.pathname);
        }
    }

    if (window.__authModalError || new URLSearchParams(window.location.search).get('login') === '1') openModal();

    // Switch vers panel mot de passe oublié
    document.getElementById('forgot-password-btn')?.addEventListener('click', showForgotPanel);
    document.getElementById('forgot-back-btn')?.addEventListener('click', showLoginPanel);

    // Switch vers register modal
    document.getElementById('login-to-register')?.addEventListener('click', () => {
        closeModal();
        document.getElementById('register-modal')?.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.getElementById('register-modal-close')?.focus();
    });

    // Boutons mobile nav
    document.querySelectorAll('[data-open-modal="login-modal"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('header-nav-mobile')?.classList.remove('is-open');
            document.getElementById('header-burger')?.setAttribute('aria-expanded', 'false');
            openModal();
        });
    });

    trigger.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
    });

    // Toggle password visibility
    modal.querySelectorAll('.login-modal__pwd-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.querySelector('.pwd-eye--show').hidden = isHidden;
            btn.querySelector('.pwd-eye--hide').hidden = !isHidden;
            btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    // Expose pour le reset modal (token invalide → ouvrir panel forgot)
    window.__openLoginModalForgot = openModalForgot;

    if (window.__forgotSuccess) openModalForgot();
}

// ============================================================
// Register modal
// ============================================================

function initRegisterModal() {
    const modal    = document.getElementById('register-modal');
    const closeBtn = document.getElementById('register-modal-close');
    const backdrop = document.getElementById('register-modal-backdrop');
    const toLogin  = document.getElementById('register-to-login');

    if (!modal) return;

    function openModal() {
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeBtn?.focus();
        resetMatchState();
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (window.__registerSuccess) {
            window.__registerSuccess = false;
            window.location.replace(window.location.pathname);
        }
    }

    // Switch vers login modal
    toLogin?.addEventListener('click', () => {
        closeModal();
        const loginModal = document.getElementById('login-modal');
        const loginTrigger = document.getElementById('login-modal-trigger');
        if (loginModal) {
            loginModal.setAttribute('aria-hidden', 'false');
            loginTrigger?.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            document.getElementById('login-modal-close')?.focus();
        }
    });

    closeBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
    });

    // Toggle individual / company fields
    modal.querySelectorAll('[name="account_type"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const isCompany = radio.value === 'company';
            modal.querySelector('.js-reg-individual')?.toggleAttribute('hidden', isCompany);
            modal.querySelector('.js-reg-company')?.toggleAttribute('hidden', !isCompany);
        });
    });

    // Toggle password visibility
    modal.querySelectorAll('.register-modal__pwd-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.querySelector('.pwd-eye--show').hidden = isHidden;
            btn.querySelector('.pwd-eye--hide').hidden = !isHidden;
            btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    // ── Validation en temps réel : correspondance des mots de passe ──────────
    const pwdInput     = modal.querySelector('#reg-password');
    const confirmInput = modal.querySelector('#reg-password-confirm');
    const matchError   = modal.querySelector('.js-pwd-match-error');
    const submitBtn    = modal.querySelector('.register-modal__submit');

    function checkPasswordMatch() {
        const mismatch = confirmInput.value.length > 0
                      && confirmInput.value.length >= pwdInput.value.length
                      && pwdInput.value !== confirmInput.value;

        // Bloque la soumission HTML5 native + désactive le bouton
        confirmInput.setCustomValidity(mismatch ? 'mismatch' : '');
        if (submitBtn) submitBtn.disabled = mismatch;

        // Affiche / masque le message d'erreur inline
        if (matchError) {
            matchError.textContent = confirmInput.dataset.mismatchLabel
                                  ?? 'Les mots de passe ne correspondent pas.';
            matchError.hidden = !mismatch;
        }
    }

    // Réinitialise l'état à chaque ouverture (évite les résidus de soumission précédente)
    function resetMatchState() {
        confirmInput.setCustomValidity('');
        if (submitBtn) submitBtn.disabled = false;
        if (matchError) matchError.hidden = true;
    }

    pwdInput?.addEventListener('input', checkPasswordMatch);
    confirmInput?.addEventListener('input', checkPasswordMatch);

    // Auto-open si erreurs de soumission
    if (window.__authRegisterOpen) openModal();

    // Boutons mobile nav
    document.querySelectorAll('[data-open-modal="register-modal"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('header-nav-mobile')?.classList.remove('is-open');
            document.getElementById('header-burger')?.setAttribute('aria-expanded', 'false');
            openModal();
        });
    });
}

// ============================================================
// Cart guest button (bouton panier header — non connecté)
// Les invités peuvent consulter leur panier — redirection vers /panier
// ============================================================

function initCartGuestButton() {
    document.querySelectorAll('.js-cart-login-prompt').forEach((btn) => {
        btn.addEventListener('click', () => {
            const cartUrl = '/' + (window.__navLang || 'fr') + '/panier';
            window.location.href = cartUrl; // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression — cartUrl is built from server-rendered window.__navLang (htmlspecialchars)
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
// Favoris — toggle AJAX (connecté uniquement)
// ============================================================

function initFavoriteToggle() {
    // Cœur brisé au survol des boutons déjà likés
    document.addEventListener('mouseover', (e) => {
        const btn = e.target.closest('.js-favorite.is-liked');
        if (!btn) return;
        const iconEl = btn.querySelector('.js-favorite-icon');
        if (iconEl) iconEl.textContent = '\uD83D\uDC94';
        else        btn.textContent    = '\uD83D\uDC94';
    });
    document.addEventListener('mouseout', (e) => {
        const btn = e.target.closest('.js-favorite.is-liked');
        if (!btn) return;
        const iconEl = btn.querySelector('.js-favorite-icon');
        if (iconEl) iconEl.textContent = '\u2665';
        else        btn.textContent    = '\u2665';
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-favorite');
        if (!btn || !window.__userLogged) return;

        const wineId = parseInt(btn.dataset.wineId, 10);
        if (!wineId) return;

        btn.disabled = true;

        fetch('/api/favorites/toggle', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ wine_id: wineId }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) return;
                const liked   = data.liked;
                const iconEl  = btn.querySelector('.js-favorite-icon');
                const labelEl = btn.querySelector('.js-favorite-label');
                const isEn    = document.documentElement.lang === 'en';

                btn.dataset.liked = liked ? 'true' : 'false';
                btn.classList.toggle('is-liked', liked);
                btn.setAttribute('aria-pressed', liked ? 'true' : 'false');

                if (iconEl)       iconEl.textContent  = liked ? '\u2665' : '\u2661';
                else              btn.textContent      = liked ? '\u2665' : '\u2661';
                if (labelEl) labelEl.textContent = liked
                    ? (isEn ? 'Remove from favourites' : 'Retirer des favoris')
                    : (isEn ? 'Add to favourites'      : 'Ajouter aux favoris');

                // Sync all other buttons + counters for this wine on the page
                document.querySelectorAll(`.js-favorite[data-wine-id="${wineId}"]`).forEach((other) => {
                    if (other === btn) return;
                    other.dataset.liked = liked ? 'true' : 'false';
                    other.classList.toggle('is-liked', liked);
                    other.setAttribute('aria-pressed', liked ? 'true' : 'false');
                    const otherIcon = other.querySelector('.js-favorite-icon');
                    if (otherIcon) otherIcon.textContent = liked ? '\u2665' : '\u2661';
                    else           other.textContent      = liked ? '\u2665' : '\u2661';
                });
                document.querySelectorAll(`.wine-card__likes-count[data-wine-id="${wineId}"]`).forEach((counter) => {
                    const current = parseInt(counter.textContent, 10) || 0;
                    counter.textContent = liked ? current + 1 : Math.max(0, current - 1);
                });
            })
            .catch(() => {
                showToast(
                    document.documentElement.lang === 'en'
                        ? 'An error occurred. Please try again.'
                        : 'Une erreur est survenue. Veuillez r\u00e9essayer.',
                    false
                );
            })
            .finally(() => { btn.disabled = false; });
    });
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

// ============================================================
// Formulaire de contact — validation + fetch + feedback
// ============================================================


// ============================================================
// Formulaire newsletter footer — AJAX + feedback
// ============================================================

function initNewsletterForm() {
    const form = document.getElementById('footer-newsletter-form');
    if (!form) return;

    const feedback = form.querySelector('.footer-newsletter__feedback');
    const input    = form.querySelector('input[name="email"]');
    const submit   = form.querySelector('button[type="submit"]');

    let timer = null;
    function showFeedback(msg, isSuccess) {
        clearTimeout(timer);
        feedback.textContent = msg;
        feedback.className   = 'footer-newsletter__feedback footer-newsletter__feedback--' + (isSuccess ? 'success' : 'error');
        feedback.hidden      = false;
        if (isSuccess) {
            timer = setTimeout(() => { feedback.hidden = true; }, 6000);
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = input.value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFeedback(form.dataset.msgInvalid, false);
            input.focus();
            return;
        }

        submit.disabled = true;
        feedback.hidden = true;

        try {
            const res  = await fetch(form.action, {
                method:  'POST',
                body:    new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            if (data.success) {
                showFeedback(data.message || form.dataset.msgSuccess, true);
                form.reset();
            } else {
                showFeedback(data.error || form.dataset.msgError, false);
            }
        } catch {
            showFeedback(form.dataset.msgError, false);
        } finally {
            submit.disabled = false;
        }
    });
}
function initContactForm() {
    const form     = document.getElementById('contact-form');
    if (!form) return;

    const feedback = document.getElementById('contact-feedback');
    const submit   = document.getElementById('contact-submit');
    const label    = submit?.querySelector('.btn__label');
    const spinner  = submit?.querySelector('.btn__spinner');

    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');

    const rgpdError = document.getElementById('rgpd-error');
    const rgpdInput = form.querySelector('input[name="rgpd"]');

    let feedbackTimer = null;
    function showFeedback(msg, isSuccess) {
        clearTimeout(feedbackTimer);
        feedback.textContent = msg;
        feedback.className   = 'contact-form__feedback contact-form__feedback--' + (isSuccess ? 'success' : 'error');
        feedback.hidden      = false;
        if (isSuccess) {
            feedbackTimer = setTimeout(() => { feedback.hidden = true; }, 3000);
        }
    }

    function setLoading(on) {
        submit.disabled  = on;
        label.hidden     = on;
        spinner.hidden   = !on;
    }

    function markInvalid(field) {
        const group = field.closest('.contact-form__group, .contact-form__fieldset, .contact-form__rgpd');
        if (!group) return;
        group.classList.remove('is-invalid');
        void group.offsetWidth; // force reflow pour re-déclencher l'animation
        group.classList.add('is-invalid');
        field.addEventListener('change', () => group.classList.remove('is-invalid'), { once: true });
        field.addEventListener('input',  () => group.classList.remove('is-invalid'), { once: true });
    }

    if (rgpdInput && rgpdError) {
        rgpdInput.addEventListener('change', () => { rgpdError.hidden = true; });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validation client
        let valid = true;
        let onlyRgpdMissing = true;
        requiredFields.forEach((field) => {
            const empty = field.type === 'checkbox'
                ? !field.checked
                : (field.type === 'radio'
                    ? !form.querySelector(`input[name="${field.name}"]:checked`)
                    : field.value.trim() === '');
            if (empty) {
                valid = false;
                markInvalid(field);
                if (field.name !== 'rgpd') onlyRgpdMissing = false;
            }
        });

        if (!valid) {
            feedback.hidden = true;
            if (rgpdInput && !rgpdInput.checked && rgpdError) {
                rgpdError.hidden = false;
            }
            if (!onlyRgpdMissing) {
                showFeedback(form.dataset.msgFields, false);
                (form.closest('section') ?? form).scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                rgpdError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }
        if (rgpdError) rgpdError.hidden = true;

        setLoading(true);
        feedback.hidden = true;

        try {
            const res  = await fetch(form.action, {
                method:  'POST',
                body:    new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            if (data.success) {
                showFeedback(data.message || form.dataset.msgSuccess, true);
                form.reset();
            } else {
                showFeedback(data.message || form.dataset.msgError, false);
            }
        } catch {
            showFeedback(form.dataset.msgError, false);
        } finally {
            setLoading(false);
        }
    });
}

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

// ============================================================
// Page intro overlay — animation d'arrivée post age-gate
// ============================================================

function initPageIntro() {
    const hasCookie = document.cookie.split('; ').some((c) => c.startsWith('age_intro=1'));
    if (!hasCookie) return;

    // Consommer le cookie immédiatement (TTL 30s, on ne veut pas rejouer l'anim)
    document.cookie = 'age_intro=; path=/; max-age=0; SameSite=Lax';

    const overlay = document.createElement('div');
    overlay.className = 'page-intro';
    overlay.setAttribute('aria-hidden', 'true');

    const img = document.createElement('img');
    img.src       = '/assets/images/logo/crabitan-bellevue-logo-modern.svg';
    img.alt       = '';
    img.width     = 180;
    img.height    = 180;
    img.className = 'page-intro__logo';
    overlay.appendChild(img);

    const welcome = document.createElement('p');
    welcome.className   = 'page-intro__welcome';
    welcome.textContent = document.documentElement.lang === 'en' ? 'Welcome' : 'Bienvenue';
    overlay.appendChild(welcome);

    document.body.appendChild(overlay);

    // Suppression après la fin de l'animation de l'overlay uniquement
    // (animationend bubble — on filtre sur e.target pour ignorer les enfants)
    overlay.addEventListener('animationend', (e) => {
        if (e.target !== overlay) return;
        overlay.remove();
    });
}

// ============================================================
// Widget météo — carousel héro (proxy /api/meteo → WeatherAPI.com)
// ============================================================

const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin',
    'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const MONTHS_EN = ['January','February','March','April','May','June',
    'July','August','September','October','November','December'];

async function initWeatherWidget() {
    const widget = document.getElementById('weather-widget');
    if (!widget) return;

    const lang   = window.__navLang === 'en' ? 'en' : 'fr';
    const months = lang === 'en' ? MONTHS_EN : MONTHS_FR;
    const month  = months[new Date().getMonth()];

    try {
        const res = await fetch('/api/meteo?lang=' + lang);
        if (!res.ok) return;
        const data = await res.json();
        if (data.error) return;

        const line1 = document.createElement('span');
        line1.className = 'carousel__weather-top';
        line1.textContent = `${month} \u2014 ${data.condition}`;

        const line2 = document.createElement('span');
        line2.className = 'carousel__weather-bottom';
        line2.textContent = `${data.tmin}\u00b0 \u2192 ${data.tmax}\u00b0 \u00b7 ${data.wind}\u00a0km/h`;

        const credit = widget.querySelector('.carousel__weather-credit');
        widget.replaceChildren(line1, line2);
        if (credit) widget.append(credit);
        widget.removeAttribute('hidden');
    } catch {
        // Silencieux — le widget reste masqué si l'API est inaccessible
    }
}

// ============================================================
// Reset password modal
// ============================================================

function initResetModal() {
    const modal    = document.getElementById('reset-modal');
    const closeBtn = document.getElementById('reset-modal-close');
    const backdrop = document.getElementById('reset-modal-backdrop');
    const form     = document.getElementById('reset-modal-form');

    if (!modal) return;

    if (form && window.__resetToken) {
        form.action = '/' + window.__navLang + '/reinitialisation/' + window.__resetToken;
    }

    function openModal() {
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeBtn?.focus();
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (window.__resetSuccess) {
            window.location.replace('/' + (window.__navLang || 'fr'));
        }
    }

    if (window.__resetOpen) openModal();

    // Succès reset : modal ouvert avec message, redirection automatique vers homepage après 3 s
    if (window.__resetSuccess) {
        openModal();
        setTimeout(() => { // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression
            window.location.href = '/' + (window.__navLang || 'fr');
        }, 3000);
    }

    closeBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);

    document.getElementById('reset-to-forgot')?.addEventListener('click', () => {
        closeModal();
        if (typeof window.__openLoginModalForgot === 'function') window.__openLoginModalForgot();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
    });

    modal.querySelectorAll('.login-modal__pwd-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.querySelector('.pwd-eye--show').hidden = isHidden;
            btn.querySelector('.pwd-eye--hide').hidden = !isHidden;
            btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
}

// ============================================================
// Indicateur de force mot de passe (ANSSI MDP 2021)
// Cible tous les champs [data-pwd-strength] de la page.
// ============================================================

/**
 * Injecte une checklist de force sous chaque champ password
 * portant l'attribut data-pwd-strength="<uid>".
 * Les IDs de champ attendus : reg-password, new_password, reset-modal-password.
 */
function initPasswordStrengthIndicator() {
    const SPECIAL = /[!@#$%^&*()\-_+=[[\]{}|;:,.<>?]/;

    const rules = [
        { id: 'len',     re: null,       label: '12 caractères minimum' },
        { id: 'upper',   re: /[A-Z]/,    label: 'Une majuscule' },
        { id: 'lower',   re: /[a-z]/,    label: 'Une minuscule' },
        { id: 'digit',   re: /[0-9]/,    label: 'Un chiffre' },
        { id: 'special', re: SPECIAL,    label: 'Un caractère spécial (!@#$%…)' },
    ];

    /**
     * Crée la checklist HTML et l'insère après le conteneur du champ.
     * @param {HTMLInputElement} input
     * @param {string} uid - identifiant unique pour l'aria
     * @returns {HTMLElement} l'élément <ul> inséré
     */
    function createChecklist(input, uid) {
        const ul = document.createElement('ul');
        ul.id = 'pwd-strength-' + uid;
        ul.className = 'pwd-strength-list';
        ul.setAttribute('aria-live', 'polite');
        ul.setAttribute('aria-label', 'Critères du mot de passe');

        rules.forEach((rule) => {
            const li = document.createElement('li');
            li.id = 'pwd-rule-' + uid + '-' + rule.id;
            li.className = 'pwd-strength-item';
            li.innerHTML = '<span class="pwd-strength-icon" aria-hidden="true">✗</span> ' + rule.label;
            ul.appendChild(li);
        });

        // Insérer après le parent immédiat (form-group, register-modal__field…)
        const container = input.closest('.form-group, .register-modal__field, .login-modal__field') ?? input.parentElement;
        container.insertAdjacentElement('afterend', ul);
        return ul;
    }

    /**
     * Met à jour les items de la checklist selon la valeur courante.
     * @param {HTMLInputElement} input
     * @param {HTMLElement} ul
     */
    function updateChecklist(input, ul) {
        const val = input.value;
        const checks = [
            val.length >= 12,
            /[A-Z]/.test(val),
            /[a-z]/.test(val),
            /[0-9]/.test(val),
            SPECIAL.test(val),
        ];
        ul.querySelectorAll('.pwd-strength-item').forEach((li, i) => {
            const ok = checks[i];
            li.classList.toggle('pwd-strength-item--ok', ok);
            li.classList.toggle('pwd-strength-item--ko', !ok);
            li.querySelector('.pwd-strength-icon').textContent = ok ? '✓' : '✗';
        });
    }

    // Champs ciblés : inscription (modal), reset password (modal), changement mdp (page sécurité)
    const targetIds = ['reg-password', 'new_password', 'reset-modal-password'];
    targetIds.forEach((id) => {
        const input = document.getElementById(id);
        if (!input) return;
        const ul = createChecklist(input, id);
        input.addEventListener('input', () => updateChecklist(input, ul));
    });
}

// ============================================================
// Espace compte — toggle mot de passe (page sécurité)
// ============================================================

function initAccountPasswordToggle() {
    document.querySelectorAll('.form-pwd-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.querySelector('.pwd-eye--show').hidden = isHidden;
            btn.querySelector('.pwd-eye--hide').hidden = !isHidden;
        });
    });
}

// ============================================================
// Espace compte — confirmation avant soumission (data-confirm)
// ============================================================

function initConfirmForms() {
    // Injecte un modal de confirmation générique (réutilise les styles .account-delete-modal)
    const modal = document.createElement('div');
    modal.id = 'js-confirm-modal';
    modal.className = 'account-delete-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'js-confirm-modal-title');
    modal.hidden = true;
    modal.innerHTML = `
        <div class="account-delete-modal__backdrop" id="js-confirm-backdrop"></div>
        <div class="account-delete-modal__inner">
            <h2 id="js-confirm-modal-title" class="account-delete-modal__title"></h2>
            <p class="account-delete-modal__body" id="js-confirm-modal-body"></p>
            <div class="account-delete-modal__actions">
                <button type="button" class="btn btn--danger" id="js-confirm-ok">Confirmer</button>
                <button type="button" class="btn btn--ghost" id="js-confirm-cancel">Annuler</button>
            </div>
        </div>`;
    document.body.appendChild(modal);

    const titleEl    = modal.querySelector('#js-confirm-modal-title');
    const bodyEl     = modal.querySelector('#js-confirm-modal-body');
    const okBtn      = modal.querySelector('#js-confirm-ok');
    const cancelBtn  = modal.querySelector('#js-confirm-cancel');
    const backdrop   = modal.querySelector('#js-confirm-backdrop');

    let pendingForm = null;

    function openConfirm(form, msg) {
        pendingForm = form;
        // Le message peut contenir un pipe "Titre|Corps" ou être une simple string
        const parts = msg.split('|');
        if (titleEl) titleEl.textContent = parts[0] ?? '';
        if (bodyEl)  bodyEl.textContent  = parts[1] ?? '';
        modal.hidden = false;
        okBtn?.focus();
    }

    function closeConfirm() {
        modal.hidden = true;
        pendingForm  = null;
    }

    okBtn?.addEventListener('click', () => {
        if (pendingForm) {
            // Soumettre le formulaire en bypassant l'écouteur submit
            pendingForm.removeAttribute('data-confirm');
            pendingForm.submit();
        }
        closeConfirm();
    });

    cancelBtn?.addEventListener('click', closeConfirm);
    backdrop?.addEventListener('click', closeConfirm);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) closeConfirm();
    });

    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (!form) return;
        e.preventDefault();
        openConfirm(form, form.dataset.confirm ?? '');
    });
}

// ============================================================
// Espace compte — toggle formulaire ajout adresse
// ============================================================

function initAlertAutoDismiss() {
    document.querySelectorAll('.alert--success:not([data-no-auto-dismiss])').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 400ms ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 2500);
    });
}

function initAddressAddToggle() {
    const section = document.getElementById('address-add-form');
    if (!section) return;

    document.querySelectorAll('.js-address-add-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const isHidden = section.hidden;
            section.hidden = !isHidden;
            if (!isHidden) return;
            section.querySelector('input:not([type="hidden"]),[select]')?.focus();
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

// ============================================================
// Espace compte — suppression carte favori après unlike
// ============================================================

function initAccountFavoritesRemove() {
    const grid = document.querySelector('.account-favorites-grid');
    if (!grid) return;

    grid.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-account-fav-remove');
        if (!btn) return;

        const wineId = parseInt(btn.dataset.wineId, 10);
        if (!wineId) return;

        btn.disabled = true;

        fetch('/api/favorites/toggle', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ wine_id: wineId }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (!data.success || data.liked) {
                    btn.disabled = false;
                    return;
                }
                const card = btn.closest('li');
                if (!card) return;
                card.style.transition = 'opacity .3s';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    if (!grid.querySelector('li')) {
                        grid.closest('.account-content')
                            ?.querySelector('.account-empty')
                            ?.removeAttribute('hidden');
                    }
                }, 320);
            })
            .catch(() => { btn.disabled = false; });
    });
}

// ============================================================
// Espace compte — modal réinitialisation sécurité
// ============================================================

function initResetSecurityModal() {
    const modal    = document.getElementById('reset-security-modal');
    if (!modal) return;

    const openBtn  = document.getElementById('js-open-reset-modal');
    const closeBtn = document.getElementById('js-close-reset-modal');
    const backdrop = document.getElementById('js-reset-security-backdrop');
    const pwdInput = modal.querySelector('input[name="password"]');

    function openModal() {
        modal.hidden = false;
        pwdInput?.focus();
    }

    function closeModal() {
        modal.hidden = true;
        if (pwdInput) pwdInput.value = '';
    }

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    if (modal.hasAttribute('data-has-error')) openModal();
}

// ============================================================
// Espace compte — modal confirmation suppression de compte
// ============================================================

function initDeleteAccountModal() {
    const modal     = document.getElementById('delete-modal');
    if (!modal) return;

    const openBtn   = document.getElementById('js-open-delete-modal');
    const closeBtn  = document.getElementById('js-close-delete-modal');
    const backdrop  = document.getElementById('js-delete-modal-backdrop');
    const submitBtn = document.getElementById('js-delete-submit');
    const confirmTxt = modal.querySelector('input[name="confirm_text"]');

    const pwdGroup = document.getElementById('js-delete-pwd-group');
    const pwdInput = modal.querySelector('input[name="confirm_password"]');

    function checkConfirmText() {
        if (!submitBtn || !confirmTxt) return;
        const ok = confirmTxt.value.trim().toUpperCase() === 'SUPPRESSION';
        if (pwdGroup) {
            pwdGroup.style.display = ok ? '' : 'none';
            if (ok && pwdInput) pwdInput.focus();
        }
        submitBtn.disabled = !ok;
    }

    function openModal() {
        modal.hidden = false;
        (confirmTxt ?? modal.querySelector('input[name="confirm_password"]'))?.focus();
    }

    function closeModal() {
        modal.hidden = true;
        if (pwdInput) pwdInput.value = '';
        if (confirmTxt) {
            confirmTxt.value = '';
            checkConfirmText();
        }
    }

    confirmTxt?.addEventListener('input', checkConfirmText);

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    // Auto-ouvrir si erreur de validation (retour serveur)
    if (modal.hasAttribute('data-has-error')) {
        openModal();
    }
}

// ============================================================
// Page panier — interactions connecté + affichage invité
// ============================================================

function initCartPage() {
    if (!document.querySelector('.page-cart')) return;

    const isEn = document.documentElement.lang === 'en';

    // ------------------------------------------------------------------
    // Invité : construire l'affichage depuis le cookie cb-cart
    // ------------------------------------------------------------------
    if (!window.__userLogged) {
        const guestContainer = document.getElementById('cart-guest');
        if (!guestContainer) return;

        const localItems = getLocalCart(); // [{id, qty}]

        if (!localItems.length) {
            guestContainer.querySelector('.cart-guest__loading').textContent =
                isEn ? 'Your cart is empty.' : 'Votre panier est vide.';
            return;
        }

        // Récupère nom, image et prix depuis la BDD
        const ids = localItems.map((i) => i.id).join(',');
        fetch('/api/cart/details?ids=' + ids)
            .then((r) => r.json())
            .then((details) => {
                const detailsMap = {};
                details.forEach((d) => { detailsMap[d.wine_id] = d; });

                const ul = document.createElement('ul');
                ul.className = 'cart-guest__list';

                localItems.forEach((item) => {
                    const d         = detailsMap[item.id] ?? {};
                    const name      = d.name  || 'Vin';
                    const image     = d.image || '';
                    const priceNote = isEn ? 'Price calculated at checkout' : 'Prix calculé à la commande';
                    const qtyLabel  = isEn ? 'Qty' : 'Qté';

                    const li = document.createElement('li');
                    li.className = 'cart-guest__item';

                    const imgHtml = image
                        ? `<img src="${image}" alt="${name}" class="cart-guest__img" width="48" height="48" loading="lazy">`
                        : '';

                    li.innerHTML = `
                        ${imgHtml}
                        <div class="cart-guest__info">
                            <p class="cart-guest__name">${name}</p>
                            <p class="cart-guest__qty">${qtyLabel}\u00a0: ${item.qty || 1}</p>
                            <p class="cart-guest__price-note">${priceNote}</p>
                        </div>`;
                    ul.appendChild(li);
                });

                guestContainer.replaceChildren(ul);
            })
            .catch(() => {
                guestContainer.querySelector('.cart-guest__loading').textContent =
                    isEn ? 'Unable to load cart.' : 'Impossible de charger le panier.';
            });

        // CTA "Se connecter pour commander" → ouvre le modal de connexion en place
        document.querySelector('.js-open-login-from-cart')?.addEventListener('click', () => {
            document.getElementById('login-modal-trigger')?.click();
        });
        return;
    }

    // ------------------------------------------------------------------
    // Connecté : recalcul total + handlers quantité / suppression
    // ------------------------------------------------------------------

    const csrfInput = document.getElementById('cart-modal-csrf');
    const getCSRF   = () => csrfInput?.value ?? '';

    /**
     * Recalcule et affiche le grand total à partir des données du DOM.
     */
    function updateCartTotal() {
        let total = 0;
        document.querySelectorAll('.cart-table__row').forEach((row) => {
            const price = parseFloat(row.dataset.price) || 0;
            const qty   = parseInt(row.querySelector('.js-cart-qty')?.value, 10) || 0;
            const subtotalEl = row.querySelector('.js-cart-subtotal');
            const sub = price * qty;
            total += sub;
            if (subtotalEl) {
                subtotalEl.textContent = sub.toLocaleString(isEn ? 'en-GB' : 'fr-FR', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                }) + '\u00a0€';
            }
        });
        const totalEl = document.getElementById('cart-total');
        if (totalEl) {
            totalEl.textContent = total.toLocaleString(isEn ? 'en-GB' : 'fr-FR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2,
            }) + '\u00a0€';
        }
    }

    // Debounce helper (400 ms)
    let qtyDebounceTimer = null;
    function debounceQty(fn) {
        clearTimeout(qtyDebounceTimer);
        qtyDebounceTimer = setTimeout(fn, 400);
    }

    // Délégation — changement quantité
    document.addEventListener('change', (e) => {
        const input = e.target.closest('.js-cart-qty');
        if (!input) return;

        const wineId = parseInt(input.dataset.wineId, 10);
        const qty    = Math.max(1, parseInt(input.value, 10) || 1);
        input.value  = qty;

        debounceQty(async () => {
            try {
                const res  = await fetch('/api/cart/update', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ wine_id: wineId, quantity: qty, csrf_token: getCSRF() }),
                });
                const data = await res.json();
                if (!data.success) throw new Error('update failed');
                updateCartCount(data.total_quantity ?? null);
            } catch {
                showToast(isEn ? 'Failed to update quantity.' : 'Échec de la mise à jour.', true);
            }
            updateCartTotal();
        });
    });

    // Délégation — suppression d'article
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-cart-remove');
        if (!btn) return;

        const wineId = parseInt(btn.dataset.wineId, 10);
        btn.disabled = true;

        fetch('/api/cart/remove', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ wine_id: wineId, csrf_token: getCSRF() }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) throw new Error('remove failed');

                const row = btn.closest('.cart-table__row');
                if (row) {
                    row.style.transition = 'opacity 250ms ease';
                    row.style.opacity    = '0';
                    setTimeout(() => {
                        row.remove();
                        updateCartTotal();

                        // Panier vide après suppression
                        const tbody = document.getElementById('cart-tbody');
                        if (tbody && !tbody.querySelector('.cart-table__row')) {
                            const section = document.querySelector('.cart-section');
                            if (section) {
                                section.innerHTML = `
                                    <div class="cart-empty-state">
                                        <p class="cart-empty">${isEn ? 'Your cart is empty' : 'Votre panier est vide'}</p>
                                        <a href="/${window.__navLang || 'fr'}/vins" class="btn btn--outline">
                                            ${isEn ? 'Browse wines' : 'Voir les vins'}
                                        </a>
                                    </div>`;
                            }
                        }
                    }, 260);
                }
                updateCartCount(data.total_quantity ?? null);
            })
            .catch(() => {
                btn.disabled = false;
                showToast(isEn ? 'Failed to remove item.' : 'Échec de la suppression.', true);
            });
    });

    // Calcul initial
    updateCartTotal();
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.__flashInfo) showToast(window.__flashInfo, false, 2000);
    initPageIntro();
    initTheme();
    initThemeToggle();
    initBurger();
    initAgeGate();
    initCookieBanner();
    initCarousel();
    initAccountPanel();
    initWeatherWidget();
    initLoginModal();
    initRegisterModal();
    initResetModal();
    initCartModal();
    initCartGuestButton();
    initFavoriteAuth();
    initFavoriteToggle();
    initAccountFavoritesRemove();
    initPasswordStrengthIndicator();
    initAccountPasswordToggle();
    initResetSecurityModal();
    initDeleteAccountModal();
    initConfirmForms();
    initAddressAddToggle();
    initAlertAutoDismiss();
    initWineZoom();
    // Pour les connectés : charger le compteur depuis l'API au chargement de page (si disponible)
    if (window.__userLogged) {
        fetch('/api/cart/count')
            .then((r) => r.ok ? r.json() : null)
            .then((data) => { if (data && typeof data.total_quantity === 'number') updateCartCount(data.total_quantity); })
            .catch(() => { /* fallback sur cookie local déjà affiché */ });
    } else {
        updateCartCount();
    }
    initNewsletterForm();
    initContactForm();
    initAnchorScroll();
    initFaqAccordion();
    initCarbonBadge();
    initCartPage();

    // Chargement à la demande — uniquement sur la page jeux
    if (document.getElementById('memo-game')) {
        import('./memo-game.js').then((m) => m.initMemoGame());
    }
    if (document.getElementById('runner-game')) {
        import('./runner-game.js').then((m) => m.initRunnerGame());
    }
    if (document.getElementById('labour-chrono-game')) {
        import('./labour-chrono.js').then((m) => m.initLabourChronoGame());
    }
    if (document.getElementById('tonneau-catapulte-game')) {
        import('./tonneau-catapulte.js').then((m) => m.initTonneauCatapulteGame());
    }
    if (document.getElementById('vendange-express-game')) {
        import('./vendange-express.js').then((m) => m.initVendangeExpressGame());
    }
});
