<?php
$pageTitle       = __('auth.google_link_title');
$noindex         = true;
$pollTimeoutMsg  = htmlspecialchars(__('auth.google_link_poll_timeout'));
$pollWaitingMsg  = htmlspecialchars(__('auth.google_link_poll_waiting'));
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card__google-icon" aria-hidden="true">
            <img src="/assets/images/login/Google__G__logo.png" alt="" width="32" height="32">
        </div>

        <h1><?= htmlspecialchars(__('auth.google_link_title')) ?></h1>

        <p class="auth-card__intro">
            <?= htmlspecialchars(__('auth.google_link_email_sent_intro')) ?>
            <strong><?= htmlspecialchars($email) ?></strong>.
        </p>
        <p><?= htmlspecialchars(__('auth.google_link_email_sent_instructions')) ?></p>
        <p class="auth-card__note"><?= htmlspecialchars(__('auth.google_link_email_sent_ttl')) ?></p>

        <div id="google-link-poll-status" class="auth-card__poll-status" aria-live="polite">
            <span class="auth-card__poll-spinner" aria-hidden="true"></span>
            <span id="google-link-poll-msg" class="auth-card__poll-msg"><?= $pollWaitingMsg ?></span>
        </div>

        <form method="POST" action="/<?= htmlspecialchars($lang) ?>/auth/google/link" class="auth-card__form auth-card__form--cancel">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn--ghost btn--full">
                <?= htmlspecialchars(__('auth.google_link_cancel')) ?>
            </button>
        </form>
    </div>
</main>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var POLL_INTERVAL = 2000;
    var MAX_POLLS     = 150; // 5 min = 300 s / 2 s
    var pollCount     = 0;
    var timer         = null;
    var msgEl         = document.getElementById('google-link-poll-msg');
    var spinnerEl     = document.querySelector('.auth-card__poll-spinner');
    var timeoutMsg    = <?= json_encode($pollTimeoutMsg) ?>;

    function stopPolling() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function onTimeout() {
        stopPolling();
        if (spinnerEl) { spinnerEl.style.display = 'none'; }
        msgEl.textContent = timeoutMsg;
    }

    function poll() {
        if (pollCount >= MAX_POLLS) {
            onTimeout();
            return;
        }
        pollCount++;

        fetch('/api/google-link-status', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'linked' && data.redirect) {
                    stopPolling();
                    window.location = data.redirect;
                } else if (data.status === 'expired') {
                    onTimeout();
                } else {
                    timer = setTimeout(poll, POLL_INTERVAL);
                }
            })
            .catch(function () {
                timer = setTimeout(poll, POLL_INTERVAL);
            });
    }

    timer = setTimeout(poll, POLL_INTERVAL);
}());
</script>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
