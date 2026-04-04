<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Service\PaymentService;

/**
 * Tests unitaires du PaymentService (Up2pay e-Transactions CA).
 *
 * Les interactions réseau (cURL, vérification serveur) sont neutralisées
 * via une sous-classe anonyme surchargeant les méthodes protected.
 */
class PaymentServiceTest extends TestCase
{
    /** @var array<string, string> */
    private array $defaultEnv = [];

    protected function setUp(): void
    {
        // Clé HMAC fictive : 128 caractères hex = 64 octets
        $this->defaultEnv = [
            'CA_PBX_SITE'           => '1999887',
            'CA_PBX_RANG'           => '032',
            'CA_PBX_IDENTIFIANT'    => '107904482',
            'CA_PBX_HMAC_KEY'       => str_repeat('ab', 64),
            'CA_PUBKEY_PATH'        => '/tmp/fake_pubkey.pem',
            'CA_SANDBOX_MODE'       => 'true',
            'CA_FALLBACK_BASE_URL'  => 'https://test.example.com',
        ];

        foreach ($this->defaultEnv as $k => $v) {
            $_ENV[$k] = $v;
        }
    }

    protected function tearDown(): void
    {
        foreach (array_keys($this->defaultEnv) as $k) {
            unset($_ENV[$k]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Retourne un PaymentService dont isServerAvailable() retourne toujours true
     * et curlPost() retourne la valeur $curlResponse (défaut : 'CODEREPONSE=00000').
     *
     * @param string|false $curlResponse Réponse simulée pour curlPost()
     * @return PaymentService
     */
    private function makeService(string|false $curlResponse = 'CODEREPONSE=00000'): PaymentService
    {
        return new class ($curlResponse) extends PaymentService {
            /** @var string|false */
            private string|false $mockResponse;

            public function __construct(string|false $mockResponse)
            {
                $this->mockResponse = $mockResponse;
            }

            protected function isServerAvailable(string $baseUrl): bool
            {
                return true;
            }

            protected function curlPost(string $url, string $postFields): string|false
            {
                return $this->mockResponse;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Tests buildPbxFields
    // -------------------------------------------------------------------------

    /**
     * Vérifie que buildPbxFields() retourne bien tous les champs PBX_* obligatoires.
     */
    public function testBuildPbxFieldsContainsRequiredKeys(): void
    {
        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-001',
            5000,
            'client@example.com',
            'Jean',
            'Dupont',
            '12 rue de la Paix',
            '75001',
            'Paris',
            3
        );

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('fields', $result);

        $fields = $result['fields'];

        $requiredKeys = [
            'PBX_SITE',
            'PBX_RANG',
            'PBX_TOTAL',
            'PBX_CMD',
            'PBX_PORTEUR',
            'PBX_HMAC',
            'PBX_RETOUR',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $fields, "Champ manquant : $key");
        }

        $this->assertSame('1999887', $fields['PBX_SITE']);
        $this->assertSame('032', $fields['PBX_RANG']);
        $this->assertSame('5000', $fields['PBX_TOTAL']);
        $this->assertSame('CMD-001', $fields['PBX_CMD']);
        $this->assertSame('client@example.com', $fields['PBX_PORTEUR']);
    }

    /**
     * Vérifie que PBX_RETOUR se termine toujours par ';Sign:K'.
     */
    public function testBuildPbxFieldsPbxRetourEndsWithSignK(): void
    {
        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-002',
            1000,
            'buyer@test.com',
            'Marie',
            'Martin',
            '5 avenue Victor Hugo',
            '69002',
            'Lyon',
            1
        );

        $this->assertStringEndsWith(';Sign:K', $result['fields']['PBX_RETOUR']);
    }

    /**
     * Vérifie que PBX_SOUHAITAUTHENT vaut '02' (pas de challenge) pour un montant ≤ 200€ (20000 centimes).
     */
    public function testBuildPbxFieldsSouhaitAuthent02ForSmallAmount(): void
    {
        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-003',
            20000,
            'test@example.com',
            'Paul',
            'Leroy',
            '1 place Bellecour',
            '69001',
            'Lyon',
            1
        );

        $this->assertSame('02', $result['fields']['PBX_SOUHAITAUTHENT']);
    }

    /**
     * Vérifie que PBX_SOUHAITAUTHENT vaut '03' (challenge souhaité) pour un montant > 200€.
     */
    public function testBuildPbxFieldsSouhaitAuthent03ForLargeAmount(): void
    {
        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-004',
            20001,
            'test@example.com',
            'Paul',
            'Leroy',
            '1 place Bellecour',
            '69001',
            'Lyon',
            2
        );

        $this->assertSame('03', $result['fields']['PBX_SOUHAITAUTHENT']);
    }

    /**
     * Vérifie que PBX_EFFECTUE, PBX_REFUSE, PBX_ANNULE et PBX_REPONDRE sont présents
     * et construits correctement lorsque CA_FALLBACK_BASE_URL est défini.
     */
    public function testBuildPbxFieldsContainsReturnUrlsWhenBaseUrlSet(): void
    {
        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-URL-01',
            2500,
            'buyer@example.com',
            'Pierre',
            'Leblanc',
            '10 rue du Commerce',
            '75015',
            'Paris',
            2,
            'fr'
        );

        $fields = $result['fields'];

        $this->assertArrayHasKey('PBX_EFFECTUE', $fields);
        $this->assertArrayHasKey('PBX_REFUSE', $fields);
        $this->assertArrayHasKey('PBX_ANNULE', $fields);
        $this->assertArrayHasKey('PBX_REPONDRE', $fields);

        $this->assertSame('https://test.example.com/fr/commande/paiement-ca/ok', $fields['PBX_EFFECTUE']);
        $this->assertSame('https://test.example.com/fr/commande/paiement-ca/refuse', $fields['PBX_REFUSE']);
        $this->assertSame('https://test.example.com/fr/commande/paiement-ca/annule', $fields['PBX_ANNULE']);
        $this->assertSame('https://test.example.com/payment/ipn', $fields['PBX_REPONDRE']);
    }

