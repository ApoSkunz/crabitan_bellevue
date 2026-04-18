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
}
