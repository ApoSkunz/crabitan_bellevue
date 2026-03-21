<?php
$pageTitle = __('auth.verify_email');
$noindex   = true;
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('auth.verify_email') ?></h1>

        <div class="alert <?= $success ? 'alert--success' : 'alert--error' ?>" role="alert">
            <?= htmlspecialchars($message) ?>
        </div>

        <?php if ($success): ?>
            <a href="/<?= htmlspecialchars($lang) ?>/connexion" class="btn btn--primary btn--full">
                <?= __('auth.login') ?>
            </a>
        <?php else: ?>
            <p><?= __('auth.verify_contact') ?></p>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
