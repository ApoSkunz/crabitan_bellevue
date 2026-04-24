<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\OrderFormController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Couvre les branches non atteintes dans OrderFormControllerTest :
 *   - line 36 : Response::abort(404) quand le formulaire est null (via sous-classe sans auth)
 *   - line 41 : Response::abort(404) quand le fichier PDF est absent (via sous-classe sans auth)
 */
#[AllowMockObjectsWithoutExpectations]
class OrderFormControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');

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
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée une sous-classe de OrderFormController qui bypass l'auth admin.
     *
     * @param array<string, mixed>|null $formRow Ligne retournée par findByIdOrNull
     * @return OrderFormController
     */
    private function makeController(?array $formRow): OrderFormController
    {
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);

        if ($formRow === null) {
            $dbMock->method('fetchOne')->willReturn(false);
        } else {
            $dbMock->method('fetchOne')->willReturn($formRow);
        }

        $this->instanceProp->setValue(null, $dbMock);

        return new class (new Request()) extends OrderFormController {
            /** @return void */
            protected function requireAdminAccess(): void
            {
                // Bypass — pas de vérification auth dans les tests unitaires
            }
        };
    }

    // ----------------------------------------------------------------
    // line 36 — abort 404 quand le bon de commande est null en BDD
    // ----------------------------------------------------------------

    /**
     * Couvre line 36 : Response::abort(404) quand findByIdOrNull retourne null.
     * La sous-classe bypass requireAdminAccess() pour que l'exécution atteigne la ligne.
     */
    public function testLine36AbortWhenFormNotFoundInDb(): void
    {
        $controller = $this->makeController(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $controller->download(['id' => '99999']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Couvre line 36 également avec id manquant (cast vers 0).
     */
    public function testLine36AbortWhenIdAbsent(): void
    {
        $controller = $this->makeController(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $controller->download([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // line 41 — abort 404 quand le fichier PDF n'existe pas sur disque
    // ----------------------------------------------------------------

    /**
     * Couvre line 41 : Response::abort(404) quand is_file($path) est false.
     */
    public function testLine41AbortWhenFileNotFoundOnDisk(): void
    {
        $controller = $this->makeController([
            'id'       => 1,
            'year'     => 2024,
            'label'    => 'Test',
            'filename' => 'fichier_qui_nexiste_vraiment_pas_' . uniqid() . '.pdf',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $controller->download(['id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Couvre line 41 avec label null et fichier absent.
     */
    public function testLine41AbortWhenFileNotFoundNullLabel(): void
    {
        $controller = $this->makeController([
            'id'       => 2,
            'year'     => 2023,
            'label'    => null,
            'filename' => 'absent_nulllabel_' . uniqid() . '.pdf',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $controller->download(['id' => '2']);
        } finally {
            ob_end_clean();
        }
    }
}

