<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\OrderFormController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de OrderFormController.
 *
 * Couvre :
 *   - download() → 404 si le bon de commande est introuvable en BDD
 *   - download() → 404 si le fichier PDF n'existe pas sur le disque
 *   - download() → envoi du PDF avec label (header + readfile)
 *   - download() → envoi du PDF sans label (label null)
 */
class OrderFormControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/bons-de-commande/1/telecharger';
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    // ----------------------------------------------------------------
    // download() — bon de commande introuvable en BDD
    // ----------------------------------------------------------------

    /**
     * download() avec ID inexistant retourne 404 (bon introuvable en BDD).
     */
    public function testDownloadUnknownIdReturns404(): void
    {
        // DB retourne false → findByIdOrNull → null → abort 404
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $this->instanceProp->setValue(null, $dbMock);

        $controller = new OrderFormController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(404);
            $controller->download(['id' => '99999']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * download() avec id=0 (absent des params) retourne 404.
     */
    public function testDownloadMissingIdReturns404(): void
    {
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $this->instanceProp->setValue(null, $dbMock);

        $controller = new OrderFormController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(404);
            $controller->download([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // download() — fichier introuvable sur le disque
    // ----------------------------------------------------------------

    /**
     * download() avec fichier PDF absent du disque retourne 404.
     */
    public function testDownloadMissingFileReturns404(): void
    {
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn([
            'id'       => 1,
            'year'     => 2024,
            'label'    => 'Printemps',
            'filename' => 'fichier_inexistant_xyz_abc.pdf',
        ]);
        $this->instanceProp->setValue(null, $dbMock);

        $controller = new OrderFormController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(404);
            $controller->download(['id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * download() avec label null et fichier absent retourne 404.
     *
     * Vérifie que la branche label=null est atteignable (sans dépendre du FS).
     */
    public function testDownloadNullLabelMissingFileReturns404(): void
    {
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn([
            'id'       => 2,
            'year'     => 2023,
            'label'    => null,
            'filename' => 'absent_null_label.pdf',
        ]);
        $this->instanceProp->setValue(null, $dbMock);

        $controller = new OrderFormController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(404);
            $controller->download(['id' => '2']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // download() — happy path : fichier existant avec label
    // ----------------------------------------------------------------

    /**
     * download() envoie les headers PDF et le contenu quand le fichier existe (label défini).
     */
    public function testDownloadSendsFileWithLabel(): void
    {
        $storageDir = ROOT_PATH . '/storage/order_forms';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $tmpName = 'test_order_form_label_' . uniqid() . '.pdf';
        $tmpPath = $storageDir . '/' . $tmpName;
        file_put_contents($tmpPath, '%PDF-1.4 fake');

        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn([
            'id'       => 10,
            'year'     => 2025,
            'label'    => 'Printemps',
            'filename' => $tmpName,
        ]);
        $this->instanceProp->setValue(null, $dbMock);

        $controller = new class (new Request()) extends OrderFormController {
            protected function requireAdminAccess(): void
            {
                /* bypass auth in unit test */
            }
        };

        ob_start();
        try {
            $controller->download(['id' => '10']);
            $output = ob_get_clean();
        } finally {
            @unlink($tmpPath);
        }

        $this->assertStringContainsString('%PDF', $output ?? '');
    }

    // ----------------------------------------------------------------
    // download() — happy path : fichier existant sans label (null)
    // ----------------------------------------------------------------

    /**
     * download() envoie les headers PDF quand le fichier existe et label est null.
     */
    public function testDownloadSendsFileWithNullLabel(): void
    {
        $storageDir = ROOT_PATH . '/storage/order_forms';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $tmpName = 'test_order_form_nolabel_' . uniqid() . '.pdf';
        $tmpPath = $storageDir . '/' . $tmpName;
        file_put_contents($tmpPath, '%PDF-1.4 nolabel');

        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn([
            'id'       => 11,
            'year'     => 2024,
            'label'    => null,
            'filename' => $tmpName,
        ]);
        $this->instanceProp->setValue(null, $dbMock);

        $controller = new class (new Request()) extends OrderFormController {
            protected function requireAdminAccess(): void
            {
                /* bypass auth in unit test */
            }
        };

        ob_start();
        try {
            $controller->download(['id' => '11']);
            $output = ob_get_clean();
        } finally {
            @unlink($tmpPath);
        }

        $this->assertStringContainsString('%PDF', $output ?? '');
    }
}
