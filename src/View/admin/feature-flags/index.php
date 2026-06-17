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
 * @var int                             $otpBlockSecondsLeft
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

        <?php if (!$otpBatch && !empty($_SESSION['admin_flash'])): ?>
            <?php $resultFlash = $_SESSION['admin_flash']; unset($_SESSION['admin_flash']); ?>
            <div id="ff-result-overlay" role="dialog" aria-modal="true" aria-live="polite"
                 class="admin-ff-result-overlay">
                <div class="admin-ff-result-card">
                    <?php foreach ($resultFlash as $fType => $fMsg): ?>
                    <?php $isSuccess = $fType === 'success'; ?>
                    <div class="admin-ff-result-icon<?= $isSuccess ? ' admin-ff-result-icon--success' : ' admin-ff-result-icon--error' ?>">
                        <?= $isSuccess ? '✓' : '✕' ?>
                    </div>
                    <p class="u-text-95 u-font-semibold u-text-gray u-mb-2">
                        <?= htmlspecialchars((string) $fMsg) ?>
                    </p>
                    <?php endforeach; ?>
                    <p class="u-text-sm-plus u-mb-5 u-mt-4 u-text-muted-aa">
                        Fermeture automatique dans <strong id="ff-result-timer"
                            class="u-text-gray">15</strong>s
                    </p>
                    <button id="ff-result-close"
                            class="admin-btn admin-btn--outline u-w-full u-justify-center">
                        Fermer
                    </button>
                </div>
            </div>
            <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
            (function () {
                var t       = 15;
                var timerEl = document.getElementById('ff-result-timer');
                var overlay = document.getElementById('ff-result-overlay');
                var closeBtn = document.getElementById('ff-result-close');

                function dismiss() { clearInterval(iv); if (overlay) { overlay.remove(); } }

                if (closeBtn) { closeBtn.addEventListener('click', dismiss); }

                var iv = setInterval(function () {
                    t--;
                    if (timerEl) { timerEl.textContent = t; }
                    if (t <= 0) { dismiss(); }
                }, 1000);
            }());
            </script>
        <?php endif; ?>

        <?php if ($otpBlockSecondsLeft > 0): ?>
            <p id="ff-block-banner"
               class="u-text-85 u-font-medium u-mb-6 admin-ff-block-banner">
                Accès bloqué — nouvelle demande possible dans
                <strong id="ff-block-countdown">…</strong>.
            </p>
            <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
            (function () {
                var remaining = <?= (int) $otpBlockSecondsLeft ?>;
                var el       = document.getElementById('ff-block-countdown');
                var bannerEl = document.getElementById('ff-block-banner');
                function fmt(s) {
                    return Math.ceil(s / 60) + ' min';
                }
                function tick() {
                    if (remaining <= 0) {
                        bannerEl.remove();
                        document.querySelectorAll('#ff-form input, #ff-form button').forEach(function (e) {
                            e.disabled = false;
                        });
                        var sb = document.getElementById('ff-savebar-countdown');
                        if (sb) { sb.closest('.ff-save-bar__hint') && (sb.closest('.ff-save-bar__hint').textContent = 'Un code OTP sera envoyé à votre adresse email pour confirmer les modifications.'); }
                        return;
                    }
                    var fmtd = fmt(remaining);
                    el.textContent = fmtd;
                    var savebarEl = document.getElementById('ff-savebar-countdown');
                    if (savebarEl) { savebarEl.textContent = fmtd; }
                    remaining--;
                    setTimeout(tick, 1000);
                }
                tick();
            }());
            </script>
        <?php endif; ?>

        <?php if (!$isSuperAdmin): ?>
            <div class="admin-alert admin-alert--info u-mb-6" role="note">
                <strong>Lecture seule.</strong>
                Pour modifier ces paramètres, contactez un super administrateur :
                <?php foreach ($superAdminEmails as $i => $email): ?>
                    <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a><?= $i < count($superAdminEmails) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($otpBatch): ?>
        <div id="ff-otp-overlay" role="dialog" aria-modal="true" aria-labelledby="ff-otp-title"
             class="admin-ff-otp-overlay">
            <div id="ff-otp-card" class="admin-ff-otp-card">

                <h2 id="ff-otp-title" class="admin-ff-otp-title">Confirmation OTP requise</h2>
                <p class="u-text-sm u-mb-4 u-text-muted-88">
                    Entrez le code à 6 chiffres reçu par email.
                </p>

                <?php
                $otpFlashMessages = $_SESSION['admin_flash'] ?? [];
                unset($_SESSION['admin_flash']);
                foreach ($otpFlashMessages as $fType => $fMsg): // NOSONAR — php:S3973 : syntaxe alternative PHP standard dans les vues
                ?>
                <div class="admin-ff-otp-flash u-flex u-items-start u-gap-2 u-mb-4 u-text-sm-plus u-font-medium<?= $fType === 'error' ? ' admin-ff-otp-flash--error' : ' admin-ff-otp-flash--success' ?>">
                    <span><?= $fType === 'error' ? '✕' : '✓' ?></span>
                    <span><?= htmlspecialchars((string) $fMsg) ?></span>
                </div>
                <?php endforeach; ?>

                <?php if ($remainingAttempts !== null && $remainingAttempts <= 0): ?>
                <div class="admin-ff-otp-blocked u-mb-5">
                    <strong>Compte bloqué 1 heure.</strong>
                    3 tentatives incorrectes — vos modifications ont été annulées.
                    Nouvelle demande possible dans 1 heure.
                </div>
                <a href="/admin/feature-flags" class="admin-btn admin-btn--outline u-w-full u-justify-center">
                    Retour à la liste
                </a>
                <?php else: ?>

                <div class="u-flex u-items-center u-justify-between u-mb-5">
                    <div class="u-flex u-items-center u-gap-2">
                        <span class="u-text-sm u-text-muted-88">Expire dans</span>
                        <span id="ff-otp-countdown" class="admin-ff-otp-countdown">
                            2:00
                        </span>
                    </div>
                    <?php if ($remainingAttempts !== null): ?>
                    <span class="u-text-78 u-font-semibold<?= $remainingAttempts === 1 ? ' admin-ff-otp-attempts--danger' : ' u-text-muted-88' ?>">
                        <?= (int) $remainingAttempts ?> tentative<?= $remainingAttempts > 1 ? 's' : '' ?> restante<?= $remainingAttempts > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="/admin/feature-flags/batch-confirm" id="ff-otp-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div id="ff-otp-field" class="u-mb-5">
                        <label for="ff-otp-digit-0" class="u-block u-text-sm u-font-semibold u-text-gray u-mb-3">
                            Code OTP
                        </label>
                        <input type="hidden" id="otp-input" name="otp">
                        <div id="ff-otp-digits" class="u-flex u-gap-3 u-justify-center">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <input
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                pattern="[0-9]"
                                id="ff-otp-digit-<?= $i ?>"
                                aria-label="Chiffre <?= ($i + 1) ?>"
                                autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                <?= $i === 0 ? 'autofocus' : '' ?>
                                class="admin-ff-otp-digit"
                                onfocus="this.style.borderBottomColor='#3d3425'"
                                onblur="this.style.borderBottomColor='#c5b99a'"
                            >
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="ff-otp-expired"
                         class="u-hidden u-text-error u-text-85 u-mb-4 text-center">
                        Code expiré —
                        <a href="/admin/feature-flags" class="u-text-error">demandez un nouveau code</a>
                    </div>

                    <div id="ff-otp-actions" class="u-flex u-gap-3 u-items-center">
                        <button type="submit" id="ff-otp-submit"
                                class="admin-btn admin-btn--primary u-flex u-items-center u-gap-2">
                            <span id="ff-otp-btn-text">Valider les modifications</span>
                            <svg id="ff-otp-spinner" xmlns="http://www.w3.org/2000/svg"
                                 width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5"
                                 class="u-hidden admin-ff-otp-spinner-svg">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83
                                         M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                            </svg>
                        </button>
                        <a href="/admin/feature-flags" class="admin-btn admin-btn--outline">
                            Annuler
                        </a>
                    </div>
                </form>

                <?php endif; ?>

            </div>
        </div>
        <script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
        (function(){var s=document.createElement('style');s.textContent='@keyframes ff-spin{to{transform:rotate(360deg)}}';document.head.appendChild(s);}());
        </script>

        <?php if ($remainingAttempts === null || $remainingAttempts > 0): ?>
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
                    document.getElementById('ff-otp-field').classList.add('u-hidden');
                    expiredEl.classList.remove('u-hidden');
                    var closeBtn = document.createElement('button');
                    closeBtn.type = 'button';
                    closeBtn.className = 'admin-btn admin-btn--outline';
                    closeBtn.style.cssText = 'width:100%;justify-content:center;';
                    closeBtn.textContent = 'Fermer';
                    closeBtn.addEventListener('click', function () {
                        window.location.href = '/admin/feature-flags';
                    });
                    var actionsEl = document.getElementById('ff-otp-actions');
                    actionsEl.innerHTML = '';
                    actionsEl.appendChild(closeBtn);
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
                submitBtn.disabled = hidden.value.length < 6;
            }
            submitBtn.disabled = true;

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
                spinner.classList.remove('u-hidden');
                btnText.textContent   = 'Vérification…';
                document.getElementById('ff-otp-card').style.opacity = '0.6';
            });
        }());
        </script>
        <?php endif; // remainingAttempts > 0 ?>
        <?php endif; // otpBatch ?>

        <form method="POST" action="/admin/feature-flags/batch-request" id="ff-form">
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
                                        <?php $blocked = !$isSuperAdmin || $otpBlockSecondsLeft > 0; ?>
                                        <?php $ro = $blocked ? 'disabled' : ''; ?>
                                        <?php if ($type === 'bool'): ?>
                                            <label class="ff-toggle<?= $blocked ? ' ff-toggle--readonly' : '' ?>" aria-label="<?= htmlspecialchars($label) ?>">
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
                                                aria-label="<?= htmlspecialchars($label) ?>"
                                                value="<?= htmlspecialchars($value) ?>"
                                                class="ff-input"
                                                step="1"
                                                <?= $ro ?>
                                            >
                                        <?php else: ?>
                                            <input
                                                type="number"
                                                name="<?= htmlspecialchars($key) ?>"
                                                aria-label="<?= htmlspecialchars($label) ?>"
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
                    <?php if ($otpBlockSecondsLeft > 0): ?>
                        Nouvelles modifications bloquées — réessayez dans <strong id="ff-savebar-countdown">…</strong>.
                    <?php else: ?>
                        Un code OTP sera envoyé à votre adresse email pour confirmer les modifications.
                    <?php endif; ?>
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
            var confirmed = false;

            function checkDirty() {
                btn.disabled = serialize() === initial;
            }

            form.addEventListener('change', checkDirty);
            form.addEventListener('input', checkDirty);

            form.addEventListener('submit', function (e) {
                sessionStorage.removeItem('ff_otp_ts');
                if (confirmed) { return; }
                e.preventDefault();
                var msg = 'Envoyer un code OTP pour enregistrer toutes les modifications ?';
                if (typeof window.__openConfirm === 'function' && window.__confirmModal) {
                    window.__openConfirm(msg, function () { confirmed = true; form.submit(); });
                } else {
                    if (window.confirm(msg)) { confirmed = true; form.submit(); }
                }
            });
        }());
        </script>

    </div>
</main>

<?php require_once ROOT_PATH . '/src/View/admin/_close.php'; ?>
