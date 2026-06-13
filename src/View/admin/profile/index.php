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
<div class="admin-card admin-card--narrow u-mb-8">
    <div class="admin-card__body">
        <h2 class="admin-profile-section-title">Changer mon mot de passe</h2>

        <form method="POST" action="/admin/securite/mot-de-passe" class="admin-form" novalidate id="profile-pwd-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="admin-field">
                <label class="admin-field__label" for="current_password">Mot de passe actuel *</label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="current_password" name="current_password"
                           class="admin-field__input" autocomplete="current-password">
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            data-pwd-target="current_password">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide u-hidden" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
                <span class="admin-field__error u-hidden" id="err-current">Ce champ est obligatoire.</span>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="new_password">Nouveau mot de passe * <span class="u-font-normal u-text-xxs">(12 caractères minimum)</span></label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="new_password" name="new_password"
                           class="admin-field__input" autocomplete="new-password">
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            data-pwd-target="new_password">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide u-hidden" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
                <span class="admin-field__error u-hidden" id="err-new">Minimum 12 caractères.</span>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="new_password_confirm">Confirmer le nouveau mot de passe *</label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="new_password_confirm" name="new_password_confirm"
                           class="admin-field__input" autocomplete="new-password">
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            data-pwd-target="new_password_confirm">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide u-hidden" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
                    </button>
                </div>
                <span class="admin-field__error u-hidden" id="err-confirm">Les mots de passe ne correspondent pas.</span>
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
<div class="admin-card u-mb-8">
    <div class="admin-card__body">
        <h2 class="admin-profile-section-title">Sessions actives</h2>

        <?php if ($sessions === []) : ?>
            <p class="admin-profile-empty">Aucune session active.</p>
        <?php else : ?>
            <div class="admin-profile-list">
                <?php foreach ($sessions as $session) :
                    $isCurrent       = isset($currentToken) && isset($session['token']) && $session['token'] === $currentToken;
                    $isDeviceTrusted = in_array($session['device_token'] ?? '', $trustedTokens, true);
                ?>
                    <div class="admin-profile-row<?= $isCurrent ? ' admin-profile-row--current' : '' ?>">
                        <div class="admin-profile-row__info">
                            <strong class="admin-profile-row__name">
                                <?= htmlspecialchars($session['device_name'] ?? 'Appareil inconnu') ?>
                            </strong>
                            <span class="admin-profile-row__meta">
                                <?= htmlspecialchars($session['ip_address'] ?? '—') ?>
                                &nbsp;·&nbsp;
                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($session['created_at']))) ?>
                            </span>
                            <?php if ($isCurrent) : ?>
                                <span class="admin-profile-badge admin-profile-badge--session">Session actuelle</span>
                            <?php endif; ?>
                            <?php if ($isDeviceTrusted) : ?>
                                <span class="admin-profile-badge admin-profile-badge--device">Appareil de confiance</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="/admin/securite/session/<?= (int) $session['id'] ?>/revoquer"
                              <?= $isCurrent ? 'data-admin-confirm="Révoquer votre session actuelle vous déconnectera. Continuer ?"' : '' ?>>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Révoquer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" action="/admin/securite/sessions/revoquer-toutes"
                  data-admin-confirm="Toutes vos sessions seront révoquées et vous serez déconnecté. Continuer ?"
                  class="u-mt-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Révoquer toutes les sessions</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================================================================
     Appareils de confiance
================================================================ -->
<div class="admin-card u-mb-8" id="appareils">
    <div class="admin-card__body">
        <h2 class="admin-profile-section-title">Appareils de confiance</h2>

        <?php if ($trustedDevices === []) : ?>
            <p class="admin-profile-empty">Aucun appareil de confiance enregistré.</p>
        <?php else : ?>
            <div class="admin-profile-list">
                <?php foreach ($trustedDevices as $device) :
                    $isCurrentDevice = isset($currentDeviceToken) && $device['device_token'] === $currentDeviceToken;
                ?>
                    <div class="admin-profile-row<?= $isCurrentDevice ? ' admin-profile-row--current' : '' ?>">
                        <div class="admin-profile-row__info">
                            <strong class="admin-profile-row__name">
                                <?= htmlspecialchars($device['device_name'] ?? 'Appareil inconnu') ?>
                            </strong>
                            <span class="admin-profile-row__meta">
                                Dernière activité : <?= htmlspecialchars(date('d/m/Y', strtotime($device['last_seen']))) ?>
                            </span>
                            <?php if ($isCurrentDevice) : ?>
                                <span class="admin-profile-badge admin-profile-badge--session">Appareil actuel</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="/admin/securite/appareils/retirer-confiance"
                              data-admin-confirm="Retirer cet appareil de la liste de confiance ?">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="device_token" value="<?= htmlspecialchars($device['device_token']) ?>">
                            <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Retirer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" action="/admin/securite/appareils/supprimer-toutes"
                  data-admin-confirm="Tous les appareils de confiance seront supprimés. Vous resterez connecté pour cette session, mais devrez revalider chaque appareil à la prochaine connexion. Continuer ?"
                  class="u-mt-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Supprimer tous les appareils de confiance</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================================================================
     Réinitialiser la sécurité
================================================================ -->
<div class="admin-card admin-card--danger u-mb-8">
    <div class="admin-card__body">
        <h2 class="admin-profile-section-title admin-profile-section-title--danger">Réinitialiser la sécurité</h2>
        <p class="u-text-87 admin-muted u-mb-5">
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

            <div class="admin-field u-mb-5">
                <label class="admin-field__label" for="admin_reset_password">Mot de passe</label>
                <div class="admin-field__pwd-wrap">
                    <input type="password" id="admin_reset_password" name="password"
                           class="admin-field__input" autocomplete="current-password" required>
                    <button type="button" class="admin-field__eye" aria-label="Afficher le mot de passe"
                            data-pwd-target="admin_reset_password">
                        <svg class="eye-icon eye-icon--show" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>
                        <svg class="eye-icon eye-icon--hide u-hidden" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="2" x2="14" y2="14"/></svg>
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


<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
// Validation formulaire changement mot de passe
document.getElementById('profile-pwd-form').addEventListener('submit', function (e) {
    let hasError = false;
    const current = document.getElementById('current_password');
    const errCurrent = document.getElementById('err-current');
    if (!current.value.trim()) {
        current.classList.add('is-error'); errCurrent.classList.remove('u-hidden'); hasError = true;
    } else { current.classList.remove('is-error'); errCurrent.classList.add('u-hidden'); }

    const newPwd = document.getElementById('new_password');
    const errNew = document.getElementById('err-new');
    if (newPwd.value.length < 12) {
        newPwd.classList.add('is-error'); errNew.classList.remove('u-hidden'); hasError = true;
    } else { newPwd.classList.remove('is-error'); errNew.classList.add('u-hidden'); }

    const conf = document.getElementById('new_password_confirm');
    const errConf = document.getElementById('err-confirm');
    if (conf.value !== newPwd.value || !conf.value) {
        conf.classList.add('is-error'); errConf.classList.remove('u-hidden'); hasError = true;
    } else { conf.classList.remove('is-error'); errConf.classList.add('u-hidden'); }

    if (hasError) {
        e.preventDefault();
        document.querySelector('#profile-pwd-form .admin-field__error:not(.u-hidden)')
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
