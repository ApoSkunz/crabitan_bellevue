<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\RgpdExportService;

/**
 * Tests unitaires de RgpdExportService.
 *
 * Couvre buildForSubscriber, buildZip et buildExportContent
 * sans dépendance BDD (buildForAccount nécessite AccountModel — testé en TI).
 */
class RgpdExportServiceTest extends TestCase
{
    private RgpdExportService $service;
    private string|false $savedEncryptKey;
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $this->dbInstanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');

        $dbStub = $this->createStub(\Core\Database::class);
        $dbStub->method('fetchOne')->willReturn(false);
        $dbStub->method('fetchAll')->willReturn([]);
        $this->dbInstanceProp->setValue(null, $dbStub);

        $this->savedEncryptKey = $_ENV['DB_ENCRYPT_KEY'] ?? false;
        if ($this->savedEncryptKey === false || $this->savedEncryptKey === '') {
            $_ENV['DB_ENCRYPT_KEY'] = 'test-key-rgpd-export-service';
        }
        $this->service = new RgpdExportService();
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);

        if ($this->savedEncryptKey !== false) {
            $_ENV['DB_ENCRYPT_KEY'] = $this->savedEncryptKey;
        } else {
            unset($_ENV['DB_ENCRYPT_KEY']);
        }
    }

    // ----------------------------------------------------------------
    // buildForSubscriber
    // ----------------------------------------------------------------

    /**
     * Vérifie que buildForSubscriber retourne les champs RGPD attendus.
     */
    public function testBuildForSubscriberReturnsExpectedStructure(): void
    {
        $subscription = [
            'email'                   => 'test@example.com',
            'newsletter_confirmed'    => 1,
            'newsletter_consent_date' => '2026-01-01 10:00:00',
            'newsletter_consent_ip'   => '127.0.0.1',
            'consent_ip'              => '127.0.0.1',
            'lang'                    => 'fr',
            'created_at'              => '2026-01-01 09:00:00',
        ];

        $result = $this->service->buildForSubscriber($subscription);

        $this->assertArrayHasKey('exported_at', $result);
        $this->assertSame('newsletter_subscription', $result['source']);
        $this->assertArrayHasKey('newsletter_subscription', $result);
        $this->assertSame('test@example.com', $result['newsletter_subscription']['email']);
        $this->assertTrue($result['newsletter_subscription']['newsletter_confirmed']);
    }

    /**
     * Vérifie que newsletter_confirmed est bien casté en bool.
     */
    public function testBuildForSubscriberCastsConfirmedToBool(): void
    {
        $subscription = [
            'email'                   => 'foo@bar.com',
            'newsletter_confirmed'    => 0,
            'newsletter_consent_date' => null,
            'newsletter_consent_ip'   => null,
            'consent_ip'              => null,
            'lang'                    => 'en',
            'created_at'              => '2026-02-01 00:00:00',
        ];

        $result = $this->service->buildForSubscriber($subscription);

        $this->assertFalse($result['newsletter_subscription']['newsletter_confirmed']);
    }

    // ----------------------------------------------------------------
    // buildZip
    // ----------------------------------------------------------------

    /**
     * Vérifie que buildZip retourne un JSON valide quand ZipArchive n'est pas disponible.
     * Force le fallback via sous-classe anonyme overridant isZipAvailable().
     */
    public function testBuildZipReturnsFallbackJsonWhenNoZipArchive(): void
    {
        $service = new class extends RgpdExportService {
            protected function isZipAvailable(): bool
            {
                return false;
            }
        };

        $data   = ['foo' => 'bar'];
        [$content, $filename] = $service->buildZip($data, 'test-export');

        $this->assertStringEndsWith('.json', $filename);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertSame('bar', $decoded['foo']);
    }

    /**
     * Vérifie que buildZip avec ZipArchive retourne une archive .zip non vide.
     */
    public function testBuildZipReturnsZipWhenZipArchiveAvailable(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive non disponible.');
        }

        $data   = ['key' => 'value', 'items' => [1, 2, 3]];
        [$content, $filename] = $this->service->buildZip($data, 'mes-donnees-2026-04-01');

        $this->assertStringEndsWith('.zip', $filename);
        $this->assertNotEmpty($content);
        // Signature ZIP : PK (0x504B0304)
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }

    // ----------------------------------------------------------------
    // buildExportContent
    // ----------------------------------------------------------------

    /**
     * Vérifie que buildExportContent retourne [string, bool] avec les types attendus.
     */
    public function testBuildExportContentReturnsTwoElements(): void
    {
        if (!defined('ROOT_PATH')) {
            $this->markTestSkipped('ROOT_PATH non défini — TCPDF inaccessible.');
        }

        $export = $this->makeMinimalExportData();
        [$content, $isZip] = $this->service->buildExportContent($export, '2026-04-12', 'mes-donnees-2026-04-12');

        $this->assertIsString($content);
        $this->assertIsBool($isZip);
        $this->assertNotEmpty($content);
    }

    /**
     * Vérifie que buildExportContent produit un ZIP quand ZipArchive est disponible.
     */
    public function testBuildExportContentProducesZipWithZipArchive(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive non disponible.');
        }
        if (!defined('ROOT_PATH')) {
            $this->markTestSkipped('ROOT_PATH non défini — TCPDF inaccessible.');
        }

        $export = $this->makeMinimalExportData();
        [$content, $isZip] = $this->service->buildExportContent($export, '2026-04-12', 'mes-donnees-2026-04-12');

        $this->assertTrue($isZip);
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }

    /**
     * Vérifie que buildExportContent retourne du JSON si ZipArchive est absent.
     * Force le fallback via sous-classe anonyme overridant isZipAvailable().
     */
    public function testBuildExportContentFallbackToJsonWhenNoZip(): void
    {
        $service = new class extends RgpdExportService {
            protected function isZipAvailable(): bool
            {
                return false;
            }
        };

        $export = $this->makeMinimalExportData();
        [$content, $isZip] = $service->buildExportContent($export, '2026-04-12', 'mes-donnees-2026-04-12');

        $this->assertFalse($isZip);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
    }

    // ----------------------------------------------------------------
    // buildExportPdf
    // ----------------------------------------------------------------

    /**
     * Vérifie que buildExportPdf génère un binaire PDF valide (commence par %PDF).
     */
    public function testBuildExportPdfReturnsPdfBytes(): void
    {
        if (!defined('ROOT_PATH')) {
            $this->markTestSkipped('ROOT_PATH non défini — TCPDF inaccessible.');
        }

        $export = $this->makeMinimalExportData();
        $pdf    = $this->service->buildExportPdf($export, '2026-04-12');

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(100, strlen($pdf));
    }

    // ----------------------------------------------------------------
    // buildPdfTableSection (méthode privée — via réflexion)
    // ----------------------------------------------------------------

    /**
     * Appelle buildPdfTableSection via réflexion.
     *
     * @param array<mixed> $rows
     * @param callable     $rowFn
     */
    private function callBuildPdfTableSection(
        string $label,
        array $rows,
        string $header,
        callable $rowFn,
        string $emptyMsg
    ): string {
        $method = new \ReflectionMethod(RgpdExportService::class, 'buildPdfTableSection');
        return (string) $method->invoke($this->service, $label, $rows, $header, $rowFn, $emptyMsg);
    }

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
    // buildForAccount — branche review !== null (lignes 148-152)
    // ----------------------------------------------------------------

    /**
     * buildForAccount avec review non nulle → couvre le ternaire review !== null (lignes 148-152).
     */
    public function testBuildForAccountWithNonNullReviewCoversReviewBlock(): void
    {
        $dbProp = new \ReflectionProperty(\Core\Database::class, 'instance');

        // Stub Database pour éviter la connexion réelle
        $dbStub = $this->createStub(\Core\Database::class);
        $dbStub->method('fetchOne')->willReturn(false);
        $dbStub->method('fetchAll')->willReturn([]);
        $dbProp->setValue(null, $dbStub);

        // Créer le service et injecter les modèles via Reflection
        $service = new RgpdExportService();
        $ref     = new \ReflectionObject($service);

        $reviewMock = $this->createStub(\Model\ReviewModel::class);
        $reviewMock->method('findByAccountId')->willReturn([
            'rating'     => 5,
            'comment'    => 'Excellent',
            'location'   => 'Paris',
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $ratingMock = $this->createStub(\Model\RatingModel::class);
        $ratingMock->method('getAllByAccount')->willReturn([]);

        $prop = $ref->getProperty('reviewModel');
        $prop->setValue($service, $reviewMock);

        $prop2 = $ref->getProperty('ratingModel');
        $prop2->setValue($service, $ratingMock);

        $result = $service->buildForAccount(1);

        // La branche review !== null a été empruntée
        $this->assertIsArray($result['review']);
        $this->assertSame(5, $result['review']['rating']);
        $this->assertSame('Excellent', $result['review']['comment']);

        $dbProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Retourne un tableau d'export RGPD minimal pour les tests.
     *
     * @return array<string, mixed>
     */
    private function makeMinimalExportData(): array
    {
        return [
            'exported_at'       => date('c'),
            'account'           => [
                'Email'            => 'test@example.com',
                'Type de compte'   => 'individual',
                'Langue'           => 'fr',
                'Date de création' => '2026-01-01',
                'Civilité'         => 'M',
                'Prénom'           => 'Jean',
                'Nom'              => 'Dupont',
                'Newsletter'       => 'Non',
            ],
            'addresses'               => [],
            'orders'                  => [],
            'favorites'               => [],
            'trusted_devices'         => [],
            'active_sessions'         => [],
            'connection_logs'         => [],
            'adresses_commandes'      => [],
            'consentements'           => [
                'newsletter'             => ['abonne' => 'Non', 'email_verifie_le' => null],
                'cgv_versions_acceptees' => [],
            ],
        ];
    }
}
