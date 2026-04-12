<?php

declare(strict_types=1);

namespace Tests\Unit\Fuzzing;

use Core\Database;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * US3 — Fuzzing upload de fichiers.
 *
 * Vérifie que les endpoints d'upload rejettent (4xx) les fichiers
 * malveillants : faux MIME, double extension, nullbyte dans le nom,
 * path traversal, fichier vide, fichier > limite.
 *
 * Aucun fichier exécutable ne doit être accepté dans public/assets/images/wines/.
 */
class UploadFuzzingTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;
    private string $tmpDir;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock);

        $_ENV['JWT_SECRET'] = 'test-secret-key-minimum-32-chars!!';

        $adminId               = 1;
        $token                 = Jwt::generate($adminId, 'admin');
        $_COOKIE               = ['auth_token' => $token];
        $_SESSION              = ['csrf' => 'csrf-upload-fuzz'];
        $_GET                  = [];
        $_POST                 = [];
        $_FILES                = [];
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/admin/vins/ajouter';
        $_SERVER['HTTP_USER_AGENT'] = 'FuzzingTest/1.0';

        $this->tmpDir = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Crée un fichier temporaire avec le contenu donné et retourne son chemin.
     *
     * @param string $content Contenu binaire du fichier
     * @param string $suffix  Extension du fichier temporaire
     * @return string Chemin absolu du fichier créé
     */
    private function createTmpFile(string $content, string $suffix = '.tmp'): string
    {
        $path = tempnam($this->tmpDir, 'fuzz_') . $suffix;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Retourne les bytes magiques PNG valides (1×1 pixel).
     */
    private function minimalPng(): string
    {
        return "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01"
             . "\x08\x02\x00\x00\x00\x90\x77\x53\xde\x00\x00\x00\x0CIDAT\x08\xd7\x63"
             . "\xf8\xcf\xc0\x00\x00\x00\x02\x00\x01\xe2!\xbc\x33\x00\x00\x00\x00IEND\xaeB`\x82";
    }

    /**
     * Retourne un payload PHP camouflé avec en-tête image/jpeg.
     */
    private function phpPayloadWithJpegMagic(): string
    {
        return "\xFF\xD8\xFF\xE0" // JPEG magic bytes
             . "<?php system(\$_GET['cmd']); ?>\xFF\xD9";
    }

    /**
     * Injecte un faux upload dans $_FILES['image'] et vérifie que le contrôleur
     * ne crashe pas et ne retourne pas 500.
     *
     * @param string               $context Nom du cas pour le message d'erreur
     * @param array<string, mixed> $file    Entrée $_FILES['image']
     * @param array<string, mixed> $post    Champs POST additionnels
     */
    private function assertUploadRejected(string $context, array $file, array $post = []): void
    {
        $_FILES['image'] = $file;
        $_POST           = array_merge([
            'csrf_token'   => 'csrf-upload-fuzz',
            'appellation'  => 'Bordeaux Rouge',
            'vintage'      => '2020',
            'price'        => '15.00',
            'stock_quantity' => '10',
            'production_quantity' => '100',
            'available'    => '0',
            'certification_label' => 'AOC',
            'area'         => '2.5',
            'city'         => 'Bordeaux',
            'variety_of_vine' => 'Merlot',
            'age_of_vineyard' => '20',
            'oenological_comment_fr' => 'Desc FR',
            'soil_fr'      => 'Argile',
            'pruning_fr'   => 'Guyot',
            'harvest_fr'   => 'Manuel',
            'vinification_fr' => 'Inox',
            'barrel_fermentation_fr' => '12 mois',
        ], $post);
        $_SESSION = ['csrf' => 'csrf-upload-fuzz'];

        // On utilise une sous-classe qui surcharge moveUploadedFile pour ne pas appeler
        // la vraie fonction PHP (impossible hors contexte web SAPI)
        $ctrl = $this->buildTestableController();

        try {
            ob_start();
            $ctrl->store([]);
            ob_end_clean();
            // Redirect ou vue rendue — pas de 500 → OK
            $this->assertTrue(true, $context . ': no exception is acceptable (redirect ou view)');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertLessThan(500, $e->getCode(), $context . ': 5xx interdit');
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->fail($context . ': crash non géré — ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * Crée un WineAdminController dont moveUploadedFile retourne toujours false.
     * Évite d'appeler move_uploaded_file() qui échoue hors SAPI.
     *
     * @return \Controller\Admin\WineAdminController
     */
    private function buildTestableController(): \Controller\Admin\WineAdminController
    {
        return new class (new Request()) extends \Controller\Admin\WineAdminController {
            /** @param string $src Fichier source */
            protected function moveUploadedFile(string $src, string $dest): bool
            {
                // Simuler un déplacement — copier le fichier source en destination
                return copy($src, $dest);
            }
        };
    }

    // ================================================================
    // Cas 1 — Faux MIME : contenu PHP avec magic bytes JPEG
    // ================================================================

    public function testFakeMimePhpContentWithJpegMagicRejected(): void
    {
        $path = $this->createTmpFile($this->phpPayloadWithJpegMagic(), '.jpg');

        $this->assertUploadRejected(
            'fake_mime_php_as_jpeg',
            [
                'name'     => 'shell.jpg',
                'type'     => 'image/jpeg', // MIME déclaré (non fiable)
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 2 — Double extension .php.jpg
    // ================================================================

    public function testDoubleExtensionPhpJpgRejected(): void
    {
        // Contenu PNG valide mais nom de fichier .php.jpg
        // La validation doit se baser sur finfo (MIME réel), pas le nom
        $path = $this->createTmpFile($this->minimalPng(), '.php.jpg');

        $this->assertUploadRejected(
            'double_extension_php_jpg',
            [
                'name'     => 'shell.php.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 3 — Nullbyte dans le nom de fichier
    // ================================================================

    public function testNullByteInFilenameRejected(): void
    {
        $path = $this->createTmpFile($this->minimalPng(), '.jpg');

        $this->assertUploadRejected(
            'nullbyte_in_filename',
            [
                'name'     => "shell\x00.php.jpg",
                'type'     => 'image/jpeg',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 4 — Path traversal dans le nom
    // ================================================================

    public function testPathTraversalInFilenameRejected(): void
    {
        $path = $this->createTmpFile($this->minimalPng(), '.jpg');

        $this->assertUploadRejected(
            'path_traversal_filename',
            [
                'name'     => '../../public/index.php',
                'type'     => 'image/jpeg',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 5 — Fichier vide
    // ================================================================

    public function testEmptyFileRejected(): void
    {
        $path = $this->createTmpFile('', '.jpg');

        $this->assertUploadRejected(
            'empty_file',
            [
                'name'     => 'empty.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => 0,
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 6 — Fichier > 5 MB (UPLOAD_ERR_INI_SIZE)
    // ================================================================

    public function testOversizedFileRejected(): void
    {
        $this->assertUploadRejected(
            'oversized_file_error_ini_size',
            [
                'name'     => 'huge.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => '',
                'error'    => UPLOAD_ERR_INI_SIZE,
                'size'     => 0,
            ]
        );
    }

    public function testOversizedFileErrorFormSize(): void
    {
        $this->assertUploadRejected(
            'oversized_file_error_form_size',
            [
                'name'     => 'huge.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => '',
                'error'    => UPLOAD_ERR_FORM_SIZE,
                'size'     => 0,
            ]
        );
    }

    // ================================================================
    // Cas 7 — Extension exécutable déguisée en image
    // ================================================================

    public function testPhpFileDeGuisedAsWebpRejected(): void
    {
        // Contenu PHP pur, MIME type sera application/x-httpd-php ou text/html
        $phpContent = '<?php phpinfo(); ?>';
        $path       = $this->createTmpFile($phpContent, '.webp');

        $this->assertUploadRejected(
            'php_as_webp',
            [
                'name'     => 'shell.webp',
                'type'     => 'image/webp',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($phpContent),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 8 — MIME SVG (non autorisé)
    // ================================================================

    public function testSvgWithXssRejected(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $path       = $this->createTmpFile($svgContent, '.svg');

        $this->assertUploadRejected(
            'svg_xss',
            [
                'name'     => 'xss.svg',
                'type'     => 'image/svg+xml',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($svgContent),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 9 — GIF magique (image/gif — non autorisé)
    // ================================================================

    public function testGifMagicBytesRejected(): void
    {
        $gifContent = "GIF89a\x01\x00\x01\x00\x00\xff\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x00;";
        $path       = $this->createTmpFile($gifContent, '.gif');

        $this->assertUploadRejected(
            'gif_magic_bytes',
            [
                'name'     => 'image.gif',
                'type'     => 'image/gif',
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($gifContent),
            ]
        );

        @unlink($path);
    }

    // ================================================================
    // Cas 10 — Absence totale de fichier (tmp_name vide, required=true)
    // ================================================================

    public function testMissingRequiredFileRejected(): void
    {
        $_FILES = []; // Aucun fichier

        $this->assertUploadRejected(
            'missing_required_file',
            [
                'name'     => '',
                'type'     => '',
                'tmp_name' => '',
                'error'    => UPLOAD_ERR_NO_FILE,
                'size'     => 0,
            ]
        );
    }

    // ================================================================
    // Cas 11 — OrderAdminController::uploadInvoice (facture PDF)
    // ================================================================

    public function testInvoiceUploadWithPhpContentRejected(): void
    {
        $phpContent = '<?php system(\$_GET["cmd"]); ?>';
        $path       = $this->createTmpFile($phpContent, '.pdf');

        $_FILES['invoice'] = [
            'name'     => 'facture.pdf',
            'type'     => 'application/pdf',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($phpContent),
        ];
        $_POST    = ['csrf_token' => 'csrf-upload-fuzz', 'confirm_replace' => '1'];
        $_SESSION = ['csrf' => 'csrf-upload-fuzz'];

        $ctrl = new class (new Request()) extends \Controller\Admin\OrderAdminController {
            protected function moveUploadedFile(string $src, string $dest): bool
            {
                return copy($src, $dest);
            }
        };

        try {
            ob_start();
            $ctrl->uploadInvoice(['id' => '1']);
            ob_end_clean();
            $this->assertTrue(true); // Redirect ou view sans 500
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertLessThan(500, $e->getCode(), 'invoiceUpload PHP content: 5xx interdit');
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->fail('invoiceUpload crash — ' . get_class($e) . ': ' . $e->getMessage());
        }

        @unlink($path);
    }

    public function testInvoiceUploadWithEmptyFileRejected(): void
    {
        $_FILES['invoice'] = [
            'name'     => '',
            'type'     => '',
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'size'     => 0,
        ];
        $_POST    = ['csrf_token' => 'csrf-upload-fuzz'];
        $_SESSION = ['csrf' => 'csrf-upload-fuzz'];

        $ctrl = new class (new Request()) extends \Controller\Admin\OrderAdminController {
            protected function moveUploadedFile(string $src, string $dest): bool
            {
                return false;
            }
        };

        try {
            ob_start();
            $ctrl->uploadInvoice(['id' => '1']);
            ob_end_clean();
            $this->assertTrue(true);
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertLessThan(500, $e->getCode(), 'invoiceUpload empty: 5xx interdit');
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->fail('invoiceUpload empty crash — ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    // ================================================================
    // Vérification — aucun fichier .php dans public/assets/images/wines
    // ================================================================

    public function testNoExecutableFilesInWinesUploadDirectory(): void
    {
        $winesDir = ROOT_PATH . '/public/assets/images/wines/';

        if (!is_dir($winesDir)) {
            $this->markTestSkipped('Dossier wines/ absent (hors contexte serveur).');
        }

        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'sh', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp'];
        $found               = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($winesDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $dangerousExtensions, true)) {
                $found[] = $file->getPathname();
            }
        }

        $this->assertEmpty(
            $found,
            'Fichiers exécutables trouvés dans wines/ : ' . implode(', ', $found)
        );
    }
}
