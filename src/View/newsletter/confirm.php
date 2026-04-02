<?php
$pageTitle = __('newsletter.confirm_title');
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool $success */
/** @var string $message */
?>
<main class="auth-page">
    <div class="auth-card">
        <?php if ($success) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✓</span>
            </div>
            <h1><?= __('newsletter.confirm_title') ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= __('nav.home') ?>
            </a>
        <?php else : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✗</span>
            </div>
            <h1><?= __('newsletter.confirm_title') ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= __('nav.home') ?>
            </a>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
