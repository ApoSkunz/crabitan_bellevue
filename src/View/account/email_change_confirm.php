<?php
$pageTitle     = __('account.email_change_confirm_title');
$activeSection = 'profile';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool        $success */
/** @var string|null $error */
/** @var string      $lang */
?>
<main class="account-page">
    <div class="account-shell">
        <div class="account-content account-content--centered">
            <header class="account-header">
                <h1 class="account-header__title">
                    <?= __('account.email_change_confirm_title') ?>
                </h1>
            </header>

            <?php if ($success) : ?>
                <div class="alert alert--success" role="alert">
                    <?= __('account.email_change_confirmed') ?>
                </div>
                <p class="account-text">
                    <?= __('account.email_change_confirmed_body') ?>
                </p>
                <a href="/<?= htmlspecialchars($lang) ?>"
                   class="btn btn--primary">
                    <?= __('account.email_change_login_btn') ?>
                </a>
            <?php else : ?>
                <div class="alert alert--error" role="alert">
                    <?= htmlspecialchars($error ?? __('account.email_change_token_invalid')) ?>
                </div>
                <p class="account-text">
                    <?= __('account.email_change_token_expired_hint') ?>
                </p>
                <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/profil"
                   class="btn btn--secondary">
                    <?= __('account.email_change_retry_btn') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
