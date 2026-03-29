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
            <p class="auth-status__message" style="font-weight:600;">
                <?= __('account.new_device_waiting') ?>
            </p>
            <p class="auth-status__message">
                <?= __('account.new_device_intro') ?>
                <?php if ($deviceName !== '') : ?>
                    <strong><?= htmlspecialchars($deviceName) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <p id="mfa-hint" style="font-size:0.9rem;color:var(--color-text-muted);line-height:1.6;margin-top:1rem;">
            <?= __('account.new_device_email_sent') ?>
        </p>

        <div id="mfa-denied" style="display:none;">
            <div style="
                width:4.5rem;height:4.5rem;border-radius:50%;
                background:rgba(192,57,43,0.10);border:2px solid #c0392b;
                display:flex;align-items:center;justify-content:center;
                margin:0 auto 1rem;
            " aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                     fill="none" stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </div>
            <p style="font-weight:600;color:#c0392b;font-size:0.95rem;margin-bottom:1.25rem;">
                <?= __('account.device_confirm_expired') ?>
            </p>
            <div style="
                padding:1rem 1.25rem;text-align:left;
                background:rgba(192,57,43,0.06);border-left:3px solid #c0392b;
                font-size:0.8rem;line-height:1.7;color:var(--color-text-muted);
            ">
                <strong style="display:block;margin-bottom:0.4rem;color:#c0392b;font-size:0.75rem;letter-spacing:0.08em;text-transform:uppercase;">
                    <?= $lang === 'fr' ? 'Avertissement légal' : 'Legal notice' ?>
                </strong>
                <?= __('account.mfa_denied_legal') ?>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
<script>
(function () {
    const token    = <?= json_encode($mfaToken) ?>;
    const interval = 3000;
    let   attempts = 0;
    const maxAttempts = 300; // 15 min / 3s

    if (!token) return;

    const showDenied = () => {
        document.getElementById('mfa-status').style.display = 'none';
        document.getElementById('mfa-hint').style.display   = 'none';
        document.getElementById('mfa-denied').style.display = 'block';
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
                window.location.href = data.redirect || '/';
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
