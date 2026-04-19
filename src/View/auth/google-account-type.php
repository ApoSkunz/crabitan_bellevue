<?php
$pageTitle       = __('auth.google_type_title');
$noindex         = true;
$googleFullName  = trim(($googleFirstname ?? '') . ' ' . ($googleLastname ?? ''));
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card__google-icon" aria-hidden="true">
            <img src="/assets/images/login/Google__G__logo.png" alt="" width="32" height="32">
        </div>

        <h1><?= htmlspecialchars(__('auth.google_type_title')) ?></h1>

        <form method="POST"
              action="/<?= htmlspecialchars($lang) ?>/auth/google/type-compte"
              class="auth-card__form"
              id="google-type-form">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

            <div role="group"
                 aria-labelledby="account-type-legend"
                 class="form-group--radio">
                <span id="account-type-legend" class="sr-only"><?= htmlspecialchars(__('form.account_type')) ?></span>

                <label class="radio-label">
                    <input type="radio" name="account_type" value="individual" checked>
                    <?= htmlspecialchars(__('form.account_type.individual')) ?>
                </label>

                <label class="radio-label">
                    <input type="radio" name="account_type" value="company" id="type-company">
                    <?= htmlspecialchars(__('form.account_type.company')) ?>
                </label>
            </div>

            <div class="form-group" id="company-name-group" style="display:none">
                <label class="form-label" for="company_name">
                    <?= htmlspecialchars(__('auth.google_type_company_name')) ?>
                </label>
                <input type="text"
                       id="company_name"
                       name="company_name"
                       class="form-control"
                       value="<?= htmlspecialchars($googleFullName) ?>"
                       maxlength="255"
                       autocomplete="organization">
            </div>

            <button type="submit" class="btn btn--primary btn--full">
                <?= htmlspecialchars(__('auth.google_type_submit')) ?>
            </button>
        </form>
    </div>
</main>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var radios   = document.querySelectorAll('input[name="account_type"]');
    var group    = document.getElementById('company-name-group');
    var input    = document.getElementById('company_name');
    var typeComp = document.getElementById('type-company');

    function toggle() {
        var isCompany = typeComp.checked;
        group.style.display = isCompany ? '' : 'none';
        input.required = isCompany;
    }

    radios.forEach(function (r) { r.addEventListener('change', toggle); });
    toggle();
}());
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
