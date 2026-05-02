<?php
$pageTitle = __($success ? 'account.security_deny_success_title' : 'account.security_deny_error_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool $success */
/** @var bool $expired */
?>
<main class="auth-page">
    <div class="auth-card">
        <?php if ($success) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">&#10003;</span>
            </div>
            <h1><?= __('account.security_deny_success_title') ?></h1>
            <p><?= __('account.security_deny_success_body') ?></p>
            <a class="btn btn--primary btn--sm u-block text-center u-mt-6" href="/<?= htmlspecialchars($lang) ?>/connexion">
                <?= __('account.security_deny_login_btn') ?>
            </a>
        <?php else : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">&#10007;</span>
            </div>
            <h1><?= __('account.security_deny_error_title') ?></h1>
            <p><?= $expired ? __('account.security_deny_expired_body') : __('account.security_deny_invalid_body') ?></p>
            <a class="btn btn--ghost btn--sm u-block text-center u-mt-6" href="/<?= htmlspecialchars($lang) ?>/mot-de-passe-oublie">
                <?= __('account.security_deny_reset_btn') ?>
            </a>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
