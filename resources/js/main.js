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
        if (!getConsentCookie()) {
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
const COOKIE_CONSENT_TTL = 397 * 24 * 3600; // 13 mois — durée max CNIL/RGPD

function getConsentCookie() {
    const match = document.cookie.split('; ').find((c) => c.startsWith(COOKIE_CONSENT_KEY + '='));
    return match ? decodeURIComponent(match.slice(COOKIE_CONSENT_KEY.length + 1)) : null;
}

function setConsentCookie(value) {
    document.cookie = COOKIE_CONSENT_KEY + '=' + encodeURIComponent(value)
        + '; path=/; max-age=' + COOKIE_CONSENT_TTL + '; SameSite=Lax';
}

function shakeCookieBanner(banner) {
    banner.classList.remove('is-shaking');
    // Force reflow pour relancer l'animation
    void banner.offsetWidth;
    banner.classList.add('is-shaking');
    banner.addEventListener('animationend', (e) => {
        if (e.animationName !== 'cookie-shake') return;
        banner.classList.remove('is-shaking');
        // Empêche le CSS "animation: fade-in" de rejouer quand is-shaking est retiré
        banner.style.animationName = 'none';
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
        return;
    }

    const requiredMsg = banner.querySelector('.cookie-banner__required');

    document.getElementById('cookie-accept')?.addEventListener('click', () => {
        setConsentCookie('accepted');
        loadGoogleAnalytics();
        banner.classList.add('is-hidden');
    });

    document.getElementById('cookie-refuse')?.addEventListener('click', () => {
        setConsentCookie('refused');
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
        cart.push(item);
    }
    saveLocalCart(cart);
    updateCartCount();
}

function getLocalCartCount() {
    return getLocalCart().reduce((sum, i) => sum + (i.qty || 1), 0);
}

function updateCartCount() {
    const badge = document.querySelector('.header-cart__count');
    if (!badge) return;
    const count = window.__userLogged ? 0 : getLocalCartCount();
    badge.textContent = count;
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
                    ? 'Added to cart!'
                    : 'Ajouté au panier !',
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
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
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
// Cart login prompt (bouton panier header — non connecté)
// ============================================================

function initCartLoginPrompt() {
    document.querySelectorAll('.js-cart-login-prompt').forEach((btn) => {
        btn.addEventListener('click', () => {
            const loginUrl = btn.dataset.loginUrl || ('/' + (window.__navLang || 'fr') + '/connexion');
            showToast(
                document.documentElement.lang === 'en'
                    ? 'Please log in to complete your order.'
                    : 'Connectez-vous pour finaliser votre commande.',
                false
            );
            setTimeout(() => { window.location.href = loginUrl; }, 1500); // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression — loginUrl is server-rendered via htmlspecialchars(), not user input // nosemgrep: javascript.lang.security.detect-eval-with-expression.detect-eval-with-expression
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

// ============================================================
// Formulaire de contact — validation + fetch + feedback
// ============================================================

function initContactForm() {
    const form     = document.getElementById('contact-form');
    if (!form) return;

    const feedback = document.getElementById('contact-feedback');
    const submit   = document.getElementById('contact-submit');
    const label    = submit?.querySelector('.btn__label');
    const spinner  = submit?.querySelector('.btn__spinner');

    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');

    function showFeedback(msg, isSuccess) {
        feedback.textContent = msg;
        feedback.className   = 'contact-form__feedback contact-form__feedback--' + (isSuccess ? 'success' : 'error');
        feedback.hidden      = false;
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

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validation client
        let valid = true;
        requiredFields.forEach((field) => {
            const empty = field.type === 'checkbox'
                ? !field.checked
                : (field.type === 'radio'
                    ? !form.querySelector(`input[name="${field.name}"]:checked`)
                    : field.value.trim() === '');
            if (empty) {
                valid = false;
                markInvalid(field);
            }
        });

        if (!valid) {
            showFeedback(form.dataset.msgFields, false);
            (form.closest('section') ?? form).scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

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
// Widget météo — carousel héro (Open-Meteo, sans clé API)
// ============================================================

const WMO_FR = {
    0: 'Ensoleillé', 1: 'Peu nuageux', 2: 'Nuageux', 3: 'Couvert',
    45: 'Brouillard', 48: 'Brouillard givrant',
    51: 'Bruine légère', 53: 'Bruine', 55: 'Bruine dense',
    61: 'Pluie légère', 63: 'Pluie', 65: 'Pluie forte',
    71: 'Neige légère', 73: 'Neige', 75: 'Neige forte', 77: 'Grésil',
    80: 'Averses légères', 81: 'Averses', 82: 'Averses fortes',
    85: 'Averses de neige', 86: 'Averses de neige fortes',
    95: 'Orageux', 96: 'Orage avec grêle', 99: 'Orage violent',
};

const WMO_EN = {
    0: 'Clear sky', 1: 'Mainly clear', 2: 'Partly cloudy', 3: 'Overcast',
    45: 'Foggy', 48: 'Freezing fog',
    51: 'Light drizzle', 53: 'Drizzle', 55: 'Dense drizzle',
    61: 'Light rain', 63: 'Rain', 65: 'Heavy rain',
    71: 'Light snow', 73: 'Snow', 75: 'Heavy snow', 77: 'Snow grains',
    80: 'Light showers', 81: 'Showers', 82: 'Heavy showers',
    85: 'Snow showers', 86: 'Heavy snow showers',
    95: 'Thunderstorm', 96: 'Thunderstorm & hail', 99: 'Violent storm',
};

const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin',
    'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const MONTHS_EN = ['January','February','March','April','May','June',
    'July','August','September','October','November','December'];

async function initWeatherWidget() {
    const widget = document.getElementById('weather-widget');
    if (!widget) return;

    const lang   = window.__navLang === 'en' ? 'en' : 'fr';
    const months = lang === 'en' ? MONTHS_EN : MONTHS_FR;
    const wmo    = lang === 'en' ? WMO_EN : WMO_FR;
    const month  = months[new Date().getMonth()];

    try {
        const res  = await fetch(
            'https://api.open-meteo.com/v1/forecast'
            + '?latitude=44.58&longitude=-0.27'
            + '&current=weather_code,wind_speed_10m'
            + '&daily=temperature_2m_min,temperature_2m_max'
            + '&timezone=Europe%2FParis'
        );
        if (!res.ok) return;
        const data  = await res.json();
        const code  = data.current?.weather_code ?? 0;
        const wind  = Math.round(data.current?.wind_speed_10m ?? 0);
        const tmin  = Math.round(data.daily?.temperature_2m_min?.[0] ?? 0);
        const tmax  = Math.round(data.daily?.temperature_2m_max?.[0] ?? 0);
        const cond  = wmo[code] ?? (lang === 'en' ? 'Unknown' : 'Inconnu');

        const line1 = document.createElement('span');
        line1.className = 'carousel__weather-top';
        line1.textContent = `${month} \u2014 ${cond}`;

        const line2 = document.createElement('span');
        line2.className = 'carousel__weather-bottom';
        line2.textContent = `${tmin}\u00b0 \u2192 ${tmax}\u00b0 \u00b7 ${wind}\u00a0km/h`;

        widget.replaceChildren(line1, line2);
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
    }

    if (window.__resetOpen) openModal();

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
    initCartLoginPrompt();
    initFavoriteAuth();
    initWineZoom();
    updateCartCount();
    initContactForm();
    initAnchorScroll();
    initFaqAccordion();
    initCarbonBadge();

    // Chargement à la demande — uniquement sur la page jeux
    if (document.getElementById('memo-game')) {
        import('./memo-game.js').then((m) => m.initMemoGame());
    }
});
