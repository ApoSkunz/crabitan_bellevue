<?php
/**
 * Partial SEO — calcule canonical, hreflang et og:* depuis des variables simples.
 *
 * @var string      $navLang      Langue courante ('fr' ou 'en') — doit être définie avant l'include
 * @var string      $seoSlug      Slug URL (même valeur FR et EN, ex: 'le-chateau')
 * @var string|null $seoOgTitle   og:title — si absent, og:* non émis
 * @var string|null $seoOgImage   Chemin relatif image og (ex: '/assets/images/logo/…png') — défaut logo
 * @var string|null $metaDescription Description meta — requise si $seoOgTitle présent
 */
$appUrl     = APP_BASE_URL;
$canonical  = $appUrl . '/' . $navLang . '/' . $seoSlug;
$hreflangFr = $appUrl . '/fr/' . $seoSlug;
$hreflangEn = $appUrl . '/en/' . $seoSlug;

if (isset($seoOgTitle)) {
    $ogTitle       = $seoOgTitle;
    $ogDescription = $metaDescription ?? '';
    $ogImage       = $appUrl . ($seoOgImage ?? '/assets/images/logo/crabitan-bellevue-logo-restored.png');
    $ogUrl         = $canonical;
}

unset($seoSlug, $seoOgTitle, $seoOgImage);
