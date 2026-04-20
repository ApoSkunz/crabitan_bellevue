<?php
$pageTitle = __('auth.google_link_verify_done_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card__google-icon" aria-hidden="true">
            <img src="/assets/images/login/Google__G__logo.png" alt="" width="32" height="32">
        </div>

        <h1><?= htmlspecialchars(__('auth.google_link_verify_done_title')) ?></h1>
        <p class="auth-card__intro"><?= htmlspecialchars(__('auth.google_link_verify_done_body')) ?></p>
        <p class="auth-card__note"><?= htmlspecialchars(__('auth.google_link_verify_done_note')) ?></p>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
