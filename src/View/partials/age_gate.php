<?php
/**
 * Age gate — modale de vérification de majorité (Art. L3342-1 CSP).
 * Incluse dans le layout principal avant tout contenu.
 * N'affiche rien si le cookie age_verified est déjà présent.
 *
 * @var string $lang
 */
$showAgeGate = empty($_COOKIE['age_verified']);
if (!$showAgeGate) {
    return;
}

$currentUrl = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');
$gateLang   = $lang ?? DEFAULT_LANG;
?>
<div class="age-gate-overlay" id="age-gate-overlay" role="dialog" aria-modal="true" aria-labelledby="age-gate-overlay-title">
    <div class="age-gate-overlay__backdrop"></div>
    <div class="age-gate-overlay__panel">
        <div class="age-gate-overlay__logo">
            <img src="/assets/images/logo/crabitan-bellevue-logo.png" alt="<?= htmlspecialchars(APP_NAME) ?>" width="100" height="100">
        </div>

        <h2 class="age-gate-overlay__title" id="age-gate-overlay-title">
            <?= $gateLang === 'en' ? 'Welcome' : 'Bienvenue' ?>
        </h2>

        <p class="age-gate-overlay__intro">
            <?= $gateLang === 'en'
                ? 'This website sells alcoholic beverages. You must be of legal drinking age to enter.'
                : 'Ce site commercialise des boissons alcoolisées. Vous devez être majeur pour y accéder.' ?>
        </p>

        <p class="age-gate-overlay__question">
            <?= $gateLang === 'en'
                ? 'Are you of legal drinking age in your country of residence?'
                : 'Avez-vous l\'âge légal pour consommer de l\'alcool dans votre pays de résidence&#160;?' ?>
        </p>

        <div class="age-gate-overlay__actions">
            <form method="POST" action="/<?= htmlspecialchars($gateLang) ?>/age-gate/confirmer" class="age-gate-overlay__form-enter">
                <input type="hidden" name="redirect" value="<?= $currentUrl ?>">
                <button type="submit" class="age-gate-overlay__btn age-gate-overlay__btn--enter">
                    <?= $gateLang === 'en' ? 'Yes, I am of legal age — Enter' : 'Oui, je suis majeur — Entrer' ?>
                </button>
            </form>

            <form method="POST" action="/<?= htmlspecialchars($gateLang) ?>/age-gate/quitter" class="age-gate-overlay__form-exit">
                <button type="submit" class="age-gate-overlay__btn age-gate-overlay__btn--exit">
                    <?= $gateLang === 'en' ? 'No, I am a minor — Leave' : 'Non, je suis mineur — Quitter' ?>
                </button>
            </form>
        </div>

        <p class="age-gate-overlay__legal">
            <?= $gateLang === 'en'
                ? 'L\'abus d\'alcool est dangereux pour la santé. À consommer avec modération. — Alcohol abuse is dangerous for your health. Drink responsibly.'
                : 'L\'abus d\'alcool est dangereux pour la santé. À consommer avec modération.' ?>
        </p>
    </div>
</div>
