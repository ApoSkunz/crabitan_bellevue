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
<p class="click-collect-badge container">
    <?= $bannerLang === 'en'
        ? 'Click &amp; Collect option available — pickup at the château within 48h'
        : 'Option Click &amp; Collect disponible — retrait au château sous 48h' ?>
</p>
<?php endif; ?>