    /**
     * Vérifie que les champs d'URL sont absents lorsque CA_FALLBACK_BASE_URL est vide
     * (CA utilise alors les URLs configurées dans Vision Air).
     */
    public function testBuildPbxFieldsOmitsReturnUrlsWhenBaseUrlEmpty(): void
    {
        $_ENV['CA_FALLBACK_BASE_URL'] = '';

        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-URL-02',
            1500,
            'buyer@example.com',
            'Luc',
            'Moreau',
            '3 allée des Roses',
            '13001',
            'Marseille',
            1
        );

        $fields = $result['fields'];

        $this->assertArrayNotHasKey('PBX_EFFECTUE', $fields);
        $this->assertArrayNotHasKey('PBX_REFUSE', $fields);
        $this->assertArrayNotHasKey('PBX_ANNULE', $fields);
        $this->assertArrayNotHasKey('PBX_REPONDRE', $fields);
    }

    /**
     * Vérifie que PBX_HMAC est bien le dernier champ (pour que l'ordre du message
     * corresponde à l'ordre des champs retournés sans PBX_HMAC).
     */
    public function testBuildPbxFieldsHmacIsLastField(): void
    {
        $service = $this->makeService();

        $result = $service->buildPbxFields(
            'CMD-005',
            2000,
            'last@example.com',
            'Alice',
            'Bernard',
            '99 rue des Fleurs',
            '33000',
            'Bordeaux',
            4
        );

        $keys = array_keys($result['fields']);
        $this->assertSame('PBX_HMAC', end($keys));
    }

    // -------------------------------------------------------------------------
    // Tests verifyIpnSignature
    // -------------------------------------------------------------------------

    /**
     * Vérifie que verifyIpnSignature() retourne false pour une chaîne sans &Sign=.
     */
    public function testVerifyIpnSignatureReturnsFalseForInvalidQueryString(): void
    {
        $service = $this->makeService();

        $this->assertFalse($service->verifyIpnSignature('Mt=1000&Ref=CMD-001&Erreur=00000'));
    }

    /**
     * Vérifie que verifyIpnSignature() retourne false si le fichier pubkey est absent.
     */
    public function testVerifyIpnSignatureReturnsFalseIfPubkeyMissing(): void
    {
        $_ENV['CA_PUBKEY_PATH'] = '/tmp/does_not_exist_xyz.pem';

        $service = $this->makeService();

        $qs = 'Mt=1000&Ref=CMD-001&Sign=' . urlencode(base64_encode('fakesig'));

        $this->assertFalse($service->verifyIpnSignature($qs));
    }

    /**
     * Vérifie que verifyIpnSignature() retourne false si la signature est vide.
     */
    public function testVerifyIpnSignatureReturnsFalseForEmptySign(): void
    {
        $service = $this->makeService();

        // Sign vide après urlencode/base64 → base64_decode('') = ''
        $qs = 'Mt=500&Ref=CMD-002&Sign=';

        $this->assertFalse($service->verifyIpnSignature($qs));
    }

    // -------------------------------------------------------------------------
    // Test formatBillingField (méthode privée — accès via Reflection)
    // -------------------------------------------------------------------------

    /**
     * Vérifie que formatBillingField() supprime les accents et met en majuscules.
     */
    public function testFormatBillingFieldStripsAccents(): void
    {
        $service = new PaymentService();

        $ref    = new ReflectionClass(PaymentService::class);
        $method = $ref->getMethod('formatBillingField');
        $method->setAccessible(true);

        // 'Éléonore Ça' → normalisation NFD → suppression diacritiques → majuscules → alphanum+espace
        $result = $method->invoke($service, 'Éléonore Ça', 50);

        $this->assertSame('ELEONORE CA', $result);
    }

    /**
     * Vérifie que formatBillingField() tronque à maxLen.
     */
    public function testFormatBillingFieldTruncatesToMaxLen(): void
    {
        $service = new PaymentService();

        $ref    = new ReflectionClass(PaymentService::class);
        $method = $ref->getMethod('formatBillingField');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ABCDEFGHIJ', 5);

        $this->assertSame('ABCDE', $result);
    }

    // -------------------------------------------------------------------------
    // Tests callGaeRefund
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Tests callGaeCancelOrRefund
    // -------------------------------------------------------------------------

    /**
     * Retourne un service dont curlPost() retourne les réponses dans l'ordre donné.
     *
     * @param list<string|false> $responses
     */
    private function makeServiceWithQueue(array $responses): PaymentService
    {
        return new class ($responses) extends PaymentService {
            /** @var list<string|false> */
            private array $queue;

            /** @param list<string|false> $queue */
            public function __construct(array $queue)
            {
                $this->queue = $queue;
            }

            protected function isServerAvailable(string $baseUrl): bool
            {
                return true;
            }

            protected function curlPost(string $url, string $postFields): string|false
            {
                return array_shift($this->queue) ?? false;
            }
        };
    }

    /**
     * TYPE=00017 : REMISE vide → annulation (TYPE=00005) acceptée.
     */
    public function testCallGaeCancelOrRefundCancelsWhenNotYetSettled(): void
    {
        $service = $this->makeServiceWithQueue([
            'CODEREPONSE=00000&STATUS=A&REMISE=',     // 00017 → pas encore télécollecté
            'CODEREPONSE=00000',                       // 00005 → annulation OK
        ]);

        $this->assertTrue($service->callGaeCancelOrRefund('CMD-200', '0000001234', '0000005678', 5000));
    }

    /**
     * TYPE=00017 : REMISE renseigné → remboursement (TYPE=00014) accepté.
     */
    public function testCallGaeCancelOrRefundRefundsWhenAlreadySettled(): void
    {
        $service = $this->makeServiceWithQueue([
            'CODEREPONSE=00000&STATUS=T&REMISE=001234', // 00017 → télécollecté
            'CODEREPONSE=00000',                         // 00014 → remboursement OK
        ]);

        $this->assertTrue($service->callGaeCancelOrRefund('CMD-201', '0000001234', '0000005678', 5000));
    }

    /**
     * TYPE=00017 inaccessible → fallback : annulation (TYPE=00005) acceptée.
     */
    public function testCallGaeCancelOrRefundFallsBackToCancelWhenStatusUnreachable(): void
    {
        $service = $this->makeServiceWithQueue([
            false,                // 00017 → erreur réseau
            'CODEREPONSE=00000',  // 00005 → annulation OK
        ]);

        $this->assertTrue($service->callGaeCancelOrRefund('CMD-202', '0000001234', '0000005678', 5000));
    }

    /**
     * TYPE=00017 inaccessible → fallback : annulation refusée + remboursement accepté.
     */
    public function testCallGaeCancelOrRefundFallsBackToRefundWhenCancelAlsoFails(): void
    {
        $service = $this->makeServiceWithQueue([
            false,
            'CODEREPONSE=00015&COMMENTAIRE=Annulation impossible',
            'CODEREPONSE=00000',
        ]);

        $this->assertTrue($service->callGaeCancelOrRefund('CMD-203', '0000001234', '0000005678', 5000));
    }

    /**
     * Tous les appels échouent → retourne false.
     */
    public function testCallGaeCancelOrRefundReturnsFalseWhenAllFail(): void
    {
        $service = $this->makeServiceWithQueue([false, false, false]);

        $this->assertFalse($service->callGaeCancelOrRefund('CMD-204', '0000001234', '0000005678', 5000));
    }

    // -------------------------------------------------------------------------
    // Tests callGaeRefund
    // -------------------------------------------------------------------------

    /**
     * Vérifie que callGaeRefund() retourne true lorsque CODEREPONSE=00000.
     */
    public function testCallGaeRefundReturnsTrueOnSuccess(): void
    {
        $service = $this->makeService('CODEREPONSE=00000');

        $this->assertTrue($service->callGaeRefund('CMD-100', '0000001234', '0000005678', 5000));
    }

    /**
     * Vérifie que callGaeRefund() retourne false si cURL échoue (erreur réseau).
     */
    public function testCallGaeRefundReturnsFalseOnCurlError(): void
    {
        $service = $this->makeService(false);

        $this->assertFalse($service->callGaeRefund('CMD-101', '0000001234', '0000005678', 5000));
    }

    /**
     * Vérifie que callGaeRefund() retourne false si CODEREPONSE ≠ 00000.
     */
    public function testCallGaeRefundReturnsFalseOnErrorCode(): void
    {
        $service = $this->makeService('CODEREPONSE=00001&COMMENTAIRE=Erreur');

        $this->assertFalse($service->callGaeRefund('CMD-102', '0000001234', '0000005678', 5000));
    }
}
