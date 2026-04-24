<?php
$pageTitle     = __('account.review_title');
$activeSection = 'review';
$noindex       = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<string, mixed>|null $review */
$saved   = isset($_GET['saved']);
$current = (int) ($review['rating']   ?? 0);
$comment = (string) ($review['comment']  ?? '');
$location = (string) ($review['location'] ?? '');
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.review_title') ?></h1>
                <p class="account-header__sub"><?= htmlspecialchars(__('account.review_intro')) ?></p>
            </header>

            <?php if ($saved) : ?>
            <div class="alert alert--success js-autohide" role="alert">
                <?= htmlspecialchars(__('account.review_saved')) ?>
            </div>
            <?php endif; ?>

            <form method="post"
                  action="/<?= htmlspecialchars($lang) ?>/mon-compte/mon-avis"
                  class="review-form-page auth-card">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

                <fieldset class="review-stars" style="margin-bottom:1.5rem;">
                    <legend class="form-label"><?= htmlspecialchars(__('review.rating_label')) ?></legend>
                    <div class="review-stars__inputs">
                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                        <input type="radio" class="review-star-input"
                               id="account-review-star-<?= $i ?>"
                               name="rating" value="<?= $i ?>"
                               <?= $current === $i ? 'checked' : '' ?>
                               required>
                        <label class="review-star-label" for="account-review-star-<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </fieldset>

                <div class="form-group">
                    <label class="form-label" for="account-review-comment">
                        <?= htmlspecialchars(__('review.comment_label')) ?>
                    </label>
                    <textarea id="account-review-comment"
                              name="comment"
                              class="form-control review-form__textarea"
                              placeholder="<?= htmlspecialchars(__('review.comment_placeholder')) ?>"
                              maxlength="1000"><?= htmlspecialchars($comment) ?></textarea>
                </div>

                <div class="form-group review-form__location" style="margin-bottom:2rem;">
                    <label class="form-label" for="account-review-location">
                        <?= htmlspecialchars(__('review.location_label')) ?>
                    </label>
                    <input type="text"
                           id="account-review-location"
                           name="location"
                           class="form-control"
                           placeholder="<?= htmlspecialchars(__('review.location_placeholder') ?? 'Paris, Lyon...') ?>"
                           maxlength="100"
                           value="<?= htmlspecialchars($location) ?>">
                </div>

                <button type="submit" class="btn btn--gold">
                    <?= htmlspecialchars(__($review !== null ? 'account.review_update' : 'account.review_submit')) ?>
                </button>
            </form>
        </div>
    </div>
</main>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var el = document.querySelector('.js-autohide');
    if (!el) { return; }
    setTimeout(function () {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 400);
    }, 4000);
}());
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
