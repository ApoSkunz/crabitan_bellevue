<?php
/** @var int    $note Note choisie (1–5) */
/** @var string $lang Code langue courant */

$pageTitle = $lang === 'fr' ? 'Merci pour votre avis !' : 'Thank you for your review!';
$stars     = str_repeat('★', $note) . str_repeat('☆', 5 - $note);
?>
<?php require_once SRC_PATH . '/View/partials/head.php'; ?>
<?php require_once SRC_PATH . '/View/partials/header.php'; ?>

<main class="rating-confirm">
    <div class="rating-confirm__card">
        <h1 class="rating-confirm__title">
            <?= $lang === 'fr' ? 'Merci pour votre avis&nbsp;!' : 'Thank you for your review!' ?>
        </h1>
        <p class="rating-confirm__stars" aria-label="<?= $note ?>/5">
            <?= htmlspecialchars($stars) ?>
        </p>
        <p class="rating-confirm__note">
            <?= $lang === 'fr'
                ? htmlspecialchars("Vous avez noté ce vin {$note}/5.")
                : htmlspecialchars("You rated this wine {$note}/5.") ?>
        </p>
        <a href="/<?= htmlspecialchars($lang) ?>" class="btn btn--primary rating-confirm__cta">
            <?= $lang === 'fr' ? 'Retour à l\'accueil' : 'Back to home' ?>
        </a>
    </div>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
