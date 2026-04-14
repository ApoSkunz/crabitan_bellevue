<?php
$pageTitle = __('auth.google_link_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card__google-icon" aria-hidden="true">
            <img src="/assets/images/login/Google__G__logo.png" alt="" width="32" height="32">
        </div>

        <h1><?= htmlspecialchars(__('auth.google_link_title')) ?></h1>

        <p class="auth-card__intro">
            <?= htmlspecialchars(__('auth.google_link_email_sent_intro')) ?>
            <strong><?= htmlspecialchars($email) ?></strong>.
        </p>
        <p><?= htmlspecialchars(__('auth.google_link_email_sent_instructions')) ?></p>
        <p class="auth-card__note"><?= htmlspecialchars(__('auth.google_link_email_sent_ttl')) ?></p>

        <form method="POST" action="/<?= htmlspecialchars($lang) ?>/auth/google/link" class="auth-card__form auth-card__form--cancel">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn--ghost btn--full">
                <?= htmlspecialchars(__('auth.google_link_cancel')) ?>
            </button>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
