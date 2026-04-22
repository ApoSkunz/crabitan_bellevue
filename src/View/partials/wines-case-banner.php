<?php
/**
 * Bandeau élégant invitant à composer une caisse de 12 bouteilles assorties.
 * Affiché au-dessus des filtres sur /vins et /vins/collection.
 */
$bannerLang = $navLang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
?>
<aside class="wines-case-banner" aria-label="<?= htmlspecialchars(__('wine.case_banner.aria')) ?>">
    <div class="container">
        <div class="wines-case-banner__inner">
            <span class="wines-case-banner__ornament" aria-hidden="true">&#10086;</span>
            <p class="wines-case-banner__text">
                <span class="wines-case-banner__eyebrow"><?= htmlspecialchars(__('wine.case_banner.eyebrow')) ?></span>
                <span class="wines-case-banner__message"><?= htmlspecialchars(__('wine.case_banner.message')) ?></span>
            </p>
            <span class="wines-case-banner__ornament" aria-hidden="true">&#10086;</span>
        </div>
    </div>
</aside>

<?php if (\Core\FeatureFlags::isClickCollectEnabled()) : ?>
<div class="click-collect-badge container">
    <span class="click-collect-badge__icon" aria-hidden="true">&#127984;</span>
    <span class="click-collect-badge__text">
        <?= $bannerLang === 'en'
            ? 'Click &amp; Collect — available at the château within 48h'
            : 'Click &amp; Collect — disponible au château sous 48h' ?>
    </span>
</div>
<?php endif; ?>
