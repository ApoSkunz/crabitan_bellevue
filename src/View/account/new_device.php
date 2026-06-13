<?php
$pageTitle = __('account.new_device_title');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('account.new_device_title') ?></h1>

        <div class="auth-status" id="mfa-status">
            <div class="auth-status__icon auth-status__icon--loading" aria-hidden="true"></div>
            <p class="auth-status__message u-font-semibold">
                <?= __('account.new_device_waiting') ?>
            </p>
            <p class="auth-status__message">
                <?= __('account.new_device_intro') ?>
                <?php if ($deviceName !== '') : ?>
                    <strong><?= htmlspecialchars($deviceName) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <p id="mfa-hint" class="u-text-9 text-muted u-leading-relaxed u-mt-4">
            <?= __('account.new_device_email_sent') ?>
        </p>

        <div id="mfa-denied" class="u-hidden">
            <div class="u-alert-error-ring" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                     fill="none" stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </div>
            <p class="u-font-semibold u-text-error u-text-95 u-mb-5">
                <?= __('account.device_confirm_expired') ?>
            </p>
            <div class="u-alert-error-bar u-text-sm u-leading-loose text-muted">
                <strong class="u-block u-mb-1 u-text-error u-text-2xs u-tracking-wider u-uppercase">
                    <?= $lang === 'fr' ? 'Avertissement légal' : 'Legal notice' ?>
                </strong>
                <?= __('account.mfa_denied_legal') ?>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    const token    = <?= json_encode($mfaToken) ?>;
    const interval = 3000;
    let   attempts = 0;
    const maxAttempts = 300; // 15 min / 3s

    if (!token) return;

    const showDenied = () => {
        document.getElementById('mfa-status').classList.add('u-hidden');
        document.getElementById('mfa-hint').classList.add('u-hidden');
        document.getElementById('mfa-denied').classList.remove('u-hidden');
    };

    const poll = async () => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(timer);
            showDenied();
            return;
        }

        try {
            const res  = await fetch('/api/mfa/poll?token=' + encodeURIComponent(token));
            const data = await res.json();

            if (data.status === 'ok') {
                clearInterval(timer);
                var dest = data.redirect || '/';
                try { if (new URL(dest, location.origin).origin !== location.origin) { dest = '/'; } } catch (_) { dest = '/'; }
                window.location.href = dest;
            } else if (data.status === 'expired') {
                clearInterval(timer);
                showDenied();
            }
            // status === 'pending' → on continue à poller
        } catch (_) { /* réseau temporairement indisponible, on réessaie */ }
    };

    const timer = setInterval(poll, interval);
    poll(); // premier appel immédiat
}());
</script>
