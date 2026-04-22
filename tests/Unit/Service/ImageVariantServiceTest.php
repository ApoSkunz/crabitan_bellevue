<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\ImageVariantService;

/**
 * Tests unitaires pour ImageVariantService.
 */
class ImageVariantServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
        $this->tmpFiles = [];
    }

    private function tmpPath(string $ext): string
    {
        $path = sys_get_temp_dir() . '/ivs_test_' . uniqid() . $ext;
        $this->tmpFiles[] = $path;
        return $path;
    }

    // ----------------------------------------------------------------
    // Fichier inexistant
    // ----------------------------------------------------------------

    /**
     * Chemin inexistant → null.
     */
    public function testReturnsNullIfFileNotExists(): void
    {
        $svc = new ImageVariantService();
        $this->assertNull($svc->convertToWebp('/no/such/file.png'));
    }

    // ----------------------------------------------------------------
    // GD non disponible
    // ----------------------------------------------------------------

    /**
     * GD indisponible → null sans toucher le fichier.
     */
    public function testReturnsNullIfGdNotSupported(): void
    {
        $path = $this->tmpPath('.png');
        file_put_contents($path, 'dummy');

        $svc = new class extends ImageVariantService {
            protected function gdSupported(): bool
            {
                return false;
            }
        };

        $this->assertNull($svc->convertToWebp($path));
    }

    // ----------------------------------------------------------------
    // Fichier déjà WebP
    // ----------------------------------------------------------------

    /**
     * Fichier déjà WebP → retourne le chemin inchangé.
     */
    public function testReturnsPathIfAlreadyWebp(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $path = $this->tmpPath('.webp');
        // Minimal WebP header (RIFF + WEBP signature)
        file_put_contents($path, "RIFF\x1c\x00\x00\x00WEBPVP8 \x10\x00\x00\x00");

        $svc = new ImageVariantService();
        $this->assertSame($path, $svc->convertToWebp($path));
    }

    // ----------------------------------------------------------------
    // Conversion PNG → WebP
    // ----------------------------------------------------------------

    /**
     * PNG valide → WebP créé, chemin retourné.
     */
    public function testConvertsPngToWebp(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $pngPath  = $this->tmpPath('.png');
        $webpPath = (string) preg_replace('/\.[^.]+$/', '.webp', $pngPath);
        $this->tmpFiles[] = $webpPath;

        $img = imagecreatetruecolor(4, 4);
        imagepng($img, $pngPath);
        imagedestroy($img);

        $svc    = new ImageVariantService();
        $result = $svc->convertToWebp($pngPath);

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.webp', (string) $result);
        $this->assertFileExists((string) $result);
    }

    // ----------------------------------------------------------------
    // Conversion JPEG → WebP
    // ----------------------------------------------------------------

    /**
     * JPEG valide → WebP créé, chemin retourné.
     */
    public function testConvertsJpegToWebp(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $jpgPath  = $this->tmpPath('.jpg');
        $webpPath = (string) preg_replace('/\.[^.]+$/', '.webp', $jpgPath);
        $this->tmpFiles[] = $webpPath;

        $img = imagecreatetruecolor(4, 4);
        imagejpeg($img, $jpgPath, 85);
        imagedestroy($img);

        $svc    = new ImageVariantService();
        $result = $svc->convertToWebp($jpgPath);

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.webp', (string) $result);
        $this->assertFileExists((string) $result);
    }

    // ----------------------------------------------------------------
    // Format non supporté
    // ----------------------------------------------------------------

    /**
     * Fichier texte (mime non supporté) → null.
     */
    public function testReturnsNullForUnsupportedMime(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $path = $this->tmpPath('.txt');
        file_put_contents($path, 'plain text content, not an image');

        $svc = new ImageVariantService();
        $this->assertNull($svc->convertToWebp($path));
    }

    // ----------------------------------------------------------------
    // generateVariants — PNG
    // ----------------------------------------------------------------

    /**
     * generateVariants sur PNG 1000px → crée WebP + variantes 400w + 800w.
     */
    public function testGenerateVariantsPng(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $pngPath = $this->tmpPath('.png');
        $base    = (string) preg_replace('/\.[^.]+$/', '', $pngPath);

        $this->tmpFiles[] = $base . '.webp';
        $this->tmpFiles[] = $base . '-400w.png';
        $this->tmpFiles[] = $base . '-400w.webp';
        $this->tmpFiles[] = $base . '-800w.png';
        $this->tmpFiles[] = $base . '-800w.webp';

        $img = imagecreatetruecolor(1000, 1400);
        imagepng($img, $pngPath);
        imagedestroy($img);

        (new ImageVariantService())->generateVariants($pngPath);

        $this->assertFileExists($base . '.webp');
        $this->assertFileExists($base . '-400w.png');
        $this->assertFileExists($base . '-400w.webp');
        $this->assertFileExists($base . '-800w.png');
        $this->assertFileExists($base . '-800w.webp');

        // Dimensions 400w correctes
        [$w400] = getimagesize($base . '-400w.png');
        $this->assertSame(400, $w400);
    }

    // ----------------------------------------------------------------
    // generateVariants — image source plus petite que la cible
    // ----------------------------------------------------------------

    /**
     * generateVariants sur image 200px de large → variantes ≤ 200px (pas d'upscale).
     */
    public function testGenerateVariantsNoUpscale(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $pngPath = $this->tmpPath('.png');
        $base    = (string) preg_replace('/\.[^.]+$/', '', $pngPath);

        $this->tmpFiles[] = $base . '.webp';
        $this->tmpFiles[] = $base . '-400w.png';
        $this->tmpFiles[] = $base . '-400w.webp';
        $this->tmpFiles[] = $base . '-800w.png';
        $this->tmpFiles[] = $base . '-800w.webp';

        $img = imagecreatetruecolor(200, 280);
        imagepng($img, $pngPath);
        imagedestroy($img);

        (new ImageVariantService())->generateVariants($pngPath);

        // 200px < 400w → copie de l'original, pas d'upscale
        [$w400] = getimagesize($base . '-400w.png');
        $this->assertSame(200, $w400);
    }

    // ----------------------------------------------------------------
    // generateVariants — GD non disponible (ligne 43)
    // ----------------------------------------------------------------

    /**
     * generateVariants ne génère rien si GD n'est pas supporté (ligne 43).
     */
    public function testGenerateVariantsNoOpWhenGdNotSupported(): void
    {
        $pngPath = $this->tmpPath('.png');
        file_put_contents($pngPath, 'dummy content');

        $svc = new class extends ImageVariantService {
            protected function gdSupported(): bool
            {
                return false;
            }
        };

        $svc->generateVariants($pngPath);
        $base = (string) preg_replace('/\.[^.]+$/', '', $pngPath);

        // Aucun fichier WebP ni variante généré
        $this->assertFileDoesNotExist($base . '.webp');
    }

    // ----------------------------------------------------------------
    // generateVariants — fichier inexistant (ligne 43)
    // ----------------------------------------------------------------

    /**
     * generateVariants ne fait rien si le fichier source n'existe pas (ligne 43).
     */
    public function testGenerateVariantsNoOpWhenFileNotExists(): void
    {
        $svc = new ImageVariantService();
        // Ne lève pas d'exception — silencieux
        $svc->generateVariants('/no/such/file.png');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // generateVariants — source WebP (ligne 49)
    // ----------------------------------------------------------------

    /**
     * generateVariants retourne sans traitement si la source est déjà WebP (ligne 49).
     */
    public function testGenerateVariantsNoOpWhenSourceIsWebp(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $path = $this->tmpPath('.webp');
        // WebP minimal header
        file_put_contents($path, "RIFF\x1c\x00\x00\x00WEBPVP8 \x10\x00\x00\x00");

        $svc  = new ImageVariantService();
        $base = (string) preg_replace('/\.[^.]+$/', '', $path);
        $svc->generateVariants($path);

        // Aucune variante créée
        $this->assertFileDoesNotExist($base . '-400w.webp');
    }

    // ----------------------------------------------------------------
    // generateVariants — JPEG (ligne 123)
    // ----------------------------------------------------------------

    /**
     * generateVariants sur JPEG 1000px → crée WebP + variantes 400w + 800w en JPEG (ligne 123).
     */
    public function testGenerateVariantsJpeg(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $jpgPath = $this->tmpPath('.jpg');
        $base    = (string) preg_replace('/\.[^.]+$/', '', $jpgPath);

        $this->tmpFiles[] = $base . '.webp';
        $this->tmpFiles[] = $base . '-400w.jpg';
        $this->tmpFiles[] = $base . '-400w.webp';
        $this->tmpFiles[] = $base . '-800w.jpg';
        $this->tmpFiles[] = $base . '-800w.webp';

        $img = imagecreatetruecolor(1000, 800);
        imagejpeg($img, $jpgPath, 85);
        imagedestroy($img);

        (new ImageVariantService())->generateVariants($jpgPath);

        // Vérifie la variante 400w JPEG (ligne 123)
        $this->assertFileExists($base . '-400w.jpg');
        $this->assertFileExists($base . '-800w.jpg');
        $this->assertFileExists($base . '.webp');
    }

    // ----------------------------------------------------------------
    // deleteVariants
    // ----------------------------------------------------------------

    /**
     * deleteVariants supprime original + WebP + variantes 400w/800w.
     */
    public function testDeleteVariants(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP requis');
        }

        $pngPath = $this->tmpPath('.png');
        $base    = (string) preg_replace('/\.[^.]+$/', '', $pngPath);

        $variants = [
            $pngPath,
            $base . '.webp',
            $base . '-400w.png',
            $base . '-400w.webp',
            $base . '-800w.png',
            $base . '-800w.webp',
        ];

        foreach ($variants as $f) {
            file_put_contents($f, 'x');
        }

        (new ImageVariantService())->deleteVariants($pngPath);

        foreach ($variants as $f) {
            $this->assertFileDoesNotExist($f);
        }
    }
}
