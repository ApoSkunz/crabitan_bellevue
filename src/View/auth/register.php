<?php
$pageTitle = __('auth.register');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
$e = fn(string $field) => isset($errors[$field])
    ? '<span class="form-error">' . htmlspecialchars($errors[$field]) . '</span>'
    : '';
$v = fn(string $field) => htmlspecialchars($old[$field] ?? '');
?>
<main class="auth-page">
    <div class="auth-card auth-card--wide">
        <h1><?= __('auth.register') ?></h1>

        <?php if (!empty($errors['email']) && count($errors) === 1) : ?>
            <div class="alert alert--error" role="alert">
                <?= htmlspecialchars($errors['email']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/<?= htmlspecialchars($lang) ?>/inscription" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="lastname"><?= __('form.lastname') ?></label>
                    <input type="text" id="lastname" name="lastname"
                           value="<?= $v('lastname') ?>" autocomplete="family-name" required>
                    <?= $e('lastname') ?>
                </div>
                <div class="form-group">
                    <label for="firstname"><?= __('form.firstname') ?></label>
                    <input type="text" id="firstname" name="firstname"
                           value="<?= $v('firstname') ?>" autocomplete="given-name" required>
                    <?= $e('firstname') ?>
                </div>
            </div>

            <div class="form-group">
                <label for="gender"><?= __('form.gender') ?></label>
                <select id="gender" name="gender" required>
                    <option value="" disabled <?= !isset($old['gender']) ? 'selected' : '' ?>></option>
                    <option value="M"       <?= ($old['gender'] ?? '') === 'M'       ? 'selected' : '' ?>><?= __('form.gender.m') ?></option>
                    <option value="F"       <?= ($old['gender'] ?? '') === 'F'       ? 'selected' : '' ?>><?= __('form.gender.f') ?></option>
                    <option value="other"   <?= ($old['gender'] ?? '') === 'other'   ? 'selected' : '' ?>><?= __('form.gender.other') ?></option>
                    <option value="society" <?= ($old['gender'] ?? '') === 'society' ? 'selected' : '' ?>><?= __('form.gender.society') ?></option>
                </select>
                <?= $e('gender') ?>
            </div>

            <div class="form-group form-group--society js-society" style="display:none">
                <label for="company_name"><?= __('form.company') ?></label>
                <input type="text" id="company_name" name="company_name"
                       value="<?= $v('company') ?>">
                <?= $e('company_name') ?>
            </div>

            <div class="form-group">
                <label for="email"><?= __('auth.email') ?></label>
                <input type="email" id="email" name="email"
                       value="<?= $v('email') ?>" autocomplete="email" required>
                <?= $e('email') ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password"><?= __('auth.password') ?></label>
                    <input type="password" id="password" name="password"
                           autocomplete="new-password" required minlength="8">
                    <?= $e('password') ?>
                </div>
                <div class="form-group">
                    <label for="password_confirm"><?= __('form.password_confirm') ?></label>
                    <input type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password" required minlength="8">
                    <?= $e('password_confirm') ?>
                </div>
            </div>

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
    const genderSelect = document.getElementById('gender');
    const societyField = document.querySelector('.js-society');
    genderSelect.addEventListener('change', () => {
        societyField.style.display = genderSelect.value === 'society' ? '' : 'none';
    });
    if (genderSelect.value === 'society') societyField.style.display = '';
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
