<?php
// Standalone error page — ne dépend pas de __() ni des partials
$statusCode = $statusCode ?? 404;

// Détection de la langue (CURRENT_LANG défini si le routeur a déjà résolu la route)
$lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';
if (!defined('CURRENT_LANG')) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if (preg_match('#^/en(/|$)#', $uri)) {
        $lang = 'en';
    }
}

$appName = defined('APP_NAME') ? APP_NAME : 'Château Crabitan Bellevue';

$labels = [
    400 => ['fr' => 'Requête invalide',         'en' => 'Bad Request'],
    403 => ['fr' => 'Accès interdit',            'en' => 'Forbidden'],
    404 => ['fr' => 'Page introuvable',          'en' => 'Page Not Found'],
    405 => ['fr' => 'Méthode non autorisée',     'en' => 'Method Not Allowed'],
    429 => ['fr' => 'Trop de requêtes',          'en' => 'Too Many Requests'],
];

$descriptions = [
    400 => [
        'fr' => 'Votre requête ne peut pas être traitée.',
        'en' => 'Your request could not be processed.',
    ],
    403 => [
        'fr' => 'Vous n\'avez pas les droits pour accéder à cette page.',
        'en' => 'You do not have permission to access this page.',
    ],
    404 => [
        'fr' => 'La page que vous recherchez est introuvable ou a été déplacée.',
        'en' => 'The page you are looking for cannot be found or has been moved.',
    ],
    405 => [
        'fr' => 'Cette méthode HTTP n\'est pas autorisée sur cette ressource.',
        'en' => 'This HTTP method is not allowed on this resource.',
    ],
    429 => [
        'fr' => 'Vous avez effectué trop de requêtes. Veuillez patienter un instant.',
        'en' => 'You have made too many requests. Please wait a moment.',
    ],
];

$title   = $labels[$statusCode][$lang]       ?? ($lang === 'en' ? 'Error'       : 'Erreur');
$desc    = $descriptions[$statusCode][$lang] ?? ($lang === 'en' ? 'An error occurred.' : 'Une erreur est survenue.');
$homeUrl = '/' . $lang;
$homeLbl = $lang === 'en' ? '← Back to home' : '← Retour à l\'accueil';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $statusCode ?> — <?= htmlspecialchars($title) ?> — <?= htmlspecialchars($appName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/assets/images/crabitan-bellevue-logo.png">
    <link rel="stylesheet" href="/assets/css/main.css">
    <script>
        // Restore theme preference without flash
        (function () {
            var t = localStorage.getItem('cb-theme');
            document.documentElement.dataset.theme = t === 'light' ? 'light' : 'dark';
        }());
    </script>
</head>
<body>
    <main class="error-page" id="main-content">
        <div class="error-page__inner">
            <a href="<?= htmlspecialchars($homeUrl) ?>" class="error-page__logo"
               aria-label="<?= htmlspecialchars($appName) ?>">
                <img src="/assets/images/crabitan-bellevue-logo-modern.svg" alt="" width="72" height="72">
            </a>

            <p class="error-page__code"><?= $statusCode ?></p>
            <div class="home-section__divider home-section__divider--center"></div>
            <h1 class="error-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="error-page__desc"><?= htmlspecialchars($desc) ?></p>

            <a href="<?= htmlspecialchars($homeUrl) ?>" class="btn btn--gold error-page__btn">
                <?= htmlspecialchars($homeLbl) ?>
            </a>
        </div>
    </main>
</body>
</html>
