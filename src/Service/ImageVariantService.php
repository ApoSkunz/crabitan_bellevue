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

    /** @var string Extension WebP */
    private const EXT_WEBP = '.webp';

    /** @var string Regex suppression extension */
    private const EXT_REGEX = '/\.[^.]+$/';

    /** @var string MIME type PNG */
    private const MIME_PNG = 'image/png';

    /**
     * Génère toutes les variantes depuis une image source :
     *   - {name}.webp (conversion à taille originale)
     *   - {name}-400w.{ext} + {name}-400w.webp
     *   - {name}-800w.{ext} + {name}-800w.webp
     *
     * @param string $absPath Chemin absolu vers l'image source (PNG, JPEG)
     * @return void
     */
    public function generateVariants(string $absPath): void // NOSONAR — php:S3776 : complexité inhérente au pipeline GD multi-format
    {
        if (!$this->gdSupported() || !file_exists($absPath)) {
            return;
        }

        $mime = mime_content_type($absPath) ?: '';

        if ($mime === 'image/webp') {
            return;
        }

        $base = (string) preg_replace(self::EXT_REGEX, '', $absPath);
        $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        $source = $this->loadImage($absPath, $mime);
        if ($source === false) {
            return;
        }

        imagewebp($source, $base . self::EXT_WEBP, self::WEBP_QUALITY);

        $origW = imagesx($source);
        $origH = imagesy($source);

        foreach (self::WIDTHS as $targetW) {
            $this->generateWidth($source, $base, $ext, $mime, $targetW, $origW, $origH);
        }

        imagedestroy($source);
    }

    /**
     * Génère une variante redimensionnée pour une largeur cible donnée.
     *
     * @param \GdImage $source  Image source GD
     * @param string   $base    Chemin sans extension (ex. /path/Wine_X_2022)
     * @param string   $ext     Extension format original (ex. png)
     * @param string   $mime    MIME type source
     * @param int      $targetW Largeur cible
     * @param int      $origW   Largeur originale
     * @param int      $origH   Hauteur originale
     * @return void
     */
    private function generateWidth(
        \GdImage $source,
        string $base,
        string $ext,
        string $mime,
        int $targetW,
        int $origW,
        int $origH
    ): void {
        $suffix = '-' . $targetW . 'w.';

        if ($origW <= $targetW) {
            copy($base . '.' . $ext, $base . $suffix . $ext);
            imagewebp($source, $base . $suffix . 'webp', self::WEBP_QUALITY);
            return;
        }

        $targetH = (int) round($origH * $targetW / $origW);
        $resized = imagecreatetruecolor($targetW, $targetH);

        if ($resized === false) {
            return;
        }

        if ($mime === self::MIME_PNG) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $transparent);
            }
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

        $variantPath = $base . $suffix . $ext;
        if ($mime === self::MIME_PNG) {
            imagepng($resized, $variantPath, 9);
        } else {
            imagejpeg($resized, $variantPath, self::JPEG_QUALITY);
        }

        imagewebp($resized, $base . $suffix . 'webp', self::WEBP_QUALITY);
        imagedestroy($resized);
    }

    /**
     * Supprime toutes les variantes d'une image (original + WebP + 400w + 800w).
     *
     * @param string $absPath Chemin absolu de l'image originale
     * @return void
     */
    public function deleteVariants(string $absPath): void
    {
        $base = (string) preg_replace(self::EXT_REGEX, '', $absPath);
        $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        $candidates = [
            $absPath,
            $base . self::EXT_WEBP,
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

        $image    = $this->loadImage($absPath, $mime);
        $webpPath = (string) preg_replace(self::EXT_REGEX, '', $absPath) . self::EXT_WEBP;
        $ok       = $image !== false && imagewebp($image, $webpPath, self::WEBP_QUALITY);

        if ($image !== false) {
            imagedestroy($image);
        }

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
            self::MIME_PNG  => @imagecreatefrompng($absPath),
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
