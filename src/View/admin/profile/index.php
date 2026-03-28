<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php if ($success ?? null) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error ?? null) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Sécurité</h1>
</div>

<?php
/** @var array<int, array<string, mixed>> $sessions */
/** @var array<int, array<string, mixed>> $trustedDevices */
$trustedTokens = array_column($trustedDevices, 'device_token');
?>

<!-- ================================================================
     Changement de mot de passe
================================================================ -->
<div class="admin-card" style="max-width:480px;margin-bottom:2rem;">
    <div class="admin-card__body">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;color:#1a1208;">Changer mon mot de passe</h2>

        <form method="POST" action="/admin/securite/mot-de-passe" class="admin-form" novalidate id="profile-pwd-form">
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
                <span class="admin-field__error" id="err-current" style="display:none;">Ce champ est obligatoire.</span>
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
                <span class="admin-field__error" id="err-new" style="display:none;">Minimum 12 caractères.</span>
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
                <span class="admin-field__error" id="err-confirm" style="display:none;">Les mots de passe ne correspondent pas.</span>
            </div>

            <div class="admin-form__actions">
                <button type="submit" class="admin-btn admin-btn--primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     Sessions actives
================================================================ -->
<div class="admin-card" style="margin-bottom:2rem;">
    <div class="admin-card__body">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;color:#1a1208;">Sessions actives</h2>

        <?php if ($sessions === []) : ?>
            <p style="color:#8a7a60;font-size:0.875rem;">Aucune session active.</p>
        <?php else : ?>
            <div style="display:flex;flex-direction:column;gap:0.625rem;">
                <?php foreach ($sessions as $session) :
                    $isCurrent       = isset($currentToken) && isset($session['token']) && $session['token'] === $currentToken;
                    $isDeviceTrusted = in_array($session['device_token'] ?? '', $trustedTokens, true);
                ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.75rem;border:1px solid #e8e0d0;border-radius:6px;<?= $isCurrent ? 'background:#fdf9f0;' : '' ?>">
                        <div style="flex:1;min-width:0;">
                            <strong style="font-size:0.875rem;color:#1a1208;display:block;">
                                <?= htmlspecialchars($session['device_name'] ?? 'Appareil inconnu') ?>
                            </strong>
                            <span style="font-size:0.8rem;color:#8a7a60;">
                                <?= htmlspecialchars($session['ip_address'] ?? '—') ?>
                                &nbsp;·&nbsp;
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($session['created_at']))) ?>
                            </span>
                            <?php if ($isCurrent) : ?>
                                <span style="display:inline-block;margin-top:0.2rem;font-size:0.7rem;background:#c9a84c;color:#fff;padding:0.1rem 0.45rem;border-radius:3px;">Session actuelle</span>
                            <?php endif; ?>
                            <?php if ($isDeviceTrusted) : ?>
                                <span style="display:inline-block;margin-top:0.2rem;font-size:0.7rem;background:#2d7a4f;color:#fff;padding:0.1rem 0.45rem;border-radius:3px;">Appareil de confiance</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="/admin/securite/session/<?= (int) $session['id'] ?>/revoquer"
                              <?= $isCurrent ? 'onsubmit="return confirm(\'Révoquer votre session actuelle vous déconnectera. Continuer ?\')"' : '' ?>>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Révoquer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" action="/admin/securite/sessions/revoquer-toutes"
                  onsubmit="return confirm('Toutes vos sessions seront révoquées et vous serez déconnecté. Continuer ?')"
                  style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Révoquer toutes les sessions</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================================================================
     Appareils de confiance
================================================================ -->
<div class="admin-card" style="margin-bottom:2rem;" id="appareils">
    <div class="admin-card__body">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;color:#1a1208;">Appareils de confiance</h2>

        <?php if ($trustedDevices === []) : ?>
            <p style="color:#8a7a60;font-size:0.875rem;">Aucun appareil de confiance enregistré.</p>
        <?php else : ?>
            <div style="display:flex;flex-direction:column;gap:0.625rem;">
                <?php foreach ($trustedDevices as $device) :
                    $isCurrentDevice = isset($currentDeviceToken) && $device['device_token'] === $currentDeviceToken;
                ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.75rem;border:1px solid #e8e0d0;border-radius:6px;<?= $isCurrentDevice ? 'background:#fdf9f0;' : '' ?>">
                        <div style="flex:1;min-width:0;">
                            <strong style="font-size:0.875rem;color:#1a1208;display:block;">
                                <?= htmlspecialchars($device['device_name'] ?? 'Appareil inconnu') ?>
                            </strong>
                            <span style="font-size:0.8rem;color:#8a7a60;">
                                Dernière activité : <?= htmlspecialchars(date('d/m/Y', strtotime($device['last_seen']))) ?>
                            </span>
                            <?php if ($isCurrentDevice) : ?>
                                <span style="display:inline-block;margin-top:0.2rem;font-size:0.7rem;background:#c9a84c;color:#fff;padding:0.1rem 0.45rem;border-radius:3px;">Appareil actuel</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="/admin/securite/appareils/retirer-confiance"
                              onsubmit="return confirm('Retirer cet appareil de la liste de confiance ?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="device_token" value="<?= htmlspecialchars($device['device_token']) ?>">
                            <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Retirer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" action="/admin/securite/appareils/supprimer-toutes"
                  onsubmit="return confirm('Tous les appareils de confiance seront supprimés. Vous resterez connecté pour cette session, mais devrez revalider chaque appareil à la prochaine connexion. Continuer ?')"
                  style="margin-top:1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Supprimer tous les appareils de confiance</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================================================================
     Réinitialiser la sécurité
