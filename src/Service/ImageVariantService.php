<?php

declare(strict_types=1);

namespace Service;

/**
 * Génère et supprime les variantes WebP + redimensionnées d'une image vin.
 * Utilise l'extension GD (toujours disponible sur PHP 8.x).
 * Sans effet silencieux si GD ou le support WebP est absent.
 */
class ImageVariantService
{
    /** @var int[] Largeurs cibles en pixels */
    private const WIDTHS = [300, 600];

    /** @var int Qualité WebP (0–100) */
    private const WEBP_QUALITY = 82;

    /** @var int Qualité JPEG (0–100) */
    private const JPEG_QUALITY = 85;

    /**
     * Génère les variantes {base}-300w.webp, {base}-600w.webp,
     * {base}-300w.{ext} et {base}-600w.{ext} à côté de l'original.
     *
     * @param string $absPath Chemin absolu vers l'image source
     * @return void
     */
    public function generateWineVariants(string $absPath): void
    {
        if (!$this->gdSupported() || !file_exists($absPath)) {
            return;
        }

        $mime  = mime_content_type($absPath) ?: '';
        $image = $this->loadImage($absPath, $mime);
        if ($image === false) {
            return;
        }

        $origW = imagesx($image);
        $origH = imagesy($image);
        $ext   = $this->mimeToExt($mime);
        $base  = (string) preg_replace('/\.[^.]+$/', '', $absPath);

        foreach (self::WIDTHS as $w) {
            if ($origW < $w) {
                continue;
            }

            $h       = (int) round($origH * ($w / $origW));
            $resized = imagecreatetruecolor($w, $h);

            if ($mime === 'image/png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $w, $h, $transparent);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $w, $h, $origW, $origH);

            imagewebp($resized, "{$base}-{$w}w.webp", self::WEBP_QUALITY);

            if ($ext === 'png') {
                imagepng($resized, "{$base}-{$w}w.png", 9);
            } else {
                imagejpeg($resized, "{$base}-{$w}w.jpg", self::JPEG_QUALITY);
            }

            imagedestroy($resized);
        }

        imagedestroy($image);
    }

    /**
     * Supprime toutes les variantes générées pour une image vin.
     *
     * @param string $absPath Chemin absolu vers l'image source
     * @return void
     */
    public function deleteWineVariants(string $absPath): void
    {
        $base = (string) preg_replace('/\.[^.]+$/', '', $absPath);

        foreach (self::WIDTHS as $w) {
            foreach (['webp', 'png', 'jpg'] as $varExt) {
                $variant = "{$base}-{$w}w.{$varExt}";
                if (file_exists($variant)) {
                    @unlink($variant);
                }
            }
        }
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
            'image/webp' => imagecreatefromwebp($absPath),
            default      => false,
        };
    }

    private function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    private function gdSupported(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }
}
