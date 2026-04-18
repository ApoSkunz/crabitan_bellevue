<?php

declare(strict_types=1);

namespace Service;

/**
 * Convertit une image vin uploadée en WebP à dimensions originales.
 * Utilise l'extension GD. Sans effet silencieux si GD absent ou conversion impossible.
 */
class ImageVariantService
{
    /** @var int Qualité WebP (0–100) */
    private const WEBP_QUALITY = 85;

    /**
     * Convertit l'image source en WebP, supprime l'original et retourne le nouveau chemin.
     * Retourne null si GD est absent, le format non supporté, ou si l'image est déjà en WebP.
     *
     * @param string $absPath Chemin absolu vers l'image source (PNG, JPEG…)
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

        if (!imagewebp($image, $webpPath, self::WEBP_QUALITY)) {
            imagedestroy($image);
            return null;
        }

        imagedestroy($image);

        return $webpPath;
    }

    /**
     * @param string $absPath
     * @param string $mime
     * @return \GdImage|false
     */
    private function loadImage(string $absPath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($absPath),
            'image/png'  => imagecreatefrompng($absPath),
            default      => false,
        };
    }

    private function gdSupported(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }
}
