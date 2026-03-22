<?php
// ============================================================
// Page de maintenance — à servir via .htaccess
// Activer : décommenter le bloc "Maintenance mode" dans .htaccess
// ============================================================
http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance — Château Crabitan Bellevue</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/assets/images/logo/crabitan-bellevue-logo.png">
    <style>
        /* ---- Reset minimal ---- */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ---- Tokens (dark uniquement — page standalone) ---- */
        :root {
            --bg:           #0a0805;
            --surface:      #13100c;
            --gold:         #c9a84c;
            --gold-light:   #e2c47a;
            --text:         #e8e0d0;
            --text-muted:   #8a7f72;
            --border-gold:  rgba(201, 168, 76, 0.35);
            --font-serif:   Georgia, 'Times New Roman', serif;
            --font-sans:    'Helvetica Neue', Arial, sans-serif;
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
        }

        /* ---- Contenu central ---- */
        .maint {
            text-align: center;
            max-width: 540px;
        }

        .maint__logo {
            width: 80px;
            height: auto;
            margin-bottom: 2.5rem;
            opacity: 0.9;
        }

        .maint__divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
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
    <main class="maint" role="main">
        <img
            src="/assets/images/logo/crabitan-bellevue-logo-modern.svg"
            alt="Château Crabitan Bellevue"
            class="maint__logo"
            width="80"
            height="80"
        >

        <h1 class="maint__title">Château Crabitan Bellevue</h1>
        <p class="maint__subtitle">Appellation Sainte-Croix-du-Mont Contrôlée</p>

        <div class="maint__divider" aria-hidden="true"></div>

        <div class="maint__message">
            <p>
                Notre site est actuellement en <strong>maintenance</strong>.<br>
                Nous serons de retour très prochainement.
            </p>
            <p class="maint__lang">
                Our website is currently under maintenance.<br>
                We will be back shortly.
            </p>
        </div>

        <div class="maint__border">
            <p class="maint__footer">Château Crabitan Bellevue &mdash; Sainte-Croix-du-Mont</p>
        </div>
    </main>
</body>
</html>
