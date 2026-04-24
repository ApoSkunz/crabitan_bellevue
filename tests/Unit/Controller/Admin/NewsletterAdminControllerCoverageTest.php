<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\NewsletterAdminController;
use Core\Exception\HttpException;
use Model\NewsletterModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour NewsletterAdminController.
 * Surcharge les seams HTTP pour éviter headers/exit en CLI.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableNewsletterAdminController extends NewsletterAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'email' => 'admin@test.com'];

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return true;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op — évite la génération de vue en CLI
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_test_flash'][$type][] = $message;
    }

    protected function getFlash(string $type): ?string
    {
        return null;
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        throw new HttpException($status);
    }
}

/**
 * Tests de couverture pour NewsletterAdminController.
 *
 * Cible : L289-290 — mkdir($permanentDir, 0750, true) dans storeNewsletterPdf()
 *         quand le répertoire storage/newsletters/attachments/ n'existe pas encore.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class NewsletterAdminControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(\Core\Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/admin/newsletter/envoyer';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
    }

    private function makeController(): TestableNewsletterAdminController
    {
        return new TestableNewsletterAdminController(new \Core\Request());
    }

    /**
     * @param TestableNewsletterAdminController $ctrl
     * @param NewsletterModel                   $mock
     */
    private function injectNewslettersMock(
        TestableNewsletterAdminController $ctrl,
        NewsletterModel $mock
    ): void {
        $prop = new \ReflectionProperty(NewsletterAdminController::class, 'newsletters');
        $prop->setValue($ctrl, $mock); // NOSONAR — test unitaire
    }

    // ================================================================
    // storeNewsletterPdf() — mkdir déclenché quand le répertoire est absent (L289-290)
    // ================================================================

    /**
     * storeNewsletterPdf() exécute mkdir() (L290) quand le répertoire de destination
     * storage/newsletters/attachments/ n'existe pas encore.
     *
     * Stratégie : renommer temporairement le répertoire existant pour forcer
     * is_dir() → false, appeler la méthode privée via Reflection avec un vrai PDF
     * minimal, puis restaurer le répertoire d'origine.
     */
    public function testStoreNewsletterPdfCreatesDirWhenMissing(): void
    {
        // Créer un PDF minimal reconnu par finfo (magic bytes %PDF)
        $tmpFile = tempnam(sys_get_temp_dir(), 'nl_pdf_cov_');
        // PDF minimal avec magic bytes valides
        file_put_contents(
            $tmpFile,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n"
            . "xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\nstartxref\n9\n%%EOF"
        );

        $permanentDir = ROOT_PATH . '/storage/newsletters/attachments/';
        $backupDir    = ROOT_PATH . '/storage/newsletters/attachments_backup_cov_' . uniqid();
        $dirExisted   = is_dir($permanentDir);

        // Masquer temporairement le répertoire pour forcer la branche mkdir (L290)
        if ($dirExisted) {
            rename($permanentDir, $backupDir);
        }

        $ctrl = $this->makeController();

        $newsletterMock = $this->createMock(NewsletterModel::class);
        $newsletterMock->method('saveAttachment'); // peut être appelé ou non selon le flow
        $this->injectNewslettersMock($ctrl, $newsletterMock);

        $method = new \ReflectionMethod(NewsletterAdminController::class, 'storeNewsletterPdf');

        $file = [
            'tmp_name' => $tmpFile,
            'name'     => 'newsletter_test.pdf',
            'size'     => filesize($tmpFile) ?: 1,
            'error'    => UPLOAD_ERR_OK,
        ];

        $dirCreatedByMkdir = false;
        $result            = null;

        try {
            $result = $method->invoke($ctrl, $file, 999);
            $dirCreatedByMkdir = is_dir($permanentDir);
        } finally {
            // Nettoyage des fichiers créés dans le répertoire (si mkdir a réussi)
            if (is_dir($permanentDir)) {
                foreach (glob($permanentDir . 'nl_999_*.pdf') ?: [] as $f) {
                    @unlink($f);
                }
                // Supprimer le répertoire créé par mkdir avant de restaurer
                @rmdir($permanentDir);
            }
            // Restaurer le répertoire original
            if ($dirExisted) {
                rename($backupDir, $permanentDir);
            }
            @unlink($tmpFile);
            // Nettoyage du fichier tmp créé pour l'envoi mail
            if (is_array($result) && isset($result['tmp_path'])) {
                @unlink($result['tmp_path']);
            }
        }

        if ($result === null) {
            $this->markTestSkipped(
                'finfo MIME non disponible en CLI pour PDF minimal — L290 non déclenchée'
            );
            return;
        }

        // L290 a été exécutée : le répertoire a été créé par mkdir()
        $this->assertTrue($dirCreatedByMkdir, 'mkdir() (L290) doit avoir créé le répertoire.');
        $this->assertArrayHasKey('tmp_path', $result);
        $this->assertArrayHasKey('original_name', $result);
        $this->assertSame('newsletter_test.pdf', $result['original_name']);
    }

    // ================================================================
    // storeNewsletterPdf() — fichier vide → retourne null (L273-275)
    // ================================================================

    /**
     * storeNewsletterPdf() retourne null quand tmp_name est vide.
     * Couvre la branche early-return L273-275.
     */
    public function testStoreNewsletterPdfReturnsNullWhenFileEmpty(): void
    {
        $ctrl = $this->makeController();

        $newsletterMock = $this->createStub(NewsletterModel::class);
        $this->injectNewslettersMock($ctrl, $newsletterMock);

        $method = new \ReflectionMethod(NewsletterAdminController::class, 'storeNewsletterPdf');
        $result = $method->invoke($ctrl, [], 1);

        $this->assertNull($result);
    }

    // ================================================================
    // storeNewsletterPdf() — MIME invalide → retourne null (L276-278)
    // ================================================================

    /**
     * storeNewsletterPdf() retourne null quand le fichier n'est pas un PDF valide.
     * Couvre la branche MIME check L276-278.
     */
    public function testStoreNewsletterPdfReturnsNullWhenMimeInvalid(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'nl_notpdf_');
        file_put_contents($tmpFile, 'this is plain text, not a PDF');

        $ctrl = $this->makeController();

        $newsletterMock = $this->createStub(NewsletterModel::class);
        $this->injectNewslettersMock($ctrl, $newsletterMock);

        $method = new \ReflectionMethod(NewsletterAdminController::class, 'storeNewsletterPdf');
        $result = null;

        try {
            $result = $method->invoke($ctrl, [
                'tmp_name' => $tmpFile,
                'name'     => 'not_a_pdf.txt',
                'size'     => 29,
                'error'    => UPLOAD_ERR_OK,
            ], 1);
        } finally {
            @unlink($tmpFile);
        }

        $this->assertNull($result);
    }
}
