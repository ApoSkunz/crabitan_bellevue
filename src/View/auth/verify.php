<?php
$pageTitle = __('auth.verify_email');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('auth.verify_email') ?></h1>

        <div class="auth-status">
            <div class="auth-status__icon auth-status__icon--<?= $success ? 'success' : 'error' ?>"
                 aria-hidden="true">
                <?= $success ? '✓' : '✕' ?>
            </div>
            <p class="auth-status__message">
                <?= htmlspecialchars($message) ?>
            </p>
        </div>

        <?php if ($success) : ?>
            <a href="/<?= htmlspecialchars($lang) ?>?login=1" class="btn btn--gold btn--full">
                <?= __('auth.login') ?>
            </a>
        <?php else : ?>
            <p class="auth-card__switch"><?= __('auth.verify_contact') ?></p>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
