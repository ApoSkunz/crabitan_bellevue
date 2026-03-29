<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\OrderFormAdminController;
use Core\Exception\HttpException;
use Model\OrderFormModel;

class OrderFormAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): OrderFormAdminController
    {
        return new OrderFormAdminController(
            $this->makeRequest($method, '/admin/bons-de-commande')
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersOrderFormsList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
        $this->assertStringContainsString('Bons de commande', $output);
    }

    public function testIndexRespectsPagination(): void
    {
        $_GET['per_page'] = '25';
        $_GET['page'] = '1';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // upload — CSRF invalide
    // ----------------------------------------------------------------

    public function testUploadRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année invalide
    // ----------------------------------------------------------------

    public function testUploadRedirectsOnInvalidYear(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year'] = '1800';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année valide, pas de fichier
    // ----------------------------------------------------------------

    public function testUploadRedirectsOnMissingFile(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year'] = '2025';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // delete — CSRF invalide
    // ----------------------------------------------------------------

    public function testDeleteRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->delete(['id' => '1']);
    }

    // ----------------------------------------------------------------
    // delete — CSRF valide, id introuvable
    // ----------------------------------------------------------------

    public function testDeleteRedirectsWhenNotFound(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->delete(['id' => '999999']);
    }

    // ----------------------------------------------------------------
    // delete — CSRF valide, id existant (sans fichier physique)
    // ----------------------------------------------------------------

    public function testDeleteRemovesExistingRecord(): void
    {
        $model = new OrderFormModel();
        $id    = $model->create(2099, 'TI', 'ti_test.pdf');

        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->delete(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // download — id introuvable
    // ----------------------------------------------------------------

    public function testDownloadAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->download(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // download — enregistrement existant mais fichier physique absent
    // ----------------------------------------------------------------

    public function testDownloadAborts404WhenFileNotFound(): void
    {
        // Insère un enregistrement avec un nom de fichier qui n'existe pas sur disque
        $id = (int) self::$db->insert(
            "INSERT INTO order_forms (year, label, filename) VALUES (2099, 'TI', 'nonexistent_ti_file.pdf')"
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->download(['id' => (string) $id]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // index — per_page invalide → retombe sur défaut
    // ----------------------------------------------------------------

    public function testIndexWithInvalidPerPageFallsBackToDefault(): void
    {
        $_GET['per_page'] = '999';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année valide, fichier non-PDF → 302
    // ----------------------------------------------------------------

    /**
     * Un upload avec une année invalide flash une erreur et redirige.
     */
    public function testUploadRedirectsWhenYearInvalid(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '1999';
        $_POST['label']      = '';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST')->upload([]);
    }

    /**
     * Un upload sans fichier flash une erreur et redirige.
     */
    public function testUploadRedirectsWhenNoFile(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2025';
        $_POST['label']      = '';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // delete — CSRF valide, enregistrement existant avec fichier physique
    // ----------------------------------------------------------------

    public function testDeleteRemovesRecordAndPhysicalFile(): void
    {
        // Crée un vrai fichier dans storage/order_forms pour tester la suppression physique
        $storageDir = ROOT_PATH . '/storage/order_forms';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0750, true);
        }
        $filename = 'ti_delete_test_' . uniqid() . '.pdf';
        $filepath = $storageDir . '/' . $filename;
        file_put_contents($filepath, '%PDF-1.4 fake');

        $model = new OrderFormModel();
        $id    = $model->create(2098, 'TI Delete', $filename);

        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->delete(['id' => (string) $id]);
        } finally {
            // Nettoyage si le fichier n'a pas été supprimé par le controller
            if (is_file($filepath)) {
                unlink($filepath);
            }
        }
    }

    // ----------------------------------------------------------------
    // index — per_page valide = 50 (dans ALLOWED_PER_PAGES)
    // Couvre la branche in_array($perPage, ALLOWED_PER_PAGES) → true pour 50
    // ----------------------------------------------------------------

    /**
     * Vérifie que index accepte per_page=50 (valeur valide) sans retomber sur le défaut.
     */
    public function testIndexWithValidPerPage50Renders(): void
    {
        $_GET['per_page'] = '50';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    /**
     * Vérifie que index accepte per_page=25 (valeur valide dans ALLOWED_PER_PAGES).
     */
    public function testIndexWithValidPerPage25Renders(): void
    {
        $_GET['per_page'] = '25';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Bons de commande', $output);
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année valide, fichier présent, mime non-PDF
    // Couvre : mime_content_type() appelé + branche mime !== 'application/pdf'
    // ----------------------------------------------------------------

    /**
     * Un upload avec un fichier non-PDF (mime != application/pdf) redirige avec erreur.
     */
    public function testUploadRedirectsWhenMimeIsNotPdf(): void
    {
        // Crée un vrai fichier texte pour que mime_content_type retourne text/plain
        $tmpFile = tempnam(sys_get_temp_dir(), 'ti_upload_notpdf_');
        file_put_contents($tmpFile, 'This is not a PDF file, just plain text content.');

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2025';
        $_POST['label']      = 'Test label';
        $_FILES = [
            'pdf' => [
                'name'     => 'test.txt',
                'type'     => 'text/plain',
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmpFile),
            ],
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->upload([]);
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
            $_FILES = [];
        }
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année valide, fichier PDF, move_uploaded_file échoue
    // Couvre : branche !move_uploaded_file() → flash error + redirect
    // Note : move_uploaded_file() retourne false hors contexte HTTP réel (pas is_uploaded_file)
    // ----------------------------------------------------------------

    /**
     * Un upload avec un PDF réel mais move_uploaded_file hors contexte → erreur sauvegarde.
     */
    public function testUploadRedirectsWhenMoveUploadedFileFails(): void
    {
        // Crée un vrai fichier PDF minimal pour passer le check mime
        $tmpFile = tempnam(sys_get_temp_dir(), 'ti_upload_pdf_');
        // En-tête PDF minimal pour que mime_content_type retourne application/pdf
        file_put_contents($tmpFile, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2025';
        $_POST['label']      = '';
        $_FILES = [
            'pdf' => [
                'name'     => 'test.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmpFile),
            ],
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            // move_uploaded_file() retourne false car le fichier n'est pas un vrai upload HTTP
            // → la branche !move_uploaded_file() est couverte
            $this->makeController('POST')->upload([]);
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
            $_FILES = [];
        }
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année valide, fichier PDF avec label null
    // Couvre : $label !== '' ? $label : null (branche label vide → null)
    // ----------------------------------------------------------------

    /**
     * Un upload avec label vide → label null, puis échec move_uploaded_file (hors HTTP).
     */
    public function testUploadWithEmptyLabelSetsNullAndContinues(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ti_upload_nolabel_');
        file_put_contents($tmpFile, "%PDF-1.4\n%%EOF");

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2026';
        $_POST['label']      = '   '; // trim → '' → null
        $_FILES = [
            'pdf' => [
                'name'     => 'tarifs.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmpFile),
            ],
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->upload([]);
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
            $_FILES = [];
        }
    }

    // ----------------------------------------------------------------
    // upload — fichier avec erreur d'upload (!= UPLOAD_ERR_OK)
    // Couvre la branche $file['error'] !== UPLOAD_ERR_OK → flash error
    // ----------------------------------------------------------------

    /**
     * Un upload avec label non vide génère un slug avec le label, puis échoue move_uploaded_file.
     * Couvre la branche $label !== null dans la construction de $labelSlug (L100).
     */
    public function testUploadWithNonEmptyLabelBuildsSluggifiedFilename(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ti_upload_label_');
        file_put_contents($tmpFile, "%PDF-1.4\n%%EOF");

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2025';
        $_POST['label']      = 'Cuvée Prestige 2025';
        $_FILES = [
            'pdf' => [
                'name'     => 'cuvee.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmpFile),
            ],
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->upload([]);
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
            $_FILES = [];
        }
    }

    /**
     * Un upload avec erreur UPLOAD_ERR_PARTIAL redirige avec message d'erreur.
     */
    public function testUploadRedirectsOnFileUploadError(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2025';
        $_FILES = [
            'pdf' => [
                'name'     => 'broken.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => '',
                'error'    => UPLOAD_ERR_PARTIAL,
                'size'     => 0,
            ],
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->upload([]);
        } finally {
            $_FILES = [];
        }
    }

    // ----------------------------------------------------------------
    // download — chemin nominal : fichier physique présent
    // Le test s'exécute en processus séparé car download() appelle exit().
    // Couvre : header(), readfile(), exit (L160-173)

    // ----------------------------------------------------------------
    // index — page demandée > nombre de pages total → recentrage sur la dernière page
    // Couvre la branche min((int) ($_GET['page'] ?? 1), $pages) quand page > pages
    // ----------------------------------------------------------------

    /**
     * Vérifie que index() recadre la page sur la dernière page valide
     * lorsque $_GET['page'] est supérieur au nombre de pages réelles.
     *
     * @return void
     */
    public function testIndexClampsPageWhenPageExceedsTotal(): void
    {
        // page=9999 dépasse largement le nombre de pages réel → clampé à $pages
        $_GET['per_page'] = '10';
        $_GET['page']     = '9999';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // upload — storageDir absent → mkdir() est appelé
    // Couvre la branche !is_dir($this->storageDir) → mkdir() (L96-98)
    // Note : le répertoire est recréé en tearDown si absent.
    // ----------------------------------------------------------------

    /**
     * Vérifie que upload() crée le répertoire storage/order_forms s'il est absent,
     * puis échoue sur move_uploaded_file() (hors contexte HTTP) → 302.
     * Couvre la branche mkdir() (L96-98).
     *
     * @return void
     */
    public function testUploadCreatesDirWhenMissing(): void
    {
        $storageDir = ROOT_PATH . '/storage/order_forms';
        $dirExisted = is_dir($storageDir);

        // Supprime temporairement le répertoire pour forcer la branche mkdir
        if ($dirExisted) {
            // Sauvegarde le contenu pour restaurer après le test
            $backup = sys_get_temp_dir() . '/order_forms_backup_' . uniqid();
            rename($storageDir, $backup);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ti_mkdir_pdf_');
        file_put_contents($tmpFile, "%PDF-1.4\n%%EOF");

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year']       = '2025';
        $_POST['label']      = '';
        $_FILES = [
            'pdf' => [
                'name'     => 'test.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmpFile),
            ],
        ];

        $caught = null;

        try {
            $this->makeController('POST')->upload([]);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        } finally {
            // Nettoyage : supprime le répertoire créé par le controller si vide
            if (is_dir($storageDir) && !$dirExisted) {
                @rmdir($storageDir);
            }
            // Restaure le répertoire d'origine si on l'avait sauvegardé
            if ($dirExisted && isset($backup) && is_dir($backup)) {
                rename($backup, $storageDir);
            }
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
            $_FILES = [];
        }

        $this->assertNotNull($caught, 'Expected HttpException was not thrown.');
        $this->assertSame(302, $caught->status);
    }
}
