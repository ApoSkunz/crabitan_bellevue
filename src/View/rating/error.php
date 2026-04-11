<?php
/** @var string $lang   Code langue courant */
/** @var string $reason Raison de l'erreur : 'invalid_note' | 'invalid_token' */

$isInvalidToken = ($reason ?? '') === 'invalid_token';

if ($lang === 'fr') {
    $pageTitle = 'Lien invalide';
    $heading   = $isInvalidToken ? 'Lien invalide ou déjà utilisé' : 'Note invalide';
    $message   = $isInvalidToken
        ? 'Ce lien de notation est invalide ou a déjà été utilisé.'
        : 'La note doit être comprise entre 1 et 5.';
} else {
    $pageTitle = 'Invalid link';
    $heading   = $isInvalidToken ? 'Invalid or already used link' : 'Invalid rating';
    $message   = $isInvalidToken
        ? 'This rating link is invalid or has already been used.'
        : 'The rating must be between 1 and 5.';
}
?>
<?php require_once SRC_PATH . '/View/partials/head.php'; ?>
<?php require_once SRC_PATH . '/View/partials/header.php'; ?>

<main class="rating-error">
    <div class="rating-error__card">
        <h1 class="rating-error__title"><?= htmlspecialchars($heading) ?></h1>
        <p class="rating-error__message"><?= htmlspecialchars($message) ?></p>
        <a href="/<?= htmlspecialchars($lang) ?>" class="btn btn--primary rating-error__cta">
            <?= $lang === 'fr' ? 'Retour à l\'accueil' : 'Back to home' ?>
        </a>
    </div>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
