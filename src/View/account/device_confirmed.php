<?php
$pageTitle = $success ? __('account.device_confirmed_title') : __('account.device_confirm_expired_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= $success ? __('account.device_confirmed_title') : __('account.device_confirm_expired_title') ?></h1>

        <?php if ($success) : ?>
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--success" aria-hidden="true">✓</div>
                <p class="auth-status__message">
                    <?= __('account.device_confirmed_body') ?>
                </p>
            </div>
        <?php else : ?>
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--error" aria-hidden="true">✗</div>
                <p class="auth-status__message">
                    <?= __('account.device_confirm_expired') ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
