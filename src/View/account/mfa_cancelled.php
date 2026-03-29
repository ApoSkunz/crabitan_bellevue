<?php
$pageTitle = __('account.mfa_cancelled_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('account.mfa_cancelled_title') ?></h1>

        <div class="auth-status">
            <div class="auth-status__icon auth-status__icon--<?= $revoked ? 'success' : 'error' ?>" aria-hidden="true">
                <?= $revoked ? '✓' : '✗' ?>
            </div>
            <p class="auth-status__message">
                <?= $revoked ? __('account.mfa_cancelled_body') : __('account.device_confirm_expired') ?>
            </p>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
