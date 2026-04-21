<?php
$pageTitle       = __('review.page_title');
$noindex         = true;
$metaDescription = __('review.page_title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var bool        $form        Afficher le formulaire (true) ou un message d'erreur (false) */
/** @var string|null $error       Message d'erreur (token invalide / expiré) */
/** @var string      $token       Token de commande (htmlspecialchars déjà appliqué) */
/** @var string      $displayName Prénom + initiale nom du client */
/** @var string      $csrf        Token CSRF de session */
/** @var string      $lang        Langue courante */
?>
<main class="auth-page review-form-page">
    <div class="auth-card">
        <?php if (!($form ?? false)) : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✗</span>
            </div>
            <h1><?= htmlspecialchars(__('review.page_title')) ?></h1>
            <p><?= htmlspecialchars($error ?? __('review.invalid_token')) ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= htmlspecialchars(__('nav.home')) ?>
            </a>
        <?php else : ?>
            <h1><?= htmlspecialchars(__('review.page_title')) ?></h1>
            <p class="auth-card__subtitle"><?= htmlspecialchars(__('review.intro')) ?></p>

            <form method="POST" action="/<?= htmlspecialchars($lang) ?>/mon-avis" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="token"      value="<?= htmlspecialchars($token ?? '') ?>">

                <!-- Nom affiché (lecture seule) -->
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('review.your_name')) ?></label>
                    <input
                        type="text"
                        class="form-input"
                        value="<?= htmlspecialchars($displayName ?? '') ?>"
                        readonly
                        aria-readonly="true"
                    >
                </div>

                <!-- Étoiles -->
                <fieldset class="review-stars" aria-required="true">
                    <legend class="form-label"><?= htmlspecialchars(__('review.rating_label')) ?></legend>
                    <div class="review-stars__inputs" role="group">
                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                            <input
                                type="radio"
                                id="star<?= $i ?>"
                                name="rating"
                                value="<?= $i ?>"
                                class="review-star-input"
                                <?= $i === 5 ? 'required' : '' ?>
                            >
                            <label for="star<?= $i ?>" class="review-star-label" aria-label="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">&#9733;</label>
                        <?php endfor; ?>
                    </div>
                </fieldset>

                <!-- Commentaire -->
                <div class="form-group">
                    <label for="review-comment" class="form-label">
                        <?= htmlspecialchars(__('review.comment_label')) ?>
                    </label>
                    <textarea
                        id="review-comment"
                        name="comment"
                        class="form-input review-form__textarea"
                        rows="4"
                        maxlength="500"
                        placeholder="<?= htmlspecialchars(__('review.comment_placeholder')) ?>"
                    ></textarea>
                </div>

                <!-- Localisation -->
                <div class="form-group">
                    <label for="review-location" class="form-label">
                        <?= htmlspecialchars(__('review.location_label')) ?>
                    </label>
                    <input
                        type="text"
                        id="review-location"
                        name="location"
                        class="form-input review-form__location"
                        maxlength="100"
                        placeholder="Paris, Lyon…"
                    >
                </div>

                <button type="submit" class="btn btn--gold btn--full">
                    <?= htmlspecialchars(__('review.submit')) ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>
<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
