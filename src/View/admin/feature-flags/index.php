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

        <?php if (!empty($_SESSION['flash'])): ?>
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
            <div class="admin-card" style="margin-bottom:1.5rem;border-left:3px solid #c9a84c;">
                <div class="admin-card__header">
                    <h2 class="admin-card__title" style="font-size:1rem;">Confirmation OTP requise</h2>
                    <p class="admin-card__subtitle">
                        Entrez le code à 6 chiffres reçu par email (valable 120 s, 3 tentatives max).
                    </p>
                </div>
                <div class="admin-card__body">
                    <form
                        method="POST"
                        action="/admin/feature-flags/batch-confirm"
                        class="admin-form"
                        style="max-width:320px;"
                    >
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <div class="admin-form__group">
                            <label class="admin-form__label" for="otp-input">Code OTP</label>
                            <input
                                type="text"
                                id="otp-input"
                                name="otp"
                                class="admin-form__input"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                placeholder="000000"
                                autocomplete="one-time-code"
                                inputmode="numeric"
                                required
                                autofocus
                                style="letter-spacing:0.35em;font-size:1.5rem;text-align:center;"
                            >
                        </div>
                        <div style="display:flex;gap:.75rem;align-items:center;margin-top:1rem;">
                            <button type="submit" class="admin-btn admin-btn--primary">
                                Valider les modifications
                            </button>
                            <a href="/admin/feature-flags" class="admin-btn admin-btn--ghost">
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/feature-flags/batch-request" id="ff-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

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
                    data-admin-confirm="Envoyer un code OTP pour enregistrer toutes les modifications ?"
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
        }());
        </script>

    </div>
</main>

<?php require_once ROOT_PATH . '/src/View/admin/_close.php'; ?>
