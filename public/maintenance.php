<?php
// ============================================================
// Page de maintenance — à servir via .htaccess
// Activer : décommenter le bloc "Maintenance mode" dans .htaccess
// ============================================================
http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance — Château Crabitan Bellevue</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/assets/images/logo/crabitan-bellevue-logo.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ---- Tokens light (défaut) ---- */
        :root {
            --bg:           #faf8f4;
            --surface:      #ffffff;
            --gold:         #9a7520;
            --gold-light:   #b8901e;
            --gold-deco:    #c9a84c;
            --text:         #1a1410;
            --text-muted:   #6b5e4e;
            --border-gold:  rgba(139, 105, 20, 0.30);
            --font-serif:   Georgia, 'Times New Roman', serif;
            --font-sans:    'Helvetica Neue', Arial, sans-serif;
        }

        /* ---- Tokens dark ---- */
        [data-theme="dark"] {
            --bg:           #0a0805;
            --surface:      #13100c;
            --gold:         #c9a84c;
            --gold-light:   #e2c47a;
            --gold-deco:    #c9a84c;
            --text:         #e8e0d0;
            --text-muted:   #8a7f72;
            --border-gold:  rgba(201, 168, 76, 0.35);
        }

        /* ---- Page ---- */
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            transition: background-color 0.25s, color 0.25s;
        }

        /* ---- Toggle thème ---- */
        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: none;
            border: 1px solid rgba(201, 168, 76, 0.55);
            border-radius: 50%;
            width: 2.2rem;
            height: 2.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            color: var(--text);
            transition: color 0.2s, background-color 0.2s;
        }

        .theme-toggle:hover {
            color: var(--gold-deco);
            background-color: rgba(201, 168, 76, 0.12);
        }

        .icon-sun  { display: none; }
        .icon-moon { display: block; }

        [data-theme="light"] .icon-sun  { display: block; }
        [data-theme="light"] .icon-moon { display: none; }

        /* ---- Contenu central ---- */
        .maint {
            text-align: center;
            max-width: 540px;
        }

        .maint__logo {
            width: 160px;
            height: auto;
            margin-bottom: 2.5rem;
        }

        .maint__divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold-deco), transparent);
            margin: 1.25rem auto;
        }

        .maint__title {
            font-family: var(--font-serif);
            font-size: clamp(1.6rem, 5vw, 2.6rem);
            font-weight: 700;
            color: var(--gold);
            letter-spacing: 0.05em;
            line-height: 1.2;
        }

        .maint__subtitle {
            font-family: var(--font-serif);
            font-size: clamp(0.95rem, 2.5vw, 1.15rem);
            color: var(--text-muted);
            margin-top: 0.5rem;
            font-style: italic;
        }

        .maint__message {
            margin-top: 1.75rem;
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--text);
        }

        .maint__message strong {
            color: var(--gold-light);
        }

        .maint__lang {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-style: italic;
        }

        .maint__border {
            margin-top: 2.5rem;
            border-top: 1px solid var(--border-gold);
            padding-top: 1.5rem;
        }

        .maint__footer {
            font-size: 0.8rem;
            color: var(--text-muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="js-theme-toggle" type="button"
            aria-label="Basculer le thème jour / nuit">
        <span class="icon-sun"  aria-hidden="true">&#9728;</span>
        <span class="icon-moon" aria-hidden="true">&#9790;</span>
    </button>

    <main class="maint" role="main">
        <img
            src="/assets/images/logo/crabitan-bellevue-logo-modern.svg"
            alt="Château Crabitan Bellevue"
            class="maint__logo"
            width="160"
            height="160"
        >

        <h1 class="maint__title">Château Crabitan Bellevue</h1>
        <p class="maint__subtitle">Appellation Sainte-Croix-du-Mont Contrôlée</p>

        <div class="maint__divider" aria-hidden="true"></div>

        <div class="maint__message">
            <p>
                Notre site est actuellement en <strong>maintenance</strong><br>
                Nous serons de retour très prochainement
            </p>
            <p class="maint__lang">
                Our website is currently under maintenance<br>
                We will be back shortly
            </p>
        </div>

        <div class="maint__border">
            <p class="maint__footer">Château Crabitan Bellevue &mdash; Sainte-Croix-du-Mont</p>
        </div>
    </main>

    <script>
    (function () {
        var THEME_KEY = 'cb-theme';
        var btn  = document.getElementById('js-theme-toggle');
        var html = document.documentElement;

        function applyTheme(theme) {
            html.setAttribute('data-theme', theme);
            try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
        }

        // Priorité : localStorage (même clé que le site) → préférence système → light
        var stored = null;
        try { stored = localStorage.getItem(THEME_KEY); } catch (e) {}
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(stored || (prefersDark ? 'dark' : 'light'));

        btn.addEventListener('click', function () {
            applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    })();
    </script>
</body>
</html>
