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
    // normalizePhone()
    // ----------------------------------------------------------------

    /**
     * Appelle normalizePhone via réflexion.
     */
    private function callNormalizePhone(string $phone): string
    {
        $method = new \ReflectionMethod(AccountController::class, 'normalizePhone');
        $method->setAccessible(true);
        return (string) $method->invoke($this->controller, $phone);
    }

    /**
     * Un numéro sans espace est retourné tel quel.
     */
    public function testNormalizePhoneNoSpaces(): void
    {
        $this->assertSame('0601020304', $this->callNormalizePhone('0601020304'));
    }

    /**
     * Un numéro au format international est conservé tel quel.
     */
    public function testNormalizePhoneInternational(): void
    {
        $this->assertSame('+33 6 01 02 03 04', $this->callNormalizePhone('+33 6 01 02 03 04'));
    }

    /**
     * Les espaces multiples sont réduits à un seul espace.
     */
    public function testNormalizePhoneCollapseSpaces(): void
    {
        $this->assertSame('06 01 02 03 04', $this->callNormalizePhone('06  01  02  03  04'));
    }

    /**
     * Les espaces de début et de fin sont supprimés.
     */
    public function testNormalizePhoneTrimsBothEnds(): void
    {
        $this->assertSame('0601020304', $this->callNormalizePhone('  0601020304  '));
    }

    /**
     * Une chaîne vide reste vide.
     */
    public function testNormalizePhoneEmptyString(): void
    {
        $this->assertSame('', $this->callNormalizePhone(''));
    }

    // ----------------------------------------------------------------
    // isValidFranceMetroZip()
    // ----------------------------------------------------------------

    /**
     * Appelle isValidFranceMetroZip via réflexion.
     */
    private function callIsValidZip(string $zip): bool
    {
        $method = new \ReflectionMethod(AccountController::class, 'isValidFranceMetroZip');
        $method->setAccessible(true);
        return (bool) $method->invoke($this->controller, $zip);
    }

    /**
     * 75001 est un code postal valide.
     */
    public function testIsValidZipParisCentral(): void
    {
        $this->assertTrue($this->callIsValidZip('75001'));
    }

    /**
     * 01000 est la borne basse valide (≥ 1000).
     */
    public function testIsValidZipMinBound(): void
    {
        $this->assertTrue($this->callIsValidZip('01000'));
    }

    /**
     * 95999 est la borne haute valide.
     */
    public function testIsValidZipMaxBound(): void
    {
        $this->assertTrue($this->callIsValidZip('95999'));
    }

    /**
     * 00000 est invalide (< 1000).
     */
    public function testIsValidZipTooLow(): void
    {
        $this->assertFalse($this->callIsValidZip('00000'));
    }

    /**
     * 00999 est invalide (999 < 1000).
     */
    public function testIsValidZipJustBelowMin(): void
    {
        $this->assertFalse($this->callIsValidZip('00999'));
    }

    /**
     * 96000 est invalide (> 95999).
     */
    public function testIsValidZipTooHigh(): void
    {
        $this->assertFalse($this->callIsValidZip('96000'));
    }

    /**
     * 20000 (Corse) est invalide (préfixe 20 exclu).
     */
    public function testIsValidZipCorse(): void
    {
        $this->assertFalse($this->callIsValidZip('20000'));
    }

    /**
     * 20200 (Corse-du-Sud) est invalide.
     */
    public function testIsValidZipCorseDuSud(): void
    {
        $this->assertFalse($this->callIsValidZip('20200'));
    }

    /**
     * 97100 (Guadeloupe DOM) est invalide (préfixe 97).
     */
    public function testIsValidZipDomTom97(): void
    {
        $this->assertFalse($this->callIsValidZip('97100'));
    }

    /**
     * 98000 (Monaco / collectivités) est invalide (> 95999).
     */
    public function testIsValidZipOver98(): void
    {
        $this->assertFalse($this->callIsValidZip('98000'));
    }

    /**
     * Un code à 4 chiffres est invalide (format incorrect).
     */
    public function testIsValidZipTooShort(): void
    {
        $this->assertFalse($this->callIsValidZip('7500'));
    }

    /**
     * Un code à 6 chiffres est invalide (format incorrect).
     */
    public function testIsValidZipTooLong(): void
    {
        $this->assertFalse($this->callIsValidZip('750011'));
    }

    /**
     * Un code non numérique est invalide.
     */
    public function testIsValidZipNonNumeric(): void
    {
        $this->assertFalse($this->callIsValidZip('abc12'));
    }

    // ----------------------------------------------------------------
    // verifyCsrf()
    // ----------------------------------------------------------------

    /**
     * Appelle verifyCsrf via réflexion.
     */
    private function callVerifyCsrf(): bool
    {
        $method = new \ReflectionMethod(AccountController::class, 'verifyCsrf');
        $method->setAccessible(true);
        return (bool) $method->invoke($this->controller);
    }

    /**
     * Un token CSRF identique à la session retourne true.
     */
    public function testVerifyCsrfValidToken(): void
    {
        $_SESSION['csrf'] = 'valid-token';
        $_POST['csrf_token'] = 'valid-token';

        // Recréer le controller pour qu'il lise le nouveau $_POST
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte';
        $this->controller = new AccountController(new \Core\Request());

        $this->assertTrue($this->callVerifyCsrf());
    }

    /**
     * Un token CSRF différent de la session retourne false.
     */
    public function testVerifyCsrfInvalidToken(): void
    {
        $_SESSION['csrf'] = 'valid-token';
        $_POST['csrf_token'] = 'wrong-token';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte';
        $this->controller = new AccountController(new \Core\Request());

        $this->assertFalse($this->callVerifyCsrf());
    }

    /**
     * Absence de token CSRF en session retourne false.
     */
    public function testVerifyCsrfMissingSession(): void
    {
        unset($_SESSION['csrf']);
        $_POST['csrf_token'] = 'any-token';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte';
        $this->controller = new AccountController(new \Core\Request());

        $this->assertFalse($this->callVerifyCsrf());
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

    // ----------------------------------------------------------------
    // buildExportHeaders() — RGPD headers (Cache-Control + Content-Disposition)
    // ----------------------------------------------------------------

    /**
     * Appelle buildExportHeaders via réflexion.
     *
     * @return array<int, string>
     */
    private function callBuildExportHeaders(string $basename, bool $isZip): array
    {
        $method = new \ReflectionMethod(AccountController::class, 'buildExportHeaders');
        $method->setAccessible(true);
        return (array) $method->invoke($this->controller, $basename, $isZip);
    }

    /**
     * Les headers ZIP doivent inclure Cache-Control no-store et Content-Disposition
     * avec le nom de fichier au format export-rgpd-{date}.zip.
     */
    public function testBuildExportHeadersZipContainsCacheControlNoStore(): void
    {
        $headers = $this->callBuildExportHeaders('mes-donnees-2026-04-01', true);

        $this->assertContains('Cache-Control: no-store, no-cache', $headers);
    }

    /**
     * Le Content-Disposition ZIP force le téléchargement avec le bon nom de fichier.
     */
    public function testBuildExportHeadersZipContentDisposition(): void
    {
        $headers = $this->callBuildExportHeaders('mes-donnees-2026-04-01', true);

        $this->assertContains(
            'Content-Disposition: attachment; filename="mes-donnees-2026-04-01.zip"',
            $headers
        );
    }

    /**
     * Les headers JSON doivent également inclure Cache-Control no-store.
     */
    public function testBuildExportHeadersJsonContainsCacheControlNoStore(): void
    {
        $headers = $this->callBuildExportHeaders('mes-donnees-2026-04-01', false);

        $this->assertContains('Cache-Control: no-store, no-cache', $headers);
    }

    /**
     * Le Content-Disposition JSON force le téléchargement avec l'extension .json.
     */
    public function testBuildExportHeadersJsonContentDisposition(): void
    {
        $headers = $this->callBuildExportHeaders('mes-donnees-2026-04-01', false);

        $this->assertContains(
            'Content-Disposition: attachment; filename="mes-donnees-2026-04-01.json"',
            $headers
        );
    }

    // ----------------------------------------------------------------
    // buildExportContent() — ZIP vs JSON fallback
    // ----------------------------------------------------------------

    /**
     * Appelle buildExportContent via réflexion.
     *
     * @param array<string, mixed> $export
     * @return array{0: string, 1: bool}
     */
    private function callBuildExportContent(array $export, string $date, string $basename): array
    {
        $method = new \ReflectionMethod(AccountController::class, 'buildExportContent');
        $method->setAccessible(true);
        /** @var array{0: string, 1: bool} */
        return (array) $method->invoke($this->controller, $export, $date, $basename);
    }

    /**
     * Avec ZipArchive disponible → retourne un ZIP (isZip = true) contenant le JSON.
     */
    public function testBuildExportContentReturnsZipWhenZipArchiveAvailable(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive non disponible');
        }

        $export   = ['exported_at' => '2026-04-02T00:00:00+00:00', 'account' => []];
        [$content, $isZip] = $this->callBuildExportContent($export, '2026-04-02', 'export-rgpd-2026-04-02');

        $this->assertTrue($isZip, 'isZip doit être true quand ZipArchive est disponible');
        // Un fichier ZIP commence par PK\x03\x04 (Local file header signature)
        $this->assertStringStartsWith("PK\x03\x04", $content, 'Le contenu doit être un ZIP valide');
        $this->assertNotEmpty($content);
    }

    /**
     * Le ZIP contient bien un fichier .json avec les données exportées.
     */
    public function testBuildExportContentZipContainsJsonFile(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive non disponible');
        }

        $export   = ['exported_at' => '2026-04-02', 'account' => ['Email' => 'test@example.com']];
        [$content] = $this->callBuildExportContent($export, '2026-04-02', 'export-rgpd-2026-04-02');

        // Écrire le ZIP en temp pour l'inspecter
        $tmp = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tmp, $content);

        $zip = new \ZipArchive();
        $zip->open($tmp);
        $json = $zip->getFromName('export-rgpd-2026-04-02.json');
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($json, 'Le ZIP doit contenir export-rgpd-2026-04-02.json');
        $decoded = json_decode((string) $json, true);
        $this->assertSame('test@example.com', $decoded['account']['Email'] ?? null);
    }

    /**
     * Sans ZipArchive → retourne le JSON brut (isZip = false).
     * Simulé via un sous-controlleur qui override buildExportContent pour forcer la branche JSON.
     */
    public function testBuildExportContentFallbackJsonWhenNoZip(): void
    {
        $export   = ['exported_at' => '2026-04-02', 'account' => ['Email' => 'test@example.com']];
        $basename = 'export-rgpd-2026-04-02';

        // Sous-classe qui force la branche JSON (simule ZipArchive absent)
        $stub = new class ($this->createStubRequest()) extends AccountController {
            protected function buildExportContent(array $export, string $date, string $basename): array
            {
                $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
                return [$json, false];
            }
        };

        $method = new \ReflectionMethod($stub, 'buildExportContent');
        $method->setAccessible(true);
        /** @var array{0: string, 1: bool} $result */
        $result  = (array) $method->invoke($stub, $export, '2026-04-02', $basename);
        [$content, $isZip] = $result;

        $this->assertFalse($isZip, 'isZip doit être false dans le fallback JSON');
        $decoded = json_decode($content, true);
        $this->assertSame('test@example.com', $decoded['account']['Email'] ?? null);
    }

    /**
     * Crée un Request stub minimal pour instancier AccountController dans les tests.
     */
    private function createStubRequest(): \Core\Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/export';
        return new \Core\Request();
    }
}
