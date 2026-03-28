<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($success ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Mon profil</h1>
</div>

<div class="admin-card" style="max-width:480px;">
    <div class="admin-card__body">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;color:#1a1208;">Changer mon mot de passe</h2>

        <form method="POST" action="/admin/mon-profil/mot-de-passe" class="admin-form" novalidate id="profile-pwd-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="admin-field">
                <label class="admin-field__label" for="current_password">Mot de passe actuel *</label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="current_password" name="current_password"
                           class="admin-field__input" autocomplete="current-password">
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            onclick="togglePwd('current_password', this)">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
                <span class="admin-field__error" id="err-current" style="display:none;">
                    Ce champ est obligatoire.
                </span>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="new_password">Nouveau mot de passe * <span style="font-weight:400;font-size:0.72rem;">(12 caractères minimum)</span></label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="new_password" name="new_password"
                           class="admin-field__input" autocomplete="new-password">
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            onclick="togglePwd('new_password', this)">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
                <span class="admin-field__error" id="err-new" style="display:none;">
                    Minimum 12 caractères.
                </span>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="new_password_confirm">Confirmer le nouveau mot de passe *</label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="new_password_confirm" name="new_password_confirm"
                           class="admin-field__input" autocomplete="new-password">
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            onclick="togglePwd('new_password_confirm', this)">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
                <span class="admin-field__error" id="err-confirm" style="display:none;">
                    Les mots de passe ne correspondent pas.
                </span>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="admin-btn admin-btn--primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<style>
.admin-field__pwd-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.admin-field__pwd-wrap .admin-field__input {
    flex: 1;
    padding-right: 2.5rem;
}
.admin-field__eye {
    position: absolute;
    right: 0.625rem;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    color: #8a7a60;
    display: flex;
    align-items: center;
    transition: color 150ms ease;
}
.admin-field__eye:hover { color: #1a1208; }
</style>

<script>
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelector('.eye-icon--show').style.display = isHidden ? 'none' : '';
    btn.querySelector('.eye-icon--hide').style.display = isHidden ? '' : 'none';
    btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
}

document.getElementById('profile-pwd-form').addEventListener('submit', function (e) {
    let hasError = false;

    const current = document.getElementById('current_password');
    const errCurrent = document.getElementById('err-current');
    if (!current.value.trim()) {
        current.classList.add('is-error');
        errCurrent.style.display = 'block';
        hasError = true;
    } else {
        current.classList.remove('is-error');
        errCurrent.style.display = 'none';
    }

    const newPwd = document.getElementById('new_password');
    const errNew = document.getElementById('err-new');
    if (newPwd.value.length < 12) {
        newPwd.classList.add('is-error');
        errNew.style.display = 'block';
        hasError = true;
    } else {
        newPwd.classList.remove('is-error');
        errNew.style.display = 'none';
    }

    const confirm = document.getElementById('new_password_confirm');
    const errConfirm = document.getElementById('err-confirm');
    if (confirm.value !== newPwd.value || !confirm.value) {
        confirm.classList.add('is-error');
        errConfirm.style.display = 'block';
        hasError = true;
    } else {
        confirm.classList.remove('is-error');
        errConfirm.style.display = 'none';
    }

    if (hasError) {
        e.preventDefault();
        document.querySelector('#profile-pwd-form .admin-field__error[style*="block"]')
            ?.closest('.admin-field')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
