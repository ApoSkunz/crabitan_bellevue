<?php
/**
 * Partial — JSON-LD Article (Schema.org)
 *
 * Variables attendues :
 *   string $articleHeadline       Titre de l'article
 *   string $articleDatePublished  Date de publication (YYYY-MM-DD)
 *   string $articleImage          Chemin relatif de l'image (ex: /assets/images/...)
 *   string $articleAbout          Description du sujet (Thing.name)
 *   string $articleSlug           Slug de la page (ex: le-chateau)
 *   string $metaDescription       Description meta de la page
 *   string $navLang               Langue active (fr|en)
 */
$jsonLd = json_encode([
    '@context'      => 'https://schema.org',
    '@type'         => 'Article',
    'headline'      => $articleHeadline,
    'description'   => $metaDescription,
    'datePublished' => $articleDatePublished,
    'dateModified'  => '2026-01-01',
    'author'        => ['@type' => 'Organization', 'name' => 'GFA Bernard Solane et Fils'],
    'publisher'     => [
        '@type' => 'Organization',
        'name'  => APP_NAME,
        'logo'  => ['@type' => 'ImageObject', 'url' => APP_BASE_URL . '/assets/images/logo/crabitan-bellevue-logo-restored.png'],
    ],
    'image'         => APP_BASE_URL . $articleImage,
    'about'         => ['@type' => 'Thing', 'name' => $articleAbout],
    'url'           => APP_BASE_URL . '/' . $navLang . '/' . $articleSlug,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
