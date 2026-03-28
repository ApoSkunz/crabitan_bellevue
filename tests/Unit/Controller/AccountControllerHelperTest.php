<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AccountController;
use Core\Database;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires des helpers privés d'AccountController.
 * La Database est mockée — aucune connexion BDD réelle requise.
 */
class AccountControllerHelperTest extends TestCase
{
    private \ReflectionProperty $instanceProp;
    private AccountController $controller;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte';
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];

        $this->controller = new AccountController(new Request());
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
    }

    /**
     * Appel du helper privé via réflexion.
     *
     * @param array<mixed>   $rows
     * @param callable       $rowFn
     */
    private function callBuildPdfTableSection(string $label, array $rows, string $header, callable $rowFn, string $emptyMsg): string
    {
        $method = new \ReflectionMethod(AccountController::class, 'buildPdfTableSection');
        $method->setAccessible(true);
        return (string) $method->invoke($this->controller, $label, $rows, $header, $rowFn, $emptyMsg);
    }

    // ----------------------------------------------------------------
    // buildPdfTableSection()
    // ----------------------------------------------------------------

    /**
     * Avec une liste vide, retourne le titre et le message vide.
     */
    public function testBuildPdfTableSectionEmptyRows(): void
    {
        $result = $this->callBuildPdfTableSection(
            'Commandes',
            [],
            '<tr><th>Ref</th></tr>',
            static fn(array $r) => '<tr><td>' . $r['ref'] . '</td></tr>',
            'Aucune commande.'
        );

        $this->assertStringContainsString('<h2>Commandes (0)</h2>', $result);
        $this->assertStringContainsString('Aucune commande.', $result);
        $this->assertStringNotContainsString('<table>', $result);
    }

    /**
     * Avec des lignes fournies, génère le tableau HTML complet.
     */
    public function testBuildPdfTableSectionWithRows(): void
    {
        $rows = [
            ['ref' => 'REF-001', 'status' => 'paid'],
            ['ref' => 'REF-002', 'status' => 'pending'],
        ];

        $result = $this->callBuildPdfTableSection(
            'Commandes',
            $rows,
            '<tr><th>Ref</th><th>Statut</th></tr>',
            static fn(array $r) => '<tr><td>' . $r['ref'] . '</td><td>' . $r['status'] . '</td></tr>',
            'Aucune commande.'
        );

        $this->assertStringContainsString('<h2>Commandes (2)</h2>', $result);
        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('REF-001', $result);
        $this->assertStringContainsString('REF-002', $result);
        $this->assertStringContainsString('</table>', $result);
        $this->assertStringNotContainsString('Aucune commande.', $result);
    }

    /**
     * Le compteur de lignes reflète exactement le nombre de lignes passées.
     */
    public function testBuildPdfTableSectionRowCount(): void
    {
        $rows = array_fill(0, 5, ['ref' => 'R']);

        $result = $this->callBuildPdfTableSection(
            'Favoris',
            $rows,
            '<tr><th>Vin</th></tr>',
            static fn(array $r) => '<tr><td>' . $r['ref'] . '</td></tr>',
            'Aucun.'
        );

        $this->assertStringContainsString('<h2>Favoris (5)</h2>', $result);
    }

    // ----------------------------------------------------------------
    // validateAndSaveIndividual()
    // ----------------------------------------------------------------

    /**
     * Appel du helper privé validateAndSaveIndividual via réflexion.
     *
     * @return array<string, string>
     */
    private function callValidateAndSaveIndividual(int $userId, string $civility, string $firstname, string $lastname): array
    {
        $method = new \ReflectionMethod(AccountController::class, 'validateAndSaveIndividual');
        $method->setAccessible(true);
        return (array) $method->invoke($this->controller, $userId, $civility, $firstname, $lastname);
    }

    /**
     * Un prénom vide retourne une erreur.
     */
    public function testValidateAndSaveIndividualMissingFirstname(): void
    {
        $errors = $this->callValidateAndSaveIndividual(1, 'M', '', 'Martin');

        $this->assertArrayHasKey('firstname', $errors);
        $this->assertArrayNotHasKey('lastname', $errors);
    }

    /**
     * Un nom vide retourne une erreur.
     */
    public function testValidateAndSaveIndividualMissingLastname(): void
    {
        $errors = $this->callValidateAndSaveIndividual(1, 'M', 'Alice', '');

        $this->assertArrayHasKey('lastname', $errors);
        $this->assertArrayNotHasKey('firstname', $errors);
    }

    /**
     * Des valeurs valides retournent un tableau d'erreurs vide.
     */
    public function testValidateAndSaveIndividualValid(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        // Recréer le controller avec le mock qui supporte execute()
        $this->controller = new AccountController(new Request());

        $errors = $this->callValidateAndSaveIndividual(1, 'M', 'Alice', 'Martin');

        $this->assertSame([], $errors);
    }

    // ----------------------------------------------------------------
    // validateAndSaveCompany()
    // ----------------------------------------------------------------

    /**
     * Appel du helper privé validateAndSaveCompany via réflexion.
     *
     * @return array<string, string>
     */
    private function callValidateAndSaveCompany(int $userId, string $companyName, ?string $siret): array
    {
        $method = new \ReflectionMethod(AccountController::class, 'validateAndSaveCompany');
        $method->setAccessible(true);
        return (array) $method->invoke($this->controller, $userId, $companyName, $siret);
    }

    /**
     * Une raison sociale vide retourne une erreur.
     */
    public function testValidateAndSaveCompanyMissingName(): void
    {
        $errors = $this->callValidateAndSaveCompany(1, '', null);

        $this->assertArrayHasKey('company_name', $errors);
    }

    /**
     * Une raison sociale valide retourne un tableau d'erreurs vide.
     */
    public function testValidateAndSaveCompanyValid(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);
        $dbMock->method('execute')->willReturn(1);
        $this->instanceProp->setValue(null, $dbMock);

        $this->controller = new AccountController(new Request());

        $errors = $this->callValidateAndSaveCompany(1, 'Acme SARL', '12345678901234');

        $this->assertSame([], $errors);
    }
}
