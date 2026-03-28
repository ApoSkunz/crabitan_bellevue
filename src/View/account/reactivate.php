<?php
$pageTitle = __($success ? 'account.reactivate_success_title' : 'account.reactivate_error_title');
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool $success */
?>
<main class="auth-page">
    <div class="auth-card">
        <?php if ($success) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">&#10003;</span>
            </div>
            <h1><?= __('account.reactivate_success_title') ?></h1>
            <p><?= __('account.reactivate_success_body') ?></p>
            <a class="btn btn--primary btn--sm" href="/<?= htmlspecialchars($lang) ?>/connexion"
               style="display:block;text-align:center;margin-top:1.5rem;">
                <?= __('account.reactivate_login_btn') ?>
            </a>
        <?php else : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">&#10007;</span>
            </div>
            <h1><?= __('account.reactivate_error_title') ?></h1>
            <p><?= __('account.reactivate_error_body') ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>"
               style="display:block;text-align:center;margin-top:1.5rem;">
                <?= __('nav.home') ?>
            </a>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