================================================================ -->
<div class="admin-card" style="border-color:#fca5a5;max-width:480px;margin-bottom:2rem;">
    <div class="admin-card__body">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;color:#991b1b;">Réinitialiser la sécurité</h2>
        <p style="font-size:0.875rem;color:#8a7a60;margin-bottom:1.25rem;">
            Révoque toutes les sessions actives ET supprime tous les appareils de confiance. Vous serez déconnecté immédiatement.
        </p>

        <button type="button" class="admin-btn admin-btn--danger" id="js-admin-open-reset-modal">
            Réinitialiser la sécurité
        </button>
    </div>
</div>

<!-- Modal réinitialisation sécurité -->
<div class="account-delete-modal" id="admin-reset-security-modal"
     role="dialog" aria-modal="true" aria-labelledby="admin-reset-modal-title" hidden>
    <div class="account-delete-modal__backdrop" id="js-admin-reset-backdrop"></div>
    <div class="account-delete-modal__inner">
        <h2 id="admin-reset-modal-title" class="account-delete-modal__title">Réinitialiser la sécurité</h2>
        <p class="account-delete-modal__body">
            Toutes les sessions et appareils de confiance seront supprimés. Vous serez déconnecté immédiatement.<br>
            Confirmez avec votre mot de passe.
        </p>

        <form method="POST" action="/admin/securite/reinitialiser">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="admin-field" style="margin-bottom:1.25rem;">
                <label class="admin-field__label" for="admin_reset_password">Mot de passe</label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="admin_reset_password" name="password"
                           class="admin-field__input" autocomplete="current-password" required>
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            onclick="togglePwd('admin_reset_password', this)">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
            </div>

            <div class="account-delete-modal__actions">
                <button type="submit" class="admin-btn admin-btn--danger">Réinitialiser</button>
                <button type="button" class="admin-btn admin-btn--outline" id="js-admin-close-reset-modal">Annuler</button>
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

// Validation formulaire changement mot de passe
document.getElementById('profile-pwd-form').addEventListener('submit', function (e) {
    let hasError = false;
    const current = document.getElementById('current_password');
    const errCurrent = document.getElementById('err-current');
    if (!current.value.trim()) {
        current.classList.add('is-error'); errCurrent.style.display = 'block'; hasError = true;
    } else { current.classList.remove('is-error'); errCurrent.style.display = 'none'; }

    const newPwd = document.getElementById('new_password');
    const errNew = document.getElementById('err-new');
    if (newPwd.value.length < 12) {
        newPwd.classList.add('is-error'); errNew.style.display = 'block'; hasError = true;
    } else { newPwd.classList.remove('is-error'); errNew.style.display = 'none'; }

    const conf = document.getElementById('new_password_confirm');
    const errConf = document.getElementById('err-confirm');
    if (conf.value !== newPwd.value || !conf.value) {
        conf.classList.add('is-error'); errConf.style.display = 'block'; hasError = true;
    } else { conf.classList.remove('is-error'); errConf.style.display = 'none'; }

    if (hasError) {
        e.preventDefault();
        document.querySelector('#profile-pwd-form .admin-field__error[style*="block"]')
            ?.closest('.admin-field')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// Modal réinitialisation sécurité
(function () {
    const modal    = document.getElementById('admin-reset-security-modal');
    const openBtn  = document.getElementById('js-admin-open-reset-modal');
    const closeBtn = document.getElementById('js-admin-close-reset-modal');
    const backdrop = document.getElementById('js-admin-reset-backdrop');
    const pwdInput = modal.querySelector('input[name="password"]');

    function openModal() { modal.hidden = false; pwdInput?.focus(); }
    function closeModal() { modal.hidden = true; if (pwdInput) pwdInput.value = ''; }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
