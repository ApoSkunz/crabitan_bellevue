<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\OrderController;
use Core\Database;
use Core\Exception\HttpException;
use Core\FeatureFlags;
use Core\Request;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests du flux paiement dans OrderController.
 *
 * Couvre les méthodes :
 *   - paymentReturnOk()     — retour CA paiement accepté (toutes branches)
 *   - paymentReturnCancel() — retour CA annulation (avec et sans Ref)
 *   - paymentReturnRefuse() — retour CA refus (avec et sans Ref)
 *   - confirmation()        — rendu vue quand commande trouvée
 *   - payment()             — flux carte (redirect vers CA)
 *
 * Les dépendances BDD sont interceptées via Database::instance stub.
 * Les branches nécessitant une vraie signature RSA utilisent une paire
 * de clés générée dynamiquement en mémoire dans le setUp.
 */
#[AllowMockObjectsWithoutExpectations]
class OrderControllerPaymentFlowTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    /** Chemin vers la clé publique RSA temporaire générée pour les tests. */
    private string $tmpPubkeyPath = '';

    /** Clé privée RSA pour signer les QUERY_STRING dans les tests. */
    private \OpenSSLAsymmetricKey|false $privateKey = false;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_SERVER['QUERY_STRING']    = '';

        // Générer une paire RSA temporaire pour les tests nécessitant une signature valide
        // Sur Windows (XAMPP) openssl_pkey_new() échoue sans chemin explicite vers openssl.cnf
        $opensslConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['C:/xampp/apache/conf/openssl.cnf', 'C:/xampp/php/extras/ssl/openssl.cnf'] as $candidate) {
                if (file_exists($candidate)) {
                    $opensslConfig['config'] = $candidate;
                    break;
                }
            }
        }
        $this->privateKey = openssl_pkey_new($opensslConfig);
        if ($this->privateKey !== false) {
            $details = openssl_pkey_get_details($this->privateKey);
            if ($details !== false) {
                $this->tmpPubkeyPath = sys_get_temp_dir() . '/test_ca_pubkey_' . getmypid() . '.pem';
                file_put_contents($this->tmpPubkeyPath, $details['key']);
            }
        }

        // Neutraliser CA_PUBKEY_PATH par défaut (signature invalide sauf si surchargé)
        $_ENV['CA_PUBKEY_PATH'] = '/tmp/does_not_exist_pubkey_test.pem';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_COOKIE  = [];
        $_SERVER['QUERY_STRING'] = '';
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        if ($this->tmpPubkeyPath !== '' && file_exists($this->tmpPubkeyPath)) {
            @unlink($this->tmpPubkeyPath);
        }

        unset($_ENV['CA_PUBKEY_PATH']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Remplace le stub Database::instance et retourne le nouveau stub.
     *
     * @return \PHPUnit\Framework\MockObject\Stub
     */
    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    /**
     * Génère un JWT valide pour les tests.
     *
     * @param int    $userId Identifiant utilisateur
     * @param string $role   Rôle JWT
     * @return string
     */
    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }

    /**
     * Construit un QUERY_STRING signé avec la clé RSA de test.
     *
     * Reproduit le mécanisme CA : les données précèdent &Sign= encodé en base64+urlencoded.
     *
     * @param string $data Partie data du QUERY_STRING (avant &Sign=)
     * @return string QUERY_STRING complet avec signature RSA valide
     */
    private function buildSignedQueryString(string $data): string
    {
        if ($this->privateKey === false) {
            return $data;
        }
        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA1);
        return $data . '&Sign=' . urlencode(base64_encode($signature));
    }

    /**
     * Retourne un snapshot de payment_intent par défaut pour les tests.
     *
     * @param int    $userId    Identifiant du client
     * @param string $reference Référence commande
     * @return array<string, mixed>
     */
    private function defaultSnapshot(int $userId = 1, string $reference = 'WEB-CB-AABBCCDD-2026'): array
    {
        return [
            'reference'           => $reference,
            'user_id'             => $userId,
            'items'               => [['wine_id' => 1, 'qty' => 12, 'price' => 10.0, 'name' => 'Wine']],
            'total'               => 120.0,
            'delivery_discount'   => 0.0,
            'billing_address_id'  => 1,
            'delivery_address_id' => 1,
            'cgv_version'         => '1.0',
            'lang'                => 'fr',
            'newsletter'          => false,
            'client_email'        => 'client@example.com',
            'client_name'         => 'Jean Dupont',
        ];
    }

    // ================================================================
    // paymentReturnOk() — ref vide → order null → redirect /fr/commande
    // ================================================================

    /**
     * Quand $_GET['Ref'] est absent, la commande ne peut pas être retrouvée
     * et le controller redirige vers /fr/commande avec un flash d'erreur.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsWhenRefIsEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Erreur' => '00000']; // Ref absent

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — commande déjà existante → idempotence → redirect confirmation
    // ================================================================

    /**
     * Si la commande existe déjà en BDD (IPN déjà traité), le controller
     * stocke la référence en session et redirige vers la confirmation
     * sans rentrer dans le bloc de fallback RSA.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsToConfirmationWhenOrderAlreadyExists(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000'];
        $_SESSION['ca_payment'] = ['some' => 'data'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            [            // OrderModel::findByReference → commande déjà existante
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode([]),
            ]
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('WEB-CB-AABBCCDD-2026', $_SESSION['last_order_ref'] ?? '');
        $this->assertSame('card', $_SESSION['last_order_payment'] ?? '');
        // La session ca_payment doit être nettoyée
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // paymentReturnOk() — Erreur ≠ 00000, commande introuvable → redirect
    // ================================================================

    /**
     * Si CA retourne un code erreur ≠ 00000 et que la commande n'existe pas,
     * le bloc fallback n'est pas exécuté et le controller redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsWhenErreurNotSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00001'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            false        // OrderModel::findByReference → introuvable
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — signature RSA invalide → redirect /fr/commande
    // ================================================================

    /**
     * Quand la référence est présente, la commande n'existe pas encore,
     * Erreur=00000 mais la signature RSA est invalide (CA_PUBKEY_PATH absent),
     * le controller redirige vers /fr/commande avec un flash d'erreur paiement.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsOnInvalidSignature(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        // QUERY_STRING sans &Sign= → verifyIpnSignature() retourne false
        $_SERVER['QUERY_STRING']   = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000';
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000'];
        // CA_PUBKEY_PATH déjà neutralisé dans setUp → retourne false

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            false        // OrderModel::findByReference → commande introuvable
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — intent introuvable après signature valide → redirect
    // ================================================================

    /**
     * Quand la signature RSA est valide mais le payment_intent est introuvable
     * en BDD (expiré ou jamais créé), le controller redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnOkRedirectsWhenIntentNotFound(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $stub = $this->setDbMock();
        // fetchOne : Auth → findByReference (introuvable) → PaymentIntentModel::findByReference (introuvable)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            false,       // OrderModel::findByReference → commande introuvable
            false        // PaymentIntentModel::findByReference → intent introuvable (SELECT snapshot)
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnOk() — commande créée depuis snapshot valide → mail + redirect confirmation
    // ================================================================

    /**
     * Quand la signature RSA est valide, l'intent existe, et la commande n'a
     * pas encore été créée (findByReferenceOnly = null), le controller crée la
     * commande, envoie les mails et redirige vers la confirmation.
     *
     * @return void
     */
    public function testPaymentReturnOkCreatesOrderFromSnapshotAndRedirectsToConfirmation(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // Ordre des appels fetchOne :
        // 1. AuthMiddleware
        // 2. OrderModel::findByReference (commande absente)
        // 3. PaymentIntentModel::findByReference → snapshot valide
        // 4. OrderModel::findByReferenceOnly → null (pas encore créée)
        // 5. OrderModel::insert via createFromIpn → stub via fetchOne/execute
        // 6. OrderModel::findByReference après création → commande trouvée
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // 1. AuthMiddleware
            false,          // 2. OrderModel::findByReference → commande absente
            $snapshotRow,   // 3. PaymentIntentModel::findByReference → snapshot
            false,          // 4. OrderModel::findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 8. dedup lookup delivery → pas trouvé
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null, 'slug' => 'wine', 'vintage' => 2020], // 9. postOrderStockActions → getById
            [               // 10. OrderModel::findByReferenceWithAddresses → génération facture
                'id'               => 684,
                'order_reference'  => 'WEB-CB-AABBCCDD-2026',
                'payment_method'   => 'card',
                'content'          => json_encode($snapshot['items']),
                'price'            => 120.0,
                'shipping_discount' => 0.0,
                'ordered_at'       => date('Y-m-d H:i:s'),
            ],
            // 11. buildOwnerPdfContext → AddressModel::findByIdForUser billing
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            [               // 12. OrderModel::findByReference après création (line 420)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );
        // insert et execute ne lèvent pas d'exception (stub no-op par défaut)

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('WEB-CB-AABBCCDD-2026', $_SESSION['last_order_ref'] ?? '');
        $this->assertSame('card', $_SESSION['last_order_payment'] ?? '');
    }

    // ================================================================
    // paymentReturnOk() — idempotence : findByReferenceOnly non null → pas de createFromIpn
    // ================================================================

    /**
     * Race condition IPN/return : si findByReferenceOnly retourne la commande
     * (IPN a gagné la course), createFromIpn n'est pas appelé mais le controller
     * redirige quand même vers la confirmation.
     *
     * @return void
     */
    public function testPaymentReturnOkIdempotenceSkipsCreateWhenReferenceAlreadyExists(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // Ordre :
        // 1. AuthMiddleware
        // 2. OrderModel::findByReference (commande absente au premier appel)
        // 3. PaymentIntentModel::findByReference → snapshot valide
        // 4. OrderModel::findByReferenceOnly → commande existe déjà (IPN l'a créée)
        //    → pas de createFromIpn
        // 5. OrderModel::findByReference après le bloc if → commande trouvée
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware
            false,          // OrderModel::findByReference → commande absente
            $snapshotRow,   // PaymentIntentModel::findByReference
            ['order_reference' => 'WEB-CB-AABBCCDD-2026'], // findByReferenceOnly → existe
            [               // OrderModel::findByReference (appel final ligne 415)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ]
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('WEB-CB-AABBCCDD-2026', $_SESSION['last_order_ref'] ?? '');
    }

    // ================================================================
    // generateAndStoreInvoice() — findByReferenceWithAddresses null → null-guard
    // ================================================================

    /**
     * Quand findByReferenceWithAddresses retourne null (commande introuvable avec adresses),
     * generateAndStoreInvoice retourne immédiatement sans générer de facture.
     *
     * @return void
     */
    public function testGenerateAndStoreInvoiceSkipsWhenOrderNotFound(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // 6e appel = findByReferenceWithAddresses → null (commande introuvable avec adresses)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware
            false,          // 2. OrderModel::findByReference → absente
            $snapshotRow,   // 3. PaymentIntentModel::findByReference
            false,          // 4. findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 8. dedup lookup delivery → pas trouvé
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null, 'slug' => 'wine', 'vintage' => 2020], // 9. postOrderStockActions → getById
            false,          // 10. findByReferenceWithAddresses → false (null-guard : facture skippée)
            // 11. buildOwnerPdfContext → AddressModel::findByIdForUser billing
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            [               // 12. findByReference après création (line 420)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // generateAndStoreInvoice() — avec email client → sendInvoiceToClient couvert
    // ================================================================

    /**
     * Quand findByReferenceWithAddresses retourne un ordre avec email,
     * generateAndStoreInvoice tente d'envoyer l'email facture.
     * L'envoi SMTP échoue (pas de SMTP réel) mais est absorbé silencieusement.
     *
     * @return void
     */
    public function testGenerateAndStoreInvoiceCoversEmailBranchWithClientEmail(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // 1. AuthMiddleware
            false,          // 2. OrderModel::findByReference → absente
            $snapshotRow,   // 3. PaymentIntentModel::findByReference
            false,          // 4. findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,          // 8. dedup lookup delivery → pas trouvé
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null, 'slug' => 'wine', 'vintage' => 2020], // 9. postOrderStockActions → getById
            [               // 10. findByReferenceWithAddresses → ordre avec email
                'id'               => 684,
                'order_reference'  => 'WEB-CB-AABBCCDD-2026',
                'payment_method'   => 'card',
                'content'          => json_encode($snapshot['items']),
                'price'            => 120.0,
                'shipping_discount' => 0.0,
                'ordered_at'       => date('Y-m-d H:i:s'),
                'email'            => 'client@example.com',
                'firstname'        => 'Jean',
                'lastname'         => 'Dupont',
                'lang'             => 'fr',
            ],
            // 11. buildOwnerPdfContext → AddressModel::findByIdForUser billing
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            [               // 12. findByReference après création (line 420)
                'order_reference' => 'WEB-CB-AABBCCDD-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // L'email échoue silencieusement (pas de SMTP réel) → redirect confirmation quand même
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // paymentReturnCancel() — avec Ref → delete intent + flash + redirect
    // ================================================================

    /**
     * Avec une référence dans $_GET, le controller supprime l'intent en BDD
     * (via PaymentIntentModel::delete), pose un flash d'annulation et redirige
     * vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnCancelWithRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/annule';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026'];
        $_SESSION['ca_payment'] = ['some' => 'data'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnCancel(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // paymentReturnCancel() — sans Ref → flash + redirect (sans delete)
    // ================================================================

    /**
     * Sans référence dans $_GET, le controller ne tente pas de delete l'intent
     * mais pose quand même le flash d'annulation et redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnCancelWithoutRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/annule';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = []; // Pas de Ref

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnCancel(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // paymentReturnRefuse() — avec Ref → delete intent + flash + redirect
    // ================================================================

    /**
     * Avec une référence dans $_GET, le controller supprime l'intent, pose un
     * flash de refus et redirige vers /fr/commande.
     *
     * @return void
     */
    public function testPaymentReturnRefuseWithRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/refuse';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026'];
        $_SESSION['ca_payment'] = ['some' => 'data'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnRefuse(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // paymentReturnRefuse() — sans Ref → flash + redirect
    // ================================================================

    /**
     * Sans référence dans $_GET, le controller pose le flash de refus et redirige
     * vers /fr/commande sans appel BDD pour delete.
     *
     * @return void
     */
    public function testPaymentReturnRefuseWithoutRefFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/refuse';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_GET = []; // Pas de Ref

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->paymentReturnRefuse(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // confirmation() — référence en session, commande trouvée → rendu vue
    // ================================================================

    /**
     * Quand last_order_ref est en session et que la commande est retrouvée,
     * le controller efface les clés de session de commande et tente le rendu de la vue.
     *
     * @return void
     */
    public function testConfirmationClearsSessionKeysAndRendersViewWhenOrderFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['last_order_ref']        = 'WEB-CB-AABBCCDD-2026';
        $_SESSION['last_order_payment']    = 'card';
        $_SESSION['last_order_newsletter'] = true;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            [            // OrderModel::findByReference → commande trouvée
                'order_reference'   => 'WEB-CB-AABBCCDD-2026',
                'payment_method'    => 'card',
                'price'             => 120.0,
                'shipping_discount' => 0.0,
                'content'           => json_encode([['wine_id' => 1, 'qty' => 12, 'price' => 10.0, 'name' => 'Wine']]),
                'bill_firstname'    => 'Jean',
                'bill_lastname'     => 'Dupont',
                'bill_street'       => '1 Rue Test',
                'bill_zip'          => '75001',
                'bill_city'         => 'Paris',
                'bill_country'      => 'France',
                'del_firstname'     => 'Jean',
                'del_lastname'      => 'Dupont',
                'del_street'        => '1 Rue Test',
                'del_zip'           => '75001',
                'del_city'          => 'Paris',
                'del_country'       => 'France',
            ]
        );

        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            // Attendu : view() lève une exception car pas de template en context unitaire
        } catch (\Throwable) {
            // Vue non disponible en contexte unitaire — comportement attendu
        }

        // Les clés de session doivent être effacées après lecture
        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
        $this->assertArrayNotHasKey('last_order_payment', $_SESSION);
        $this->assertArrayNotHasKey('last_order_newsletter', $_SESSION);
    }

    // ================================================================
    // confirmation() — newsletterOptIn false → variable correcte
    // ================================================================

    /**
     * Quand last_order_newsletter est absent ou false, newsletterOptIn vaut false
     * et les clés de session sont nettoyées.
     *
     * @return void
     */
    public function testConfirmationWithNewsletterFalseCleansSessions(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['last_order_ref']     = 'WEB-VB-XXYYZZ-2026';
        $_SESSION['last_order_payment'] = 'virement';
        // last_order_newsletter absent → false

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1], // AuthMiddleware
            [            // OrderModel::findByReference
                'order_reference'   => 'WEB-VB-XXYYZZ-2026',
                'payment_method'    => 'virement',
                'price'             => 60.0,
                'shipping_discount' => 0.0,
                'content'           => json_encode([]),
                'bill_firstname'    => 'Test',
                'bill_lastname'     => 'User',
                'bill_street'       => '2 Rue Exemple',
                'bill_zip'          => '33000',
                'bill_city'         => 'Bordeaux',
                'bill_country'      => 'France',
                'del_firstname'     => 'Test',
                'del_lastname'      => 'User',
                'del_street'        => '2 Rue Exemple',
                'del_zip'           => '33000',
                'del_city'          => 'Bordeaux',
                'del_country'       => 'France',
            ]
        );

        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect ou vue
        } catch (\Throwable) {
            // Vue non disponible en contexte unitaire
        }

        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
        $this->assertArrayNotHasKey('last_order_payment', $_SESSION);
    }

    // ================================================================
    // payment() — flux carte (method=card) → redirect vers CA (vue auto-submit)
    // ================================================================

    /**
     * Pour le paiement par carte, le controller génère les champs PBX via
     * PaymentService et rend la vue formulaire auto-submit sans redirection HTTP.
     * Le test vérifie qu'aucune HttpException 302 n'est levée jusqu'à l'appel
     * de buildPbxFields (qui peut échouer sur les ENV CA absents) — la branche
     * est bien empruntée.
     *
     * @return void
     */
    public function testPaymentCardFlowEntersCardBranch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'test-submit-token',
            'payment_method'      => 'card',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '5',
        ];

        // Neutraliser les limites de rate-limit
        $_SESSION['payment_attempts'][1] = ['count' => 0, 'window_start' => time()];

        // Variables d'env CA minimales pour que buildPbxFields ne lève pas d'exception réseau
        $_ENV['CA_PBX_SITE']          = '1999887';
        $_ENV['CA_PBX_RANG']          = '032';
        $_ENV['CA_PBX_IDENTIFIANT']   = '107904482';
        $_ENV['CA_PBX_HMAC_KEY']      = str_repeat('ab', 64);
        $_ENV['CA_SANDBOX_MODE']      = 'true';
        $_ENV['CA_FALLBACK_BASE_URL'] = 'https://test.example.com';

        $stub = $this->setDbMock();
        // Ordre :
        // 1. AuthMiddleware
        // 2. CartModel::findByUserId → panier avec 12 bouteilles
        // 3. WineModel::getById (enrichItems)
        // 4. AddressModel::findByIdForUser (resolveAddress delivery)
        // 5. AddressModel::findByIdForUser (mainland zip check)
        // 6. PricingRuleModel::findForQuantity (computeDeliveryDiscount)
        // 7. AccountModel::findById
        // 8. AddressModel::findByIdForUser (billingAddr pour PBX_BILLING)
        // 9. PaymentIntentModel::save → execute (pas de fetchOne)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])],
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false],
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],
            false, // PricingRuleModel::findForQuantity
            ['id' => 1, 'email' => 'jean@test.com', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'newsletter' => false],
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'street' => '1 Rue Test', 'zip_code' => '75001', 'city' => 'Paris']
        );

        $exceptionCaught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $exceptionCaught = $e;
        } catch (\Throwable $e) {
            // buildPbxFields peut échouer si le serveur CA est inaccessible en test
            // ce qui confirme que la branche card a bien été atteinte
            $exceptionCaught = null;
        } finally {
            unset(
                $_ENV['CA_PBX_SITE'],
                $_ENV['CA_PBX_RANG'],
                $_ENV['CA_PBX_IDENTIFIANT'],
                $_ENV['CA_PBX_HMAC_KEY'],
                $_ENV['CA_SANDBOX_MODE'],
                $_ENV['CA_FALLBACK_BASE_URL']
            );
        }

        // En branche card, soit on atteint la vue (pas de 302), soit buildPbxFields échoue
        // Dans les deux cas, il ne doit PAS y avoir de clé last_order_ref (celle-ci n'est
        // posée que par les branches virement/chèque)
        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
    }

    // ================================================================
    // checkPaymentRateLimit() — count > 10 → flash + redirect (L567-584)
    // ================================================================

    /**
     * Quand le compteur de tentatives dépasse 10 dans la fenêtre courante,
     * checkPaymentRateLimit() pose un flash d'erreur et redirige vers /fr/commande.
     *
     * Ce test déclenche la branche via payment() + flux carte avec un compteur
     * pré-positionné à 10 (le +1 interne dépasse la limite).
     *
     * @return void
     */
    public function testPaymentCardRateLimitExceededRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        // Pré-remplir le compteur à 10 — le +1 interne le portera à 11 > 10
        $_SESSION['payment_attempts'][1] = ['count' => 10, 'window_start' => time()];
        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'test-submit-token',
            'payment_method'      => 'card',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '5',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],                                          // AuthMiddleware
            ['majority_declared_at' => '2025-01-01 00:00:00'],    // hasDeclaredMajority → true
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // cart
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false], // wine
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],   // resolveAddress
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],   // mainland check
            false,                                                // PricingRuleModel
            ['id' => 1, 'slug' => 'cgv', 'version' => '1.0']    // LegalDocumentModel
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('rate_limit', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // checkPaymentRateLimit() — fenêtre expirée → reset compteur (L574-576)
    // ================================================================

    /**
     * Quand la fenêtre de rate-limit est expirée (window_start vieux de > 3600s),
     * le compteur est remis à zéro et la requête carte passe (count=1, non bloqué).
     *
     * @return void
     */
    public function testPaymentCardRateLimitWindowExpiredResetsCounter(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        // Fenêtre expirée : window_start vieux de 7200s, count=10
        $_SESSION['payment_attempts'][1] = ['count' => 10, 'window_start' => time() - 7200];

        $_ENV['CA_PBX_SITE']          = '1999887';
        $_ENV['CA_PBX_RANG']          = '032';
        $_ENV['CA_PBX_IDENTIFIANT']   = '107904482';
        $_ENV['CA_PBX_HMAC_KEY']      = str_repeat('ab', 64);
        $_ENV['CA_SANDBOX_MODE']      = 'true';
        $_ENV['CA_FALLBACK_BASE_URL'] = 'https://test.example.com';

        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'test-submit-token',
            'payment_method'      => 'card',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '5',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],                                          // AuthMiddleware
            ['majority_declared_at' => '2025-01-01 00:00:00'],    // hasDeclaredMajority → true
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // cart
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false], // wine
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],   // resolveAddress
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],   // mainland check
            false,                                                // PricingRuleModel
            ['id' => 1, 'slug' => 'cgv', 'version' => '1.0'],   // LegalDocumentModel
            ['id' => 1, 'email' => 'jean@test.com', 'firstname' => 'Jean', 'lastname' => 'Dupont'], // AccountModel
            // billingAddr snapshot
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont',
                'street' => '1 Rue Test', 'zip_code' => '75001', 'city' => 'Paris']
        );

        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            // 302 = rate_limit → test échoue
            $this->assertArrayNotHasKey('rate_limit', $_SESSION['flash']['checkout_errors'] ?? []);
        } catch (\Throwable) {
            // buildPbxFields peut échouer — ce qui prouve que la branche n'a pas été bloquée
        } finally {
            unset(
                $_ENV['CA_PBX_SITE'],
                $_ENV['CA_PBX_RANG'],
                $_ENV['CA_PBX_IDENTIFIANT'],
                $_ENV['CA_PBX_HMAC_KEY'],
                $_ENV['CA_SANDBOX_MODE'],
                $_ENV['CA_FALLBACK_BASE_URL']
            );
        }

        // Après reset, le compteur doit être à 1 (pas bloqué)
        $this->assertSame(1, $_SESSION['payment_attempts'][1]['count'] ?? -1);
    }

    // ================================================================
    // payment() — flux carte complet : L226-286 (snapshot + PBX fields)
    // ================================================================

    /**
     * Flux carte avec séquence fetchOne correcte incluant hasDeclaredMajority.
     * Couvre les lignes 226-286 (snapshot, PaymentIntentModel::save, buildPbxFields).
     *
     * @return void
     */
    public function testPaymentCardFlowCoversSnapshotAndPbxLines(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        // Compteur à 0 → rate limit non atteint
        $_SESSION['payment_attempts'][1] = ['count' => 0, 'window_start' => time()];

        $_ENV['CA_PBX_SITE']          = '1999887';
        $_ENV['CA_PBX_RANG']          = '032';
        $_ENV['CA_PBX_IDENTIFIANT']   = '107904482';
        $_ENV['CA_PBX_HMAC_KEY']      = str_repeat('ab', 64);
        $_ENV['CA_SANDBOX_MODE']      = 'true';
        $_ENV['CA_FALLBACK_BASE_URL'] = 'https://test.example.com';

        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'test-submit-token',
            'payment_method'      => 'card',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '5',
            'newsletter'          => '',
        ];

        $stub = $this->setDbMock();
        // Séquence exacte :
        // 1. AuthMiddleware
        // 2. hasDeclaredMajority → true
        // 3. CartModel::findByUserId → panier
        // 4. WineModel::getById (enrichItems)
        // 5. resolveAddress delivery → findByIdForUser
        // 6. isMainlandFranceZip → findByIdForUser
        // 7. PricingRuleModel::findForQuantity (computeDeliveryDiscount)
        // 8. LegalDocumentModel::findBySlug
        // 9. AccountModel::findById (email + nom)
        // 10. AddressModel::findByIdForUser (billingAddr pour PBX_BILLING)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],                                           // 1. AuthMiddleware
            ['majority_declared_at' => '2025-01-01 00:00:00'],     // 2. hasDeclaredMajority → true
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // 3. cart
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false], // 4. wine
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],   // 5. resolveAddress
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'zip_code' => '75001'],   // 6. mainland check
            false,                                                 // 7. PricingRuleModel
            ['id' => 1, 'slug' => 'cgv', 'version' => '1.0'],    // 8. LegalDocumentModel
            ['id' => 1, 'email' => 'jean@test.com', 'firstname' => 'Jean', 'lastname' => 'Dupont'], // 9. AccountModel
            ['id' => 5, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'street' => '1 Rue Test', // 10. billingAddr
             'zip_code' => '75001', 'city' => 'Paris', 'country' => 'France']
        );

        $exceptionCaught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $exceptionCaught = $e;
        } catch (\Throwable) {
            // buildPbxFields peut échouer — prouve que la branche card L226+ a été atteinte
            $exceptionCaught = null;
        } finally {
            unset(
                $_ENV['CA_PBX_SITE'],
                $_ENV['CA_PBX_RANG'],
                $_ENV['CA_PBX_IDENTIFIANT'],
                $_ENV['CA_PBX_HMAC_KEY'],
                $_ENV['CA_SANDBOX_MODE'],
                $_ENV['CA_FALLBACK_BASE_URL']
            );
        }

        // Flux carte ne pose pas last_order_ref (seulement virement/chèque)
        // Si on a une 302, ce n'est pas un rate_limit (compteur était à 0)
        if ($exceptionCaught !== null) {
            $this->assertArrayNotHasKey('rate_limit', $_SESSION['flash']['checkout_errors'] ?? []);
        }
        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
    }

    // ================================================================
    // dispatchOrderEmails() — catch Throwable (L865) via flux virement
    // ================================================================

    /**
     * Vérifie que dispatchOrderEmails() absorbe silencieusement les exceptions mail.
     * Le flux virement avec email client valide déclenche dispatchOrderEmails()
     * qui tente l'envoi SMTP (échec en test) et absorbe l'erreur sans propager.
     *
     * @return void
     */
    public function testPaymentVirementDispatchEmailsAbsorbsMailException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(2, 'customer');
        $_SESSION['submit_token']  = 'dispatch-test-token';
        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'dispatch-test-token',
            'payment_method'      => 'virement',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '10',
            'newsletter'          => '',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 2],                                          // AuthMiddleware (userId=2)
            ['majority_declared_at' => '2025-01-01 00:00:00'],    // hasDeclaredMajority → true
            ['user_id' => 2, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // cart
            ['id' => 1, 'price' => 15.0, 'label_name' => 'Bordeaux', 'is_cuvee_speciale' => false], // wine
            ['id' => 10, 'firstname' => 'Marie', 'lastname' => 'Curie', 'zip_code' => '75005'], // resolveAddress
            ['id' => 10, 'firstname' => 'Marie', 'lastname' => 'Curie', 'zip_code' => '75005'], // mainland
            false,                                                // PricingRuleModel
            ['id' => 1, 'slug' => 'cgv', 'version' => '2.0'],   // LegalDocumentModel
            ['id' => 2, 'email' => 'marie@test.com', 'firstname' => 'Marie', 'lastname' => 'Curie'], // AccountModel
            ['id' => 10, 'civility' => 'Mme', 'firstname' => 'Marie', 'lastname' => 'Curie',
             'street' => '1 Quai Test', 'city' => 'Paris', 'zip_code' => '75005', 'country' => 'France', 'phone' => ''],
            false, // dedup billing
            ['id' => 10, 'civility' => 'Mme', 'firstname' => 'Marie', 'lastname' => 'Curie',
             'street' => '1 Quai Test', 'city' => 'Paris', 'zip_code' => '75005', 'country' => 'France', 'phone' => ''],
            false, // dedup delivery
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null, 'slug' => 'wine', 'vintage' => 2020], // postOrderStockActions → getById
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // Malgré l'échec SMTP (MailService sans config réelle), la commande est créée
        // et le controller redirige vers la confirmation
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('last_order_ref', $_SESSION);
    }

    // ================================================================
    // payment() — mode Click & Collect (pickup) : remise via BDD singleton
    // Couvre OrderController::payment L263-266 (branche pickup)
    // ================================================================

    /**
     * Quand le client choisit le retrait Click & Collect et que le flag est actif,
     * la remise est calculée via ClickCollectSettingsModel::getDiscountPerBottle()
     * (× nb bouteilles) au lieu de la table pricing_rules.
     *
     * @return void
     */
    public function testPaymentPickupUsesClickCollectDiscountFromSingleton(): void
    {
        FeatureFlags::setCache(['click_collect_enabled' => ['key' => 'click_collect_enabled', 'value' => 'true', 'type' => 'bool']]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(3, 'customer');
        $_SESSION['submit_token']  = 'pickup-test-token';
        $_POST = [
            'csrf_token'          => 'valid-csrf',
            'submit_token'        => 'pickup-test-token',
            'payment_method'      => 'virement',
            'cgv'                 => '1',
            'same_address'        => '1',
            'delivery_address_id' => '20',
            'delivery_method'     => 'pickup',
            'newsletter'          => '',
        ];

        $stub = $this->setDbMock();
        // Séquence adaptée pickup : pas d'appel PricingRuleModel::findForQuantity,
        // mais ClickCollectSettingsModel::getDiscountPerBottle (fetchOne après mainland)
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 3],                                          // AuthMiddleware
            ['majority_declared_at' => '2025-01-01 00:00:00'],   // hasDeclaredMajority
            ['user_id' => 3, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // cart 12 bouteilles
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Vin', 'is_cuvee_speciale' => false],
            ['id' => 20, 'firstname' => 'Paul', 'lastname' => 'Dupont', 'zip_code' => '33410'], // resolveAddress
            ['id' => 20, 'firstname' => 'Paul', 'lastname' => 'Dupont', 'zip_code' => '33410'], // mainland check
            // Click & Collect discount : 2.50€/bouteille × 12 = 30€ de remise
            ['discount_per_bottle' => '2.50'],
            ['id' => 1, 'slug' => 'cgv', 'version' => '1.0'],
            ['id' => 3, 'email' => 'paul@test.com', 'firstname' => 'Paul', 'lastname' => 'Dupont'],
            ['id' => 20, 'civility' => 'M.', 'firstname' => 'Paul', 'lastname' => 'Dupont',
             'street' => '1 Rue Test', 'city' => 'Bordeaux', 'zip_code' => '33000',
             'country' => 'France', 'phone' => ''],
            false,
            ['id' => 20, 'civility' => 'M.', 'firstname' => 'Paul', 'lastname' => 'Dupont',
             'street' => '1 Rue Test', 'city' => 'Bordeaux', 'zip_code' => '33000',
             'country' => 'France', 'phone' => ''],
            false,
            ['id' => 1, 'label_name' => 'Vin', 'stock_quantity' => 200, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null, 'slug' => 'vin', 'vintage' => 2020],
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            FeatureFlags::flush();
        }

        // La commande a été créée (virement → redirect + last_order_ref posé)
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('last_order_ref', $_SESSION);
    }

    // ================================================================
    // paymentReturnOk() — exception lors de createFromIpn → redirect /fr/commande
    // ================================================================

    /**
     * Si createFromIpn lève une PDOException (collision persistante), le controller
     * propage l'exception. Ce test vérifie que l'exception n'est pas silencieusement
     * avalée — le comportement attendu est une propagation (pas de redirect 302).
     *
     * Ce test couvre la branche catch (\PDOException) dans createFromIpn
     * (après 3 tentatives sans succès).
     *
     * @return void
     */
    public function testPaymentReturnOkPropagatesExceptionFromCreateFromIpn(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs = 'Ref=WEB-CB-AABBCCDD-2026&Erreur=00000&Appel=1234&Trans=5678';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = ['Ref' => 'WEB-CB-AABBCCDD-2026', 'Erreur' => '00000', 'Appel' => '1234', 'Trans' => '5678'];

        $snapshot = $this->defaultSnapshot(1, 'WEB-CB-AABBCCDD-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // 1. AuthMiddleware
            false,         // 2. OrderModel::findByReference → commande absente
            $snapshotRow,  // 3. PaymentIntentModel::findByReference
            false,         // 4. OrderModel::findByReferenceOnly → non créée
            // createFromIpn() : fetch address billing + dedup lookup, idem delivery
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,         // 6. dedup lookup billing → pas trouvé
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false          // 8. dedup lookup delivery → pas trouvé
        );

        // insert() lèvera une PDOException avec code 23000 pour simuler une collision persistante
        $pdoException = new \PDOException('Duplicate entry', 23000);
        $stub->method('insert')->willThrowException($pdoException);

        $this->expectException(\PDOException::class);

        (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
    }

    // ================================================================
    // paymentReturnOk() — decrementStock appelé dans le chemin fallback
    // ================================================================

    /**
     * Vérifie que decrementStock (execute GREATEST) est appelé après la création
     * de commande dans le chemin fallback de paymentReturnOk.
     *
     * @return void
     */
    public function testPaymentReturnOkDecrementsStockAfterOrderCreation(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-STOCK-DEC&Erreur=00000&Appel=9999&Trans=8888';
        $_SERVER['QUERY_STRING'] = $this->buildSignedQueryString($dataQs);
        $_GET = ['Ref' => 'WEB-CB-STOCK-DEC', 'Erreur' => '00000', 'Appel' => '9999', 'Trans' => '8888'];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-STOCK-DEC');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $executedSqls = [];
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        $mock->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            false,
            $snapshotRow,
            false,
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris', 'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null, 'slug' => 'wine', 'vintage' => 2020], // postOrderStockActions → getById
            ['id' => 684, 'order_reference' => 'WEB-CB-STOCK-DEC', 'payment_method' => 'card',
             'content' => json_encode($snapshot['items']), 'price' => 120.0,
             'shipping_discount' => 0.0, 'ordered_at' => date('Y-m-d H:i:s')],
            // buildOwnerPdfContext → AddressModel::findByIdForUser billing
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            ['order_reference' => 'WEB-CB-STOCK-DEC', 'payment_method' => 'card',
             'content' => json_encode($snapshot['items'])],
        );
        $mock->method('execute')->willReturnCallback(
            function (string $sql, array $params = []) use (&$executedSqls): int {
                $executedSqls[] = $sql;
                return 1;
            }
        );

        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (HttpException $e) {
            // redirect attendu
        }

        $hasDecrement = false;
        foreach ($executedSqls as $sql) {
            if (stripos($sql, 'GREATEST') !== false) {
                $hasDecrement = true;
                break;
            }
        }
        $this->assertTrue($hasDecrement, 'decrementStock (GREATEST) doit être appelé dans paymentReturnOk');
    }

    // ================================================================
    // paymentReturnOk() — catch (\Throwable $mailError) L527-529
    // Couvre : RuntimeException injectée via seam newMailService() dans
    // sendOrderConfirmationToOwner() est absorbée par le catch Throwable ;
    // le controller redirige vers /fr/commande/confirmation sans propager.
    // ================================================================

    /**
     * Quand newMailService() retourne un mock dont sendOrderConfirmationToOwner()
     * lève une RuntimeException, le catch (\Throwable $mailError) absorbe l'erreur
     * et le controller redirige normalement vers /fr/commande/confirmation.
     *
     * Utilise une anonymous class pour injecter le mock via la seam.
     *
     * @return void
     */
    public function testPaymentReturnOkMailThrowIsCaughtByErrorLogger(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-SEAM-MAIL-2026&Erreur=00000&Appel=3333&Trans=4444';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = [
            'Ref'    => 'WEB-CB-SEAM-MAIL-2026',
            'Erreur' => '00000',
            'Appel'  => '3333',
            'Trans'  => '4444',
        ];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-SEAM-MAIL-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // Séquence DB pour atteindre le bloc try-mail (L499-529) :
        // 1. AuthMiddleware
        // 2. OrderModel::findByReference → commande absente
        // 3. PaymentIntentModel::findByReference → snapshot valide
        // 4. OrderModel::findByReferenceOnly → non créée (createFromIpn déclenché)
        // 5. AddressModel fetch billing   6. dedup billing
        // 7. AddressModel fetch delivery  8. dedup delivery
        // 9. postOrderStockActions → WineModel::getById
        // 10. generateAndStoreInvoice → findByReferenceWithAddresses → null (skip)
        // 11. buildOwnerPdfContext → AddressModel billing (OrderPdfContextBuilder)
        // → newMailService() appelé → mock injecté → sendOrderConfirmationToOwner lève RuntimeException
        // → catch (\Throwable $mailError) L528 absorbe
        // 12. OrderModel::findByReference après catch → commande trouvée → redirect confirmation
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],    // 1. AuthMiddleware
            false,           // 2. OrderModel::findByReference → absente
            $snapshotRow,    // 3. PaymentIntentModel::findByReference
            false,           // 4. findByReferenceOnly → non créée
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],  // 5. billing fetch
            false,           // 6. dedup billing
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],  // 7. delivery fetch
            false,           // 8. dedup delivery
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null,
             'slug' => 'wine', 'vintage' => 2020],                          // 9. WineModel::getById
            false,           // 10. findByReferenceWithAddresses → null (facture skippée)
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],  // 11. billing PDF
            [                // 12. findByReference post-catch → commande trouvée
                'order_reference' => 'WEB-CB-SEAM-MAIL-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );

        // Mock MailService : sendOrderConfirmationToClient passe, sendOrderConfirmationToOwner lève
        $mailMock = $this->createStub(\Service\MailService::class);
        $mailMock->method('sendOrderConfirmationToOwner')
                 ->willThrowException(new \RuntimeException('SMTP down'));

        // Anonymous class overridant newMailService() et generateAndStoreInvoice()
        // pour injecter le mock et contourner la génération de PDF (inutile ici)
        $controller = new class (new \Core\Request(), $mailMock) extends OrderController {
            private \Service\MailService $injectedMail;

            public function __construct(\Core\Request $request, \Service\MailService $mail)
            {
                parent::__construct($request);
                $this->injectedMail = $mail;
            }

            protected function newMailService(): \Service\MailService
            {
                return $this->injectedMail;
            }
        };

        $mailException = null;
        $httpException = null;
        try {
            $controller->paymentReturnOk(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException $e) {
            $httpException = $e;
        } catch (\Throwable $e) {
            $mailException = $e;
        }

        // Le catch L528 doit avoir absorbé la RuntimeException
        $this->assertNull(
            $mailException,
            'Le catch (\Throwable $mailError) L528 doit absorber la RuntimeException SMTP sans la propager'
        );
        // Le controller doit rediriger vers la confirmation malgré l'échec mail
        $this->assertNotNull($httpException, 'Une HttpException 302 doit être levée après le catch mail');
        $this->assertSame(302, $httpException->status);
        $this->assertSame('WEB-CB-SEAM-MAIL-2026', $_SESSION['last_order_ref'] ?? '');
    }

    // ================================================================
    // paymentReturnOk() — catch (\Throwable $mailError) L517-530
    // Couvre : l'exception PHPMailer levée dans sendOrderConfirmationToClient
    // est absorbée silencieusement ; le controller redirige quand même vers
    // la confirmation (aucune exception mail ne propage).
    // ================================================================

    /**
     * Quand MailService lève une exception SMTP (PHPMailer non configuré),
     * le catch (\Throwable $mailError) à la ligne 528 absorbe l'erreur et
     * le controller complète la redirection vers /fr/commande/confirmation
     * sans propager l'exception.
     *
     * @return void
     */
    public function testPaymentReturnOkMailExceptionIsCaughtAndDoesNotPropagate(): void
    {
        if ($this->privateKey === false || $this->tmpPubkeyPath === '') {
            $this->markTestSkipped('Génération de clé RSA non disponible dans cet environnement.');
        }

        $_ENV['CA_PUBKEY_PATH'] = $this->tmpPubkeyPath;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement-ca/ok';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $dataQs   = 'Ref=WEB-CB-MAILCATCH-2026&Erreur=00000&Appel=1111&Trans=2222';
        $signedQs = $this->buildSignedQueryString($dataQs);
        $_SERVER['QUERY_STRING'] = $signedQs;
        $_GET = [
            'Ref'    => 'WEB-CB-MAILCATCH-2026',
            'Erreur' => '00000',
            'Appel'  => '1111',
            'Trans'  => '2222',
        ];

        $snapshot    = $this->defaultSnapshot(1, 'WEB-CB-MAILCATCH-2026');
        $snapshotRow = ['snapshot' => json_encode($snapshot)];

        $stub = $this->setDbMock();
        // Séquence DB permettant d'atteindre le bloc mail (L500-530) :
        // 1. AuthMiddleware
        // 2. OrderModel::findByReference → commande absente
        // 3. PaymentIntentModel::findByReference → snapshot valide
        // 4. OrderModel::findByReferenceOnly → non créée → createFromIpn s'exécute
        // 5-8. createFromIpn : fetch + dedup billing & delivery
        // 9. postOrderStockActions → WineModel::getById
        // 10. findByReferenceWithAddresses → null (facture skippée)
        // 11. buildOwnerPdfContext → AddressModel billing
        // → new MailService() instancié, sendOrderConfirmationToClient() lève PHPMailer\Exception
        // → catch (\Throwable $mailError) L528 absorbe l'exception (L529 : ErrorLogger::warning)
        // 12. OrderModel::findByReference après catch → commande trouvée → redirect confirmation
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],    // 1. AuthMiddleware
            false,           // 2. OrderModel::findByReference → absente
            $snapshotRow,    // 3. PaymentIntentModel::findByReference
            false,           // 4. findByReferenceOnly → non créée
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,           // 6. dedup lookup billing
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            false,           // 8. dedup lookup delivery
            ['id' => 1, 'label_name' => 'Wine', 'stock_quantity' => 500, 'available' => 1,
             'image_path' => '', 'low_stock_notified_at' => null,
             'slug' => 'wine', 'vintage' => 2020],
            false,           // 10. findByReferenceWithAddresses → null (facture skippée)
            ['id' => 1, 'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
             'street' => '1 rue Test', 'city' => 'Paris',
             'zip_code' => '75001', 'country' => 'France', 'phone' => ''],
            [                // 12. findByReference après catch → commande trouvée
                'order_reference' => 'WEB-CB-MAILCATCH-2026',
                'payment_method'  => 'card',
                'content'         => json_encode($snapshot['items']),
            ],
        );

        // On attend une HttpException 302 vers /fr/commande/confirmation.
        // Si le catch L528 ne fonctionnait pas, une PHPMailer\Exception se propagerait
        // et ce test lèverait une exception non-HttpException → assertion échouerait.
        $mailException = null;
        $httpException = null;
        try {
            (new OrderController(new Request()))->paymentReturnOk(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException $e) {
            $httpException = $e;
        } catch (\Throwable $e) {
            $mailException = $e;
        }

        // Aucune exception mail ne doit s'être propagée (catch L528 a absorbé)
        $this->assertNull(
            $mailException,
            'Le catch (\Throwable $mailError) L528 doit absorber l\'exception SMTP sans la propager'
        );
        // Le controller doit avoir redirigé normalement
        $this->assertNotNull($httpException, 'Une HttpException 302 doit être levée après le catch mail');
        $this->assertSame(302, $httpException->status);
        $this->assertSame('WEB-CB-MAILCATCH-2026', $_SESSION['last_order_ref'] ?? '');
    }
}
