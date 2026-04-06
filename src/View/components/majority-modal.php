<?php
/**
 * Modal de déclaration de majorité (Art. L3342-1 CSP).
 * Variables attendues :
 *   $majorityRedirect (string) — URL de redirection après confirmation
 *   $navLang          (string) — code langue courant (fr|en)
 */

$lang             = $navLang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$majorityRedirect = $majorityRedirect ?? "/{$lang}/mon-compte";

$title     = $lang === 'en' ? 'Age Declaration' : 'Déclaration de majorité';
$legalText = $lang === 'en'
    ? 'I certify on my honour that I am of the legal age required to purchase and consume alcohol in my country of residence (18 years old in France — Art. L3342-1 CSP).'
    : 'Je certifie sur l\'honneur avoir l\'âge légal requis pour l\'achat et la consommation d\'alcool dans mon pays de résidence (18 ans en France — Art. L3342-1 CSP).';
$checkLabel = $lang === 'en' ? 'I confirm that I am of legal age.' : 'Je confirme avoir l\'âge légal.';
$btnLabel   = $lang === 'en' ? 'Confirm' : 'Confirmer';
?>
<div class="majority-modal" id="majority-modal" role="dialog" aria-modal="true"
     aria-labelledby="majority-modal-title">
    <div class="majority-modal__backdrop" aria-hidden="true"></div>
    <div class="majority-modal__inner">
        <div class="majority-modal__header">
            <h2 class="majority-modal__title" id="majority-modal-title">
                <?= htmlspecialchars($title) ?>
            </h2>
        </div>
        <div class="majority-modal__body">
            <p class="majority-modal__legal">
                <?= htmlspecialchars($legalText) ?>
            </p>
            <form
                method="POST"
                action="/<?= htmlspecialchars($lang) ?>/declaration-majorite"
                class="majority-modal__form"
                id="majority-modal-form"
            >
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                <input type="hidden" name="redirect"
                       value="<?= htmlspecialchars($majorityRedirect) ?>">

                <label class="majority-modal__checkbox-label">
                    <input
                        type="checkbox"
                        name="majority_confirmed"
                        value="1"
                        id="majority-checkbox"
                        class="majority-modal__checkbox"
                    >
                    <?= htmlspecialchars($checkLabel) ?>
                </label>

                <button
                    type="submit"
                    class="btn btn--gold majority-modal__submit"
                    id="majority-submit-btn"
                    disabled
                >
                    <?= htmlspecialchars($btnLabel) ?>
                </button>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var checkbox = document.getElementById('majority-checkbox');
    var submitBtn = document.getElementById('majority-submit-btn');
    if (!checkbox || !submitBtn) { return; }
    checkbox.addEventListener('change', function () {
        submitBtn.disabled = !this.checked;
    });
}());
</script>
