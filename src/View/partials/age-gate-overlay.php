<?php
/**
 * Partial — overlay barrière d'âge (Art. L3342-1 CSP).
 * Affiché si cookie age_verified absent.
 * Requiert : $navLang (string).
 */
$overlayRedirect = $_SERVER['REQUEST_URI'] ?? '/' . ($navLang ?? 'fr');
?>
<div class="age-overlay" id="age-overlay" role="dialog" aria-modal="true" aria-labelledby="age-overlay-title">
    <div class="age-overlay__card">
        <div class="age-overlay__logo">
            <img
                src="/assets/images/logo/crabitan-bellevue-logo.png"
                alt="Château Crabitan Bellevue"
                width="80"
                height="80"
            >
        </div>

        <h2 id="age-overlay-title" class="age-overlay__title">Château Crabitan Bellevue</h2>

        <p class="age-overlay__text"><?= htmlspecialchars(__('age_gate.overlay_text')) ?></p>

        <div class="age-overlay__actions">
            <form method="POST" action="/age-gate">
                <input type="hidden" name="legal_age" value="1">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($overlayRedirect) ?>">
                <button type="submit" class="btn btn--gold age-overlay__btn-enter">
                    <?= htmlspecialchars(__('age_gate.enter')) ?>
                </button>
            </form>
            <form method="POST" action="/age-gate">
                <input type="hidden" name="legal_age" value="0">
                <button type="submit" class="age-overlay__btn-exit">
                    <?= htmlspecialchars(__('age_gate.exit')) ?>
                </button>
            </form>
        </div>

        <nav class="age-overlay__legal" aria-label="<?= htmlspecialchars(__('age_gate.info_label')) ?>">
            <a href="/<?= htmlspecialchars($navLang ?? 'fr') ?>/politique-de-confidentialite" target="_blank" rel="noopener noreferrer">
                <?= htmlspecialchars(__('footer.privacy_policy')) ?>
            </a>
            <span aria-hidden="true">·</span>
            <a href="/<?= htmlspecialchars($navLang ?? 'fr') ?>/mentions-legales" target="_blank" rel="noopener noreferrer">
                <?= htmlspecialchars(__('footer.legal_notice')) ?>
            </a>
        </nav>
    </div>
</div>
