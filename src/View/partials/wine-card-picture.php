<?php
/**
 * Partial — image responsive d'une card vin (srcset 400w/800w + WebP).
 *
 * Variables attendues dans le scope parent :
 * @var array<string, mixed> $wine     Données du vin (image_path, label_name, vintage)
 * @var int                  $wineIdx  Index 0-based dans la liste (fetchpriority sur le premier)
 */
$wcpBase   = '/assets/images/wines/';
$wcpFile   = (string) ($wine['image_path'] ?? '');
$wcpNoExt  = (string) preg_replace('/\.[^.]+$/', '', $wcpFile);
$wcpExt    = pathinfo($wcpFile, PATHINFO_EXTENSION);
?>
<picture>
    <source
        srcset="<?= $wcpBase . rawurlencode($wcpNoExt . '-400w.webp') ?> 400w, <?= $wcpBase . rawurlencode($wcpNoExt . '-800w.webp') ?> 800w"
        sizes="(max-width: 640px) 400px, 300px"
        type="image/webp">
    <img
        src="<?= $wcpBase . rawurlencode($wcpFile) ?>"
        srcset="<?= $wcpBase . rawurlencode($wcpNoExt . '-400w.' . $wcpExt) ?> 400w, <?= $wcpBase . rawurlencode($wcpNoExt . '-800w.' . $wcpExt) ?> 800w"
        sizes="(max-width: 640px) 400px, 300px"
        alt="<?= htmlspecialchars(($wine['label_name'] ?? '') . ' ' . ($wine['vintage'] ?? '')) ?>"
        class="wine-card__image"
        <?= ($wineIdx ?? 0) === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
        width="300"
        height="420"
    >
</picture>
