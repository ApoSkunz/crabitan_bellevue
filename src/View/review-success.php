<?php
$pageTitle       = __('review.success_title');
$noindex         = true;
$metaDescription = __('review.success_title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var string $lang Langue courante */
?>
<main class="auth-page">
    <div class="auth-card">
        <div class="auth-status auth-status--ok" aria-live="polite">
            <span class="auth-status__icon" aria-hidden="true">✓</span>
        </div>
        <h1><?= htmlspecialchars(__('review.success_title')) ?></h1>
        <p><?= htmlspecialchars(__('review.success_body')) ?></p>
        <a class="btn btn--gold btn--sm" href="/<?= htmlspecialchars($lang) ?>">
            <?= htmlspecialchars(__('nav.home')) ?>
        </a>
    </div>
</main>
<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
