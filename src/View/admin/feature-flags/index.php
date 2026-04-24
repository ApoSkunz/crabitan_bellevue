<?php
/**
 * Vue admin — Feature flags
 *
 * @var array<string, mixed>             $adminUser
 * @var string                           $adminSection
 * @var string                           $pageTitle
 * @var array<int, array<string,string>> $breadcrumbs
 * @var array<int, array<string,mixed>>  $flags
 * @var string|null                      $otpKey
 * @var bool                             $otpBatch
 * @var bool                             $isSuperAdmin
 * @var array<int, string>               $superAdminEmails
 * @var int|null                         $remainingAttempts
 */
require_once ROOT_PATH . '/src/View/admin/_open.php';
$csrf = $_SESSION['csrf'] ?? '';

// Index flags by key for fast lookup
$flagsMap = [];
foreach ($flags as $f) {
    $flagsMap[(string) $f['key']] = $f;
}

// Categories — display grouping (no DB column needed)
$categories = [
    'Boutique'        => [
        'click_collect_enabled',
        'invoice_auto_generate',
    ],
    'Catalogue'       => [
        'enable_extra_info',
        'stock_alert_limit',
        'stock_notification_enabled',
    ],
    'Newsletter & Cron' => [
        'newsletter_enabled',
        'cron_newsletter_batch_size',
        'cron_mail_delay_ms',
    ],
    'Paiement'        => [
        'ca_enable_gae',
    ],
    'Sécurité accès'  => [
        'rate_limit_login_max',
        'rate_limit_login_window',
    ],
    'Sessions JWT'    => [
        'jwt_refresh_threshold',
    ],
];
?>

