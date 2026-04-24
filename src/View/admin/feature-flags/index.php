<?php
/**
 * Vue admin — Feature flags
 * Variables : $adminUser, $adminSection, $pageTitle, $breadcrumbs, $flags, $otpKey
 *
 * @var array<string, mixed>              $adminUser
 * @var string                            $adminSection
 * @var string                            $pageTitle
 * @var array<int, array<string, string>> $breadcrumbs
 * @var array<int, array<string, mixed>>  $flags
 * @var string|null                       $otpKey
 */
require_once ROOT_PATH . '/src/View/admin/_open.php';
$csrf = $_SESSION['csrf'] ?? '';
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

        <?php if ($otpKey !== null): ?>
            <?php
            $pendingFlag = null;
            foreach ($flags as $f) {
                if ($f['key'] === $otpKey) {
                    $pendingFlag = $f;
                    break;
                }
            }
            ?>
            <div class="admin-alert admin-alert--info" role="alert" style="margin-bottom:1.5rem;">
                <strong>Code envoyé par email.</strong>
                <?php if ($pendingFlag !== null): ?>
                    Confirmation requise pour modifier
                    <strong><?= htmlspecialchars((string) $pendingFlag['label']) ?></strong>.
                <?php endif; ?>
                Entrez le code à 6 chiffres reçu (valable 120 s) :
            </div>
            <form
                method="POST"
                action="/admin/feature-flags/<?= urlencode($otpKey) ?>/confirm-change"
                class="admin-form admin-form--otp"
                style="max-width:400px;margin-bottom:2rem;"
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
                        required
                        autofocus
                        style="letter-spacing:0.3em;font-size:1.4rem;text-align:center;"
                    >
                </div>
                <button type="submit" class="admin-btn admin-btn--primary">Confirmer</button>
                <a href="/admin/feature-flags" class="admin-btn admin-btn--ghost" style="margin-left:.5rem;">Annuler</a>
            </form>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card__header">
                <h1 class="admin-card__title">Feature flags</h1>
                <p class="admin-card__subtitle">
                    Toute modification est protégée par un code OTP envoyé par email (120 s, 3 tentatives max).
                </p>
            </div>
            <div class="admin-card__body">
                <table class="admin-table" aria-label="Feature flags">
                    <thead>
                        <tr>
                            <th scope="col">Flag</th>
                            <th scope="col">Description</th>
                            <th scope="col">Type</th>
                            <th scope="col">Valeur actuelle</th>
                            <th scope="col">Modifier</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($flags as $flag): ?>
                        <?php
                        $key      = (string) $flag['key'];
                        $value    = (string) $flag['value'];
                        $type     = (string) $flag['type'];
                        $label    = (string) $flag['label'];
                        $desc     = (string) ($flag['description'] ?? '');
                        $isActive = $otpKey === $key;

                        $displayValue = match ($type) {
                            'bool'  => $value === 'true' || $value === '1'
                                ? '<span class="badge badge--success">✓ Activé</span>'
                                : '<span class="badge badge--danger">✗ Désactivé</span>',
                            default => '<code>' . htmlspecialchars($value) . '</code>',
                        };
                        ?>
                        <tr class="<?= $isActive ? 'admin-table__row--highlight' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($label) ?></strong><br>
                                <code class="admin-table__key"><?= htmlspecialchars($key) ?></code>
                            </td>
                            <td class="admin-table__desc"><?= htmlspecialchars($desc) ?></td>
                            <td>
                                <?= match ($type) {
                                    'bool'  => 'Booléen',
                                    'int'   => 'Entier',
                                    'float' => 'Décimal',
                                    default => htmlspecialchars($type),
                                } ?>
                            </td>
                            <td><?= $displayValue ?></td>
                            <td>
                                <form
                                    method="POST"
                                    action="/admin/feature-flags/<?= urlencode($key) ?>/request-change"
                                    class="admin-form--inline"
                                >
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <?php if ($type === 'bool'): ?>
                                        <select name="new_value" class="admin-form__select admin-form__select--sm">
                                            <option value="true"  <?= $value === 'true'  ? 'selected' : '' ?>>Activé</option>
                                            <option value="false" <?= $value === 'false' ? 'selected' : '' ?>>Désactivé</option>
                                        </select>
                                    <?php elseif ($type === 'int'): ?>
                                        <input
                                            type="number"
                                            name="new_value"
                                            value="<?= htmlspecialchars($value) ?>"
                                            class="admin-form__input admin-form__input--sm"
                                            step="1"
                                            style="width:100px;"
                                        >
                                    <?php else: ?>
                                        <input
                                            type="number"
                                            name="new_value"
                                            value="<?= htmlspecialchars($value) ?>"
                                            class="admin-form__input admin-form__input--sm"
                                            step="0.01"
                                            style="width:100px;"
                                        >
                                    <?php endif; ?>
                                    <button
                                        type="submit"
                                        class="admin-btn admin-btn--sm admin-btn--secondary"
                                        data-admin-confirm="Envoyer un code OTP pour modifier ce flag ?"
                                    >
                                        Modifier
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<?php require_once ROOT_PATH . '/src/View/admin/_close.php'; ?>
