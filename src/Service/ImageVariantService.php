<?php

declare(strict_types=1);

namespace Service;

/**
 * Génère les variantes d'image vin : WebP original + variantes redimensionnées 400w/800w.
 * Utilise l'extension GD. Sans effet silencieux si GD absent ou conversion impossible.
 */
class ImageVariantService
{
    /** @var int Qualité WebP (0–100) */
    private const WEBP_QUALITY = 82;

    /** @var int Qualité JPEG resized (0–100) */
    private const JPEG_QUALITY = 85;

    /** @var int[] Largeurs cibles pour les variantes responsive */
    private const WIDTHS = [400, 800];

    /**
     * Génère toutes les variantes depuis une image source :
     *   - {name}.webp (conversion à taille originale)
     *   - {name}-400w.{ext} + {name}-400w.webp
     *   - {name}-800w.{ext} + {name}-800w.webp
     *
     * @param string $absPath Chemin absolu vers l'image source (PNG, JPEG)
     * @return void
     */
    public function generateVariants(string $absPath): void
    {
        if (!$this->gdSupported() || !file_exists($absPath)) {
            return;
        }

        $mime = mime_content_type($absPath) ?: '';

        if ($mime === 'image/webp') {
            return;
        }

        $base = (string) preg_replace('/\.[^.]+$/', '', $absPath);
        $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        $source = $this->loadImage($absPath, $mime);
        if ($source === false) {
            return;
        }

        // WebP à taille originale
        imagewebp($source, $base . '.webp', self::WEBP_QUALITY);

        $origW = imagesx($source);
        $origH = imagesy($source);

        foreach (self::WIDTHS as $targetW) {
            if ($origW <= $targetW) {
                // Source plus petite que la cible → copier l'original comme variante
                copy($absPath, $base . '-' . $targetW . 'w.' . $ext);
                imagewebp($source, $base . '-' . $targetW . 'w.webp', self::WEBP_QUALITY);
                continue;
            }

            $targetH  = (int) round($origH * $targetW / $origW);
            $resized  = imagecreatetruecolor($targetW, $targetH);

            if ($resized === false) {
                continue;
            }

            // Transparence PNG
            if ($mime === 'image/png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                if ($transparent !== false) {
                    imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $transparent);
                }
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

            // Variante format original
            $variantPath = $base . '-' . $targetW . 'w.' . $ext;
            if ($mime === 'image/png') {
                imagepng($resized, $variantPath, 9);
            } else {
                imagejpeg($resized, $variantPath, self::JPEG_QUALITY);
            }

            // Variante WebP
            imagewebp($resized, $base . '-' . $targetW . 'w.webp', self::WEBP_QUALITY);

            imagedestroy($resized);
        }

        imagedestroy($source);
    }

    /**
     * Supprime toutes les variantes d'une image (original + WebP + 400w + 800w).
     *
     * @param string $absPath Chemin absolu de l'image originale
     * @return void
     */
    public function deleteVariants(string $absPath): void
    {
        $base = (string) preg_replace('/\.[^.]+$/', '', $absPath);
        $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        $candidates = [
            $absPath,
            $base . '.webp',
        ];

        foreach (self::WIDTHS as $w) {
            $candidates[] = $base . '-' . $w . 'w.' . $ext;
            $candidates[] = $base . '-' . $w . 'w.webp';
        }

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Convertit l'image source en WebP à dimensions originales (compatibilité amont).
     *
     * @param string $absPath Chemin absolu vers l'image source
     * @return string|null Chemin absolu du fichier WebP généré, ou null en cas d'échec
     */
    public function convertToWebp(string $absPath): ?string
    {
        if (!$this->gdSupported() || !file_exists($absPath)) {
            return null;
        }

        $mime = mime_content_type($absPath) ?: '';

        if ($mime === 'image/webp') {
            return $absPath;
        }

        $image = $this->loadImage($absPath, $mime);
        if ($image === false) {
            return null;
        }

        $webpPath = (string) preg_replace('/\.[^.]+$/', '', $absPath) . '.webp';
        $ok       = imagewebp($image, $webpPath, self::WEBP_QUALITY);
        imagedestroy($image);

        return $ok ? $webpPath : null;
    }

    /**
     * @param string $absPath
     * @param string $mime
     * @return \GdImage|false
     */
    private function loadImage(string $absPath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absPath),
            'image/png'  => @imagecreatefrompng($absPath),
            default      => false,
        };
    }

    /**
     * @return bool
     */
    protected function gdSupported(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }
}