<main class="admin-main" id="main-content">
    <div class="admin-content">

        <?php if (!$otpBatch && !empty($_SESSION['flash'])): ?>
            <?php foreach ($_SESSION['flash'] as $type => $msg): unset($_SESSION['flash'][$type]); ?>
                <div class="admin-alert admin-alert--<?= htmlspecialchars($type) ?>" role="alert">
                    <?= htmlspecialchars((string) $msg) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$isSuperAdmin): ?>
            <div class="admin-alert admin-alert--info" role="note" style="margin-bottom:1.5rem;">
                <strong>Lecture seule.</strong>
                Pour modifier ces paramètres, contactez un super administrateur :
                <?php foreach ($superAdminEmails as $i => $email): ?>
                    <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a><?= $i < count($superAdminEmails) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($otpBatch): ?>
        <div id="ff-otp-overlay" role="dialog" aria-modal="true" aria-labelledby="ff-otp-title"
             style="position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.55);
                    display:flex;align-items:center;justify-content:center;">
            <div id="ff-otp-card" style="background:#fff;border-radius:10px;padding:2rem 2.5rem;
                        max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,0.25);
                        position:relative;transition:opacity 0.25s;">

                <h2 id="ff-otp-title" style="font-size:1rem;font-weight:600;color:#3d3425;
                                              margin:0 0 0.25rem;">Confirmation OTP requise</h2>
                <p style="font-size:0.8rem;color:#888;margin:0 0 1rem;">
                    Entrez le code à 6 chiffres reçu par email.
                </p>

                <?php if (!empty($_SESSION['flash'])): ?>
                    <?php foreach ($_SESSION['flash'] as $type => $msg): unset($_SESSION['flash'][$type]); ?>
                    <div style="padding:0.6rem 0.9rem;border-radius:6px;font-size:0.82rem;
                                margin-bottom:1rem;
                                <?= $type === 'error'
                                    ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;'
                                    : 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;' ?>">
                        <?= htmlspecialchars((string) $msg) ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="display:flex;align-items:center;justify-content:space-between;
                            margin-bottom:1.25rem;">
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <span style="font-size:0.8rem;color:#888;">Expire dans</span>
                        <span id="ff-otp-countdown"
                              style="font-size:1rem;font-weight:700;color:#c9a84c;
                                     font-variant-numeric:tabular-nums;min-width:2.2em;">
                            2:00
                        </span>
                    </div>
                    <?php if ($remainingAttempts !== null): ?>
                    <span id="ff-otp-attempts"
                          style="font-size:0.78rem;font-weight:600;
                                 <?= $remainingAttempts <= 1 ? 'color:#b91c1c;' : 'color:#888;' ?>">
                        <?= (int) $remainingAttempts ?> tentative<?= $remainingAttempts > 1 ? 's' : '' ?> restante<?= $remainingAttempts > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="/admin/feature-flags/batch-confirm" id="ff-otp-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div style="margin-bottom:1.25rem;">
                        <label style="display:block;font-size:0.8rem;font-weight:600;
                                      color:#3d3425;margin-bottom:0.75rem;">
                            Code OTP
                        </label>
                        <input type="hidden" id="otp-input" name="otp">
                        <div id="ff-otp-digits"
                             style="display:flex;gap:0.75rem;justify-content:center;">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <input
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                pattern="[0-9]"
                                autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                <?= $i === 0 ? 'autofocus' : '' ?>
                                style="width:2.5rem;height:3rem;border:none;
                                       border-bottom:2px solid #c5b99a;
                                       outline:none;background:transparent;
                                       font-size:1.75rem;font-weight:700;text-align:center;
                                       color:#3d3425;transition:border-color 0.15s;"
                                onfocus="this.style.borderBottomColor='#3d3425'"
                                onblur="this.style.borderBottomColor='#c5b99a'"
                            >
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="ff-otp-expired"
                         style="display:none;color:#b91c1c;font-size:0.85rem;
                                margin-bottom:1rem;text-align:center;">
                        Code expiré —
                        <a href="/admin/feature-flags" style="color:#b91c1c;">demandez un nouveau code</a>
                    </div>

                    <div style="display:flex;gap:0.75rem;align-items:center;">
                        <button type="submit" id="ff-otp-submit"
                                class="admin-btn admin-btn--primary"
                                style="display:flex;align-items:center;gap:0.5rem;">
                            <span id="ff-otp-btn-text">Valider les modifications</span>
                            <svg id="ff-otp-spinner" xmlns="http://www.w3.org/2000/svg"
                                 width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5"
                                 style="display:none;animation:ff-spin 0.7s linear infinite;">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83
                                         M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                            </svg>
                        </button>
                        <a href="/admin/feature-flags" class="admin-btn admin-btn--ghost">
                            Annuler
                        </a>
                    </div>
                </form>

                <style>
                @keyframes ff-spin { to { transform: rotate(360deg); } }
                </style>
            </div>
        </div>

        <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
        (function () {
            var TOTAL = 120;
            var stKey = 'ff_otp_ts';
            var now   = Date.now();
            if (!sessionStorage.getItem(stKey)) {
                sessionStorage.setItem(stKey, now.toString());
            }
            var started   = parseInt(sessionStorage.getItem(stKey), 10);
            var elapsed   = Math.floor((now - started) / 1000);
            var remaining = Math.max(0, TOTAL - elapsed);

            var countEl   = document.getElementById('ff-otp-countdown');
            var expiredEl = document.getElementById('ff-otp-expired');
            var submitBtn = document.getElementById('ff-otp-submit');

            function fmt(s) {
                var m = Math.floor(s / 60);
                var sec = s % 60;
                return m + ':' + (sec < 10 ? '0' : '') + sec;
            }

            function tick() {
                if (remaining <= 0) {
                    countEl.textContent = '0:00';
                    countEl.style.color = '#b91c1c';
                    expiredEl.style.display = 'block';
                    submitBtn.disabled = true;
                    document.querySelectorAll('#ff-otp-digits input').forEach(function (d) {
                        d.disabled = true;
                    });
                    return;
                }
                countEl.textContent = fmt(remaining);
                if (remaining <= 30) { countEl.style.color = '#b91c1c'; }
                remaining--;
                setTimeout(tick, 1000);
            }

            tick();

            // ── OTP digit inputs — auto-advance, backspace, paste ──────
            var digits  = Array.from(document.querySelectorAll('#ff-otp-digits input'));
            var hidden  = document.getElementById('otp-input');
            var otpForm = document.getElementById('ff-otp-form');
            var spinner = document.getElementById('ff-otp-spinner');
            var btnText = document.getElementById('ff-otp-btn-text');

            function collectOtp() {
                hidden.value = digits.map(function (d) { return d.value; }).join('');
            }

            digits.forEach(function (el, idx) {
                el.addEventListener('input', function () {
                    var v = el.value.replace(/[^0-9]/g, '');
                    el.value = v.slice(-1);
                    collectOtp();
                    if (v && idx < digits.length - 1) { digits[idx + 1].focus(); }
                });
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !el.value && idx > 0) {
                        digits[idx - 1].focus();
                        digits[idx - 1].value = '';
                        collectOtp();
                    }
                });
                el.addEventListener('paste', function (e) {
                    e.preventDefault();
                    var text = (e.clipboardData || window.clipboardData).getData('text');
                    var nums = text.replace(/[^0-9]/g, '').slice(0, 6);
                    nums.split('').forEach(function (ch, i) {
                        if (digits[idx + i]) { digits[idx + i].value = ch; }
                    });
                    collectOtp();
                    var next = Math.min(idx + nums.length, digits.length - 1);
                    digits[next].focus();
                });
            });

            otpForm.addEventListener('submit', function () {
                collectOtp();
                // Animation soumission
                submitBtn.disabled = true;
                spinner.style.display = 'inline';
                btnText.textContent   = 'Vérification…';
                document.getElementById('ff-otp-card').style.opacity = '0.6';
            });
        }());
        </script>
        <?php endif; ?>

        <form method="POST" action="/admin/feature-flags/batch-request" id="ff-form"
              data-admin-confirm="Envoyer un code OTP pour enregistrer toutes les modifications ?">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="admin-card">
                <div class="admin-card__header">
                    <h1 class="admin-card__title">Feature flags</h1>
                    <p class="admin-card__subtitle">
                        Modifiez les paramètres applicatifs. Un code OTP sera envoyé par email pour confirmer.
                    </p>
                </div>
                <div class="admin-card__body">

                    <?php foreach ($categories as $catName => $keys): ?>
                        <?php
                        // Skip category if none of its flags exist in DB
                        $catFlags = array_filter($keys, fn($k) => isset($flagsMap[$k]));
                        if (empty($catFlags)) {
                            continue;
                        }
                        ?>
                        <div class="ff-category">
                            <div class="ff-category__title"><?= htmlspecialchars($catName) ?></div>

                            <?php foreach ($catFlags as $key): ?>
                                <?php
                                $flag  = $flagsMap[$key];
                                $value = (string) $flag['value'];
                                $type  = (string) $flag['type'];
                                $label = (string) $flag['label'];
                                $desc  = (string) ($flag['description'] ?? '');
                                ?>
                                <div class="ff-row">
                                    <div class="ff-row__meta">
                                        <span class="ff-row__label"><?= htmlspecialchars($label) ?></span>
                                        <code class="ff-row__key"><?= htmlspecialchars($key) ?></code>
                                        <?php if ($desc !== ''): ?>
                                            <span class="ff-row__desc"><?= htmlspecialchars($desc) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ff-row__control">
                                        <?php $ro = !$isSuperAdmin ? 'disabled' : ''; ?>
                                        <?php if ($type === 'bool'): ?>
                                            <label class="ff-toggle<?= !$isSuperAdmin ? ' ff-toggle--readonly' : '' ?>" aria-label="<?= htmlspecialchars($label) ?>">
                                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="false">
                                                <input
                                                    type="checkbox"
                                                    class="ff-toggle__input"
                                                    name="<?= htmlspecialchars($key) ?>"
                                                    value="true"
                                                    <?= $value === 'true' ? 'checked' : '' ?>
                                                    <?= $ro ?>
                                                >
                                                <span class="ff-toggle__track">
                                                    <span class="ff-toggle__thumb"></span>
                                                </span>
                                            </label>
                                        <?php elseif ($type === 'int'): ?>
                                            <input
                                                type="number"
                                                name="<?= htmlspecialchars($key) ?>"
                                                value="<?= htmlspecialchars($value) ?>"
                                                class="ff-input"
                                                step="1"
                                                <?= $ro ?>
                                            >
                                        <?php else: ?>
                                            <input
                                                type="number"
                                                name="<?= htmlspecialchars($key) ?>"
                                                value="<?= htmlspecialchars($value) ?>"
                                                class="ff-input"
                                                step="0.01"
                                                min="0.01"
                                                max="0.99"
                                                <?= $ro ?>
                                            >
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <?php if ($isSuperAdmin): ?>
            <div class="ff-save-bar">
                <span class="ff-save-bar__hint">
                    Un code OTP sera envoyé à votre adresse email pour confirmer les modifications.
                </span>
                <button
                    type="submit"
                    class="admin-btn admin-btn--primary"
                    id="ff-save-btn"
                    disabled
                >
                    Enregistrer mes modifications
                </button>
            </div>
            <?php endif; ?>
        </form>

        <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
        (function () {
            var form = document.getElementById('ff-form');
            var btn  = document.getElementById('ff-save-btn');

            function serialize() {
                var data = {};
                form.querySelectorAll('input[name]').forEach(function (el) {
                    if (el.type === 'checkbox') {
                        data[el.name] = el.checked ? 'true' : 'false';
                    } else if (el.type !== 'hidden') {
                        data[el.name] = el.value;
                    }
                });
                return JSON.stringify(data);
            }

            var initial = serialize();

            function checkDirty() {
                btn.disabled = serialize() === initial;
            }

            form.addEventListener('change', checkDirty);
            form.addEventListener('input', checkDirty);
            form.addEventListener('submit', function () {
                sessionStorage.removeItem('ff_otp_ts');
            });
        }());
        </script>

    </div>
</main>

<?php require_once ROOT_PATH . '/src/View/admin/_close.php'; ?>
