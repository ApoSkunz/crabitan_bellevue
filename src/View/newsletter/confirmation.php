<?php
$pageTitle = __('newsletter.confirm_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool        $success Confirmation réussie */
/** @var string|null $reason  Raison de l'échec : 'invalid', 'expired' ou null */
/** @var string      $lang    Langue courante */
?>
<main class="auth-page">
    <div class="auth-card">
        <?php if ($success) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✓</span>
            </div>
            <h1><?= __('newsletter.confirm_title') ?></h1>
            <p><?= __('newsletter.confirm_success') ?></p>
            <a class="btn btn--primary btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= __('nav.home') ?>
            </a>

        <?php elseif (($reason ?? '') === 'expired') : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✗</span>
            </div>
            <h1><?= __('newsletter.confirm_title') ?></h1>
            <p><?= __('newsletter.confirm_expired') ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= __('nav.home') ?>
            </a>

        <?php else : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✗</span>
            </div>
            <h1><?= __('newsletter.confirm_title') ?></h1>
            <p><?= __('newsletter.confirm_invalid') ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= __('nav.home') ?>
            </a>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
