<?php
$pageTitle = __('account.email_change_confirm_title');
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool        $success */
/** @var bool        $revoked */
/** @var string|null $error */
/** @var string      $lang */
$revoked = $revoked ?? false;
?>
<main class="auth-page">
    <div class="auth-card">

        <?php if ($success && !$revoked) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✓</span>
            </div>
            <h1><?= __('account.email_change_confirm_title') ?></h1>
            <p><?= __('account.email_change_confirmed') ?></p>
            <p class="auth-hint"><?= __('account.email_change_confirmed_body') ?></p>
            <a href="/<?= htmlspecialchars($lang) ?>" class="btn btn--gold btn--sm">
                <?= __('account.email_change_login_btn') ?>
            </a>

        <?php elseif ($success && $revoked) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✓</span>
            </div>
            <h1><?= __('account.email_change_revoked_title') ?></h1>
            <p><?= __('account.email_change_revoked_body') ?></p>
            <a href="/<?= htmlspecialchars($lang) ?>" class="btn btn--ghost btn--sm">
                <?= __('nav.home') ?>
            </a>

        <?php else : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✗</span>
            </div>
            <h1><?= __('account.email_change_confirm_title') ?></h1>
            <p><?= htmlspecialchars($error ?? __('account.email_change_token_invalid')) ?></p>
            <p class="auth-hint"><?= __('account.email_change_token_expired_hint') ?></p>
            <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/profil#email-change"
               class="btn btn--ghost btn--sm">
                <?= __('account.email_change_retry_btn') ?>
            </a>
        <?php endif; ?>

    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
