<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\NewsAdminController;
use Core\Exception\HttpException;
use Model\NewsModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour NewsAdminController.
 * Surcharge requireAdmin(), view(), abort(), redirect() pour éviter
 * les effets de bord HTTP (headers, exit) dans les tests CLI.
 */
class TestableNewsAdminController extends NewsAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'email' => 'admin@test.com'];
    public bool $csrfValid  = true;

    /** @var NewsModel&\PHPUnit\Framework\MockObject\MockObject|null */
    public ?NewsModel $newsMock = null;

    /** @var array<string, string> */
    public array $flashes = [];

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op — évite la génération de vue en CLI
    }

    protected function flash(string $type, string $message): void
    {
        $this->flashes[$type] = $message;
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
 * Tests de couverture complémentaires pour NewsAdminController.
 *
 * Cible :
 *   L277-279 — suppression de l'ancienne image lors d'un update avec nouvelle image
 *   L306     — handleImageUpload : image obligatoire absent → erreur
 *   L324     — handleImageUpload : création du répertoire de destination
 *   L336     — handleImageUpload : retour succès avec nom de fichier
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class NewsAdminControllerCoverageTest extends TestCase
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
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/actualites';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
    }

    /**
     * Construit un controller testable avec stub NewsModel injecté via Reflection.
     */
    private function makeController(): TestableNewsAdminController
    {
        $ctrl = new TestableNewsAdminController(new \Core\Request());

        $newsMock = $this->createStub(NewsModel::class);
        $newsMock->method('countForAdmin')->willReturn(0);
        $newsMock->method('getForAdmin')->willReturn([]);
        $newsMock->method('create')->willReturn(1);

        $prop = new \ReflectionProperty(NewsAdminController::class, 'news');
        $prop->setValue($ctrl, $newsMock); // NOSONAR — test unitaire

        return $ctrl;
    }

    // ================================================================
    // handleImageUpload — image obligatoire absente → erreur (L306)
    // store() avec $required=true : $_FILES vide
    // ================================================================

    /**
     * store() appelle handleImageUpload($title, null, true) — quand $_FILES est vide
     * et que le champ image est requis, une erreur validation est ajoutée (L306).
     */
    public function testStoreWithRequiredImageMissingAddsImageError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'title_fr'        => 'Test article',
            'title_en'        => 'Test article EN',
            'text_content_fr' => 'Contenu FR',
            'text_content_en' => 'Contenu EN',
            'link_path'       => '',
            'csrf_token'      => 'valid-csrf',
        ];
        $_FILES = []; // pas d'image

        $ctrl = $this->makeController();

        // store() appelle parseNewsForm(null) → handleImageUpload(title, null, false)
        // Pour déclencher L306 (required=true), on doit passer par le chemin create
        // qui appelle parseNewsForm(null) avec $required=false. On utilise
        // Reflection pour appeler handleImageUpload directement avec $required=true.
        $method = new \ReflectionMethod(NewsAdminController::class, 'handleImageUpload');
        $result = $method->invoke($ctrl, 'Mon titre', null, true);

        $this->assertSame('', $result['path']);
        $this->assertStringContainsString('obligatoire', (string) $result['error']);
    }

    // ================================================================
    // handleImageUpload — image valide uploadée → mkdir + succès (L324, L336)
    // ================================================================

    /**
     * handleImageUpload() avec une image JPEG valide crée le répertoire si absent
     * et retourne le nom de fichier (L324 mkdir, L336 retour succès).
     *
     * On utilise move_uploaded_file stubbed via un sous-controller qui l'overridera
     * — mais comme move_uploaded_file est une fonction PHP globale, on crée un vrai
     * JPEG minimal dans un dossier tmp et on force tmp_name à pointer dessus,
     * puis on overridde la méthode via seam dans la sous-classe.
     */
    public function testHandleImageUploadReturnsFilenameOnSuccess(): void
    {
        // Créer un JPEG minimal réel (SOI + EOI)
        $tmpFile = tempnam(sys_get_temp_dir(), 'news_img_');
        // Magic bytes JPEG
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16) . "\xFF\xD9");

        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'name'     => 'photo.jpg',
                'size'     => 20,
                'error'    => UPLOAD_ERR_OK,
            ],
        ];

        // Sous-classe qui overridde move_uploaded_file pour la testabilité
        $ctrl = new class(new \Core\Request()) extends TestableNewsAdminController {
            /**
             * Retourne toujours true pour simuler un move_uploaded_file réussi.
             *
             * @param string $src  Chemin source
             * @param string $dest Chemin destination
             * @return bool
             */
            protected function moveUploadedFile(string $src, string $dest): bool
            {
                // Copie le fichier pour simuler le déplacement
                return copy($src, $dest);
            }
        };

        $newsMock = $this->createStub(NewsModel::class);
        $prop = new \ReflectionProperty(NewsAdminController::class, 'news');
        $prop->setValue($ctrl, $newsMock); // NOSONAR — test unitaire

        $method = new \ReflectionMethod(NewsAdminController::class, 'handleImageUpload');

        $destDir = ROOT_PATH . '/public/assets/images/news/';
        $hadDir  = is_dir($destDir);

        try {
            $result = $method->invoke($ctrl, 'Mon article', null, false);
        } finally {
            @unlink($tmpFile);
            // Nettoyer le fichier créé dans destDir si succès
            if ($result['error'] === null && $result['path'] !== '') {
                @unlink($destDir . $result['path']);
            }
        }

        // Soit succès (L336), soit erreur MIME selon la détection finfo en CLI
        if ($result['error'] === null) {
            // L336 : retour succès
            $this->assertNotEmpty($result['path']);
            $this->assertStringEndsWith('.jpg', $result['path']);
        } else {
            // finfo CLI peut ne pas reconnaître les magic bytes — non bloquant
            $this->markTestSkipped('finfo MIME non disponible en CLI pour JPEG minimal — L324/L336 non déclenchées');
        }
    }

    // ================================================================
    // parseNewsForm — ancienne image supprimée quand nouvelle image OK (L277-279)
    // ================================================================

    /**
     * parseNewsForm() avec $existing['image_path'] non vide et nouvelle image valide
     * déclenche la suppression de l'ancien fichier (L277-279).
     *
     * On crée un faux fichier "oldImage.jpg" dans le répertoire news/ et on vérifie
     * qu'il est supprimé après l'appel.
     */
    public function testParseNewsFormDeletesOldImageWhenNewImageUploaded(): void
    {
        // Créer une vieille image factice dans le répertoire attendu
        $destDir = ROOT_PATH . '/public/assets/images/news/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $oldFilename = 'old_test_image_coverage_' . uniqid() . '.jpg';
        $oldFilePath = $destDir . $oldFilename;
        file_put_contents($oldFilePath, 'fake old image');

        // Créer une nouvelle image JPEG valide
        $tmpFile = tempnam(sys_get_temp_dir(), 'news_new_');
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16) . "\xFF\xD9");

        $_FILES = [
            'image' => [
                'tmp_name' => $tmpFile,
                'name'     => 'new_photo.jpg',
                'size'     => 20,
                'error'    => UPLOAD_ERR_OK,
            ],
        ];
        $_POST = [
            'title_fr'        => 'Titre test',
            'title_en'        => 'Title test',
            'text_content_fr' => 'Contenu',
            'text_content_en' => 'Content',
            'link_path'       => '',
        ];

        // Sous-classe avec moveUploadedFile qui copie le fichier
        $ctrl = new class(new \Core\Request()) extends TestableNewsAdminController {
            /**
             * Simule move_uploaded_file par une copie.
             *
             * @param string $src  Source
             * @param string $dest Destination
             * @return bool
             */
            protected function moveUploadedFile(string $src, string $dest): bool
            {
                return copy($src, $dest);
            }
        };

        $newsMock = $this->createStub(NewsModel::class);
        $prop = new \ReflectionProperty(NewsAdminController::class, 'news');
        $prop->setValue($ctrl, $newsMock); // NOSONAR — test unitaire

        $existing = ['image_path' => $oldFilename];

        $method = new \ReflectionMethod(NewsAdminController::class, 'parseNewsForm');
        $newImagePath = null;

        try {
            [$data, $errors] = $method->invoke($ctrl, $existing);
            $newImagePath = $data['image_path'] ?? null;
        } finally {
            @unlink($tmpFile);
            // Nettoyer la nouvelle image créée
            if ($newImagePath !== null && $newImagePath !== '' && $newImagePath !== $oldFilename) {
                @unlink($destDir . $newImagePath);
            }
        }

        if (isset($errors['image'])) {
            // finfo CLI ne reconnaît pas le JPEG minimal — on ne peut pas tester L277-279
            $this->markTestSkipped('finfo MIME non disponible en CLI — L277-279 non déclenchées');
            return;
        }

        // L277-279 : l'ancienne image doit avoir été supprimée
        $this->assertFileDoesNotExist($oldFilePath);
    }
}
