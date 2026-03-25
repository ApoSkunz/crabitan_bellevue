<?php
$pageTitle = __('auth.register');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
$e       = fn(string $field) => isset($errors[$field])
    ? '<span class="form-error">' . htmlspecialchars($errors[$field]) . '</span>'
    : '';
$v       = fn(string $field) => htmlspecialchars($old[$field] ?? '');
$checked = fn(string $field, string $val) => ($old[$field] ?? '') === $val ? 'checked' : '';
$type    = $old['accountType'] ?? 'individual';
?>
<main class="auth-page">
    <div class="auth-card auth-card--wide">
        <h1><?= __('auth.register') ?></h1>
        <p class="auth-card__subtitle"><?= __('auth.register_subtitle') ?></p>

        <?php if (!empty($errors['email']) && count($errors) === 1) : ?>
            <div class="alert alert--error" role="alert">
                <?= htmlspecialchars($errors['email']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/<?= htmlspecialchars($lang) ?>/inscription" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <fieldset class="auth-fieldset">
                <legend><?= __('form.identity') ?></legend>

                <div class="form-group form-group--radio-row">
                    <span class="form-label"><?= __('form.account_type') ?> *</span>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="account_type" value="individual"
                                   <?= $type !== 'company' ? 'checked' : '' ?>>
                            <?= __('form.account_type.individual') ?>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="account_type" value="company"
                                   <?= $type === 'company' ? 'checked' : '' ?>>
                            <?= __('form.account_type.company') ?>
                        </label>
                    </div>
                    <?= $e('account_type') ?>
                </div>

                <div class="js-individual-fields"<?= $type === 'company' ? ' style="display:none"' : '' ?>>
                    <div class="form-group form-group--radio-row">
                        <span class="form-label"><?= __('form.civility') ?> *</span>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="civility" value="M" <?= $checked('civility', 'M') ?>>
                                <?= __('form.civility.m') ?>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="civility" value="F" <?= $checked('civility', 'F') ?>>
                                <?= __('form.civility.f') ?>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="civility" value="other" <?= $checked('civility', 'other') ?>>
                                <?= __('form.civility.other') ?>
                            </label>
                        </div>
                        <?= $e('civility') ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastname"><?= __('form.lastname') ?> *</label>
                            <input type="text" id="lastname" name="lastname"
                                   value="<?= $v('lastname') ?>" autocomplete="family-name">
                            <?= $e('lastname') ?>
                        </div>
                        <div class="form-group">
                            <label for="firstname"><?= __('form.firstname') ?> *</label>
                            <input type="text" id="firstname" name="firstname"
                                   value="<?= $v('firstname') ?>" autocomplete="given-name">
                            <?= $e('firstname') ?>
                        </div>
                    </div>
                </div>

                <div class="js-company-fields"<?= $type !== 'company' ? ' style="display:none"' : '' ?>>
                    <div class="form-group">
                        <label for="company_name"><?= __('form.company') ?> *</label>
                        <input type="text" id="company_name" name="company_name"
                               value="<?= $v('company') ?>" autocomplete="organization">
                        <?= $e('company_name') ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="auth-fieldset">
                <legend><?= __('form.credentials') ?></legend>

                <div class="form-group">
                    <label for="email"><?= __('auth.email') ?> *</label>
                    <input type="email" id="email" name="email"
                           value="<?= $v('email') ?>" autocomplete="email" required>
                    <?= $e('email') ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password"><?= __('auth.password') ?> *</label>
                        <input type="password" id="password" name="password"
                               autocomplete="new-password" required minlength="8">
                        <?= $e('password') ?>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm"><?= __('form.password_confirm') ?> *</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               autocomplete="new-password" required minlength="8">
                        <?= $e('password_confirm') ?>
                    </div>
                </div>
                <p class="form-hint"><?= __('form.password_hint') ?></p>
            </fieldset>

            <div class="form-group form-group--checkbox">
                <label>
                    <input type="checkbox" name="newsletter" value="1"
                           <?= ($old['newsletter'] ?? 0) ? 'checked' : '' ?>>
                    <?= __('form.newsletter') ?>
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--full">
                <?= __('auth.register') ?>
            </button>
        </form>

        <p class="auth-card__switch">
            <a href="/<?= htmlspecialchars($lang) ?>/connexion"><?= __('auth.login') ?></a>
        </p>
    </div>
</main>

<script>
    const accountTypeRadios = document.querySelectorAll('[name="account_type"]');
    const indFields  = document.querySelector('.js-individual-fields');
    const compFields = document.querySelector('.js-company-fields');

    function toggleAccountFields() {
        const isCompany = document.querySelector('[name="account_type"]:checked')?.value === 'company';
        indFields.style.display  = isCompany ? 'none' : '';
        compFields.style.display = isCompany ? '' : 'none';
    }

    accountTypeRadios.forEach(r => r.addEventListener('change', toggleAccountFields));
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
